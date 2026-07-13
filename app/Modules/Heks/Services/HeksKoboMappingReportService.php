<?php

namespace App\Modules\Heks\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HeksKoboMappingReportService
{
    /**
     * @param  array<string, array{technical: string, labels: string}>  $pairs
     * @return array{mapping_report: string, boq_report: string, rows: int, boq_rows: int}
     */
    public function generate(array $pairs, string $outputDirectory): array
    {
        File::ensureDirectoryExists($outputDirectory);

        $mappingWorkbook = new Spreadsheet;
        $mappingSheet = $mappingWorkbook->getActiveSheet();
        $mappingSheet->setTitle('Mapping');
        $mappingSheet->fromArray([
            'service_name',
            'sheet_name',
            'kobo_field',
            'display_label',
            'sample_value_type',
            'detected_data_type',
            'wide_table',
            'wide_column',
            'normalized_table',
            'normalized_column',
            'mapping_status',
            'confidence',
            'notes',
        ], null, 'A1');

        $boqWorkbook = new Spreadsheet;
        $boqSheet = $boqWorkbook->getActiveSheet();
        $boqSheet->setTitle('BOQ Mapping');
        $boqSheet->fromArray([
            'section',
            'item_code',
            'kobo_quantity_field',
            'display_label',
            'description',
            'unit',
            'catalog_item_id',
            'unit_price',
            'duplicate_code',
            'mapping_status',
            'notes',
        ], null, 'A1');

        $mappingRow = 2;
        $boqRow = 2;

        foreach ($pairs as $serviceName => $paths) {
            $technicalWorkbook = IOFactory::load($paths['technical']);
            $labelsWorkbook = IOFactory::load($paths['labels']);
            $wideTable = $this->wideTable($serviceName);

            foreach ($technicalWorkbook->getWorksheetIterator() as $technicalSheet) {
                $labelsSheet = $labelsWorkbook->getSheetByName($technicalSheet->getTitle());

                if (! $labelsSheet instanceof Worksheet) {
                    continue;
                }

                $technicalHeaderRow = $this->headerRow($technicalSheet);
                $labelsHeaderRow = $this->headerRow($labelsSheet);
                $lastColumn = max($this->highestColumnIndex($technicalSheet), $this->highestColumnIndex($labelsSheet));

                for ($column = 1; $column <= $lastColumn; $column++) {
                    $field = $this->cellText($technicalSheet, $column, $technicalHeaderRow);

                    if ($field === '') {
                        continue;
                    }

                    $label = $this->cellText($labelsSheet, $column, $labelsHeaderRow);
                    $sample = $this->sampleValue($technicalSheet, $column, $technicalHeaderRow + 1);
                    $dataType = $this->detectDataType($sample);
                    $wideColumn = $this->columnName($field);
                    [$normalizedTable, $normalizedColumn, $status, $confidence, $notes] = $this->normalizedTarget($field, $label, $serviceName);

                    $mappingSheet->fromArray([
                        $serviceName,
                        $technicalSheet->getTitle(),
                        $field,
                        $label,
                        get_debug_type($sample),
                        $dataType,
                        $wideTable,
                        $wideColumn,
                        $normalizedTable,
                        $normalizedColumn,
                        $status,
                        $confidence,
                        $notes,
                    ], null, "A{$mappingRow}");
                    $mappingRow++;

                    if ($this->looksLikeBoqQuantity($field, $label)) {
                        $boqSheet->fromArray([
                            '',
                            $this->itemCodeFromField($field),
                            $field,
                            $label,
                            $label,
                            '',
                            '',
                            '',
                            'no',
                            'boq_quantity',
                            'Detected from technical field or label. Review before normalized import.',
                        ], null, "A{$boqRow}");
                        $boqRow++;
                    }
                }
            }

            $technicalWorkbook->disconnectWorksheets();
            $labelsWorkbook->disconnectWorksheets();
        }

        foreach (range('A', 'M') as $column) {
            $mappingSheet->getColumnDimension($column)->setAutoSize(true);
        }

        foreach (range('A', 'K') as $column) {
            $boqSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $mappingReport = $outputDirectory.DIRECTORY_SEPARATOR.'heks_mapping_report.xlsx';
        $boqReport = $outputDirectory.DIRECTORY_SEPARATOR.'heks_boq_mapping_report.xlsx';

        (new Xlsx($mappingWorkbook))->save($mappingReport);
        (new Xlsx($boqWorkbook))->save($boqReport);

        $mappingWorkbook->disconnectWorksheets();
        $boqWorkbook->disconnectWorksheets();

        return [
            'mapping_report' => $mappingReport,
            'boq_report' => $boqReport,
            'rows' => max(0, $mappingRow - 2),
            'boq_rows' => max(0, $boqRow - 2),
        ];
    }

    private function headerRow(Worksheet $sheet): int
    {
        $bestRow = 1;
        $bestCount = 0;

        for ($row = 1; $row <= min(10, $sheet->getHighestDataRow()); $row++) {
            $count = 0;

            for ($column = 1; $column <= $this->highestColumnIndex($sheet); $column++) {
                if ($this->cellText($sheet, $column, $row) !== '') {
                    $count++;
                }
            }

            if ($count > $bestCount) {
                $bestRow = $row;
                $bestCount = $count;
            }
        }

        return $bestRow;
    }

    private function highestColumnIndex(Worksheet $sheet): int
    {
        return Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    }

    private function cellText(Worksheet $sheet, int $column, int $row): string
    {
        return trim((string) ($sheet->getCell([$column, $row])->getValue() ?? ''));
    }

    private function sampleValue(Worksheet $sheet, int $column, int $startRow): mixed
    {
        for ($row = $startRow; $row <= min($sheet->getHighestDataRow(), $startRow + 25); $row++) {
            $value = $sheet->getCell([$column, $row])->getValue();

            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function detectDataType(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_numeric($value)) {
            return 'number';
        }

        if (strtotime((string) $value) !== false) {
            return 'date';
        }

        if (preg_match('/\.(jpg|jpeg|png|webp|pdf|xlsx|xls)(\?.*)?$/i', (string) $value) === 1) {
            return 'attachment';
        }

        return 'text';
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    private function normalizedTarget(string $field, string $label, string $serviceName): array
    {
        $normalized = Str::of($field.' '.$label)->lower()->toString();

        $targets = [
            'code' => ['heks_beneficiaries', 'code'],
            'application_code' => ['heks_beneficiaries', 'code'],
            'beneficiary_name' => ['heks_beneficiaries', 'name'],
            'head_name' => ['heks_beneficiaries', 'name'],
            'identity' => ['heks_beneficiaries', 'identity_number'],
            'phone' => ['heks_beneficiaries', 'phone'],
            'visit' => [str_contains($serviceName, 'followup') ? 'heks_follow_ups' : 'heks_beneficiaries', 'visit_date'],
        ];

        foreach ($targets as $needle => [$table, $column]) {
            if (str_contains($normalized, $needle)) {
                return [$table, $column, 'mapped', 'medium', 'Heuristic mapping candidate; review before production import.'];
            }
        }

        if ($this->looksLikeAttachment($field, $label)) {
            return ['heks_attachments', 'filename/url', 'attachment', 'high', 'Attachment-like field.'];
        }

        if ($this->looksLikeBoqQuantity($field, $label)) {
            return ['heks_boq_items', 'quantity', 'boq_quantity', 'medium', 'BOQ quantity-like field.'];
        }

        return ['', '', 'wide_only', 'low', 'Kept in wide table and raw_data until confirmed.'];
    }

    private function wideTable(string $serviceName): string
    {
        return match ($serviceName) {
            'heks_followup' => 'heks_followups_kobo_records',
            'heks_boq' => 'heks_boq_kobo_records',
            'heks_followup_boq' => 'heks_followup_boq_kobo_records',
            default => 'heks_main_kobo_records',
        };
    }

    private function columnName(string $field): string
    {
        $column = Str::of($field)
            ->replace(['/', '-', '.', ' ', ':'], '_')
            ->replaceMatches('/[^A-Za-z0-9_]+/', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->lower()
            ->toString();

        if ($column === '') {
            return 'field_'.substr(sha1($field), 0, 12);
        }

        if (is_numeric($column[0])) {
            $column = 'field_'.$column;
        }

        return strlen($column) > 58 ? substr($column, 0, 45).'_'.substr(sha1($field), 0, 12) : $column;
    }

    private function looksLikeAttachment(string $field, string $label): bool
    {
        $value = Str::lower($field.' '.$label);

        return str_contains($value, 'photo')
            || str_contains($value, 'image')
            || str_contains($value, 'attachment')
            || str_contains($value, '_url')
            || str_contains($value, 'صورة')
            || str_contains($value, 'مرفق');
    }

    private function looksLikeBoqQuantity(string $field, string $label): bool
    {
        $value = Str::lower($field.' '.$label);

        return str_contains($value, 'boq')
            || str_contains($value, 'quantity')
            || str_contains($value, 'qty')
            || str_contains($value, 'كمية')
            || preg_match('~(^|[/_])_?\d+_\d+($|[/_])~', $field) === 1;
    }

    private function itemCodeFromField(string $field): string
    {
        if (preg_match('/(\d+)[._](\d+)/', $field, $matches) === 1) {
            return "{$matches[1]}.{$matches[2]}";
        }

        return '';
    }
}
