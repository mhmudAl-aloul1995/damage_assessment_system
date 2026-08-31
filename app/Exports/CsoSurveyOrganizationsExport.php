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

class CsoSurveyOrganizationsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
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
        'objectid' => 'Organization Object ID',
        'globalid' => 'Organization Global ID',
        'parentglobalid' => 'Parent Global ID',
        'repeat_index' => 'Repeat Index',
        'organization_name_en' => 'Organization Name EN',
        'organization_name_ar' => 'Organization Name AR',
        'organization_acronym' => 'Organization Acronym',
        'operational_status' => 'Operational Status',
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

        foreach (CsoSurveyLayout::repeatSections('CSO_Organizations') as $sectionName => $section) {
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
        return $this->surveys->flatMap(fn (CsoSurvey $survey): Collection => $survey->organizations);
    }

    public function headings(): array
    {
        return array_values(array_intersect_key(self::availableColumns(), array_flip($this->columns)));
    }

    public function title(): string
    {
        return 'CSO Organizations';
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
            'organization_name_en' => $row->organization_name_en,
            'organization_name_ar' => $row->organization_name_ar,
            'organization_acronym' => $row->organization_acronym,
            'operational_status' => $row->operational_status,
            'creationdate' => $this->formattedDate($row->creationdate),
        ];

        return collect($this->columns)
            ->map(fn (string $column): mixed => $baseValues[$column] ?? $this->layoutValue($row, $column, $this->fieldDefinition($column, CsoSurveyLayout::repeatSections('CSO_Organizations'))))
            ->all();
    }
}
