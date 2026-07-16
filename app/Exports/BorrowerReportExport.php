<?php

namespace App\Exports;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BorrowerReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    /**
     * @param  \Illuminate\Support\Collection<int, DamageAssessmentBorrower>  $borrowers
     */
    public function __construct(
        private readonly \Illuminate\Support\Collection $borrowers,
        private readonly string $reportType = 'compact',
    ) {}

    public function collection(): \Illuminate\Support\Collection
    {
        return $this->borrowers;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        if ($this->reportType === 'detailed') {
            return [
                'الكود',
                'كود المستفيد',
                'اسم المقترض',
                'رقم الهوية',
                'رقم القرض',
                'قيمة القرض',
                'المبلغ المتبقي',
                'المساحة',
                'الطابق',
                'سعر المتر',
                'قيمة الضرر للهدم الكلي',
                'قيمة الضرر بالشيكل',
                'نوع الضرر',
                'درجة الخطورة',
                'عدد الصور',
                'تاريخ المسح',
                'الملاحظات',
            ];
        }

        return [
            'الكود',
            'كود المستفيد',
            'اسم المقترض',
            'رقم الهوية',
            'قيمة القرض',
            'المبلغ المتبقي',
            'قيمة الضرر للهدم الكلي',
            'نوع الضرر',
            'الملاحظات',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        if ($this->reportType === 'detailed') {
            return [
                $this->borrowerCode($row),
                $row->form_number,
                $row->borrower_name,
                $row->borrower_id_number,
                $row->loan_number,
                $this->numberOrNull($row->loan_total_amount),
                $this->numberOrNull($row->loan_balance),
                $this->numberOrNull($row->loan_unit_area),
                $this->optionLabel($row->loan_unit_floor_type),
                $this->fullDemolitionMeterRate($row),
                $this->numberOrNull($row->boq_total_usd),
                $this->numberOrNull($row->boq_total_ils),
                $this->optionLabel($row->loan_unit_damage_status),
                $this->riskLabel($row->risk_level),
                $row->attachments_count,
                $row->surveyed_at?->format('Y-m-d'),
                $row->notes,
            ];
        }

        return [
            $this->borrowerCode($row),
            $row->form_number,
            $row->borrower_name,
            $row->borrower_id_number,
            $this->numberOrNull($row->loan_total_amount),
            $this->numberOrNull($row->loan_balance),
            $this->numberOrNull($row->boq_total_usd),
            $this->optionLabel($row->loan_unit_damage_status),
            $row->notes,
        ];
    }

    public function title(): string
    {
        return 'تقرير المقترضين';
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');

                $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D9E2F3'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle("A2:{$highestColumn}{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $numberColumns = $this->reportType === 'detailed'
                    ? ['F', 'G', 'H', 'J', 'K', 'L']
                    : ['E', 'F', 'G'];

                foreach ($numberColumns as $column) {
                    $sheet->getStyle("{$column}2:{$column}{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                }

                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension($highestColumn)->setWidth(45);
            },
        ];
    }

    private function borrowerCode(DamageAssessmentBorrower $borrower): string|int|null
    {
        return $borrower->form_number ?: ($borrower->loan_number ?: $borrower->id);
    }

    private function numberOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function fullDemolitionMeterRate(DamageAssessmentBorrower $borrower): ?int
    {
        if ($borrower->loan_unit_damage_status !== 'destroyed') {
            return null;
        }

        return match ($borrower->loan_unit_floor_type) {
            'ground' => 325,
            'repeated' => 280,
            default => null,
        };
    }

    private function riskLabel(?string $riskLevel): string
    {
        return match ($riskLevel) {
            'critical' => 'حرج',
            'high' => 'مرتفع',
            'medium' => 'متوسط',
            'low' => 'منخفض',
            default => '-',
        };
    }

    private function optionLabel(?string $value): string
    {
        return match ($value) {
            'destroyed' => 'هدم كلي',
            'severe_uninhabitable' => 'متضرر بليغ غير صالح للسكن',
            'severe_habitable' => 'متضرر بليغ صالح للسكن',
            'minor' => 'أضرار طفيفة',
            'ground' => 'أرضي',
            'repeated' => 'متكرر',
            default => $value ?: '-',
        };
    }
}
