<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class MissingCitizenIdentityReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            __('ui.missing_citizen_identities.identity_subject'),
            __('ui.missing_citizen_identities.owner_name'),
            __('ui.missing_citizen_identities.owner_id_number'),
            __('ui.missing_citizen_identities.spouse_name'),
            __('ui.missing_citizen_identities.spouse_id_number'),
            __('ui.missing_citizen_identities.marital_status'),
            __('ui.missing_citizen_identities.housing_unit_objectid'),
            __('ui.missing_citizen_identities.issue_type'),
            __('ui.missing_citizen_identities.name_match_status'),
            __('ui.missing_citizen_identities.matched_citizen'),
            __('ui.missing_citizen_identities.matched_citizen_id_number'),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');

                $sheet->getStyle("A1:K{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color' => ['rgb' => 'BFBFBF'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => 'center',
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle('A1:K1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => 'solid',
                        'startColor' => ['rgb' => '1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center',
                    ],
                ]);
            },
        ];
    }
}
