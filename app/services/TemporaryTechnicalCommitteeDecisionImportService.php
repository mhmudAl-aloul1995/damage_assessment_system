<?php

namespace App\services;

use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CommitteeDecision;
use App\Models\CommitteeDecisionSignature;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TemporaryTechnicalCommitteeDecisionImportService
{
    private const BUILDING_COMMITTEE_STATUSES = ['committee_review', 'commite_review'];

    private const UNIT_COMMITTEE_STATUSES = ['committee_review', 'commite_review', 'committee_review2'];

    /**
     * @param  list<array{
     *     record_type: string,
     *     municipality: string,
     *     sheet: string,
     *     row: int,
     *     objectid: string|int,
     *     globalid: string|null,
     *     decision_type: string,
     *     decision_text: string,
     *     action_text: string|null,
     *     member_id_numbers: list<string>
     * }>  $records
     * @return array<string, mixed>
     */
    public function importRecords(array $records): array
    {
        $summary = $this->emptySummary();
        $memberCache = [];

        foreach ($records as $record) {
            $summary['rows']++;

            $membersKey = implode('|', $record['member_id_numbers']);
            $members = $memberCache[$membersKey] ??= $this->resolveCommitteeMembers($record['member_id_numbers'], $summary);

            if ($members === []) {
                $summary['skipped_rows']++;
                $summary['issues'][] = [
                    'sheet' => $record['sheet'],
                    'row' => $record['row'],
                    'reason' => 'No configured committee users were found by id_no.',
                ];

                continue;
            }

            $decisionable = $this->resolveDecisionable(
                $record['record_type'],
                (string) $record['objectid'],
                (string) ($record['globalid'] ?? ''),
            );

            if (! $decisionable instanceof Model) {
                $summary['skipped_rows']++;
                $summary['issues'][] = [
                    'sheet' => $record['sheet'],
                    'row' => $record['row'],
                    'reason' => 'No matching building or housing unit was found.',
                ];

                continue;
            }

            if (! $this->isCommitteeReviewRecord($decisionable)) {
                $summary['skipped_rows']++;
                $summary['issues'][] = [
                    'sheet' => $record['sheet'],
                    'row' => $record['row'],
                    'reason' => 'The matched record is not currently in committee review damage status.',
                ];

                continue;
            }

            $this->completeDecision(
                $decisionable,
                $record['decision_type'],
                $record['decision_text'],
                $record['action_text'],
                $record['municipality'],
                $members,
            );
            $summary['decisions_completed']++;
        }

        $summary['missing_users'] = array_values(array_unique($summary['missing_users']));

        return $summary;
    }

    /**
     * @param  list<string>  $idNumbers
     * @param  array<string, mixed>  $summary
     * @return list<CommitteeMember>
     */
    private function resolveCommitteeMembers(array $idNumbers, array &$summary): array
    {
        $members = [];

        foreach ($idNumbers as $index => $idNumber) {
            $user = User::query()
                ->where('id_no', $idNumber)
                ->first();

            if (! $user instanceof User) {
                $summary['missing_users'][] = $idNumber;

                continue;
            }

            $members[] = CommitteeMember::query()->updateOrCreate([
                'user_id' => $user->id,
            ], [
                'name' => $user->name,
                'phone' => $user->phone,
                'title' => null,
                'is_active' => true,
                'is_required' => true,
                'sort_order' => $index + 1,
            ]);
        }

        return $members;
    }

    /**
     * @param  list<CommitteeMember>  $members
     */
    private function completeDecision(Model $decisionable, string $decisionType, string $decisionText, ?string $actionText, string $municipality, array $members): void
    {
        DB::transaction(function () use ($decisionable, $decisionType, $decisionText, $actionText, $municipality, $members): void {
            /** @var CommitteeDecision $decision */
            $decision = CommitteeDecision::query()->firstOrNew([
                'decisionable_type' => $decisionable::class,
                'decisionable_id' => $decisionable->getKey(),
            ]);

            $signedAt = Carbon::now();
            $managerUserId = $members[0]->user_id;

            $decision->fill([
                'decision_type' => $decisionType,
                'decision_text' => $decisionText,
                'action_text' => $actionText,
                'notes' => trim('Temporary technical committee seed: '.$municipality),
                'decision_date' => $signedAt->toDateString(),
                'status' => CommitteeDecision::STATUS_COMPLETED,
                'committee_manager_id' => $managerUserId,
                'created_by' => $decision->created_by ?? $managerUserId,
                'updated_by' => $managerUserId,
                'completed_at' => $signedAt,
                'arcgis_sync_status' => 'skipped',
                'arcgis_last_attempt_at' => $signedAt,
                'arcgis_last_response' => 'Temporary seed updated local status fields only.',
            ])->save();

            CommitteeDecisionSignature::query()
                ->where('committee_decision_id', $decision->id)
                ->delete();

            foreach ($members as $index => $member) {
                CommitteeDecisionSignature::query()->create([
                    'committee_decision_id' => $decision->id,
                    'committee_member_id' => $member->id,
                    'is_required' => true,
                    'sort_order' => $index + 1,
                    'status' => 'approved',
                    'signed_at' => $signedAt,
                    'signed_by_user_id' => $member->user_id,
                ]);
            }

            $this->updateLocalDecisionableStatus($decisionable, $decisionType);
            $this->archiveDecisionObject($decision, $decisionable, $managerUserId, $signedAt);
        });
    }

    private function updateLocalDecisionableStatus(Model $decisionable, string $decisionType): void
    {
        if ($decisionable instanceof Building) {
            $decisionable->forceFill([
                'building_damage_status' => $decisionType === 'fully_damaged' ? 'fully_damaged' : 'partially_damaged',
                'field_status' => 'Not_Completed',
            ])->save();

            return;
        }

        if (! $decisionable instanceof HousingUnit) {
            return;
        }

        $decisionable->forceFill([
            'unit_damage_status' => $decisionType === 'fully_damaged' ? 'fully_damaged2' : 'partially_damaged2',
        ])->save();

        $building = $decisionable->building;

        if ($building instanceof Building) {
            $building->forceFill([
                'field_status' => 'Not_Completed',
            ])->save();
        }
    }

    private function archiveDecisionObject(CommitteeDecision $decision, Model $decisionable, ?int $userId, Carbon $archivedAt): void
    {
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
            'archived_by' => $userId,
            'archived_at' => $archivedAt,
            'notes' => $decision->notes,
        ]);
    }

    private function resolveDecisionable(string $recordType, string $objectId, string $globalId): ?Model
    {
        if ($recordType === 'housing-unit') {
            return $this->findHousingUnit($objectId, $globalId);
        }

        return $this->findBuilding($objectId, $globalId);
    }

    private function findBuilding(string $objectId, string $globalId): ?Building
    {
        if ($objectId === '' && $globalId === '') {
            return null;
        }

        return Building::query()
            ->where(function ($query) use ($objectId, $globalId): void {
                $query
                    ->when($objectId !== '', fn ($query) => $query->orWhere('objectid', $objectId))
                    ->when($globalId !== '', fn ($query) => $query->orWhere('globalid', $globalId));
            })
            ->first();
    }

    private function findHousingUnit(string $objectId, string $globalId): ?HousingUnit
    {
        if ($objectId === '' && $globalId === '') {
            return null;
        }

        return HousingUnit::query()
            ->where(function ($query) use ($objectId, $globalId): void {
                $query
                    ->when($objectId !== '', fn ($query) => $query->orWhere('objectid', $objectId))
                    ->when($globalId !== '', fn ($query) => $query->orWhere('globalid', $globalId));
            })
            ->first();
    }

    private function isCommitteeReviewRecord(Model $decisionable): bool
    {
        if ($decisionable instanceof Building) {
            return in_array($this->normalizeStatus($decisionable->building_damage_status), self::BUILDING_COMMITTEE_STATUSES, true);
        }

        if ($decisionable instanceof HousingUnit) {
            return in_array($this->normalizeStatus($decisionable->unit_damage_status), self::UNIT_COMMITTEE_STATUSES, true);
        }

        return false;
    }

    private function normalizeStatus(?string $status): string
    {
        return str($status ?? '')
            ->lower()
            ->replace(' ', '_')
            ->trim()
            ->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'rows' => 0,
            'decisions_completed' => 0,
            'skipped_rows' => 0,
            'missing_users' => [],
            'issues' => [],
        ];
    }
}
