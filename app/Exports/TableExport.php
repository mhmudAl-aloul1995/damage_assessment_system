<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TableExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldAutoSize
{
    protected $building;
    protected $columns;
    protected $assessmentHints;

    public function __construct($building, $columns, $assessmentHints)
    {
        $this->building = $building;
        $this->columns = $columns;
        $this->assessmentHints = $assessmentHints;
    }

    /**
     * Returns the collection of data to be exported.
     */
    public function collection()
    {
        return $this->building;
    }

    /**
     * Dynamically sets headings using labels from assessmentHints or fallback to column names.
     */
    public function headings(): array
    {
        return array_map(function ($column) {
            return $this->assessmentHints[$column]->label ?? ucfirst(str_replace('_', ' ', $column));
        }, $this->columns);
    }

    /**
     * Maps each row to ensure data stays aligned with the dynamic columns.
     */
    public function map($row): array
    {
        $mappedData = [];
        foreach ($this->columns as $column) {
            $mappedData[] = $row->{$column};
        }
        return $mappedData;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $headerRange = 'A1:' . $highestColumn . '1'; // Target only the first row
    
                // Apply background color and bold font to the header
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'], // Optional: White text for better contrast
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'FF0000', // Set your header background color here (e.g., Red)
                        ],
                    ],
                ]);

                // Apply borders to the rest of the data
                $highestRow = $sheet->getHighestRow();
                $fullRange = 'A1:' . $highestColumn . $highestRow;
                $sheet->getStyle($fullRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }


}



?>