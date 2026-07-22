<?php

namespace App\services;

use App\Models\CommitteeDecision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
    /**
     * @param  array{units_only?: bool, ignore_higher_committee?: bool, dry_run?: bool, recognize_arabic_yes?: bool}  $options
     * @return array<string, mixed>
     */
    public function import(string $path, array $options = []): array
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
            $sheetRecords = $this->recordsFromSheet($sheet, $summary['parse_issues'], $options);
            $summary['sheets'][$sheet->getTitle()] = count($sheetRecords);
            $records = [...$records, ...$sheetRecords];
        }

        $importSummary = $this->importWorkflowRecords($records, (bool) ($options['dry_run'] ?? false));

        return [
            ...$importSummary,
            'sheets' => $summary['sheets'],
            'parse_issues' => $summary['parse_issues'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function importWorkflowRecords(array $records, bool $dryRun): array
    {
        if (! $dryRun) {
            return $this->workflowImporter->importRecords($records);
        }

        DB::beginTransaction();

        try {
            $summary = $this->workflowImporter->importRecords($records);
        } finally {
            DB::rollBack();
        }

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return list<array<string, mixed>>
     */
    private function recordsFromSheet(Worksheet $sheet, array &$issues, array $options): array
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

        if (($options['units_only'] ?? false) === true && $recordType !== 'housing-unit') {
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

            $decisionPayload = $this->decisionPayload($row, $headerRow, $recordType, $options);

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
                'municipality' => trim($this->cellValue($row, $headerRow, [
                    "\u{0627}\u{0644}\u{0645}\u{062D}\u{0627}\u{0641}\u{0638}\u{0629}",
                ], $recordType === 'housing-unit' ? 6 : 5).' '.$this->cellValue($row, $headerRow, [
                    "\u{0627}\u{0644}\u{062D}\u{064A}",
                ], $recordType === 'housing-unit' ? 7 : 6)),
                'sheet' => $sheet->getTitle(),
                'row' => $rowNumber,
                'objectid' => $objectId,
                'globalid' => null,
                'decision_type' => $decisionPayload['decision_type'],
                'decision_text' => $decisionPayload['decision_text'],
                'action_text' => $decisionPayload['action_text'],
                'decision_date' => $decisionPayload['decision_date'],
                'resurvey_completed' => $decisionPayload['resurvey_completed'],
                'defer_resurvey_arcgis' => true,
                'member_names' => $decisionPayload['member_names'],
                'use_excel_member_names' => true,
                'force_committee_review_status' => true,
                'notes' => trim(implode("\n", array_filter([
                    'Excel sheet: '.$sheet->getTitle().' row: '.$rowNumber,
                    'Researcher: '.$this->cellValue($row, $headerRow, [
                        "\u{0627}\u{0633}\u{0645} \u{0627}\u{0644}\u{0628}\u{0627}\u{062D}\u{062B}",
                    ], $recordType === 'housing-unit' ? 2 : 1),
                    'Building/Unit: '.$this->cellValue($row, $headerRow, ['Building Name'], $recordType === 'housing-unit' ? 4 : 3),
                    'Comments: '.$this->cellValue($row, $headerRow, ['6.1 Comments & Recommendations'], $recordType === 'housing-unit' ? 5 : 4),
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
            return $this->columnIndex($headerRow, ['UNITID', 'Export Row Id', 'ObjectID']);
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
        if ($this->containsAny($sheetName, ["\u{0648}\u{062D}\u{062F}\u{0627}\u{062A}", 'ظˆط­ط¯ط§طھ', 'ط¸ث†ط·آ­ط·آ¯ط·آ§ط·ع¾', 'ط·آ¸ط«â€ ط·آ·ط¢آ­ط·آ·ط¢آ¯ط·آ·ط¢آ§ط·آ·ط¹آ¾'])) {
            return 'housing-unit';
        }

        if ($this->containsAny($sheetName, ["\u{0645}\u{0628}\u{0627}\u{0646}\u{064A}", 'ظ…ط¨ط§ظ†ظٹ', 'ط¸â€¦ط·آ¨ط·آ§ط¸â€ ط¸ظ¹', 'ط·آ¸أ¢â‚¬آ¦ط·آ·ط¢آ¨ط·آ·ط¢آ§ط·آ¸أ¢â‚¬آ ط·آ¸ط¸آ¹'])) {
            return 'building';
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function decisionPayload(array $row, array $headerRow, string $recordType, array $options): ?array
    {
        $layout = $this->decisionLayout($headerRow, $recordType);
        $initialDecision = $this->value($row[$layout['initial_decision']] ?? null);
        $higherDecision = $this->value($row[$layout['higher_decision']] ?? null);
        $initialType = $this->decisionType($initialDecision);
        $higherType = $this->decisionType($higherDecision);

        if (
            ($options['ignore_higher_committee'] ?? false) !== true
            && $initialType === CommitteeDecision::TYPE_HIGHER_COMMITTEE
            && $higherType !== null
        ) {
            return [
                'source' => 'higher',
                'decision_type' => $higherType,
                'decision_text' => $this->value($row[$layout['higher_text']] ?? null) ?: $higherDecision,
                'action_text' => $this->value($row[$layout['higher_action']] ?? null) ?: null,
                'decision_date' => $this->dateValue($row[$layout['higher_date']] ?? null),
                'resurvey_completed' => $this->isYes($row[$layout['higher_resurvey']] ?? null, (bool) ($options['recognize_arabic_yes'] ?? true)),
                'member_names' => $this->memberNamesFromHeaders($row, $headerRow, $layout['higher_members_start'], $layout['higher_members_end']),
            ];
        }

        if ($initialType === null) {
            return null;
        }

        return [
            'source' => 'initial',
            'decision_type' => $initialType,
            'decision_text' => $this->value($row[$layout['initial_text']] ?? null) ?: $initialDecision,
            'action_text' => $this->value($row[$layout['initial_action']] ?? null) ?: null,
            'decision_date' => $this->dateValue($row[$layout['initial_date']] ?? null),
            'resurvey_completed' => $this->isYes($row[$layout['initial_resurvey']] ?? null, (bool) ($options['recognize_arabic_yes'] ?? true)),
            'member_names' => $this->memberNamesFromHeaders($row, $headerRow, $layout['initial_members_start'], $layout['initial_members_end']),
        ];
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function decisionLayout(array $headerRow, string $recordType): array
    {
        $offset = $recordType === 'housing-unit' ? 1 : 0;
        $initialDate = $this->firstColumnIndex($headerRow, [
            "\u{062A}\u{0627}\u{0631}\u{064A}\u{062E} \u{0627}\u{0646}\u{0639}\u{0642}\u{0627}\u{062F} \u{0627}\u{0644}\u{0644}\u{062C}\u{0646}\u{0629}",
        ], fn (string $header): bool => ! str_contains($header, $this->normalizeHeader("\u{0627}\u{0644}\u{0639}\u{0644}\u{064A}\u{0627}"))) ?? (7 + $offset);
        $initialDecision = $this->firstColumnIndex($headerRow, [
            "\u{0642}\u{0631}\u{0627}\u{0631} \u{0627}\u{0644}\u{0644}\u{062C}\u{0646}\u{0629}",
        ], fn (string $header): bool => ! str_contains($header, $this->normalizeHeader("\u{0627}\u{0644}\u{0639}\u{0644}\u{064A}\u{0627}"))) ?? (14 + $offset);
        $initialText = $this->firstColumnIndex($headerRow, [
            "\u{0646}\u{0635} \u{0642}\u{0631}\u{0627}\u{0631} \u{0627}\u{0644}\u{0644}\u{062C}\u{0646}\u{0629}",
        ], fn (string $header): bool => ! str_contains($header, $this->normalizeHeader("\u{0627}\u{0644}\u{0639}\u{0644}\u{064A}\u{0627}"))) ?? (15 + $offset);
        $initialAction = $this->firstColumnIndex($headerRow, [
            "\u{0627}\u{0644}\u{0625}\u{062C}\u{0631}\u{0627}\u{0621} \u{0627}\u{0644}\u{0645}\u{0637}\u{0644}\u{0648}\u{0628}",
        ]) ?? (16 + $offset);
        $initialResurvey = $this->firstColumnIndex($headerRow, [
            "\u{0647}\u{0644} \u{062A}\u{0645} \u{0625}\u{0639}\u{0627}\u{062F}\u{0629} \u{062D}\u{0635}\u{0631}\u{0647}",
        ], fn (string $header): bool => ! str_contains($header, $this->normalizeHeader("\u{0628}\u{0639}\u{062F}"))) ?? (17 + $offset);

        return [
            'initial_date' => $initialDate,
            'initial_members_start' => $initialDate + 1,
            'initial_members_end' => max($initialDate + 1, $initialDecision - 1),
            'initial_decision' => $initialDecision,
            'initial_text' => $initialText,
            'initial_action' => $initialAction,
            'initial_resurvey' => $initialResurvey,
            'higher_date' => 18 + $offset,
            'higher_members_start' => 19 + $offset,
            'higher_members_end' => 24 + $offset,
            'higher_decision' => 25 + $offset,
            'higher_text' => 26 + $offset,
            'higher_action' => 27 + $offset,
            'higher_resurvey' => 28 + $offset,
        ];
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @param  list<string>  $labels
     */
    private function firstColumnIndex(array $headerRow, array $labels, ?callable $filter = null): ?int
    {
        foreach ($labels as $label) {
            $normalizedLabel = $this->normalizeHeader($label);

            foreach ($headerRow as $index => $header) {
                $normalizedHeader = $this->normalizeHeader($this->value($header));

                if ($normalizedHeader === $normalizedLabel && ($filter === null || $filter($normalizedHeader))) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function decisionType(string $decision): ?string
    {
        if ($decision === '') {
            return null;
        }

        if ($this->containsAny($decision, ["\u{0644}\u{062C}\u{0646}\u{0629}", 'ظ„ط¬ظ†ط©', 'ط¸â€‍ط·آ¬ط¸â€ ط·آ©'])) {
            return CommitteeDecision::TYPE_HIGHER_COMMITTEE;
        }

        if ($this->containsAny($decision, ["\u{0643}\u{0644}\u{064A}", 'ظƒظ„ظٹ', 'ط¸ئ’ط¸â€‍ط¸ظ¹'])) {
            return CommitteeDecision::TYPE_FULLY_DAMAGED;
        }

        if ($this->containsAny($decision, ["\u{062C}\u{0632}\u{0626}\u{064A}", 'ط¬ط²ط¦ظٹ', 'ط·آ¬ط·آ²ط·آ¦ط¸ظ¹'])) {
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

    private function isYes(mixed $value, bool $recognizeArabicYes): bool
    {
        $normalizedValue = str($this->value($value))->lower()->trim()->toString();

        if ($recognizeArabicYes && $normalizedValue === "\u{0646}\u{0639}\u{0645}") {
            return true;
        }

        if (in_array($normalizedValue, ["\u{0646}\u{0639}\u{0645}", 'ظ†ط¹ظ…', 'yes', 'y', '1', 'true'], true)) {
            return true;
        }

        return $this->value($value) === 'ظ†ط¹ظ…';
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, mixed>  $headerRow
     * @param  list<string>  $labels
     */
    private function cellValue(array $row, array $headerRow, array $labels, int $fallbackIndex): string
    {
        $index = $this->columnIndex($headerRow, $labels) ?? $fallbackIndex;

        return $this->value($row[$index] ?? null);
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
