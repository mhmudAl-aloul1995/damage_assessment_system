<?php

declare(strict_types=1);

namespace App\services;

use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CommitteeDecision;
use App\Models\CommitteeDecisionSignature;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\Models\User;
use App\Notifications\CommitteeDecisionSignatureRequested;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitteeDecisionWorkflowService
{
    public function __construct(private readonly ArcGisStatusUpdaterService $arcGisStatusUpdaterService) {}

    public function findOrCreateDecision(Model $decisionable, User $user): CommitteeDecision
    {
        /** @var CommitteeDecision $decision */
        $decision = CommitteeDecision::query()->firstOrCreate(
            [
                'decisionable_type' => $decisionable::class,
                'decisionable_id' => $decisionable->getKey(),
            ],
            [
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'status' => CommitteeDecision::STATUS_DRAFT,
            ],
        );

        return $decision->load([
            'decisionable',
            'committeeManager',
            'creator',
            'updater',
            'signatures.committeeMember.user',
            'signatures.signedByUser',
        ]);
    }

    public function saveDecisionContent(CommitteeDecision $decision, array $data, User $user): CommitteeDecision
    {
        return DB::transaction(function () use ($decision, $data, $user): CommitteeDecision {
            if ($decision->isCompleted()) {
                abort(403, 'لا يمكن تعديل القرار بعد اكتماله.');
            }

            $newSignatures = $this->syncDecisionMembers(
                $decision,
                $data['committee_members'] ?? [],
            );

            $decision->fill([
                'decision_type' => $data['decision_type'],
                'decision_text' => $data['decision_text'],
                'action_text' => $data['action_text'] ?? null,
                'notes' => $data['notes'] ?? null,
                'decision_date' => $data['decision_date'],
                'status' => CommitteeDecision::STATUS_PENDING_SIGNATURES,
                'committee_manager_id' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            $this->notifyCommitteeMembersForSignatures($newSignatures);
            $this->refreshDecisionStatus($decision, $user);

            return $decision->load([
                'decisionable',
                'committeeManager',
                'signatures.committeeMember.user',
                'signatures.signedByUser',
            ]);
        });
    }

    public function recordSignature(CommitteeDecision $decision, CommitteeMember $member, array $data, User $user): CommitteeDecisionSignature
    {
        return DB::transaction(function () use ($decision, $member, $data, $user): CommitteeDecisionSignature {
            if ($decision->isCompleted()) {
                abort(403, 'لا يمكن التوقيع بعد اكتمال القرار.');
            }

            $this->ensureSignerCanSign($member, $user);
            /** @var CommitteeDecisionSignature $signature */
            $signature = CommitteeDecisionSignature::query()->where([
                'committee_decision_id' => $decision->id,
                'committee_member_id' => $member->id,
            ])->firstOrFail();

            if ($signature->status !== 'pending' && $data['status'] !== 'pending') {
                throw ValidationException::withMessages([
                    'committee_member_id' => 'تم تسجيل توقيع هذا العضو مسبقًا.',
                ]);
            }

            $signature->fill([
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'signed_at' => $data['status'] === 'pending' ? null : Carbon::now(),
                'signed_by_user_id' => $data['status'] === 'pending' ? null : $user->id,
            ])->save();

            $this->refreshDecisionStatus($decision, $user);

            return $signature->load(['committeeMember.user', 'signedByUser']);
        });
    }

    public function syncDecisionMembers(CommitteeDecision $decision, array $memberIds): array
    {
        $selectedMemberIds = collect($memberIds)
            ->map(fn (mixed $memberId): int => (int) $memberId)
            ->filter(fn (int $memberId): bool => $memberId > 0)
            ->unique()
            ->values();

        $members = CommitteeMember::query()
            ->whereIn('id', $selectedMemberIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $newSignatures = [];

        foreach ($selectedMemberIds as $memberId) {
            $member = $members->get($memberId);

            if (! $member instanceof CommitteeMember) {
                continue;
            }

            $signature = CommitteeDecisionSignature::query()->firstOrNew([
                'committee_decision_id' => $decision->id,
                'committee_member_id' => $member->id,
            ]);

            if (! $signature->exists) {
                $signature->status = 'pending';
            }

            $signature->forceFill([
                'is_required' => true,
                'sort_order' => $member->sort_order,
            ])->save();

            if (! $signature->wasRecentlyCreated) {
                continue;
            }

            $newSignatures[] = $signature->load('committeeMember.user');
        }

        CommitteeDecisionSignature::query()
            ->where('committee_decision_id', $decision->id)
            ->whereNotIn('committee_member_id', $selectedMemberIds)
            ->where('status', 'pending')
            ->delete();

        return $newSignatures;
    }

    public function latestSignatureTemplate(?CommitteeDecision $currentDecision = null): array
    {
        $latestDecision = CommitteeDecision::query()
            ->when($currentDecision?->exists, fn ($query) => $query->whereKeyNot($currentDecision->id))
            ->whereHas('signatures')
            ->with(['signatures.committeeMember'])
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if (! $latestDecision instanceof CommitteeDecision) {
            return [];
        }

        return $latestDecision->signatures
            ->filter(fn (CommitteeDecisionSignature $signature): bool => (bool) $signature->committeeMember?->is_active)
            ->sortBy('sort_order')
            ->mapWithKeys(fn (CommitteeDecisionSignature $signature): array => [
                $signature->committee_member_id => [
                    'is_required' => $signature->is_required,
                    'sort_order' => $signature->sort_order,
                ],
            ])
            ->all();
    }

    public function refreshDecisionStatus(CommitteeDecision $decision, ?User $archiver = null): CommitteeDecision
    {
        $decision->loadMissing(['signatures.committeeMember', 'decisionable']);

        if (! $this->hasDecisionContent($decision)) {
            $decision->forceFill([
                'status' => CommitteeDecision::STATUS_DRAFT,
                'updated_by' => $decision->updated_by,
            ])->save();

            return $decision;
        }

        $requiredSignatures = $decision->signatures->filter(fn (CommitteeDecisionSignature $signature): bool => $signature->committeeMember?->is_active && $signature->is_required);

        if ($requiredSignatures->contains(fn (CommitteeDecisionSignature $signature): bool => $signature->status === 'rejected')) {
            $decision->forceFill([
                'status' => CommitteeDecision::STATUS_REJECTED,
            ])->save();

            return $decision;
        }

        if ($requiredSignatures->isNotEmpty() && $requiredSignatures->every(fn (CommitteeDecisionSignature $signature): bool => $signature->status === 'approved')) {
            if (! $decision->isCompleted()) {
                $decision->forceFill([
                    'status' => CommitteeDecision::STATUS_COMPLETED,
                    'completed_at' => now(),
                ])->save();

                $this->archiveDecisionObject($decision, $archiver);
                $this->applyLocalDecisionStatus($decision);
                $this->markArcGisResult(
                    $decision,
                    $this->arcGisStatusUpdaterService->syncDecisionStatus($decision),
                );
            }

            return $decision;
        }

        $decision->forceFill([
            'status' => CommitteeDecision::STATUS_PENDING_SIGNATURES,
        ])->save();

        return $decision;
    }

    private function applyLocalDecisionStatus(CommitteeDecision $decision): void
    {
        $decision->loadMissing('decisionable');

        $decisionable = $decision->decisionable;
        $fieldStatus = (string) config('services.committee_decisions.arcgis.status_value', 'Not_Completed');

        if ($decisionable instanceof Building) {
            $decisionable->forceFill([
                'building_damage_status' => $decision->decision_type === 'fully_damaged'
                    ? 'fully_damaged'
                    : 'partially_damaged',
                'field_status' => $fieldStatus,
            ])->save();

            return;
        }

        if (! $decisionable instanceof HousingUnit) {
            return;
        }

        $decisionable->forceFill([
            'unit_damage_status' => $decision->decision_type === 'fully_damaged'
                ? 'fully_damaged2'
                : 'partially_damaged2',
        ])->save();

        if (blank($decisionable->parentglobalid)) {
            return;
        }

        Building::query()
            ->where('globalid', $decisionable->parentglobalid)
            ->update(['field_status' => $fieldStatus]);
    }

    /**
     * @param  list<CommitteeDecisionSignature>  $signatures
     */
    private function notifyCommitteeMembersForSignatures(array $signatures): void
    {
        foreach ($signatures as $signature) {
            $user = $signature->committeeMember?->user;

            if (! $user instanceof User) {
                continue;
            }

            $user->notify(new CommitteeDecisionSignatureRequested($signature));
        }
    }

    public function resolveAssignedEngineer(?CommitteeDecision $decision): ?User
    {
        if ($decision === null) {
            return null;
        }

        $decisionable = $decision->decisionable;
        $building = $decisionable instanceof HousingUnit ? $decisionable->building : $decisionable;

        if (! $building instanceof Building || blank($building->assignedto)) {
            return null;
        }

        return User::query()
            ->where(function ($query) use ($building): void {
                $query->where('username_arcgis', $building->assignedto)
                    ->orWhere('name', $building->assignedto)
                    ->orWhere('name_en', $building->assignedto);
            })
            ->first();
    }

    public function markArcGisResult(CommitteeDecision $decision, array $result): void
    {
        $decision->forceFill([
            'arcgis_sync_status' => $result['status'] ?? null,
            'arcgis_last_attempt_at' => now(),
            'arcgis_synced_at' => ($result['success'] ?? false) ? now() : $decision->arcgis_synced_at,
            'arcgis_last_error' => ($result['success'] ?? false) ? null : ($result['message'] ?? null),
            'arcgis_last_response' => $result['message'] ?? null,
        ])->save();
    }

    private function hasDecisionContent(CommitteeDecision $decision): bool
    {
        return filled($decision->decision_type)
            && filled($decision->decision_text)
            && $decision->decision_date !== null
            && $decision->committee_manager_id !== null;
    }

    private function ensureSignerCanSign(CommitteeMember $member, User $user): void
    {
        if (! $member->is_active) {
            abort(403, 'عضو اللجنة غير مفعل.');
        }

        $linkedUserId = $member->user_id;

        if ($linkedUserId !== $user->id) {
            throw ValidationException::withMessages([
                'committee_member_id' => 'لا يمكن تسجيل التوقيع إلا من حساب عضو اللجنة نفسه.',
            ]);
        }
    }

    private function archiveDecisionObject(CommitteeDecision $decision, ?User $archiver): void
    {
        $decision->loadMissing(['decisionable', 'signatures.committeeMember', 'signatures.signedByUser']);

        $decisionable = $decision->decisionable;
        $building = $decisionable instanceof HousingUnit ? $decisionable->building : $decisionable;

        if (! $building instanceof Building) {
            return;
        }

        BuildingSurveyArchiveObject::query()->updateOrCreate([
            'source_type' => 'committee_decision',
            'committee_decision_id' => $decision->id,
        ], [
            'building_objectid' => $building->objectid,
            'building_globalid' => $building->globalid,
            'housing_unit_objectid' => $decisionable instanceof HousingUnit ? $decisionable->objectid : null,
            'housing_unit_globalid' => $decisionable instanceof HousingUnit ? $decisionable->globalid : null,
            'return_request_id' => null,
            'archived_by' => $archiver?->id ?? $decision->committee_manager_id ?? $decision->updated_by ?? $decision->created_by,
            'archived_at' => now(),
            'notes' => $decision->notes,
            'building_snapshot' => $building->attributesToArray(),
            'housing_unit_snapshot' => $decisionable instanceof HousingUnit ? $decisionable->attributesToArray() : null,
            'committee_decision_snapshot' => [
                ...$decision->attributesToArray(),
                'committee_members' => $decision->signatures
                    ->sortBy('sort_order')
                    ->map(fn (CommitteeDecisionSignature $signature): array => [
                        'name' => $signature->committeeMember?->name,
                        'title' => $signature->committeeMember?->title,
                        'status' => $signature->status,
                        'notes' => $signature->notes,
                        'signed_at' => $signature->signed_at?->toDateTimeString(),
                        'signed_by' => $signature->signedByUser?->name,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
