<?php

namespace App\Modules\Reh\Services;

use App\Modules\Reh\Models\RehUnit;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RehUnitsExport extends DefaultValueBinder implements FromGenerator, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private Builder $query, private bool $sensitive, private bool $canSeeScores) {}

    public function headings(): array
    {
        return ['معرّف الوحدة', 'معرّف المبنى', 'المالك', 'الهوية', 'المحافظة', 'الحي', 'التدقيق', 'القرار', 'التسعير', 'آخر مزامنة', 'نقاط هندسية محسومة', 'فئات هندسية معلقة', 'الدرجة النهائية', 'الأولوية', 'إصدار قواعد الحساب', 'كود الوحدة', 'اسم المبنى'];
    }

    public function generator(): Generator
    {
        foreach ($this->query->lazyById(200) as $unit) {
            yield $this->row($unit);
        }
    }

    public function row(RehUnit $unit): array
    {
        $survey = app(RehSurvey::class);
        $scoring = app(RehScoring::class);
        $engineering = $scoring->engineering($unit);
        $score = $this->canSeeScores ? $scoring->calculate($unit) : null;

        return [$unit->old_objectid, $unit->building->old_objectid, $unit->owner_name, $this->sensitive ? $unit->owner_identity : 'محجوب',
            $survey->value('governorate', $unit->building->governorate), $survey->value('neighborhood', $unit->building->neighborhood),
            RehSurvey::WORKFLOW[$unit->workflow_status], ['pending' => 'لم يُقرر', 'selected' => 'مقبول مبدئيًا', 'excluded' => 'مستبعد'][$unit->decision],
            'بانتظار الأسعار', $unit->synced_at?->toDateTimeString(), $engineering['subtotal'], $engineering['pending_count'],
            $this->canSeeScores ? ($score['total'] ?? 'بانتظار استكمال البيانات') : 'محجوب',
            $this->canSeeScores ? (RehScoring::PRIORITIES[$score['priority']] ?? 'غير مصنفة') : 'محجوب', RehScoring::VERSION, $unit->code, $unit->building->name];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'وحدات تأهيل المساكن';
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $sheet->setShowGridlines(false);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter('A1:Q'.$sheet->getHighestRow());
        $sheet->getDefaultColumnDimension()->setWidth(19);
        foreach (['C', 'G', 'H', 'I', 'J', 'M', 'N', 'Q'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(27);
        }
        $sheet->getRowDimension(1)->setRowHeight(42);
        $sheet->getStyle('A1:Q'.$sheet->getHighestRow())->getAlignment()->setHorizontal('right')->setVertical('center');
        $sheet->getStyle('A1:Q1')->getAlignment()->setWrapText(true);

        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '173F44']]]];
    }
}
