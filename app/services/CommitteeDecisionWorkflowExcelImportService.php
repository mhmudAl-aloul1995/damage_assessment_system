<?php

namespace App\services;

use App\Models\CommitteeDecision;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class CommitteeDecisionWorkflowExcelImportService
{
    public function __construct(private readonly TemporaryTechnicalCommitteeDecisionImportService $workflowImporter) {}

    /**
     * @return array<string, mixed>
     */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Excel file was not found: {$path}");
        }

        $spreadsheet = IOFactory::load($path);
        $records = [];
        $summary = [
            'sheets' => [],
            'parse_issues' => [],
        ];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetRecords = $this->recordsFromSheet($sheet, $summary['parse_issues']);
            $summary['sheets'][$sheet->getTitle()] = count($sheetRecords);
            $records = [...$records, ...$sheetRecords];
        }

        $importSummary = $this->workflowImporter->importRecords($records);

        return [
            ...$importSummary,
            'sheets' => $summary['sheets'],
            'parse_issues' => $summary['parse_issues'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return list<array<string, mixed>>
     */
    private function recordsFromSheet(Worksheet $sheet, array &$issues): array
    {
        $rows = $sheet->toArray(null, true, true, false);
        $headerIndex = $this->headerIndex($rows);

        if ($headerIndex === null) {
            return [];
        }

        $recordType = $this->recordTypeFromSheetName($sheet->getTitle());

        if ($recordType === null) {
            $issues[] = [
                'sheet' => $sheet->getTitle(),
                'row' => $headerIndex + 1,
                'reason' => 'Sheet name does not identify buildings or housing units.',
            ];

            return [];
        }

        $headerRow = $rows[$headerIndex] ?? [];
        $objectIdIndex = $this->objectIdColumnIndex($headerRow, $recordType);

        if ($objectIdIndex === null) {
            $issues[] = [
                'sheet' => $sheet->getTitle(),
                'row' => $headerIndex + 1,
                'reason' => 'ObjectID or Export Row Id column was not found.',
            ];

            return [];
        }

        $records = [];

        foreach (array_slice($rows, $headerIndex + 1) as $offset => $row) {
            $rowNumber = $headerIndex + $offset + 2;

            if ($this->blankRow($row)) {
                continue;
            }

            $objectId = $this->value($row[$objectIdIndex] ?? null);

            if ($objectId === '') {
                $issues[] = [
                    'sheet' => $sheet->getTitle(),
                    'row' => $rowNumber,
                    'reason' => 'ObjectID is empty.',
                ];

                continue;
            }

            $decisionPayload = $this->decisionPayload($row, $headerRow);

            if ($decisionPayload === null) {
                $issues[] = [
                    'sheet' => $sheet->getTitle(),
                    'row' => $rowNumber,
                    'objectid' => $objectId,
                    'reason' => 'Decision is empty or not one of partial, total, or higher committee.',
                ];

                continue;
            }

            $records[] = [
                'record_type' => $recordType,
                'municipality' => trim($this->value($row[5] ?? null).' '.$this->value($row[6] ?? null)),
                'sheet' => $sheet->getTitle(),
                'row' => $rowNumber,
                'objectid' => $objectId,
                'globalid' => null,
                'decision_type' => $decisionPayload['decision_type'],
                'decision_text' => $decisionPayload['decision_text'],
                'action_text' => $decisionPayload['action_text'],
                'decision_date' => $decisionPayload['decision_date'],
                'resurvey_completed' => $decisionPayload['resurvey_completed'],
                'member_names' => $decisionPayload['member_names'],
                'use_excel_member_names' => true,
                'force_committee_review_status' => true,
                'notes' => trim(implode("\n", array_filter([
                    'Excel sheet: '.$sheet->getTitle().' row: '.$rowNumber,
                    'Researcher: '.$this->value($row[1] ?? null),
                    'Building/Unit: '.$this->value($row[3] ?? null),
                    'Comments: '.$this->value($row[4] ?? null),
                    'Resurvey completed: '.($decisionPayload['resurvey_completed'] ? 'yes' : 'no'),
                    $decisionPayload['source'] === 'higher' ? 'Decision source: higher committee' : 'Decision source: initial committee',
                ]))),
            ];
        }

        return $records;
    }

    /**
     * @param  list<array<int, mixed>>  $rows
     */
    private function headerIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            if ($this->columnIndex($row, ['ObjectID', 'Export Row Id']) !== null) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     */
    private function objectIdColumnIndex(array $headerRow, string $recordType): ?int
    {
        if ($recordType === 'housing-unit') {
            return $this->columnIndex($headerRow, ['Export Row Id', 'ObjectID']);
        }

        return $this->columnIndex($headerRow, ['ObjectID']);
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @param  list<string>  $labels
     */
    private function columnIndex(array $headerRow, array $labels): ?int
    {
        foreach ($labels as $label) {
            $normalizedLabel = $this->normalizeHeader($label);

            foreach ($headerRow as $index => $header) {
                if ($this->normalizeHeader($this->value($header)) === $normalizedLabel) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        return str($header)->lower()->replace([' ', '_', '-'], '')->toString();
    }

    private function recordTypeFromSheetName(string $sheetName): ?string
    {
        if ($this->containsAny($sheetName, ['وحدات', 'ظˆط­ط¯ط§طھ', 'ط¸ث†ط·آ­ط·آ¯ط·آ§ط·ع¾'])) {
            return 'housing-unit';
        }

        if ($this->containsAny($sheetName, ['مباني', 'ظ…ط¨ط§ظ†ظٹ', 'ط¸â€¦ط·آ¨ط·آ§ط¸â€ ط¸ظ¹'])) {
            return 'building';
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function decisionPayload(array $row, array $headerRow): ?array
    {
        $initialDecision = $this->value($row[14] ?? null);
        $higherDecision = $this->value($row[25] ?? null);
        $initialType = $this->decisionType($initialDecision);
        $higherType = $this->decisionType($higherDecision);

        if ($initialType === CommitteeDecision::TYPE_HIGHER_COMMITTEE && $higherType !== null) {
            return [
                'source' => 'higher',
                'decision_type' => $higherType,
                'decision_text' => $this->value($row[26] ?? null) ?: $higherDecision,
                'action_text' => $this->value($row[27] ?? null) ?: null,
                'decision_date' => $this->dateValue($row[18] ?? null),
                'resurvey_completed' => $this->isYes($row[28] ?? null),
                'member_names' => $this->memberNamesFromHeaders($row, $headerRow, 19, 24),
            ];
        }

        if ($initialType === null) {
            return null;
        }

        return [
            'source' => 'initial',
            'decision_type' => $initialType,
            'decision_text' => $this->value($row[15] ?? null) ?: $initialDecision,
            'action_text' => $this->value($row[16] ?? null) ?: null,
            'decision_date' => $this->dateValue($row[7] ?? null),
            'resurvey_completed' => $this->isYes($row[17] ?? null),
            'member_names' => $this->memberNamesFromHeaders($row, $headerRow, 8, 13),
        ];
    }

    private function decisionType(string $decision): ?string
    {
        if ($decision === '') {
            return null;
        }

        if ($this->containsAny($decision, ['لجنة', 'ظ„ط¬ظ†ط©', 'ط¸â€‍ط·آ¬ط¸â€ ط·آ©'])) {
            return CommitteeDecision::TYPE_HIGHER_COMMITTEE;
        }

        if ($this->containsAny($decision, ['كلي', 'ظƒظ„ظٹ', 'ط¸ئ’ط¸â€‍ط¸ظ¹'])) {
            return CommitteeDecision::TYPE_FULLY_DAMAGED;
        }

        if ($this->containsAny($decision, ['جزئي', 'ط¬ط²ط¦ظٹ', 'ط·آ¬ط·آ²ط·آ¦ط¸ظ¹'])) {
            return CommitteeDecision::TYPE_PARTIALLY_DAMAGED;
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return list<string>
     */
    private function memberNamesFromHeaders(array $row, array $headerRow, int $startIndex, int $endIndex): array
    {
        $members = [];

        for ($index = $startIndex; $index <= $endIndex; $index++) {
            $value = $this->value($row[$index] ?? null);
            $memberName = $this->value($headerRow[$index] ?? null);

            if ($value !== '' && $memberName !== '') {
                $members[] = $memberName;
            }
        }

        return array_values(array_unique($members));
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        $value = $this->value($value);

        return $value !== '' ? Carbon::parse($value)->toDateString() : null;
    }

    private function isYes(mixed $value): bool
    {
        $normalizedValue = str($this->value($value))->lower()->trim()->toString();

        if (in_array($normalizedValue, ['نعم', 'yes', 'y', '1', 'true'], true)) {
            return true;
        }

        return $this->value($value) === 'ظ†ط¹ظ…';
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function blankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->value($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function value(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }
}
