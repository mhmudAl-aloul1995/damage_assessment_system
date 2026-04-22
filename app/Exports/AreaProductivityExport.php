<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AreaProductivityExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly Collection $data,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly string $reportTitle,
        private readonly string $sectorLabel,
    ) {}

    public function startCell(): string
    {
        return 'A3';
    }

    public function collection(): Collection
    {
        $totals = (object) [
            'Sector' => __('multilingual.area_productivity_reports.labels.grand_totals'),
            'governorate' => '',
            'municipalitie' => '',
            'neighborhood' => '',
            'no_eng' => $this->data->sum('no_eng'),
            'tda_range' => $this->data->sum('tda_range'),
            'pda_range' => $this->data->sum('pda_range'),
            'cra_range' => $this->data->sum('cra_range'),
            'total_count' => $this->data->sum('total_count'),
        ];

        return collect($this->data)->push($totals);
    }

    public function headings(): array
    {
        return [
            __('multilingual.area_productivity_reports.columns.total_count'),
            __('multilingual.area_productivity_reports.columns.cra'),
            __('multilingual.area_productivity_reports.columns.pda'),
            __('multilingual.area_productivity_reports.columns.tda'),
            __('multilingual.area_productivity_reports.columns.engineers'),
            __('multilingual.area_productivity_reports.columns.neighborhood'),
            __('multilingual.area_productivity_reports.columns.municipality'),
            __('multilingual.area_productivity_reports.columns.governorate'),
            __('multilingual.area_productivity_reports.columns.sector'),
        ];
    }

    public function map($row): array
    {
        $isTotal = ($row->Sector ?? '') === __('multilingual.area_productivity_reports.labels.grand_totals');

        return [
            $row->total_count ?? 0,
            $row->cra_range ?? 0,
            $row->pda_range ?? 0,
            $row->tda_range ?? 0,
            $row->no_eng ?? 0,
            $isTotal ? '' : ($row->neighborhood ?? ''),
            $isTotal ? '' : ($row->municipalitie ?? ''),
            $isTotal ? '' : ($row->governorate ?? ''),
            $isTotal ? __('multilingual.area_productivity_reports.labels.grand_totals') : ($row->Sector ?? $this->sectorLabel),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $headerRow = 3;
        $lastRow = $this->data->count() + 4;

        $sheet->getStyle("A{$headerRow}:I{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A{$headerRow}:I{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [
            1 => ['font' => ['bold' => true, 'size' => 20]],
            $headerRow => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF28A745']],
            ],
            "A4:I{$lastRow}" => [
                'font' => ['size' => 12],
            ],
            $lastRow => [
                'font' => ['bold' => true, 'size' => 13],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE9ECEF']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->data->count() + 4;

                $sheet->mergeCells('A1:I1');
                $sheet->setCellValue('A1', "{$this->reportTitle}: {$this->startDate} to {$this->endDate}");
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getRowDimension(1)->setRowHeight(35);

                for ($row = 3; $row <= $lastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(24);
                }
            },
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '#,##0',
            'B' => '#,##0',
            'C' => '#,##0',
            'D' => '#,##0',
            'E' => '#,##0',
        ];
    }
}
