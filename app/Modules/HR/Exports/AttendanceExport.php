<?php

namespace App\Modules\HR\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class AttendanceExport extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithHeadings
{
    public function __construct(private Collection $rows) {}

    public function headings(): array
    {
        return ['رقم الموظف', 'الاسم', 'التاريخ', 'الجدول', 'المنطقة الزمنية', 'الحضور', 'الانصراف', 'الحالة', 'المصدر', 'السجل القديم', 'الدقائق المتوقعة', 'دقائق العمل', 'دقائق التأخير', 'دقائق الانصراف المبكر', 'الوقت الزائد غير المعتمد', 'تعارض'];
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn (array $row): array => [
            $row['employee_number'], $row['name'], $row['date'], $row['schedule'], $row['timezone'],
            $row['check_in'], $row['check_out'], __('hr::attendance.statuses.'.$row['status']), $row['source'],
            $row['legacy_status'], $row['expected_minutes'], $row['worked_minutes'], $row['late_minutes'],
            $row['early_minutes'], $row['overtime_minutes'], $row['conflict'] ? 'نعم' : 'لا',
        ]);
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
