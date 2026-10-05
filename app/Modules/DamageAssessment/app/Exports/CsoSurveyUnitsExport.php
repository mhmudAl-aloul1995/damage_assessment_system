<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\CsoSurvey;
use App\Support\Forms\CsoSurveyLayout;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class CsoSurveyUnitsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    use CsoSurveyExportSupport;

    /**
     * @var array<string, string>
     */
    private const BASE_COLUMNS = [
        'survey_objectid' => 'Survey Object ID',
        'survey_globalid' => 'Survey Global ID',
        'survey_organization_name' => 'Survey Organization Name',
        'survey_building_name' => 'Survey Building Name',
        'objectid' => 'Unit Object ID',
        'globalid' => 'Unit Global ID',
        'parentglobalid' => 'Parent Global ID',
        'repeat_index' => 'Repeat Index',
        'unit_name' => 'Unit Name',
        'unit_floor_number' => 'Unit Floor Number',
        'unit_number' => 'Unit Number',
        'unit_damage_status' => 'Unit Damage Status',
        'creationdate' => 'Created At',
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

        foreach (CsoSurveyLayout::repeatSections('Unit_Information') as $sectionName => $section) {
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
        return $this->surveys->flatMap(fn (CsoSurvey $survey): Collection => $survey->units);
    }

    public function headings(): array
    {
        return array_values(array_intersect_key(self::availableColumns(), array_flip($this->columns)));
    }

    public function title(): string
    {
        return 'Unit Information';
    }

    public function map($row): array
    {
        $survey = $this->surveyForChild($row);
        $baseValues = [
            'survey_objectid' => $survey?->objectid,
            'survey_globalid' => $survey?->globalid,
            'survey_organization_name' => $survey?->organization_name,
            'survey_building_name' => $survey?->building_name,
            'objectid' => $row->objectid,
            'globalid' => $row->globalid,
            'parentglobalid' => $row->parentglobalid,
            'repeat_index' => $row->repeat_index,
            'unit_name' => $row->unit_name,
            'unit_floor_number' => $row->unit_floor_number,
            'unit_number' => $row->unit_number,
            'unit_damage_status' => $row->unit_damage_status,
            'creationdate' => $this->formattedDate($row->creationdate),
        ];

        return collect($this->columns)
            ->map(fn (string $column): mixed => $baseValues[$column] ?? $this->layoutValue($row, $column, $this->fieldDefinition($column, CsoSurveyLayout::repeatSections('Unit_Information'))))
            ->all();
    }
}
