<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AuditEditDeletionPreviewExport implements FromCollection, ShouldAutoSize, WithHeadings
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
}
