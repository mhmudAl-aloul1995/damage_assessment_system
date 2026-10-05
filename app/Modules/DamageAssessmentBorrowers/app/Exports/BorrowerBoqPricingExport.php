<?php

namespace App\Exports;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Support\Collection;
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

class BorrowerBoqPricingExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly DamageAssessmentBorrower $borrower,
        private readonly Collection $rows,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'الكود',
            'البند',
            'الوحدة',
            'سعر $',
            'سعر الوحدة ILS',
            'الكمية',
            'الإجمالي $',
            'الإجمالي ILS',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row['item_code'],
            $row['description'],
            $row['unit'],
            $this->numberOrNull($row['unit_price']),
            $this->numberOrNull($row['unit_price_ils']),
            $this->numberOrNull($row['quantity']),
            $this->numberOrNull($row['total_price']),
            $this->numberOrNull($row['total_price_ils']),
        ];
    }

    public function title(): string
    {
        return 'جدول الكميات';
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->setRightToLeft(true);
                $sheet->insertNewRowBefore(1, 4);
                $highestRow = $sheet->getHighestRow();
                $sheet->mergeCells('A1:H1');
                $sheet->mergeCells('A2:H2');
                $sheet->mergeCells('A3:H3');
                $sheet->setCellValue('A1', 'جدول الكميات والتسعير BOQ');
                $sheet->setCellValue('A2', $this->borrower->borrower_name ?: '-');
                $sheet->setCellValue('A3', 'النموذج: '.($this->borrower->form_number ?: '-').' | الهوية: '.($this->borrower->borrower_id_number ?: '-').' | القرض: '.($this->borrower->loan_number ?: '-'));

                $sheet->freezePane('A6');
                $sheet->getStyle('A1:H3')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A1')->getFont()->setSize(16);

                $sheet->getStyle('A5:H5')->applyFromArray([
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

                $sheet->getStyle("A1:H{$highestRow}")->applyFromArray([
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

                if ($highestRow >= 6) {
                    foreach (['D', 'E', 'F', 'G', 'H'] as $column) {
                        $sheet->getStyle("{$column}6:{$column}{$highestRow}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                    }
                }

                $totalRow = $highestRow + 1;
                $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'الإجمالي');
                $sheet->setCellValue("G{$totalRow}", (float) $this->borrower->boq_total_usd);
                $sheet->setCellValue("H{$totalRow}", (float) $this->borrower->boq_total_ils);
                $sheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'EAF4FF'],
                    ],
                ]);
                $sheet->getStyle("G{$totalRow}:H{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                $sheet->getColumnDimension('A')->setWidth(12);
                $sheet->getColumnDimension('B')->setWidth(70);
                $sheet->getColumnDimension('C')->setWidth(12);
            },
        ];
    }

    private function numberOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
