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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class TemporaryTechnicalCommitteeDecisionImportService
{
    private const BUILDING_COMMITTEE_STATUSES = ['committee_review', 'commite_review'];

    private const UNIT_COMMITTEE_STATUSES = ['committee_review', 'commite_review', 'committee_review2'];

    /**
     * @param  list<array{path: string, municipality: string, member_id_numbers: list<string>}>  $files
     * @return array<string, mixed>
     */
    public function importFiles(array $files): array
    {
        $summary = $this->emptySummary();

        foreach ($files as $file) {
            $path = $file['path'];

            if (! is_file($path)) {
                throw new RuntimeException("Temporary committee decision workbook was not found: {$path}");
            }

            $members = $this->resolveCommitteeMembers($file['member_id_numbers'], $summary);

            if ($members === []) {
                $summary['issues'][] = [
                    'file' => $path,
                    'reason' => 'No configured committee users were found by id_no.',
                ];

                continue;
            }

            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);

            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $this->importSheet($sheet, $file['municipality'], $members, $summary);
            }
        }

        $summary['missing_users'] = array_values(array_unique($summary['missing_users']));

        return $summary;
    }

    /**
     * @param  list<CommitteeMember>  $members
     * @param  array<string, mixed>  $summary
     */
    private function importSheet(Worksheet $sheet, string $municipality, array $members, array &$summary): void
    {
        $recordType = str_contains($sheet->getTitle(), 'وحدات') ? 'housing-unit' : 'building';
        $headers = $this->headers($sheet);
        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            if ($this->isBlankRow($sheet, $row)) {
                continue;
            }

            $summary['rows']++;

            $record = $this->rowPayload($sheet, $headers, $row, $recordType);
            $decisionType = $this->resolveDecisionType($record['decision']);

            if ($decisionType === null) {
                $summary['skipped_rows']++;
                $summary['issues'][] = [
                    'sheet' => $sheet->getTitle(),
                    'row' => $row,
                    'reason' => 'Decision text is not classified as fully or partially damaged.',
                ];

                continue;
            }

            $decisionable = $this->resolveDecisionable($recordType, $record['objectid'], $record['globalid']);

            if (! $decisionable instanceof Model) {
                $summary['skipped_rows']++;
                $summary['issues'][] = [
                    'sheet' => $sheet->getTitle(),
                    'row' => $row,
                    'reason' => 'No matching building or housing unit was found.',
                ];

                continue;
            }

            if (! $this->isCommitteeReviewRecord($decisionable)) {
                $summary['skipped_rows']++;
                $summary['issues'][] = [
                    'sheet' => $sheet->getTitle(),
                    'row' => $row,
                    'reason' => 'The matched record is not currently in committee review damage status.',
                ];

                continue;
            }

            $this->completeDecision($decisionable, $decisionType, $record['decision'], $record['action'], $municipality, $members);
            $summary['decisions_completed']++;
        }
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
                'notes' => trim('Temporary technical committee Excel seed: '.$municipality),
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

    private function resolveDecisionType(string $decisionText): ?string
    {
        if (str_contains($decisionText, 'كلي')) {
            return 'fully_damaged';
        }

        if (str_contains($decisionText, 'جزئي')) {
            return 'partially_damaged';
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Worksheet $sheet): array
    {
        $headers = [];
        $highestColumn = $sheet->getHighestColumn();

        foreach ($sheet->rangeToArray("A1:{$highestColumn}1", null, true, true, true)[1] as $column => $value) {
            $header = $this->text($value);

            if ($header !== '') {
                $headers[$header] = $column;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{objectid: string, globalid: string, decision: string, action: string|null}
     */
    private function rowPayload(Worksheet $sheet, array $headers, int $row, string $recordType): array
    {
        $title = $sheet->getTitle();
        $isLargeVisitedSheet = $title === 'تم زيارتها';
        $isGazaSheet = $title === 'غزة';
        $isNuseiratVisitedSheet = $title === 'النصيرات تم زيارته';

        $decisionFallback = match (true) {
            $isGazaSheet => 'V',
            $isLargeVisitedSheet => 'EU',
            $recordType === 'housing-unit' => 'T',
            $isNuseiratVisitedSheet => 'K',
            $title === 'زيارة 19.4' => 'R',
            default => 'Q',
        };

        $actionFallback = match (true) {
            $isGazaSheet => 'W',
            $isLargeVisitedSheet => 'EV',
            $recordType === 'housing-unit' => 'M',
            $isNuseiratVisitedSheet => 'L',
            default => 'L',
        };

        return [
            'objectid' => $this->cell($sheet, $this->headerColumn($headers, ['ObjectID']) ?? 'A', $row),
            'globalid' => $this->cell($sheet, $this->headerColumn($headers, ['GlobalID']) ?? ($recordType === 'housing-unit' ? 'C' : 'B'), $row),
            'decision' => $this->cell($sheet, $this->headerColumn($headers, ['قرار اللجنة']) ?? $decisionFallback, $row),
            'action' => $this->cell($sheet, $actionFallback, $row) ?: null,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<string>  $candidates
     */
    private function headerColumn(array $headers, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($headers[$candidate])) {
                return $headers[$candidate];
            }
        }

        return null;
    }

    private function isBlankRow(Worksheet $sheet, int $row): bool
    {
        foreach ($sheet->rangeToArray("A{$row}:{$sheet->getHighestColumn()}{$row}", null, true, true, true)[$row] as $value) {
            if ($this->text($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cell(Worksheet $sheet, string $column, int $row): string
    {
        return $this->text($sheet->getCell("{$column}{$row}")->getValue());
    }

    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
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
