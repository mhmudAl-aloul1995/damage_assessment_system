<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;

class AuditEditDeletionPreviewExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings
{
    public function __construct(
        private readonly Collection $rows,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Target',
            'Edit Assessment ID',
            'Type',
            'Global ID',
            'Object ID',
            'Parent Global ID',
            'Audit URL',
            'Field Name',
            'Deleted Field Value',
            'Deleted Edit Created At',
            'Deleted Edit Updated At',
            'Previous Edit ID',
            'Previous Value',
            'Previous Edit Created At',
            'Has Later Edit For Same Field',
            'Next Edit ID',
            'Next Value',
            'Next Edit Created At',
            'Current Audit Status',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestDataRow();

                for ($row = 2; $row <= $highestRow; $row++) {
                    $cell = "G{$row}";
                    $url = trim((string) $sheet->getCell($cell)->getValue());

                    if ($url === '') {
                        continue;
                    }

                    $sheet->getCell($cell)->getHyperlink()->setUrl($url);
                    $sheet->getStyle($cell)->getFont()
                        ->setUnderline(Font::UNDERLINE_SINGLE)
                        ->getColor()
                        ->setARGB(Color::COLOR_BLUE);
                }
            },
        ];
    }
}
