<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class InfAuditRoadsSummaryExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings
{
    public function __construct(private array $rows) {}

    public function headings(): array
    {
        return [
            'المحافظة',
            'الحي',
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
                $row['neighborhood'],
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

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');

                $sheet->getStyle('A1:E1')->applyFromArray([
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

                $sheet->getStyle("A1:E{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color' => ['rgb' => 'BFBFBF'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center',
                        'wrapText' => true,
                    ],
                ]);
            },
        ];
    }
}
