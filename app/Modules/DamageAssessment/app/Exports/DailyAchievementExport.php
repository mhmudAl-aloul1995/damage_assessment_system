<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyAchievementExport implements WithMultipleSheets
{
    public function __construct(private readonly array $sheets) {}

    public function sheets(): array
    {
        return collect($this->sheets)
            ->map(fn (array $sheet): DailyAchievementSheet => new DailyAchievementSheet($sheet))
            ->all();
    }
}

class DailyAchievementSheet implements FromCollection, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    public function __construct(private readonly array $sheetData) {}

    public function title(): string
    {
        return $this->sheetData['title'];
    }

    public function collection(): Collection
    {
        $users = collect($this->sheetData['users']);
        $dailyCounts = collect($this->sheetData['daily_counts']);
        $period = CarbonPeriod::create($this->sheetData['start_date'], $this->sheetData['end_date']);

        $rows = collect([
            [$this->sheetData['report_title']],
            ['Period', $this->sheetData['start_date'].' to '.$this->sheetData['end_date']],
            collect(['Date'])->merge($users->pluck('name'))->all(),
        ]);

        foreach ($period as $date) {
            $dateKey = $date->toDateString();

            $rows->push(
                collect([$dateKey])
                    ->merge($users->map(fn (array $user): int => (int) data_get($dailyCounts, "{$dateKey}.{$user['id']}", 0)))
                    ->all()
            );
        }

        $rows->push(
            collect(['Total'])
                ->merge($users->pluck('total'))
                ->all()
        );

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->sheetData['users']) + 1);
        $lastRow = $this->collection()->count();

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle("A3:{$lastColumn}{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2F0D9']],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->sheetData['users']) + 1);
                $lastRow = $this->collection()->count();

                $sheet->freezePane('B4');
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(3)->setRowHeight(22);
                $sheet->setAutoFilter("A3:{$lastColumn}{$lastRow}");

                for ($row = 4; $row <= $lastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(20);
                }

                $sheet->getStyle("A4:A{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('yyyy-mm-dd');
            },
        ];
    }
}
