<?php

// app/Exports/tableExport.php

namespace App\Exports;

use App\Models\Assessment;
use App\Models\Invoice; // Use App\Models\Invoice in Laravel 9+
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\FromCollection;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
class ProductivityExport implements FromView, ShouldAutoSize, WithEvents
{
    protected $assignedto;
    protected $period;
    protected $stats;
    public function __construct($assignedto, $period, $stats)
    {

        $this->assignedto = $assignedto;
        $this->period = $period;
        $this->stats = $stats;
    }

    public function view(): View
    {
        return view('exports.productivity', [
            'period' => $this->period,
            'assignedto' => $this->assignedto,
            'stats' => $this->stats
        ]);
    }




    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // 1. Global Styling
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                    ],
                ]);

                // 2. Header Styling (Rows 1 & 2)
                $sheet->getStyle("A1:{$highestColumn}2")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFC107');

                // 3. Style First Column (Column A)
                $sheet->getStyle("A1:A{$highestRow}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFC107');

                // 4. Style Last Column (Highest Column)
                $sheet->getStyle("{$highestColumn}1:{$highestColumn}{$highestRow}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFC107');

                // 5. Dynamic Column Coloring (Iterating through ALL active columns)
                foreach ($sheet->getColumnIterator() as $columnIterator) {
                    $column = $columnIterator->getColumnIndex();
                    $sheet->getColumnDimension($column)->setAutoSize(true);

                    $mainHeader = (string) $sheet->getCell($column . '1')->getValue();
                    $subHeader = (string) $sheet->getCell($column . '2')->getValue();

                    $color = null;
                    switch ($subHeader) {
                        case 'PDA':
                            $color = '4CAF50';
                            break;
                        case 'TDA':
                            $color = 'E91E63';
                            break;
                        case 'Total':
                            $color = '2196F3';
                            break;
                    }

                    // If a color is determined, apply it from Row 3 downwards
                    if ($color) {
                        $sheet->getStyle("{$column}3:{$column}{$highestRow}")->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB($color);
                    }
                }
            },
        ];
    }


}