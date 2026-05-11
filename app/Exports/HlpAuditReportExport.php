<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HlpAuditReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly Collection $rows,
        private readonly string $startDate,
        private readonly string $endDate,
    ) {}

    public function collection(): Collection
    {
        $totals = (object) [
            'governorate' => 'Grand Totals',
            'neighborhood' => '',
            'hlp_buildings' => $this->rows->sum('hlp_buildings'),
            'hlp_housings' => $this->rows->sum('hlp_housings'),
        ];

        return $this->rows->values()->push($totals);
    }

    public function headings(): array
    {
        return [
            ['المحافظة', 'الحي', 'HLP Buildings', 'HLP Housings'],
            ['إسم المحافظة', 'إسم الحي', 'عدد المباني المدققة من قبل المحامي', 'عدد الوحد السكنية المدققة من قبل المحامي'],
        ];
    }

    public function map($row): array
    {
        return [
            $row->governorate ?? '',
            $row->neighborhood ?? '',
            (int) ($row->hlp_buildings ?? 0),
            (int) ($row->hlp_housings ?? 0),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 3;

        $sheet->getStyle("A1:D{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A1:D{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE9ECEF']],
            ],
            2 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8F9FA']],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDDEBF7']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->setRightToLeft(true);
                $sheet->insertNewRowBefore(1);
                $sheet->mergeCells('A1:D1');
                $sheet->setCellValue('A1', "HLP Audit Report: {$this->startDate} to {$this->endDate}");
                $sheet->freezePane('A4');
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(28);
                $sheet->getRowDimension(3)->setRowHeight(36);
                $sheet->getStyle('A1:D3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
