<?php

declare(strict_types=1);

namespace App\Exports;

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

class HousingUnitBoqExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $summary,
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
            'Object ID',
            'رقم الوحدة',
            'مالك الوحدة',
            'القسم',
            'الكود',
            'البند',
            'الوحدة',
            'الكمية',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row['objectid'],
            $row['housing_unit_number'],
            $row['unit_owner'],
            $row['section'],
            $row['item_code'],
            $row['description'],
            $row['unit'],
            $row['quantity'],
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
                $highestRow = $sheet->getHighestRow();

                $sheet->setRightToLeft(true);
                $sheet->insertNewRowBefore(1, 4);
                $sheet->mergeCells('A1:H1');
                $sheet->mergeCells('A2:H2');
                $sheet->mergeCells('A3:H3');
                $sheet->setCellValue('A1', 'جدول الكميات BOQ');
                $sheet->setCellValue('A2', 'Damage Assessment Project');
                $sheet->setCellValue('A3', 'عدد الوحدات: '.($this->summary['units_count'] ?? 0).' | عدد البنود: '.($this->summary['rows_count'] ?? 0));

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
                        'startColor' => ['rgb' => '10233F'],
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

                $sheet->getStyle("A6:H{$highestRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("H6:H{$highestRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getColumnDimension('D')->setWidth(26);
                $sheet->getColumnDimension('F')->setWidth(70);
            },
        ];
    }
}
