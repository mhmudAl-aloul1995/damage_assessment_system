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

    private const DEFAULT_EXISTING_DECISION_TYPE = 'partially_damaged';

    private const GAZA_MEMBER_ID_NUMBERS = ['934863572', '900277229', '801933490', '800282667', '956242622'];

    private const KHAN_YOUNIS_NUSEIRAT_MEMBER_ID_NUMBERS = ['801933490', '800282667', '800846958', '804475044', '801113747'];

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

            $memberIdNumbers = $this->memberIdNumbersForSeedRecord($record);
            $membersKey = implode('|', $memberIdNumbers);
            $members = $memberCache[$membersKey] ??= $this->resolveCommitteeMembers($memberIdNumbers, $summary);

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

            if (! $this->isCommitteeReviewRecord($decisionable)) {
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

        $this->completeExistingReviewDecision($decisionable, $decision, $decisionType, $members);
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
        if (in_array($decision->decision_type, ['fully_damaged', 'partially_damaged'], true)) {
            return $decision->decision_type;
        }

        $decisionText = str(implode(' ', array_filter([
            $decision->decision_text,
            $decision->action_text,
            $decision->notes,
        ])))->lower()->toString();

        if (
            str_contains($decisionText, 'كلي')
            || str_contains($decisionText, 'ظƒظ„ظٹ')
            || str_contains($decisionText, 'fully')
        ) {
            return 'fully_damaged';
        }

        if (
            str_contains($decisionText, 'جزئي')
            || str_contains($decisionText, 'ط¬ط²ط¦ظٹ')
            || str_contains($decisionText, 'partial')
        ) {
            return 'partially_damaged';
        }

        return self::DEFAULT_EXISTING_DECISION_TYPE;
    }

    /**
     * @param  list<CommitteeMember>  $members
     */
    private function completeExistingReviewDecision(Building|HousingUnit $decisionable, CommitteeDecision $decision, string $decisionType, array $members): void
    {
        DB::transaction(function () use ($decisionable, $decision, $decisionType, $members): void {
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
                'arcgis_sync_status' => 'skipped',
                'arcgis_last_attempt_at' => $completedAt,
                'arcgis_last_response' => 'Temporary seed completed existing committee review decision and updated local status fields only.',
            ])->save();

            $this->archiveDecisionObject($decision, $decisionable, $managerUserId, $completedAt);
            $this->updateLocalDecisionableStatus($decisionable, $decisionType);
        });
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

            $this->archiveDecisionObject($decision, $decisionable, $managerUserId, $signedAt);
            $this->updateLocalDecisionableStatus($decisionable, $decisionType);
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
            'skip_reasons' => [],
            'missing_users' => [],
            'issues' => [],
        ];
    }
}
