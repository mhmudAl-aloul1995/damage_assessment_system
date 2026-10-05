<?php

namespace App\Console\Commands;

use App\Modules\Heks\Models\HeksBoqCatalogItem;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportHeksBoqCatalog extends Command
{
    protected $signature = 'heks:import-boq-catalog
        {file : Excel file containing the approved BOQ catalog}
        {--sheet=جدول كميات معتمد : Worksheet name to import}
        {--dry-run : Preview imported rows without saving}';

    protected $description = 'Import the HEKS BOQ pricing catalog from an approved Excel workbook';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        $workbook = IOFactory::load($path);
        $sheet = $this->worksheet($workbook);
        $columns = $this->headerColumns($sheet);

        if ($columns === []) {
            $this->components->error('Could not find BOQ catalog headers.');
            $workbook->disconnectWorksheets();

            return self::FAILURE;
        }

        $rows = $this->catalogRows($sheet, $columns);
        $created = 0;
        $updated = 0;

        if (! $this->option('dry-run')) {
            foreach ($rows as $row) {
                $item = HeksBoqCatalogItem::query()->updateOrCreate(
                    ['item_code' => $row['item_code']],
                    [
                        'section' => $row['section'],
                        'description' => $row['description'],
                        'unit' => $row['unit'],
                        'unit_price_ils' => $row['unit_price_ils'],
                        'is_active' => true,
                        'sort_order' => $row['sort_order'],
                    ]
                );

                $item->wasRecentlyCreated ? $created++ : $updated++;
            }
        }

        $workbook->disconnectWorksheets();

        $this->components->info(
            $this->option('dry-run')
                ? "HEKS BOQ catalog preview: {$rows->count()} rows."
                : "HEKS BOQ catalog import finished. Created: {$created}, updated: {$updated}."
        );

        return self::SUCCESS;
    }

    private function worksheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $workbook): Worksheet
    {
        $sheetName = trim((string) $this->option('sheet'));

        return $sheetName !== '' && $workbook->sheetNameExists($sheetName)
            ? $workbook->getSheetByName($sheetName)
            : $workbook->getSheet(0);
    }

    /**
     * @return array{header_row: int, code: int, description: int, unit: int, price: int}|array{}
     */
    private function headerColumns(Worksheet $sheet): array
    {
        for ($row = 1; $row <= min(30, $sheet->getHighestDataRow()); $row++) {
            $columns = [];

            for ($column = 1; $column <= 30; $column++) {
                $value = $this->cellValue($sheet, $column, $row);

                if ($value === '') {
                    continue;
                }

                if ($value === '#' || str_contains($value, 'رقم')) {
                    $columns['code'] = $column;
                } elseif (str_contains($value, 'وصف') || str_contains($value, 'وضف')) {
                    $columns['description'] = $column;
                } elseif (str_contains($value, 'الوحدة')) {
                    $columns['unit'] = $column;
                } elseif (str_contains($value, 'تكلفة الوحدة') || str_contains($value, 'سعر الوحدة')) {
                    $columns['price'] = $column;
                }
            }

            if (isset($columns['code'], $columns['description'], $columns['unit'], $columns['price'])) {
                return [
                    'header_row' => $row,
                    'code' => $columns['code'],
                    'description' => $columns['description'],
                    'unit' => $columns['unit'],
                    'price' => $columns['price'],
                ];
            }

            if (isset($columns['code'])) {
                $codeColumn = $columns['code'];
                $description = $this->cellValue($sheet, $codeColumn + 1, $row);
                $unit = $this->cellValue($sheet, $codeColumn + 2, $row);
                $price = $this->cellValue($sheet, $codeColumn + 3, $row);

                if ($description !== '' && $unit !== '' && $price !== '') {
                    return [
                        'header_row' => $row,
                        'code' => $codeColumn,
                        'description' => $codeColumn + 1,
                        'unit' => $codeColumn + 2,
                        'price' => $codeColumn + 3,
                    ];
                }
            }
        }

        return [];
    }

    /**
     * @param  array{header_row: int, code: int, description: int, unit: int, price: int}  $columns
     */
    private function catalogRows(Worksheet $sheet, array $columns): \Illuminate\Support\Collection
    {
        $rows = collect();
        $section = null;

        for ($row = $columns['header_row'] + 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $code = $this->cellValue($sheet, $columns['code'], $row);
            $description = $this->cellValue($sheet, $columns['description'], $row);
            $unit = $this->cellValue($sheet, $columns['unit'], $row);
            $price = $this->numericValue($this->cellValue($sheet, $columns['price'], $row));

            if ($code !== '' && $description === '' && $unit === '' && $price === null) {
                $section = $code;

                continue;
            }

            if (! $this->isItemCode($code) || $description === '' || $price === null) {
                continue;
            }

            $rows->push([
                'section' => $section,
                'item_code' => $this->normalizeItemCode($code),
                'description' => $description,
                'unit' => $unit,
                'unit_price_ils' => $price,
                'sort_order' => $rows->count() + 1,
            ]);
        }

        return $rows;
    }

    private function numericValue(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function cellValue(Worksheet $sheet, int $column, int $row): string
    {
        $coordinate = Coordinate::stringFromColumnIndex($column).$row;

        return trim((string) $sheet->getCell($coordinate)->getValue());
    }

    private function isItemCode(string $value): bool
    {
        return preg_match('/^\d+(?:[\.,]\d+)?$/', trim($value)) === 1;
    }

    private function normalizeItemCode(string $value): string
    {
        return str_replace(',', '.', trim($value));
    }
}
