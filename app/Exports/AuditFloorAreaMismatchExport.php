<?php

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
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AuditFloorAreaMismatchExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'ObjectID',
            'فرق الطابق الأرضي 0',
            'فرق الطابق المتكرر 1',
        ];
    }

    public function map($row): array
    {
        return [
            (string) $row->objectid,
            round((float) $row->ground_floor_difference, 2),
            round((float) $row->repeated_floor_difference, 2),
        ];
    }

    public function title(): string
    {
        return 'مخالفات المساحات';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');

                if ($highestRow > 1) {
                    $sheet->setAutoFilter('A1:'.$highestColumn.'1');
                }

                $sheet->getStyle('A1:'.$highestColumn.'1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '0B1B46'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F3F6F9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
            },
        ];
    }
}
