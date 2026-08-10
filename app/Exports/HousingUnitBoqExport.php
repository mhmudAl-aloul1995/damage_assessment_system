<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class HousingUnitBoqExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $summary,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $sheetRows = [
            ['جدول الكميات BOQ', null, null, null, null],
            ['Damage Assessment Project', null, null, null, null],
            [null, null, null, null, null],
        ];

        foreach ($this->rows->groupBy('globalid') as $unitRows) {
            $firstRow = $unitRows->first();

            $sheetRows[] = ['Object ID', $firstRow['objectid'] ?? '-', 'رقم الوحدة', $firstRow['housing_unit_number'] ?? '-', 'اسم مالك الوحدة'];
            $sheetRows[] = [null, null, null, null, $firstRow['unit_owner'] ?? '-'];
            $sheetRows[] = ['القسم', 'الكود', 'البند', 'الوحدة', 'الكمية'];

            foreach ($unitRows as $row) {
                $sheetRows[] = [
                    $row['section'],
                    $row['item_code'],
                    $row['description'],
                    $row['unit'],
                    $row['quantity'],
                ];
            }

            $sheetRows[] = [null, null, null, null, null];
        }

        return $sheetRows;
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
                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');
                $sheet->getStyle('A1:E2')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A1')->getFont()->setSize(16);

                for ($row = 4; $row <= $highestRow; $row++) {
                    $firstCell = (string) $sheet->getCell("A{$row}")->getValue();

                    if ($firstCell === 'Object ID') {
                        $sheet->getStyle("A{$row}:E".($row + 1))->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'EAF4FF'],
                            ],
                        ]);
                    }

                    if ($firstCell === 'القسم') {
                        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
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
                    }
                }

                $sheet->getStyle("A1:E{$highestRow}")->applyFromArray([
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

                $sheet->getStyle("E1:E{$highestRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getColumnDimension('A')->setWidth(26);
                $sheet->getColumnDimension('C')->setWidth(70);
                $sheet->getColumnDimension('E')->setWidth(16);
            },
        ];
    }
}
