<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AttendanceMonthlyReportExport implements
    FromCollection,
    WithHeadings,
    WithTitle,
    ShouldAutoSize,
    WithStyles,
    WithEvents
{
    public function __construct(
        protected $month,
        protected $year
    ) {}

    public function title(): string
    {
        return 'Attendance Report';
    }

    public function headings(): array
    {
        return [
            'Name English',
            'Name Arabic',
            'ID No',
            'Phone',
            'Contract Type',
            'region',
            'Role',
            'Present Days',
            'Absent Days',
            'Attendance Rate',
        ];
    }

    public function collection()
    {
        return User::with('roles')
            ->withCount([
                'attendances as total_present' => function ($q) {
                    $q->whereYear('date', $this->year)
                        ->whereMonth('date', $this->month)
                        ->where('status', 1);
                },
                'attendances as total_absent' => function ($q) {
                    $q->whereYear('date', $this->year)
                        ->whereMonth('date', $this->month)
                        ->where('status', 0);
                }
            ])
            ->get()
            ->map(function ($user) {
                $totalDays = ($user->total_present ?? 0) + ($user->total_absent ?? 0);

                $rate = $totalDays > 0
                    ? round((($user->total_present ?? 0) / $totalDays) * 100, 2)
                    : 0;

                return [
                    $user->name_en ?? '',
                    $user->name ?? '',
                    $user->id_no ?? '',
                    $user->phone ?? '',
                    $user->contract_type ?? '',
                    $user->region ?? '',
                    optional($user->roles->first())->name ?? '',
                    $user->total_present ?? 0,
                    $user->total_absent ?? 0,
                    $rate . '%',
                ];
            });
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0D6EFD'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Freeze header row
                $sheet->freezePane('A2');

                // Header row height
                $sheet->getRowDimension(1)->setRowHeight(24);

                // Center all cells vertically
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Apply borders to all used cells
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Center numeric/stat columns
                $sheet->getStyle("C2:I{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Left align names
                $sheet->getStyle("A2:B{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Optional: set filter
                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");

                // Optional: zebra rows
                for ($row = 2; $row <= $highestRow; $row++) {
                    if ($row % 2 === 0) {
                        $sheet->getStyle("A{$row}:{$highestColumn}{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('F8F9FA');
                    }
                }
            },
        ];
    }
}