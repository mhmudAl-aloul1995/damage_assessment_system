<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BuildingProductivityExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $grandTotal,
        private readonly array $filters,
        private readonly string $dateField,
        private readonly array $completedStatuses,
    ) {}

    public function sheets(): array
    {
        return [
            new BuildingProductivityReportSheet(
                rows: $this->rows,
                grandTotal: $this->grandTotal,
                filters: $this->filters,
                dateField: $this->dateField,
                completedStatuses: $this->completedStatuses,
            ),
            new BuildingProductivityNeighborhoodChartSheet($this->rows),
            new BuildingProductivityNeighborhoodPieSheet($this->rows),
        ];
    }
}

class BuildingProductivityReportSheet implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $grandTotal,
        private readonly array $filters,
        private readonly string $dateField,
        private readonly array $completedStatuses,
    ) {}

    public function title(): string
    {
        return 'Report';
    }

    public function collection(): Collection
    {
        return collect($this->rows)->push($this->grandTotal);
    }

    public function headings(): array
    {
        return [
            'Gov',
            'Name',
            'Completed',
            'Not Completed',
            'Buildings Count',
            'Completed %',
            'Not Completed %',
        ];
    }

    public function map($row): array
    {
        return [
            $row['gov'],
            $row['name'],
            (int) $row['completed'],
            (int) $row['not_completed'],
            (int) $row['buildings_count'],
            (float) $row['completed_percent'],
            (float) $row['not_completed_percent'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 2;

        $sheet->getStyle("A1:G{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A1:G{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF70AD47']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->rows->count() + 2;

                $sheet->freezePane('A2');
                $sheet->getRowDimension(1)->setRowHeight(24);

                $exportRows = $this->collection()->values();

                for ($rowNumber = 2; $rowNumber <= $lastRow; $rowNumber++) {
                    $row = $exportRows->get($rowNumber - 2);
                    $rowType = $row['row_type'] ?? 'detail';

                    if (in_array($rowType, ['gov_total', 'grand_total'], true)) {
                        $fillColor = $rowType === 'grand_total' ? 'FFD9EAD3' : 'FFE2F0D9';

                        $sheet->getStyle("A{$rowNumber}:G{$rowNumber}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillColor]],
                        ]);
                    }
                }

                $sheet->getStyle("A1:G{$lastRow}")->getAlignment()->setWrapText(true);
                $sheet->setAutoFilter("A1:G{$lastRow}");
                $sheet->getHeaderFooter()->setOddHeader(
                    '&CBuilding Productivity Report - Date Field: '.$this->dateField.
                    ' - Completed: '.implode(', ', $this->completedStatuses).
                    ' - From: '.($this->filters['from_date'] ?: 'All').
                    ' - To: '.($this->filters['to_date'] ?: 'All')
                );
            },
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => '#,##0',
            'D' => '#,##0',
            'E' => '#,##0',
            'F' => NumberFormat::FORMAT_PERCENTAGE_00,
            'G' => NumberFormat::FORMAT_PERCENTAGE_00,
        ];
    }
}

class BuildingProductivityNeighborhoodChartSheet implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private readonly Collection $rows) {}

    public function title(): string
    {
        return 'Every Neighborhood Chart';
    }

    public function collection(): Collection
    {
        return collect($this->rows)
            ->filter(fn (array $row): bool => ($row['row_type'] ?? null) === 'detail')
            ->sortBy([
                ['gov', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    public function headings(): array
    {
        return [
            'Gov',
            'Name',
            'Chart Label',
            'Completed',
            'Not Completed',
            'Buildings Count',
            'Completed %',
            'Not Completed %',
        ];
    }

    public function map($row): array
    {
        return [
            $row['gov'],
            $row['name'],
            $row['gov'].' / '.$row['name'],
            (int) $row['completed'],
            (int) $row['not_completed'],
            (int) $row['buildings_count'],
            (float) $row['completed_percent'],
            (float) $row['not_completed_percent'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->collection()->count() + 1;

        $sheet->getStyle("A1:H{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A1:H{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4472C4']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->collection()->count() + 1;

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:H{$lastRow}");
                $sheet->getStyle("A1:H{$lastRow}")->getAlignment()->setWrapText(true);
            },
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '#,##0',
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => NumberFormat::FORMAT_PERCENTAGE_00,
            'H' => NumberFormat::FORMAT_PERCENTAGE_00,
        ];
    }
}

class BuildingProductivityNeighborhoodPieSheet implements FromCollection, WithCharts, WithEvents, WithTitle
{
    public function __construct(private readonly Collection $rows) {}

    public function title(): string
    {
        return 'Neighborhood Pies';
    }

    public function collection(): Collection
    {
        return collect();
    }

    public function charts(): array
    {
        return $this->detailRows()
            ->values()
            ->map(function (array $row, int $index): Chart {
                $dataRow = $index + 2;
                $gridColumn = $index % 3;
                $gridRow = intdiv($index, 3);
                $startColumn = 1 + ($gridColumn * 8);
                $endColumn = $startColumn + 7;
                $startRow = 1 + ($gridRow * 16);
                $endRow = $startRow + 15;

                $series = new DataSeries(
                    DataSeries::TYPE_PIECHART,
                    null,
                    [0],
                    [
                        new DataSeriesValues(
                            DataSeriesValues::DATASERIES_TYPE_STRING,
                            "'Neighborhood Pies'!\$AA\$1",
                            null,
                            1,
                        ),
                    ],
                    [
                        new DataSeriesValues(
                            DataSeriesValues::DATASERIES_TYPE_STRING,
                            "'Neighborhood Pies'!\$AA\$1:\$AB\$1",
                            null,
                            2,
                        ),
                    ],
                    [
                        new DataSeriesValues(
                            DataSeriesValues::DATASERIES_TYPE_NUMBER,
                            sprintf("'Neighborhood Pies'!\$AA\$%d:\$AB\$%d", $dataRow, $dataRow),
                            null,
                            2,
                            [],
                            null,
                            ['8CC36B', 'FF8F95'],
                        ),
                    ],
                );

                $chart = new Chart(
                    'neighborhood_pie_'.$index,
                    new Title((string) $row['name']),
                    new Legend(Legend::POSITION_BOTTOM, null, false),
                    new PlotArea(null, [$series]),
                );

                $chart->setTopLeftPosition(Coordinate::stringFromColumnIndex($startColumn).$startRow);
                $chart->setBottomRightPosition(Coordinate::stringFromColumnIndex($endColumn).$endRow);

                return $chart;
            })
            ->all();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->setCellValue('AA1', 'Completed');
                $sheet->setCellValue('AB1', 'Not Completed');
                $sheet->setCellValue('AC1', 'Gov');
                $sheet->setCellValue('AD1', 'Name');

                foreach ($this->detailRows()->values() as $index => $row) {
                    $rowNumber = $index + 2;

                    $sheet->setCellValue("AA{$rowNumber}", (int) $row['completed']);
                    $sheet->setCellValue("AB{$rowNumber}", (int) $row['not_completed']);
                    $sheet->setCellValue("AC{$rowNumber}", $row['gov']);
                    $sheet->setCellValue("AD{$rowNumber}", $row['name']);
                }

                foreach (['AA', 'AB', 'AC', 'AD'] as $column) {
                    $sheet->getColumnDimension($column)->setVisible(false);
                }

                $lastChartRow = max(16, (int) ceil(max(1, $this->detailRows()->count()) / 3) * 16);

                $sheet->getStyle("A1:X{$lastChartRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle("A1:X{$lastChartRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }

    private function detailRows(): Collection
    {
        return collect($this->rows)
            ->filter(fn (array $row): bool => ($row['row_type'] ?? null) === 'detail')
            ->sortBy([
                ['gov', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }
}
