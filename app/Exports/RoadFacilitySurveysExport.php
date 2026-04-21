<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\RoadFacilitySurvey;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RoadFacilitySurveysExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
            'Road Name',
            'Municipality',
            'Neighborhood',
            'Road Damage Level',
            'Road Access',
            'Submission Date',
            'Items',
            'Researcher',
        ];
    }

    public function map($row): array
    {
        /** @var RoadFacilitySurvey $row */
        return [
            $row->objectid,
            $row->str_name,
            $row->municipalitie,
            $row->neighborhood,
            $row->road_damage_level,
            $row->road_access,
            $row->submission_date?->format('Y-m-d H:i'),
            $row->items_count,
            $row->assigned_to,
        ];
    }
}
