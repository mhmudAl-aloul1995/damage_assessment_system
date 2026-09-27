<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArcgisBuildingStatusDiffExport implements FromCollection, ShouldAutoSize, WithHeadings
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
            'building_objectid',
            'source_field_status',
            'target_field_status',
            'difference_type',
            'target_objectid',
        ];
    }
}
