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

class RoadFacilitySurveyItemsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    /**
     * @var array<string, string>
     */
    private const BASE_COLUMNS = [
        'road_objectid' => 'Road Object ID',
        'road_globalid' => 'Road Global ID',
        'road_name' => 'Road Name',
        'objectid' => 'Item Object ID',
        'globalid' => 'Item Global ID',
        'parentglobalid' => 'Parent Global ID',
        'repeat_index' => 'Repeat Index',
        'item_required' => 'Item Required',
        'description' => 'Description',
        'unit' => 'Unit',
        'quantity' => 'Quantity',
        'other_comments' => 'Other Comments',
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

        foreach (RoadFacilitySurveyLayout::repeatSections('R2') as $sectionName => $section) {
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
        return $this->surveys
            ->flatMap(fn (RoadFacilitySurvey $survey): Collection => $survey->items);
    }

    public function headings(): array
    {
        return array_values(array_intersect_key(self::availableColumns(), array_flip($this->columns)));
    }

    public function title(): string
    {
        return 'Items';
    }

    public function map($row): array
    {
        $survey = $this->surveys->firstWhere('globalid', $row->parentglobalid);

        $baseValues = [
            'road_objectid' => $survey?->objectid,
            'road_globalid' => $survey?->globalid,
            'road_name' => $survey?->str_name,
            'objectid' => $row->objectid,
            'globalid' => $row->globalid,
            'parentglobalid' => $row->parentglobalid,
            'repeat_index' => $row->repeat_index,
            'item_required' => $row->item_required,
            'description' => $row->description,
            'unit' => $row->unit,
            'quantity' => $row->quantity,
            'other_comments' => $row->other_comments,
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

    private function layoutValue(object $item, string $column): ?string
    {
        $field = $this->fieldDefinition($column);
        $value = RoadFacilitySurveyLayout::value($item, $column);

        if ($field === null) {
            return is_scalar($value) ? trim((string) $value) : null;
        }

        return RoadFacilitySurveyLayout::displayValue($value, $field);
    }

    private function fieldDefinition(string $column): ?array
    {
        foreach (RoadFacilitySurveyLayout::repeatSections('R2') as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['name'] ?? null) === $column) {
                    return $field;
                }
            }
        }

        return null;
    }
}
