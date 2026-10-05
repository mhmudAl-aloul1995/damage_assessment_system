<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\RoadFacilitySurvey;
use App\Support\Forms\RoadFacilitySurveyLayout;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class RoadFacilitySurveysExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    /**
     * @var array<string, string>
     */
    private const BASE_COLUMNS = [
        'objectid' => 'Object ID',
        'phase_number' => 'Phase Number',
        'str_name' => 'Road Name',
        'municipalitie' => 'Municipality',
        'neighborhood' => 'Neighborhood',
        'road_damage_level' => 'Road Damage Level',
        'road_access' => 'Road Access',
        'submissiondate' => 'Submission Date',
        'items_count' => 'Linked Items',
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

        foreach (RoadFacilitySurveyLayout::sections() as $sectionName => $section) {
            if (($section['type'] ?? 'group') === 'repeat') {
                continue;
            }

            $columns = collect($section['fields'] ?? [])
                ->reject(fn (array $field): bool => in_array($field['type'] ?? null, ['calculate', 'note'], true))
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
        return 'Roads';
    }

    public function map($row): array
    {
        /** @var RoadFacilitySurvey $row */
        $baseValues = [
            'objectid' => $row->objectid,
            'phase_number' => $row->phase_number,
            'str_name' => $row->str_name,
            'municipalitie' => $row->municipalitie,
            'neighborhood' => $row->neighborhood,
            'road_damage_level' => $row->road_damage_level,
            'road_access' => $row->road_access,
            'submissiondate' => $row->submissiondate?->format('Y-m-d H:i'),
            'items_count' => $row->items_count,
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

    private function layoutValue(RoadFacilitySurvey $survey, string $column): ?string
    {
        $field = $this->fieldDefinition($column);
        $value = RoadFacilitySurveyLayout::value($survey, $column);

        if ($field === null) {
            return is_scalar($value) ? trim((string) $value) : null;
        }

        return RoadFacilitySurveyLayout::displayValue($value, $field);
    }

    private function fieldDefinition(string $column): ?array
    {
        foreach (RoadFacilitySurveyLayout::sections() as $section) {
            if (($section['type'] ?? 'group') === 'repeat') {
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
