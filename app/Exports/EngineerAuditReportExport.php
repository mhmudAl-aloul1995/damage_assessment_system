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

class EngineerAuditReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $summary,
    ) {}

    public function collection(): Collection
    {
        return $this->rows
            ->values()
            ->push((object) [
                'sequence' => '',
                'field_engineer_name' => 'المجموع',
                'accepted_count' => $this->summary['accepted_count'],
                'rejected_count' => $this->summary['rejected_count'],
                'need_review_count' => $this->summary['need_review_count'],
                'total_completed_count' => $this->summary['total_completed_count'],
            ]);
    }

    public function headings(): array
    {
        return [
            '#',
            'اسم الباحث الميداني',
            'عدد الوحدات المقبولة',
            'عدد الوحدات المرفوضة',
            'عدد الوحدات تحتاج مراجعة',
            'عدد الاستمارات الكلي',
        ];
    }

    public function map($row): array
    {
        return [
            $row->sequence,
            $row->field_engineer_name,
            (int) $row->accepted_count,
            (int) $row->rejected_count,
            (int) $row->need_review_count,
            (int) $row->total_completed_count,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 2;

        $sheet->getStyle("A1:F{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A1:F{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9EAF7']],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE9ECEF']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');
                $sheet->getRowDimension(1)->setRowHeight(34);
                $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
