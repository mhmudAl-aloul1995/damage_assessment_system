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
    /**
     * @var array<string, string>
     */
    private const COLUMNS = [
        'building_objectid' => 'Building Object ID',
        'building_globalid' => 'Building Global ID',
        'building_name' => 'Building Name',
        'objectid' => 'Unit Object ID',
        'globalid' => 'Unit Global ID',
        'parentglobalid' => 'Parent Global ID',
        'repeat_index' => 'Repeat Index',
        'unit_name' => 'Unit Name',
        'floor_number' => 'Floor Number',
        'damaged_area_m2' => 'Damaged Area M2',
        'occupied' => 'Occupied',
        'final_comments' => 'Final Comments',
    ];

    /**
     * @param  array<int, string>  $columns
     */
    public function __construct(
        protected Collection $surveys,
        protected array $columns = [],
    ) {
        $this->columns = $this->resolveColumns($columns);
    }

    /**
     * @return array<string, string>
     */
    public static function availableColumns(): array
    {
        return self::COLUMNS;
    }

    public function collection(): Collection
    {
        return $this->surveys
            ->flatMap(fn (PublicBuildingSurvey $survey): Collection => $survey->units);
    }

    public function headings(): array
    {
        return array_values(array_intersect_key(self::COLUMNS, array_flip($this->columns)));
    }

    public function title(): string
    {
        return 'Units';
    }

    public function map($row): array
    {
        $survey = $this->surveys->firstWhere('globalid', $row->parentglobalid);

        $values = [
            'building_objectid' => $survey?->objectid,
            'building_globalid' => $survey?->globalid,
            'building_name' => $survey?->building_name,
            'objectid' => $row->objectid,
            'globalid' => $row->globalid,
            'parentglobalid' => $row->parentglobalid,
            'repeat_index' => $row->repeat_index,
            'unit_name' => $row->unit_name,
            'floor_number' => $row->floor_number,
            'damaged_area_m2' => $row->damaged_area_m2,
            'occupied' => $row->occupied,
            'final_comments' => $row->final_comments,
        ];

        return collect($this->columns)
            ->map(fn (string $column): mixed => $values[$column] ?? null)
            ->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function resolveColumns(array $columns): array
    {
        $selectedColumns = array_values(array_intersect($columns, array_keys(self::COLUMNS)));

        return $selectedColumns !== [] ? $selectedColumns : array_keys(self::COLUMNS);
    }
}
