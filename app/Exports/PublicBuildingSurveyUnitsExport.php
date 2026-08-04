<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\PublicBuildingSurvey;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class PublicBuildingSurveyUnitsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(protected Collection $surveys) {}

    public function collection(): Collection
    {
        return $this->surveys
            ->flatMap(fn (PublicBuildingSurvey $survey): Collection => $survey->units);
    }

    public function headings(): array
    {
        return [
            'Building Object ID',
            'Building Global ID',
            'Building Name',
            'Unit Object ID',
            'Unit Global ID',
            'Parent Global ID',
            'Repeat Index',
            'Unit Name',
            'Floor Number',
            'Damaged Area M2',
            'Occupied',
            'Final Comments',
        ];
    }

    public function title(): string
    {
        return 'Units';
    }

    public function map($row): array
    {
        $survey = $this->surveys->firstWhere('globalid', $row->parentglobalid);

        return [
            $survey?->objectid,
            $survey?->globalid,
            $survey?->building_name,
            $row->objectid,
            $row->globalid,
            $row->parentglobalid,
            $row->repeat_index,
            $row->unit_name,
            $row->floor_number,
            $row->damaged_area_m2,
            $row->occupied,
            $row->final_comments,
        ];
    }
}
