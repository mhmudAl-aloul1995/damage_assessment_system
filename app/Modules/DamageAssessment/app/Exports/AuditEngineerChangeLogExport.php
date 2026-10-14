<?php

namespace App\Modules\DamageAssessment\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class AuditEngineerChangeLogExport extends StringValueBinder implements FromGenerator, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    public function __construct(private readonly Generator $rows) {}

    public function generator(): Generator
    {
        foreach ($this->rows as $row) {
            yield [
                $row['record_type_label'],
                $row['objectid'],
                $row['building_name'],
                $row['building_objectid'],
                $row['housing_unit_number'],
                $row['global_id'],
                $row['engineer_name'],
                $row['field_label'],
                $row['field_name'],
                $row['old_value'],
                $row['new_value'],
                $row['edited_at'],
            ];
        }
    }

    public function headings(): array
    {
        return [
            'النوع', 'ObjectID', 'اسم المبنى', 'ObjectID المبنى', 'رقم الوحدة', 'GlobalID',
            'المهندس', 'اسم الحقل', 'رمز الحقل', 'قبل', 'بعد', 'تاريخ التعديل',
        ];
    }

    public function title(): string
    {
        return 'تغييرات مهندسي التدقيق';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 16, 'C' => 30, 'D' => 18, 'E' => 14, 'F' => 40,
            'G' => 28, 'H' => 30, 'I' => 30, 'J' => 45, 'K' => 45, 'L' => 22,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:L'.$sheet->getHighestRow());
                $sheet->getStyle('A1:L1')->getFont()->setBold(true);
                $sheet->getStyle('A1:L'.$sheet->getHighestRow())->getAlignment()->setWrapText(true);
            },
        ];
    }
}
