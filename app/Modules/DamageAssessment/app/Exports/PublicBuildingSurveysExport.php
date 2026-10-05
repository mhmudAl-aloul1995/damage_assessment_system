<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\PublicBuildingSurvey;
use App\Support\Forms\PublicBuildingSurveyLayout;
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
    private const BASE_COLUMNS = [
        'objectid' => 'Object ID',
        'phase_number' => 'Phase Number',
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
        return self::BASE_COLUMNS + self::layoutColumns();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function availableColumnGroups(): array
    {
        $groups = [
            'Summary' => self::BASE_COLUMNS,
        ];

        foreach (PublicBuildingSurveyLayout::sections() as $sectionName => $section) {
            if (($section['type'] ?? 'group') === 'repeat') {
                continue;
            }

            if (in_array($sectionName, PublicBuildingSurveyLayout::repeatSectionNames('Unit_Information'), true)) {
                continue;
            }

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
        return 'Buildings';
    }

    public function map($row): array
    {
        /** @var PublicBuildingSurvey $row */
        $baseValues = [
            'objectid' => $row->objectid,
            'phase_number' => $row->phase_number,
            'building_name' => $row->building_name,
            'municipalitie' => $row->municipalitie,
            'neighborhood' => $row->neighborhood,
            'building_damage_status' => $row->building_damage_status,
            'date_of_damage' => $row->date_of_damage?->format('Y-m-d'),
            'units_count' => $row->units_count,
            'assignedto' => $row->assignedto,
        ];

        return collect($this->columns)
            ->map(fn (string $column): mixed => $baseValues[$column] ?? $this->layoutValue($row, $column))
            ->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function resolveColumns(array $columns): array
    {
        $selectedColumns = array_values(array_intersect($columns, array_keys(self::availableColumns())));

        return $selectedColumns !== [] ? $selectedColumns : array_keys(self::availableColumns());
    }

    /**
     * @return array<string, string>
     */
    private static function layoutColumns(): array
    {
        return collect(self::availableColumnGroups())
            ->except('Summary')
            ->flatMap(fn (array $columns): array => $columns)
            ->all();
    }

    private static function fieldLabel(array $field): string
    {
        return trim((string) ($field['label'] ?? '')) !== ''
            ? (string) $field['label']
            : (string) $field['name'];
    }

    private function layoutValue(PublicBuildingSurvey $survey, string $column): ?string
    {
        $field = $this->fieldDefinition($column);
        $value = PublicBuildingSurveyLayout::value($survey, $column);

        if ($field === null) {
            return is_scalar($value) ? trim((string) $value) : null;
        }

        return PublicBuildingSurveyLayout::displayValue($value, $field);
    }

    private function fieldDefinition(string $column): ?array
    {
        foreach (PublicBuildingSurveyLayout::sections() as $sectionName => $section) {
            if (($section['type'] ?? 'group') === 'repeat') {
                continue;
            }

            if (in_array($sectionName, PublicBuildingSurveyLayout::repeatSectionNames('Unit_Information'), true)) {
                continue;
            }

            foreach ($section['fields'] ?? [] as $field) {
                if (($field['name'] ?? null) === $column) {
                    return $field;
                }
            }
        }

        return null;
    }
}
