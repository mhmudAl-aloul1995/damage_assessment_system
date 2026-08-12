<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InfAuditRoadsSummaryExport implements FromArray, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings
{
    public function __construct(private array $rows) {}

    public function startCell(): string
    {
        return 'A8';
    }

    public function headings(): array
    {
        return [
            'المحافظة',
            'البلدية',
            'الحي',
            'مستوى الضرر',
            'ما تم حصره',
            'ما تم تدقيقه',
            'أطوال الطرق (متر)',
        ];
    }

    public function array(): array
    {
        return collect($this->rows)
            ->map(fn (array $row): array => [
                $row['governorate'],
                $row['municipality'],
                $row['neighborhood'],
                $row['road_damage_level'],
                $row['surveyed_count'],
                $row['audited_count'],
                $row['road_length_meters'],
            ])
            ->all();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $totalSurveyed = collect($this->rows)->sum('surveyed_count');
                $totalAudited = collect($this->rows)->sum('audited_count');
                $totalLength = collect($this->rows)->sum('road_length_meters');
                $auditRate = $totalSurveyed > 0 ? round(($totalAudited / $totalSurveyed) * 100, 1).'%' : '0%';

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A9');

                $sheet->mergeCells('A1:G2');
                $sheet->mergeCells('A3:B3');
                $sheet->setCellValue('A1', 'تقرير تدقيق الطرق');
                $sheet->setCellValue('A3', 'Damage Assessment System');
                $sheet->mergeCells('F3:G3');
                $sheet->setCellValue('E3', 'تاريخ التصدير');
                $sheet->setCellValue('F3', now()->format('Y-m-d H:i'));

                $sheet->getStyle('A1:G3')->applyFromArray([
                    'font' => [
                        'name' => 'Droid Arabic Kufi',
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '009EF7'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A1')->getFont()->setSize(18);
                $sheet->getStyle('A3:E3')->getFont()->setSize(10);
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(2)->setRowHeight(24);
                $sheet->getRowDimension(3)->setRowHeight(24);

                $summaryCards = [
                    ['range' => 'A5:A6', 'label' => 'ما تم حصره', 'value' => number_format((int) $totalSurveyed), 'color' => '009EF7', 'fill' => 'F1FAFF'],
                    ['range' => 'B5:B6', 'label' => 'ما تم تدقيقه', 'value' => number_format((int) $totalAudited), 'color' => '50CD89', 'fill' => 'E8FFF3'],
                    ['range' => 'C5:C6', 'label' => 'نسبة التدقيق', 'value' => $auditRate, 'color' => 'FFC700', 'fill' => 'FFF8DD'],
                    ['range' => 'D5:G6', 'label' => 'أطوال الطرق (متر)', 'value' => number_format((float) $totalLength, 2), 'color' => '181C32', 'fill' => 'F9F9F9'],
                ];

                foreach ($summaryCards as $card) {
                    $sheet->mergeCells($card['range']);
                    $topLeftCell = explode(':', $card['range'])[0];
                    $sheet->setCellValue($topLeftCell, $card['label']."\n".$card['value']);
                    $sheet->getStyle($card['range'])->applyFromArray([
                        'font' => [
                            'name' => 'Droid Arabic Kufi',
                            'bold' => true,
                            'color' => ['rgb' => $card['color']],
                            'size' => 13,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $card['fill']],
                        ],
                        'borders' => [
                            'outline' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'EFF2F5'],
                            ],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                $sheet->getRowDimension(5)->setRowHeight(32);
                $sheet->getRowDimension(6)->setRowHeight(32);

                $sheet->getStyle('A8:G8')->applyFromArray([
                    'font' => [
                        'name' => 'Droid Arabic Kufi',
                        'bold' => true,
                        'color' => ['rgb' => '3F4254'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F1F4F7'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A8:G{$highestRow}")->applyFromArray([
                    'font' => [
                        'name' => 'Droid Arabic Kufi',
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'EFF2F5'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle("A9:G{$highestRow}")->getFont()->setSize(10);
                $sheet->getStyle("G9:G{$highestRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                foreach (range(9, $highestRow) as $row) {
                    if ($row % 2 === 0) {
                        $sheet->getStyle("A{$row}:G{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('FCFCFC');
                    }
                }
            },
        ];
    }
}
