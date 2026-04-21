<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\PublicBuildingSurvey;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PublicBuildingSurveysExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(protected Collection $surveys) {}

    public function collection(): Collection
    {
        return $this->surveys;
    }

    public function headings(): array
    {
        return [
            'Object ID',
            'Building Name',
            'Municipality',
            'Neighborhood',
            'Damage Status',
            'Date Of Damage',
            'Units',
            'Researcher',
        ];
    }

    public function map($row): array
    {
        /** @var PublicBuildingSurvey $row */
        return [
            $row->objectid,
            $row->building_name,
            $row->municipalitie,
            $row->neighborhood,
            $row->building_damage_status,
            $row->date_of_damage?->format('Y-m-d'),
            $row->units_count,
            $row->assigned_to,
        ];
    }
}
