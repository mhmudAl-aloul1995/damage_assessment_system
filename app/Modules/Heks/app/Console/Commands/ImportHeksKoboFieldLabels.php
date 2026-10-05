<?php

namespace App\Console\Commands;

use App\Modules\Heks\Models\HeksKoboChoice;
use App\Modules\Heks\Models\HeksKoboFieldMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportHeksKoboFieldLabels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:kobo-import-field-labels
        {service : HEKS service name, for example heks-main}
        {technical_file : Kobo export with technical field names}
        {labels_file : Kobo export with display labels}
        {--sheet=* : Limit import to one or more worksheet names}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import HEKS Kobo technical field to display label mappings from paired Excel exports';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = (string) $this->argument('service');
        $technicalFile = (string) $this->argument('technical_file');
        $labelsFile = (string) $this->argument('labels_file');

        if (! Str::startsWith($service, 'heks-')) {
            $this->components->error('The service argument must start with heks-.');

            return self::FAILURE;
        }

        if (! is_file($technicalFile) || ! is_file($labelsFile)) {
            $this->components->error('Both Excel files must exist.');

            return self::FAILURE;
        }

        $technicalWorkbook = IOFactory::load($technicalFile);
        $labelsWorkbook = IOFactory::load($labelsFile);
        $sheetFilter = collect($this->option('sheet'))->filter()->values();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $choices = 0;

        foreach ($technicalWorkbook->getWorksheetIterator() as $technicalSheet) {
            if ($sheetFilter->isNotEmpty() && ! $sheetFilter->contains($technicalSheet->getTitle())) {
                continue;
            }

            $labelsSheet = $labelsWorkbook->getSheetByName($technicalSheet->getTitle());

            if (! $labelsSheet instanceof Worksheet) {
                $skipped++;

                continue;
            }

            [$sheetCreated, $sheetUpdated, $sheetSkipped, $sheetChoices] = $this->importSheet($service, $technicalSheet, $labelsSheet);
            $created += $sheetCreated;
            $updated += $sheetUpdated;
            $skipped += $sheetSkipped;
            $choices += $sheetChoices;
        }

        $technicalWorkbook->disconnectWorksheets();
        $labelsWorkbook->disconnectWorksheets();

        $this->components->info("HEKS Kobo field labels imported. Created: {$created}, updated: {$updated}, skipped: {$skipped}.");
        $this->components->info("HEKS Kobo choices imported from paired exports: {$choices}.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function importSheet(string $service, Worksheet $technicalSheet, Worksheet $labelsSheet): array
    {
        $technicalHeaderRow = $this->headerRow($technicalSheet);
        $labelsHeaderRow = $this->headerRow($labelsSheet);
        $lastColumn = max($this->highestColumnIndex($technicalSheet), $this->highestColumnIndex($labelsSheet));
        $tableName = $this->tableName($service);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $choices = 0;

        for ($column = 1; $column <= $lastColumn; $column++) {
            $technicalField = $this->cellText($technicalSheet, $column, $technicalHeaderRow);
            $displayLabel = $this->cellText($labelsSheet, $column, $labelsHeaderRow);

            if ($technicalField === '' || $displayLabel === '' || $technicalField === $displayLabel) {
                $skipped++;

                continue;
            }

            $mapping = HeksKoboFieldMapping::query()->firstOrNew([
                'service_name' => $service,
                'kobo_field' => $technicalField,
            ]);
            $wasRecentlyCreated = ! $mapping->exists;

            $mapping->fill([
                'table_name' => $tableName,
                'column_name' => $mapping->column_name ?: $this->uniqueColumnName($service, $technicalField),
                'display_label' => $displayLabel,
            ])->save();

            $choices += $this->importChoices($service, $mapping, $technicalField, $displayLabel, $technicalSheet, $labelsSheet, $technicalHeaderRow, $labelsHeaderRow, $column);
            $wasRecentlyCreated ? $created++ : $updated++;
        }

        return [$created, $updated, $skipped, $choices];
    }

    private function importChoices(
        string $service,
        HeksKoboFieldMapping $mapping,
        string $technicalField,
        string $displayLabel,
        Worksheet $technicalSheet,
        Worksheet $labelsSheet,
        int $technicalHeaderRow,
        int $labelsHeaderRow,
        int $column
    ): int {
        if (str_contains($technicalField, '/')) {
            [$questionKey, $choiceName] = explode('/', $technicalField, 2);

            if ($questionKey !== '' && $choiceName !== '' && $this->looksLikeCheckboxChoice($technicalSheet, $column, $technicalHeaderRow)) {
                return $this->storeChoice($service, $questionKey, $mapping->list_name, $choiceName, $this->choiceLabelFromPath($displayLabel));
            }
        }

        $fieldType = (string) ($mapping->field_type ?: $mapping->data_type);

        if (! str_starts_with($fieldType, 'select_one') && ! str_starts_with($fieldType, 'select_multiple')) {
            return 0;
        }

        $imported = 0;
        $seen = [];
        $highestRow = max($technicalSheet->getHighestDataRow(), $labelsSheet->getHighestDataRow());

        for ($row = max($technicalHeaderRow, $labelsHeaderRow) + 1; $row <= $highestRow; $row++) {
            $choiceName = $this->cellText($technicalSheet, $column, $row);
            $choiceLabel = $this->cellText($labelsSheet, $column, $row);

            if (
                $choiceName === ''
                || $choiceLabel === ''
                || $choiceName === $choiceLabel
                || isset($seen[$choiceName])
                || (str_starts_with($fieldType, 'select_multiple') && preg_match('/\s+/', $choiceName) === 1)
            ) {
                continue;
            }

            $seen[$choiceName] = true;
            $imported += $this->storeChoice($service, $technicalField, $mapping->list_name, $choiceName, $choiceLabel);
        }

        return $imported;
    }

    private function looksLikeCheckboxChoice(Worksheet $sheet, int $column, int $headerRow): bool
    {
        $highestRow = $sheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= min($highestRow, $headerRow + 25); $row++) {
            $value = $this->cellText($sheet, $column, $row);

            if ($value !== '' && ! in_array($value, ['0', '1'], true)) {
                return false;
            }
        }

        return true;
    }

    private function choiceLabelFromPath(string $displayLabel): string
    {
        if (! str_contains($displayLabel, '/')) {
            return $displayLabel;
        }

        return trim((string) Str::of($displayLabel)->afterLast('/'));
    }

    private function storeChoice(string $service, string $questionKey, ?string $listName, string $choiceName, string $choiceLabel): int
    {
        $choice = HeksKoboChoice::query()->updateOrCreate([
            'service_name' => $service,
            'question_key' => $questionKey,
            'choice_name' => $choiceName,
            'language' => 'ar',
        ], [
            'list_name' => $listName,
            'choice_label' => $choiceLabel,
            'is_active' => true,
        ]);

        return $choice->wasRecentlyCreated ? 1 : 0;
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

    private function tableName(string $service): string
    {
        return match ($service) {
            'heks-followups' => 'heks_followups_kobo_records',
            'heks-boq' => 'heks_boq_kobo_records',
            'heks-followup-boq' => 'heks_followup_boq_kobo_records',
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
            $column = 'field_'.substr(sha1($field), 0, 12);
        }

        if (is_numeric($column[0])) {
            $column = 'field_'.$column;
        }

        if (strlen($column) > 58) {
            $column = substr($column, 0, 45).'_'.substr(sha1($field), 0, 12);
        }

        return $column;
    }

    private function uniqueColumnName(string $service, string $field): string
    {
        $base = $this->columnName($field);
        $column = $base;
        $attempt = 0;

        while (HeksKoboFieldMapping::query()->where('service_name', $service)->where('column_name', $column)->exists()) {
            $attempt++;
            $suffix = substr(sha1($field.$attempt), 0, 8);
            $column = substr($base, 0, 55).'_'.$suffix;
        }

        return $column;
    }
}
