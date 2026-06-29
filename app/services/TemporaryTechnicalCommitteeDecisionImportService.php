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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TemporaryTechnicalCommitteeDecisionImportService
{
    private const BUILDING_COMMITTEE_STATUSES = ['committee_review', 'commite_review'];

    private const UNIT_COMMITTEE_STATUSES = ['committee_review', 'commite_review', 'committee_review2'];

    private const DEFAULT_EXISTING_DECISION_TYPE = 'partially_damaged';

    private const GAZA_MEMBER_ID_NUMBERS = ['934863572', '900277229', '801933490', '800282667', '956242622'];

    private const KHAN_YOUNIS_NUSEIRAT_MEMBER_ID_NUMBERS = ['801933490', '800282667', '800846958', '804475044', '801113747'];

    public function __construct(private readonly ArcGisStatusUpdaterService $arcGisStatusUpdaterService) {}

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
     *     member_id_numbers?: list<string>,
     *     member_names?: list<string>,
     * }>  $records
     * @return array<string, mixed>
     */
    public function importRecords(array $records): array
    {
        $summary = $this->emptySummary();
        $memberCache = [];

        foreach ($records as $record) {
            $summary['rows']++;

            if (($record['use_excel_member_names'] ?? false) === true) {
                $memberNames = $record['member_names'] ?? [];
                $membersKey = 'names:'.implode('|', $memberNames);
                $members = $memberCache[$membersKey] ??= $this->resolveCommitteeMembersByNames($memberNames, $summary);
            } else {
                $memberIdNumbers = $this->memberIdNumbersForSeedRecord($record);
                $membersKey = 'ids:'.implode('|', $memberIdNumbers);
                $members = $memberCache[$membersKey] ??= $this->resolveCommitteeMembers($memberIdNumbers, $summary);
            }

            if ($members === []) {
                $this->recordSkip($summary, 'missing_committee_users', [
                    'sheet' => $record['sheet'],
                    'row' => $record['row'],
                    'objectid' => $record['objectid'],
                    'record_type' => $record['record_type'],
                    'reason' => 'No configured committee users were found by id_no.',
                ]);

                continue;
            }

            $decisionable = $this->resolveDecisionable(
                $record['record_type'],
                (string) $record['objectid'],
                (string) ($record['globalid'] ?? ''),
            );

            if (! $decisionable instanceof Model) {
                $this->recordSkip($summary, 'record_not_found', [
                    'sheet' => $record['sheet'],
                    'row' => $record['row'],
                    'objectid' => $record['objectid'],
                    'record_type' => $record['record_type'],
                    'reason' => 'No matching building or housing unit was found.',
                ]);

                continue;
            }

            $resurveyCompleted = (bool) ($record['resurvey_completed'] ?? false);
            $forcedCommitteeStatus = false;

            if (($record['force_committee_review_status'] ?? false) === true && ! $this->isCommitteeReviewRecord($decisionable)) {
                $this->forceCommitteeReviewStatus($decisionable);
                $forcedCommitteeStatus = true;
                $summary['statuses_forced_to_committee_review']++;
            }

            if (! $resurveyCompleted && ! $this->isCommitteeReviewRecord($decisionable)) {
                $this->recordSkip($summary, 'not_committee_review', [
                    'sheet' => $record['sheet'],
                    'row' => $record['row'],
                    'objectid' => $record['objectid'],
                    'record_type' => $record['record_type'],
                    'current_status' => $this->currentDamageStatus($decisionable),
                    'reason' => 'The matched record is not currently in committee review damage status.',
                ]);

                continue;
            }

            $decision = $this->completeDecision(
                $decisionable,
                $record['decision_type'],
                $record['decision_text'],
                $record['action_text'],
                $record['municipality'],
                $members,
                ! $resurveyCompleted,
                $record['decision_date'] ?? null,
                trim(implode("\n", array_filter([
                    $record['notes'] ?? null,
                    $forcedCommitteeStatus ? 'Status was moved to committee review before importing this decision.' : null,
                ]))),
            );
            if ($resurveyCompleted) {
                $this->markResurveyCompletedFieldStatus($decisionable);
                $this->syncArcGisResurveyCompletedFieldStatus($decision);
            } else {
                $this->syncArcGisDecisionStatus($decision);
            }
            $summary['decisions_completed']++;
        }

        $summary['missing_users'] = array_values(array_unique($summary['missing_users']));

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncExistingCommitteeReviewDecisionSignatures(): array
    {
        $summary = [
            'decisions_synced' => 0,
            'decisions_completed' => 0,
            'skipped_without_municipality' => 0,
            'skipped_without_decision_type' => 0,
            'missing_users' => [],
        ];
        $memberCache = [];

        Building::query()
            ->whereIn('building_damage_status', self::BUILDING_COMMITTEE_STATUSES)
            ->with('committeeDecision.signatures')
            ->each(function (Building $building) use (&$summary, &$memberCache): void {
                $this->syncDecisionSignaturesForReviewRecord($building, $summary, $memberCache);
            });

        HousingUnit::query()
            ->whereIn('unit_damage_status', self::UNIT_COMMITTEE_STATUSES)
            ->with(['committeeDecision.signatures', 'building'])
            ->each(function (HousingUnit $housingUnit) use (&$summary, &$memberCache): void {
                $this->syncDecisionSignaturesForReviewRecord($housingUnit, $summary, $memberCache);
            });

        $summary['missing_users'] = array_values(array_unique($summary['missing_users']));

        return $summary;
    }

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
    public function archiveSeedRecords(array $records): array
    {
        $summary = [
            'rows' => 0,
            'archived' => 0,
            'skipped_rows' => 0,
            'skip_reasons' => [],
            'issues' => [],
        ];

        foreach ($records as $record) {
            $summary['rows']++;

            $decisionable = $this->resolveDecisionable(
                $record['record_type'],
                (string) $record['objectid'],
                (string) ($record['globalid'] ?? ''),
            );

            if (! $decisionable instanceof Model) {
                $this->recordSkip($summary, 'record_not_found', [
                    'sheet' => $record['sheet'],
                    'row' => $record['row'],
                    'objectid' => $record['objectid'],
                    'record_type' => $record['record_type'],
                    'reason' => 'No matching building or housing unit was found for exceptional archive.',
                ]);

                continue;
            }

            $userId = $this->archiveUserIdForRecord($record);

            if ($userId === null) {
                $this->recordSkip($summary, 'missing_archive_user', [
                    'sheet' => $record['sheet'],
                    'row' => $record['row'],
                    'objectid' => $record['objectid'],
                    'record_type' => $record['record_type'],
                    'reason' => 'No user was available to own the exceptional archive row.',
                ]);

                continue;
            }

            $this->archiveSeedRecord($decisionable, $record, $userId);
            $summary['archived']++;
        }

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
     * @param  list<string>  $memberNames
     * @param  array<string, mixed>  $summary
     * @return list<CommitteeMember>
     */
    private function resolveCommitteeMembersByNames(array $memberNames, array &$summary): array
    {
        $committeeMembers = CommitteeMember::query()
            ->with('user')
            ->get();
        $committeeMembersByName = $committeeMembers
            ->keyBy(fn (CommitteeMember $member): string => $this->normalizePersonName($member->name));

        $users = User::query()->get();
        $usersByName = $users->keyBy(fn (User $user): string => $this->normalizePersonName($user->name));

        $members = [];

        foreach ($memberNames as $index => $memberName) {
            $normalizedName = $this->normalizePersonName($memberName);
            $existingMember = $committeeMembersByName[$normalizedName] ?? null;
            $user = $existingMember?->user ?? $usersByName[$normalizedName] ?? $this->findUserByPartialName($normalizedName, $committeeMembers, $users);

            if (! $user instanceof User) {
                $members[] = CommitteeMember::query()->updateOrCreate([
                    'name' => $memberName,
                ], [
                    'user_id' => $existingMember?->user_id,
                    'phone' => $existingMember?->phone,
                    'title' => $existingMember?->title,
                    'is_active' => true,
                    'is_required' => true,
                    'sort_order' => $index + 1,
                ]);

                continue;
            }

            $members[] = CommitteeMember::query()->updateOrCreate([
                'user_id' => $user->id,
            ], [
                'name' => $existingMember?->name ?: $user->name,
                'phone' => $user->phone,
                'title' => $existingMember?->title,
                'is_active' => true,
                'is_required' => true,
                'sort_order' => $index + 1,
            ]);
        }

        return $members;
    }

    /**
     * @param  Collection<int, CommitteeMember>  $committeeMembers
     * @param  Collection<int, User>  $users
     */
    private function findUserByPartialName(string $normalizedName, Collection $committeeMembers, Collection $users): ?User
    {
        foreach ($committeeMembers as $member) {
            if ($this->nameContainsAllTokens($this->normalizePersonName($member->name), $normalizedName)) {
                return $member->user;
            }
        }

        foreach ($users as $user) {
            if ($this->nameContainsAllTokens($this->normalizePersonName($user->name), $normalizedName)) {
                return $user;
            }
        }

        return null;
    }

    private function nameContainsAllTokens(string $candidateName, string $searchedName): bool
    {
        $searchedTokens = array_values(array_filter(explode(' ', $searchedName)));

        if (count($searchedTokens) < 2) {
            return false;
        }

        $candidateTokens = array_flip(array_values(array_filter(explode(' ', $candidateName))));

        foreach ($searchedTokens as $token) {
            if (! isset($candidateTokens[$token])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, list<CommitteeMember>>  $memberCache
     */
    private function syncDecisionSignaturesForReviewRecord(Building|HousingUnit $decisionable, array &$summary, array &$memberCache): void
    {
        $idNumbers = $this->memberIdNumbersForDecisionable($decisionable);

        if ($idNumbers === []) {
            $summary['skipped_without_municipality']++;

            return;
        }

        $membersKey = implode('|', $idNumbers);
        $members = $memberCache[$membersKey] ??= $this->resolveCommitteeMembers($idNumbers, $summary);

        if ($members === []) {
            return;
        }

        $decision = $this->decisionForReviewRecord($decisionable, $members[0]->user_id);

        $this->syncConfiguredSignatures($decision, $members);
        $summary['decisions_synced']++;

        $decisionType = $this->decisionTypeForExistingDecision($decision);

        if ($decisionType === null) {
            $summary['skipped_without_decision_type']++;

            return;
        }

        $decision = $this->completeExistingReviewDecision($decisionable, $decision, $decisionType, $members);
        $this->syncArcGisDecisionStatus($decision);
        $summary['decisions_completed']++;
    }

    private function decisionForReviewRecord(Building|HousingUnit $decisionable, int $userId): CommitteeDecision
    {
        /** @var CommitteeDecision|null $decision */
        $decision = $decisionable->committeeDecision;

        if ($decision instanceof CommitteeDecision) {
            return $decision;
        }

        /** @var CommitteeDecision $decision */
        $decision = CommitteeDecision::query()->create([
            'decisionable_type' => $decisionable::class,
            'decisionable_id' => $decisionable->getKey(),
            'decision_type' => self::DEFAULT_EXISTING_DECISION_TYPE,
            'decision_text' => 'Temporary technical committee seed default decision.',
            'status' => CommitteeDecision::STATUS_PENDING_SIGNATURES,
            'created_by' => $userId,
            'updated_by' => $userId,
            'committee_manager_id' => $userId,
        ]);

        return $decision;
    }

    /**
     * @param  array{member_id_numbers: list<string>}  $record
     */
    private function archiveUserIdForRecord(array $record): ?int
    {
        $userId = User::query()
            ->whereIn('id_no', $record['member_id_numbers'])
            ->orderBy('id')
            ->value('id');

        if ($userId !== null) {
            return (int) $userId;
        }

        $fallbackUserId = User::query()
            ->orderBy('id')
            ->value('id');

        return $fallbackUserId === null ? null : (int) $fallbackUserId;
    }

    /**
     * @param  array{
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
     * }  $record
     */
    private function archiveSeedRecord(Model $decisionable, array $record, int $userId): void
    {
        $building = $decisionable instanceof HousingUnit ? $decisionable->building : $decisionable;

        if (! $building instanceof Building) {
            return;
        }

        /** @var CommitteeDecision|null $decision */
        $decision = $decisionable->committeeDecision;
        $housingUnit = $decisionable instanceof HousingUnit ? $decisionable : null;
        $archivedAt = Carbon::now();

        BuildingSurveyArchiveObject::query()->updateOrCreate([
            'source_type' => 'temporary_committee_excel_archive',
            'building_objectid' => $building->objectid,
            'housing_unit_objectid' => $housingUnit?->objectid,
        ], [
            'building_globalid' => $building->globalid,
            'housing_unit_globalid' => $housingUnit?->globalid,
            'return_request_id' => null,
            'committee_decision_id' => $decision?->id,
            'archived_by' => $userId,
            'archived_at' => $archivedAt,
            'notes' => sprintf(
                'Exceptional archive from temporary committee Excel seed. sheet=%s row=%s decision_type=%s',
                $record['sheet'],
                $record['row'],
                $record['decision_type'],
            ),
            'building_snapshot' => $building->attributesToArray(),
            'housing_unit_snapshot' => $housingUnit?->attributesToArray(),
            'committee_decision_snapshot' => $decision?->attributesToArray(),
        ]);
    }

    /**
     * @param  array{municipality: string, member_id_numbers: list<string>}  $record
     * @return list<string>
     */
    private function memberIdNumbersForSeedRecord(array $record): array
    {
        if (($record['use_excel_member_ids'] ?? false) === true) {
            return $record['member_id_numbers'];
        }

        $municipality = $this->normalizeMunicipality($record['municipality']);

        if ($this->isGazaMunicipality($municipality)) {
            return self::GAZA_MEMBER_ID_NUMBERS;
        }

        if ($this->isKhanYounisOrNuseiratMunicipality($municipality)) {
            return self::KHAN_YOUNIS_NUSEIRAT_MEMBER_ID_NUMBERS;
        }

        return $record['member_id_numbers'];
    }

    /**
     * @return list<string>
     */
    private function memberIdNumbersForDecisionable(Building|HousingUnit $decisionable): array
    {
        $municipality = $this->normalizeMunicipality(implode(' ', array_filter(
            $decisionable instanceof HousingUnit
                ? [
                    $decisionable->governorate,
                    $decisionable->municipalitie,
                    $decisionable->locality,
                    $decisionable->neighborhood,
                    $decisionable->building?->governorate,
                    $decisionable->building?->municipalitie,
                    $decisionable->building?->neighborhood,
                ]
                : [
                    $decisionable->governorate,
                    $decisionable->municipalitie,
                    $decisionable->neighborhood,
                ],
        )));

        if (str_contains($municipality, 'sarsour') || str_contains($municipality, 'block_f')) {
            return self::GAZA_MEMBER_ID_NUMBERS;
        }

        if (str_contains($municipality, 'gaza') || str_contains($municipality, 'غزة')) {
            return self::GAZA_MEMBER_ID_NUMBERS;
        }

        if (
            str_contains($municipality, 'khan')
            || str_contains($municipality, 'خانيونس')
            || str_contains($municipality, 'خان_يونس')
            || str_contains($municipality, 'nuseirat')
            || str_contains($municipality, 'نصيرات')
        ) {
            return self::KHAN_YOUNIS_NUSEIRAT_MEMBER_ID_NUMBERS;
        }

        return [];
    }

    private function isGazaMunicipality(string $municipality): bool
    {
        return str_contains($municipality, 'gaza')
            || str_contains($municipality, 'غزة')
            || str_contains($municipality, 'ط؛ط²ط©');
    }

    private function isKhanYounisOrNuseiratMunicipality(string $municipality): bool
    {
        return str_contains($municipality, 'khan')
            || str_contains($municipality, 'خانيونس')
            || str_contains($municipality, 'خان_يونس')
            || str_contains($municipality, 'ط®ط§ظ†ظٹظˆظ†ط³')
            || str_contains($municipality, 'ط®ط§ظ†_ظٹظˆظ†ط³')
            || str_contains($municipality, 'nuseirat')
            || str_contains($municipality, 'نصيرات')
            || str_contains($municipality, 'ظ†طµظٹط±ط§طھ');
    }

    /**
     * @param  list<CommitteeMember>  $members
     */
    private function syncConfiguredSignatures(CommitteeDecision $decision, array $members): void
    {
        CommitteeDecisionSignature::query()
            ->where('committee_decision_id', $decision->id)
            ->delete();

        $signedAt = Carbon::now();

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
    }

    private function decisionTypeForExistingDecision(CommitteeDecision $decision): ?string
    {
        if (in_array($decision->decision_type, [
            CommitteeDecision::TYPE_FULLY_DAMAGED,
            CommitteeDecision::TYPE_PARTIALLY_DAMAGED,
            CommitteeDecision::TYPE_HIGHER_COMMITTEE,
        ], true)) {
            return $decision->decision_type;
        }

        $decisionText = str(implode(' ', array_filter([
            $decision->decision_text,
            $decision->action_text,
            $decision->notes,
        ])))->lower()->toString();

        if (
            str_contains($decisionText, 'لجنة عليا')
            || str_contains($decisionText, 'لجنة فنية')
            || str_contains($decisionText, 'تحول لجنة')
            || str_contains($decisionText, 'تحويل')
            || str_contains($decisionText, 'higher committee')
            || str_contains($decisionText, 'technical committee')
        ) {
            return CommitteeDecision::TYPE_HIGHER_COMMITTEE;
        }

        if (
            str_contains($decisionText, 'كلي')
            || str_contains($decisionText, 'ظƒظ„ظٹ')
            || str_contains($decisionText, 'fully')
        ) {
            return CommitteeDecision::TYPE_FULLY_DAMAGED;
        }

        if (
            str_contains($decisionText, 'جزئي')
            || str_contains($decisionText, 'ط¬ط²ط¦ظٹ')
            || str_contains($decisionText, 'partial')
        ) {
            return CommitteeDecision::TYPE_PARTIALLY_DAMAGED;
        }

        return self::DEFAULT_EXISTING_DECISION_TYPE;
    }

    /**
     * @param  list<CommitteeMember>  $members
     */
    private function completeExistingReviewDecision(Building|HousingUnit $decisionable, CommitteeDecision $decision, string $decisionType, array $members): CommitteeDecision
    {
        return DB::transaction(function () use ($decisionable, $decision, $decisionType, $members): CommitteeDecision {
            $completedAt = Carbon::now();
            $managerUserId = $members[0]->user_id;

            $decision->forceFill([
                'decision_type' => $decisionType,
                'status' => CommitteeDecision::STATUS_COMPLETED,
                'decision_date' => $decision->decision_date ?? $completedAt->toDateString(),
                'committee_manager_id' => $decision->committee_manager_id ?? $managerUserId,
                'created_by' => $decision->created_by ?? $managerUserId,
                'updated_by' => $managerUserId,
                'completed_at' => $decision->completed_at ?? $completedAt,
            ])->save();

            $this->archiveDecisionObject($decision, $decisionable, $managerUserId, $completedAt);
            $this->updateLocalDecisionableStatus($decisionable, $decisionType);

            return $decision->refresh();
        });
    }

    /**
     * @param  list<CommitteeMember>  $members
     */
    private function completeDecision(Model $decisionable, string $decisionType, string $decisionText, ?string $actionText, string $municipality, array $members, bool $applyLocalStatus = true, ?string $decisionDate = null, ?string $notes = null): CommitteeDecision
    {
        return DB::transaction(function () use ($decisionable, $decisionType, $decisionText, $actionText, $municipality, $members, $applyLocalStatus, $decisionDate, $notes): CommitteeDecision {
            /** @var CommitteeDecision $decision */
            $decision = CommitteeDecision::query()->firstOrNew([
                'decisionable_type' => $decisionable::class,
                'decisionable_id' => $decisionable->getKey(),
            ]);

            $signedAt = Carbon::now();
            $managerUserId = auth()->id()
                ?? collect($members)->pluck('user_id')->filter()->first()
                ?? User::query()->value('id');

            $decision->fill([
                'decision_type' => $decisionType,
                'decision_text' => $decisionText,
                'action_text' => $actionText,
                'notes' => trim(implode("\n", array_filter([
                    'Temporary technical committee seed: '.$municipality,
                    $notes,
                ]))),
                'decision_date' => $decisionDate ?? $signedAt->toDateString(),
                'status' => CommitteeDecision::STATUS_COMPLETED,
                'committee_manager_id' => $managerUserId,
                'created_by' => $decision->created_by ?? $managerUserId,
                'updated_by' => $managerUserId,
                'completed_at' => $signedAt,
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

            $this->archiveDecisionObject($decision, $decisionable, $managerUserId, $signedAt);

            if ($applyLocalStatus) {
                $this->updateLocalDecisionableStatus($decisionable, $decisionType);
            }

            return $decision->refresh();
        });
    }

    private function syncArcGisDecisionStatus(CommitteeDecision $decision): void
    {
        $result = $this->arcGisStatusUpdaterService->syncDecisionStatus($decision->load('decisionable'));

        $this->markArcGisResult($decision, $result);
    }

    private function syncArcGisResurveyCompletedFieldStatus(CommitteeDecision $decision): void
    {
        $result = $this->arcGisStatusUpdaterService->syncDecisionFieldStatus($decision->load('decisionable'), 'COMPLETED');

        $this->markArcGisResult($decision, $result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function markArcGisResult(CommitteeDecision $decision, array $result): void
    {
        $decision->forceFill([
            'arcgis_sync_status' => $result['status'] ?? null,
            'arcgis_last_attempt_at' => now(),
            'arcgis_synced_at' => ($result['success'] ?? false) ? now() : $decision->arcgis_synced_at,
            'arcgis_last_error' => ($result['success'] ?? false) ? null : ($result['message'] ?? null),
            'arcgis_last_response' => $result['message'] ?? null,
        ])->save();
    }

    private function markResurveyCompletedFieldStatus(Model $decisionable): void
    {
        if ($decisionable instanceof Building) {
            $decisionable->forceFill([
                'field_status' => 'COMPLETED',
            ])->save();

            return;
        }

        if (! $decisionable instanceof HousingUnit) {
            return;
        }

        $building = $decisionable->building;

        if ($building instanceof Building) {
            $building->forceFill([
                'field_status' => 'COMPLETED',
            ])->save();
        }
    }

    private function updateLocalDecisionableStatus(Model $decisionable, string $decisionType): void
    {
        if ($decisionType === CommitteeDecision::TYPE_HIGHER_COMMITTEE) {
            return;
        }

        if ($decisionable instanceof Building) {
            $decisionable->forceFill([
                'building_damage_status' => $decisionType === CommitteeDecision::TYPE_FULLY_DAMAGED
                    ? CommitteeDecision::TYPE_FULLY_DAMAGED
                    : CommitteeDecision::TYPE_PARTIALLY_DAMAGED,
                'field_status' => 'Not_Completed',
            ])->save();

            return;
        }

        if (! $decisionable instanceof HousingUnit) {
            return;
        }

        $decisionable->forceFill([
            'unit_damage_status' => $decisionType === CommitteeDecision::TYPE_FULLY_DAMAGED ? 'fully_damaged2' : 'partially_damaged2',
        ])->save();

        $building = $decisionable->building;

        if ($building instanceof Building) {
            $building->forceFill([
                'field_status' => 'Not_Completed',
            ])->save();
        }
    }

    private function forceCommitteeReviewStatus(Model $decisionable): void
    {
        if ($decisionable instanceof Building) {
            $decisionable->forceFill([
                'building_damage_status' => 'committee_review',
            ])->save();

            return;
        }

        if (! $decisionable instanceof HousingUnit) {
            return;
        }

        $decisionable->forceFill([
            'unit_damage_status' => 'committee_review2',
        ])->save();
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
            'building_snapshot' => $building->attributesToArray(),
            'housing_unit_snapshot' => $decisionable instanceof HousingUnit ? $decisionable->attributesToArray() : null,
            'committee_decision_snapshot' => $decision->attributesToArray(),
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

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $issue
     */
    private function recordSkip(array &$summary, string $reasonKey, array $issue): void
    {
        $summary['skipped_rows']++;
        $summary['skip_reasons'][$reasonKey] = ($summary['skip_reasons'][$reasonKey] ?? 0) + 1;
        $summary['issues'][] = [
            'reason_key' => $reasonKey,
            ...$issue,
        ];
    }

    private function currentDamageStatus(Model $decisionable): ?string
    {
        if ($decisionable instanceof Building) {
            return $decisionable->building_damage_status;
        }

        if ($decisionable instanceof HousingUnit) {
            return $decisionable->unit_damage_status;
        }

        return null;
    }

    private function normalizeStatus(?string $status): string
    {
        return str($status ?? '')
            ->lower()
            ->replace(' ', '_')
            ->trim()
            ->toString();
    }

    private function normalizePersonName(?string $name): string
    {
        $normalizedName = str($name ?? '')
            ->lower()
            ->replace(['أ', 'إ', 'آ'], 'ا')
            ->replace(['ة', 'ى'], ['ه', 'ي'])
            ->replaceMatches('/[\.،,؛:\/\\\\_-]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();

        return str($normalizedName)
            ->replaceMatches('/^(م|مهندس|المهندس)\s+/u', '')
            ->trim()
            ->toString();
    }

    private function normalizeMunicipality(?string $municipality): string
    {
        return str($municipality ?? '')
            ->lower()
            ->replace([' ', '-', 'ـ'], '_')
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
            'statuses_forced_to_committee_review' => 0,
            'skip_reasons' => [],
            'missing_users' => [],
            'issues' => [],
        ];
    }
}
