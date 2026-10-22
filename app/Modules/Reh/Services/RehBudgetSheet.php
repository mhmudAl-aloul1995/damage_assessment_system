<?php

namespace App\Modules\Reh\Services;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class RehBudgetSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    public function __construct(
        private string $name,
        private array $rows,
        private array $widths,
        private array $formats = [],
        private bool $filterable = true,
        private ?string $statusColumn = null,
    ) {}

    public function title(): string
    {
        return $this->name;
    }

    public function array(): array
    {
        return array_map(fn (array $row): array => $row === [] ? [''] : $row, $this->rows);
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
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->widths));
            $lastRow = count($this->rows);
            $sheet->setRightToLeft(true)->setShowGridlines(false);
            $sheet->getTabColor()->setRGB('164E56');
            $sheet->getSheetView()->setZoomScale(85);
            $sheet->getStyle('A1:'.$lastColumn.$lastRow)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '233748']],
                'alignment' => ['vertical' => 'center', 'horizontal' => 'right', 'wrapText' => true],
            ]);
            foreach ($this->widths as $index => $width) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
            }
            $sheet->mergeCells('A2:'.$lastColumn.'2');
            $sheet->mergeCells('A3:'.$lastColumn.'3');
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('164E56');
            $sheet->getRowDimension(2)->setRowHeight(32);
            $sheet->getRowDimension(3)->setRowHeight(30);
            $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB('596B79');
            $sheet->getStyle('A5:'.$lastColumn.'5')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '164E56']],
                'alignment' => ['horizontal' => 'center'],
            ]);
            $sheet->getRowDimension(5)->setRowHeight(36);
            for ($row = 6; $row <= $lastRow; $row++) {
                $length = max(array_map(fn (mixed $value): int => is_string($value) ? mb_strlen($value) : 0, $this->rows[$row - 1]) ?: [0]);
                $sheet->getRowDimension($row)->setRowHeight(min(90, max(32, ceil($length / 45) * 16)));
                if ($row % 2 === 0) {
                    $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F7F7');
                }
            }
            foreach ($this->formats as $column => $format) {
                if ($lastRow >= 6) {
                    $sheet->getStyle($column.'6:'.$column.$lastRow)->getNumberFormat()->setFormatCode($format);
                }
            }
            if ($this->filterable) {
                $sheet->freezePane('C6');
                $sheet->setAutoFilter('A5:'.$lastColumn.max(5, $lastRow));
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 5);
            } else {
                $sheet->getStyle('C6:D7')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A7:E7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF4E2');
                $sheet->getStyle('B6:B8')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('B12')->getNumberFormat()->setFormatCode('0.0000');
                $sheet->getStyle('A15')->getFont()->setBold(true);
            }
            if ($this->statusColumn && $lastRow >= 6) {
                $rules = [];
                foreach (['مكتملة التسعير' => 'E8F4EF', 'مسعّر' => 'E8F4EF', 'مبلغ جزئي' => 'FFF1D6', 'لم تُحسب تكلفة' => 'FBEAEA', 'بانتظار السعر' => 'FBEAEA'] as $status => $color) {
                    $rule = new Conditional;
                    $rule->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition('"'.$status.'"');
                    $rule->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                    $rules[] = $rule;
                }
                $sheet->getStyle($this->statusColumn.'6:'.$this->statusColumn.$lastRow)->setConditionalStyles($rules);
            }
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setPrintArea('A2:'.$lastColumn.$lastRow);
            $sheet->getHeaderFooter()->setOddFooter('&Rصفحة &P من &N');
        }];
    }
}
