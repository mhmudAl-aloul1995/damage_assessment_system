<?php

namespace App\Modules\Reh\Services;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class RehBudgetExport extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison
{
    public function __construct(private array $budget, private array $filters) {}

    public function array(): array
    {
        $rows = [['ميزانية التأهيل حسب الفلاتر'], ['عملة الميزانية', 'USD'], ['إصدار الأسعار', $this->budget['book']?->name ?? 'غير متوفر'], ['تاريخ الإصدار', now()->format('Y-m-d H:i')], ['شيكل لكل دولار', $this->budget['book']?->exchange_rate], ['الأسعار دون إضافة ضريبة']];
        foreach ($this->filters as $name => $value) {
            $rows[] = [$name, is_array($value) ? implode('، ', $value) : $value];
        }
        $rows[] = ['تكلفة الوحدات مكتملة التسعير USD', $this->budget['usd']];
        $rows[] = ['مبالغ جزئية لوحدات غير مكتملة USD', $this->budget['partialUsd']];
        $rows[] = ['تكلفة الوحدات مكتملة التسعير ₪', $this->budget['total']];
        $rows[] = ['مبالغ جزئية لوحدات غير مكتملة ₪', $this->budget['partialTotal']];
        $rows[] = [];
        $rows[] = ['معرّف حصر الوحدة', 'معرّف حصر المبنى', 'التكلفة المحسوبة USD', 'المقابل ₪', 'الحالة', 'ملاحظات الاستكمال'];
        foreach ($this->budget['units'] as $unit) {
            $rows[] = [$unit['reference'], $unit['building'], $unit['usd'], $unit['total'], $unit['complete'] ? 'مكتملة التسعير' : 'جزئية', $unit['missing']];
        }
        $rows[] = [];
        $rows[] = ['كود البند', 'وصف البند', 'الكمية', 'الوحدة', 'القيمة USD', 'المقابل ₪'];
        foreach ($this->budget['items'] as $item) {
            $rows[] = [$item['code'], $item['label'], $item['quantity'], $item['unit'], $item['usd'] ?? 'بانتظار السعر', $item['missing'] ? 'بانتظار السعر' : $item['total']];
        }

        return $rows;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $sheet->setRightToLeft(true);
            $sheet->getColumnDimension('A')->setWidth(28);
            $sheet->getColumnDimension('B')->setWidth(65);
            foreach (['C', 'D', 'E', 'F'] as $column) {
                $sheet->getColumnDimension($column)->setWidth(22);
            }
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true);
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        }];
    }
}
