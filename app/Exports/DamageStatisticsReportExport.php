<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class DamageStatisticsReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    public function __construct(private array $rows)
    {
    }

    public function headings(): array
    {
        return [
            'م',
            'الوصف',
            'العدد',
            'ملاحظات',
        ];
    }

    public function array(): array
    {
        return collect($this->rows)->map(function ($row) {
            return [
                $row['no'],
                $row['description'],
                $row['count'],
                $row['notes'],
            ];
        })->toArray();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');

                $sheet->getStyle("A1:D1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => 'solid',
                        'startColor' => ['rgb' => '1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center',
                    ],
                ]);

                for ($row = 2; $row <= $highestRow; $row++) {
                    $count = $sheet->getCell("C{$row}")->getValue();

                    if ($count === null || $count === '') {
                        $sheet->mergeCells("B{$row}:D{$row}");

                        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => 'FFFFFF'],
                            ],
                            'fill' => [
                                'fillType' => 'solid',
                                'startColor' => ['rgb' => '305496'],
                            ],
                            'alignment' => [
                                'horizontal' => 'center',
                                'vertical' => 'center',
                            ],
                        ]);
                    }
                }

                $sheet->getStyle("A1:D{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color' => ['rgb' => 'BFBFBF'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => 'center',
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getColumnDimension('A')->setWidth(8);
                $sheet->getColumnDimension('B')->setWidth(60);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(20);
            },
        ];
    }
}