<?php

namespace App\Modules\Reh\Services;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class RehPriceImport
{
    public function __construct(private RehSurvey $survey) {}

    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'ملف Excel غير صالح.']);
        }
        $expanded = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $expanded += $zip->statIndex($i)['size'];
        }
        $zip->close();
        if ($expanded > 40000000) {
            throw ValidationException::withMessages(['file' => 'محتوى الملف أكبر من الحد المسموح.']);
        }
        $reader = IOFactory::createReader('Xlsx');
        $info = $reader->listWorksheetInfo($path);
        if (count($info) > 40 || collect($info)->contains(fn (array $sheet): bool => $sheet['totalRows'] > 5000 || $sheet['totalColumns'] > 40)) {
            throw ValidationException::withMessages(['file' => 'الملف يتجاوز حدود قالب تحليل الأسعار.']);
        }
        $workbook = $reader->load($path);
        try {
            $boq = $workbook->getSheetByName('BoQ');
            if (! $boq) {
                throw ValidationException::withMessages(['file' => 'ورقة BoQ غير موجودة.']);
            }
            $catalog = [];
            foreach ($this->survey->dictionary()['fields'] as $field => $definition) {
                if (preg_match('/^boq_(\d+)_(\d+)_/', $field, $matches)) {
                    $catalog[$matches[1].'.'.$matches[2]] = ['field' => $field, 'definition' => $definition];
                }
            }
            $analyses = [];
            foreach ($workbook->getWorksheetIterator() as $sheet) {
                if (in_array($sheet->getTitle(), ['BoQ', 'الموردون'], true)) {
                    continue;
                }
                $code = null;
                for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
                    $title = (string) $sheet->getCell('A'.$row)->getValue();
                    if (preg_match('/(?:بند\s+|^\s*)(\d+\.\d+)\s*:/u', $title, $matches)) {
                        $code = $matches[1];
                        if (isset($analyses[$code])) {
                            throw ValidationException::withMessages(['file' => 'تحليل مكرر للبند '.$code]);
                        }
                        $analyses[$code] = ['components' => [], 'cell' => null];
                    }
                    if (! $code) {
                        continue;
                    }
                    $label = trim((string) $sheet->getCell('B'.$row)->getValue());
                    if (str_contains($label, 'سعر الوحدة النهائي للبند')) {
                        $analyses[$code]['cell'] = "'".$sheet->getTitle()."'!F".$row;
                        $code = null;

                        continue;
                    }
                    $quantity = $this->value($sheet->getCell('D'.$row));
                    $rate = $this->value($sheet->getCell('E'.$row));
                    $cost = $this->value($sheet->getCell('F'.$row));
                    if ($label === '' || $label === 'مكون التحليل / نطاق العمل' || ($quantity === null && $rate === null && $cost === null)) {
                        continue;
                    }
                    if ($quantity !== null && ! is_numeric($quantity) || $rate !== null && ! is_numeric($rate)) {
                        continue;
                    }
                    $analyses[$code]['components'][] = ['category' => trim($title) ?: null, 'label' => $label,
                        'unit' => $sheet->getCell('C'.$row)->getValue(), 'quantity' => $quantity, 'rate' => $rate,
                        'source_cell' => $sheet->getTitle().'!D'.$row.':F'.$row];
                }
            }
            $items = [];
            $rates = [];
            $category = '';
            for ($row = 7; $row <= $boq->getHighestDataRow(); $row++) {
                $code = trim((string) $boq->getCell('A'.$row)->getValue());
                if (ctype_digit($code)) {
                    $category = (string) $boq->getCell('B'.$row)->getValue();

                    continue;
                }
                if (! preg_match('/^\d+\.\d+$/', $code)) {
                    continue;
                }
                if (! isset($catalog[$code]) || isset($items[$code])) {
                    throw ValidationException::withMessages(['file' => 'كود غير معروف أو مكرر: '.$code]);
                }
                $unit = trim((string) $boq->getCell('C'.$row)->getValue());
                $surveyUnit = trim(str_replace('الوحدة: ', '', strtok($catalog[$code]['definition']['hint'] ?? '', "\n")));
                if ($this->normalizeUnit($unit) !== $this->normalizeUnit($surveyUnit)) {
                    throw ValidationException::withMessages(['file' => 'وحدة القياس تختلف عن الاستبيان للبند '.$code]);
                }
                $rate = $this->value($boq->getCell('E'.$row));
                $exchange = $this->value($boq->getCell('F'.$row));
                if (! is_numeric($exchange) || $exchange <= 0) {
                    throw ValidationException::withMessages(['file' => 'سعر الصرف مفقود أو غير صالح في البند '.$code]);
                }
                $rates[] = (float) $exchange;
                $unavailable = is_string($rate) && str_contains($rate, 'غير متوفر');
                $components = $analyses[$code]['components'] ?? [];
                $issue = [];
                if (! $unavailable && (! is_numeric($rate) || $rate < 0 || $rate > 100000000)) {
                    $issue[] = 'سعر الوحدة غير صالح.';
                    $rate = null;
                }
                $formula = (string) $boq->getCell('E'.$row)->getValue();
                $expected = $analyses[$code]['cell'] ?? null;
                if ($expected && str_starts_with($formula, '=') && ltrim($formula, '=') !== $expected) {
                    $issue[] = 'مرجع السعر '.$formula.' لا يطابق تحليل البند '.$expected.'. راجع التحليل قبل الاعتماد.';
                }
                $complete = $components && collect($components)->every(fn (array $part): bool => is_numeric($part['quantity']) && is_numeric($part['rate']) && $part['quantity'] >= 0 && $part['rate'] >= 0);
                if (! $unavailable && ! $complete) {
                    $issue[] = 'تحليل السعر ناقص.';
                } elseif (! $unavailable && abs(array_sum(array_map(fn (array $part): float => $part['quantity'] * $part['rate'], $components)) - (float) $rate) > 0.01) {
                    $sum = array_sum(array_map(fn (array $part): float => $part['quantity'] * $part['rate'], $components));
                    $issue[] = 'مجموع الكمية × السعر للمكونات '.round($sum, 6).' شيكل، بينما سعر BoQ هو '.$rate.' شيكل. راجع القيم قبل الاعتماد.';
                }
                $description = (string) $boq->getCell('B'.$row)->getValue();
                $items[$code] = ['code' => $code, 'field' => $catalog[$code]['field'], 'category' => $category,
                    'label' => strtok($description, "\n"), 'description' => $description, 'unit' => $unit,
                    'availability' => $unavailable ? 'unavailable' : 'available',
                    'rate' => $unavailable ? null : $rate, 'issue' => $issue ? implode(' ', $issue) : null,
                    'source_cell' => 'BoQ!E'.$row, 'components' => $components];
            }
            if (count($items) !== count($catalog) || count(array_unique($rates)) !== 1) {
                throw ValidationException::withMessages(['file' => 'يلزم جميع بنود الاستبيان وسعر صرف موحد داخل الملف.']);
            }

            return ['items' => array_values($items), 'exchange_rate' => $rates[0], 'hash' => hash_file('sha256', $path)];
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private function value(Cell $cell): mixed
    {
        return $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    private function normalizeUnit(string $unit): string
    {
        return str_replace([' ', '²', 'مقطوعيه'], ['', '2', 'مقطوعية'], $unit);
    }
}
