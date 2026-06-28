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

        $records = [];

        foreach (array_slice($rows, $headerIndex + 1) as $offset => $row) {
            $rowNumber = $headerIndex + $offset + 2;

            if ($this->blankRow($row)) {
                continue;
            }

            $objectId = $this->value($row[0] ?? null);

            if ($objectId === '') {
                $issues[] = [
                    'sheet' => $sheet->getTitle(),
                    'row' => $rowNumber,
                    'reason' => 'ObjectID is empty.',
                ];

                continue;
            }

            $decisionPayload = $this->decisionPayload($row);

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
                'member_id_numbers' => $decisionPayload['member_id_numbers'],
                'use_excel_member_ids' => true,
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
            if ($this->value($row[0] ?? null) === 'ObjectID') {
                return $index;
            }
        }

        return null;
    }

    private function recordTypeFromSheetName(string $sheetName): ?string
    {
        if (str_contains($sheetName, 'وحدات')) {
            return 'housing-unit';
        }

        if (str_contains($sheetName, 'مباني')) {
            return 'building';
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function decisionPayload(array $row): ?array
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
                'member_id_numbers' => $this->memberIdNumbers($row, 19, 24),
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
            'member_id_numbers' => $this->memberIdNumbers($row, 8, 13),
        ];
    }

    private function decisionType(string $decision): ?string
    {
        if ($decision === '') {
            return null;
        }

        if (str_contains($decision, 'لجنة')) {
            return CommitteeDecision::TYPE_HIGHER_COMMITTEE;
        }

        if (str_contains($decision, 'كلي')) {
            return CommitteeDecision::TYPE_FULLY_DAMAGED;
        }

        if (str_contains($decision, 'جزئي')) {
            return CommitteeDecision::TYPE_PARTIALLY_DAMAGED;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return list<string>
     */
    private function memberIdNumbers(array $row, int $startIndex, int $endIndex): array
    {
        $members = [];

        for ($index = $startIndex; $index <= $endIndex; $index++) {
            $value = preg_replace('/\D+/', '', $this->value($row[$index] ?? null)) ?? '';

            if ($value !== '') {
                $members[] = $value;
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
        return $this->value($value) === 'نعم';
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
