<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\CsoSurvey;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class CsoSurveysExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    use CsoSurveyExportSupport;

    /**
     * @var array<string, string>
     */
    private const BASE_COLUMNS = [
        'objectid' => 'Survey Object ID',
        'globalid' => 'Survey Global ID',
        'organization_name' => 'Organization Name',
        'building_name' => 'Building Name',
        'municipalitie' => 'Municipality',
        'neighborhood' => 'Neighborhood',
        'building_damage_status' => 'Damage Status',
        'operational_status' => 'Operational Status',
        'damage_date' => 'Damage Date',
        'creationdate' => 'Created At',
        'organizations_count' => 'Linked Organizations',
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
        $this->columns = $this->resolveColumns($columns, self::availableColumns());
    }

    /**
     * @return array<string, string>
     */
    public static function availableColumns(): array
    {
        return self::BASE_COLUMNS + self::layoutColumnsFromGroups(self::availableColumnGroups());
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function availableColumnGroups(): array
    {
        $groups = [
            'Summary' => self::BASE_COLUMNS,
        ];

        foreach (self::surveySections() as $sectionName => $section) {
            $columns = collect($section['fields'] ?? [])
                ->reject(fn (array $field): bool => ($field['type'] ?? null) === 'calculate')
                ->mapWithKeys(fn (array $field): array => [
                    $field['name'] => self::fieldLabel($field),
                ])
                ->all();

            $columns = array_diff_key($columns, self::BASE_COLUMNS);

            if ($columns !== []) {
                $groups[(string) ($section['label'] ?? $sectionName)] = $columns;
            }
        }

        return $groups;
    }

    public function collection(): Collection
    {
        return $this->surveys;
    }

    public function headings(): array
    {
        return array_values(array_intersect_key(self::availableColumns(), array_flip($this->columns)));
    }

    public function title(): string
    {
        return 'Survey';
    }

    public function map($row): array
    {
        /** @var CsoSurvey $row */
        $baseValues = [
            'objectid' => $row->objectid,
            'globalid' => $row->globalid,
            'organization_name' => $row->organization_name,
            'building_name' => $row->building_name,
            'municipalitie' => $row->municipalitie,
            'neighborhood' => $row->neighborhood,
            'building_damage_status' => $row->building_damage_status,
            'operational_status' => $row->operational_status,
            'damage_date' => $this->formattedDate($row->damage_date, 'Y-m-d'),
            'creationdate' => $this->formattedDate($row->creationdate),
            'organizations_count' => $row->organizations_count,
            'units_count' => $row->units_count,
            'assignedto' => $row->assignedto,
        ];

        return collect($this->columns)
            ->map(fn (string $column): mixed => $baseValues[$column] ?? $this->layoutValue($row, $column, $this->fieldDefinition($column, self::surveySections()->all())))
            ->all();
    }
}
