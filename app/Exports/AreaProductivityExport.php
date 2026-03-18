<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AreaProductivityExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting, WithCustomStartCell, WithEvents
{
    protected $data, $startDate, $endDate, $rowCount;

    public function __construct($data, $startDate, $endDate)
    {
        $this->data = $data;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->rowCount = count($data);
    }

    public function startCell(): string
    {
        return 'A3';
    }

    public function collection()
    {
        $totals = [
            'Sector' => 'Grand Totals:',
            'governorate' => '',
            'municipalitie' => '',
            'neighborhood' => '',
            'no_eng' => $this->data->sum('no_eng'),
            'tda_range' => $this->data->sum('tda_range'),
            'pda_range' => $this->data->sum('pda_range'),
            'cra_range' => $this->data->sum('cra_range'),
            'total' => $this->data->sum(fn($row) => ($row->tda_range ?? 0) + ($row->pda_range ?? 0) + ($row->cra_range ?? 0))
        ];
        return collect($this->data)->push((object) $totals);
    }

    public function headings(): array
    {
        return ['Sector', 'Governorate', 'Municipality', 'Area/Neighborhood', 'Engineers', 'TDA', 'PDA', 'CRA', 'Total'];
    }

    public function styles(Worksheet $sheet)
    {
        $headerRow = 3;
        $lastRow = $this->rowCount + 4;

        // Apply Borders
        $sheet->getStyle("A{$headerRow}:I{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Center all data for better visibility
        $sheet->getStyle("A{$headerRow}:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [
            // 1. Enlarge Title (Size 18)
            1 => ['font' => ['bold' => true, 'size' => 20]],

            // 2. Enlarge Table Headers (Size 14)
            $headerRow => [
                'font' => ['bold' => true, 'size' => 18, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF28a745']],
            ],

            // 3. Enlarge Data Rows (Size 12)
            "A4:I{$lastRow}" => [
                'font' => ['size' => 15],
            ],

            // 4. Grand Total Row Background & Large Font
            $lastRow => [
                'font' => ['bold' => true, 'size' => 16],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE9ECEF']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->rowCount + 4;

                // Set Title
                $sheet->mergeCells('A1:I1');
                $sheet->setCellValue('A1', "Areas Productivity Report: {$this->startDate} to {$this->endDate}");
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ENLARGE ROW HEIGHTS (Vertical Spacing)
                $sheet->getRowDimension(1)->setRowHeight(35); // Title Height
                for ($i = 3; $i <= $lastRow; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(25); // Table Rows Height
                }
            },
        ];
    }

    public function columnFormats(): array
    {
        return ['E' => '#,##0', 'F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0', 'I' => '#,##0'];
    }

    public function map($row): array
    {
        $isTotal = $row->Sector === 'Grand Totals:';
        return [
            $row->Sector ?? 'Housing',
            $row->governorate,
            $row->municipalitie,
            $row->neighborhood,
            $row->no_eng,
            $row->tda_range,
            $row->pda_range,
            $row->cra_range,
            $isTotal ? $row->total : (($row->tda_range ?? 0) + ($row->pda_range ?? 0) + ($row->cra_range ?? 0)),
        ];
    }
}



?>