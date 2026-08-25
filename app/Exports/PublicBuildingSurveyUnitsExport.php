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

class PublicBuildingSurveyUnitsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    /**
     * @var array<string, string>
     */
    private const BASE_COLUMNS = [
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

        foreach (PublicBuildingSurveyLayout::repeatSections('Unit_Information') as $sectionName => $section) {
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
        return $this->surveys
            ->flatMap(fn (PublicBuildingSurvey $survey): Collection => $survey->units);
    }

    public function headings(): array
    {
        return array_values(array_intersect_key(self::availableColumns(), array_flip($this->columns)));
    }

    public function title(): string
    {
        return 'Units';
    }

    public function map($row): array
    {
        $survey = $this->surveys->firstWhere('globalid', $row->parentglobalid);

        $baseValues = [
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

    private function layoutValue(object $unit, string $column): ?string
    {
        $field = $this->fieldDefinition($column);
        $value = PublicBuildingSurveyLayout::value($unit, $column);

        if ($field === null) {
            return is_scalar($value) ? trim((string) $value) : null;
        }

        return PublicBuildingSurveyLayout::displayValue($value, $field);
    }

    private function fieldDefinition(string $column): ?array
    {
        foreach (PublicBuildingSurveyLayout::repeatSections('Unit_Information') as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['name'] ?? null) === $column) {
                    return $field;
                }
            }
        }

        return null;
    }
}
