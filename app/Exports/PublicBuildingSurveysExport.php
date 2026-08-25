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

class PublicBuildingSurveysExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    /**
     * @var array<string, string>
     */
    private const COLUMNS = [
        'objectid' => 'Object ID',
        'building_name' => 'Building Name',
        'municipalitie' => 'Municipality',
        'neighborhood' => 'Neighborhood',
        'building_damage_status' => 'Damage Status',
        'date_of_damage' => 'Date Of Damage',
        'units_count' => 'Linked Units',
        'assignedto' => 'Researcher',
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
        return $this->surveys;
    }

    public function headings(): array
    {
        return array_values(array_intersect_key(self::COLUMNS, array_flip($this->columns)));
    }

    public function title(): string
    {
        return 'Buildings';
    }

    public function map($row): array
    {
        /** @var PublicBuildingSurvey $row */
        $values = [
            'objectid' => $row->objectid,
            'building_name' => $row->building_name,
            'municipalitie' => $row->municipalitie,
            'neighborhood' => $row->neighborhood,
            'building_damage_status' => $row->building_damage_status,
            'date_of_damage' => $row->date_of_damage?->format('Y-m-d'),
            'units_count' => $row->units_count,
            'assignedto' => $row->assignedto,
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
