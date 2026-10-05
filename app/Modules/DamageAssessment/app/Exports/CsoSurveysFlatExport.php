<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\CsoSurvey;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CsoSurveysFlatExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array<int, string>  $surveyColumns
     * @param  array<int, string>  $organizationColumns
     * @param  array<int, string>  $unitColumns
     */
    public function __construct(
        private readonly Collection $surveys,
        private readonly array $surveyColumns,
        private readonly array $organizationColumns,
        private readonly array $unitColumns,
    ) {}

    public function collection(): Collection
    {
        return $this->typedRecords();
    }

    public function rows(): Collection
    {
        return $this->typedRecords()->flatMap(fn (array $row): array => $this->fieldRows($row));
    }

    private function typedRecords(): Collection
    {
        return $this->surveys->flatMap(function (CsoSurvey $survey): array {
            $rows = [
                [
                    'type' => 'survey',
                    'record' => $survey,
                    'survey' => $survey,
                ],
            ];

            foreach ($survey->organizations as $organization) {
                $rows[] = [
                    'type' => 'organization',
                    'record' => $organization,
                    'survey' => $survey,
                ];
            }

            foreach ($survey->units as $unit) {
                $rows[] = [
                    'type' => 'unit',
                    'record' => $unit,
                    'survey' => $survey,
                ];
            }

            return $rows;
        });
    }

    public function headings(): array
    {
        return [
            'Section Type',
            'Survey Object ID',
            'Survey Global ID',
            'Survey Organization Name',
            'Survey Building Name',
            'Child Object ID',
            'Child Global ID',
            'Repeat Index',
            'Field',
            'Value',
        ];
    }

    public function map($row): array
    {
        return $this->fieldRows($row);
    }

    private function fieldRows(array $row): array
    {
        $columns = match ($row['type']) {
            'organization' => $this->organizationColumns,
            'unit' => $this->unitColumns,
            default => $this->surveyColumns,
        };

        $availableColumns = match ($row['type']) {
            'organization' => CsoSurveyOrganizationsExport::availableColumns(),
            'unit' => CsoSurveyUnitsExport::availableColumns(),
            default => CsoSurveysExport::availableColumns(),
        };

        $record = $row['record'];
        $survey = $row['survey'];

        return collect($columns)
            ->map(fn (string $column): array => [
                'Section Type' => $row['type'],
                'Survey Object ID' => $survey->objectid,
                'Survey Global ID' => $survey->globalid,
                'Survey Organization Name' => $survey->organization_name,
                'Survey Building Name' => $survey->building_name,
                'Child Object ID' => $row['type'] === 'survey' ? null : $record->objectid,
                'Child Global ID' => $row['type'] === 'survey' ? null : $record->globalid,
                'Repeat Index' => $row['type'] === 'survey' ? null : $record->repeat_index,
                'Field' => $availableColumns[$column] ?? $column,
                'Value' => $this->stringValue($this->valueFor($row['type'], $record, $survey, $column)),
            ])
            ->all();
    }

    private function valueFor(string $type, object $record, CsoSurvey $survey, string $column): mixed
    {
        $export = match ($type) {
            'organization' => new CsoSurveyOrganizationsExport(collect([$survey]), [$column]),
            'unit' => new CsoSurveyUnitsExport(collect([$survey]), [$column]),
            default => new CsoSurveysExport(collect([$survey]), [$column]),
        };

        return $export->map($record)[0] ?? null;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            $stringValue = trim((string) $value);

            return $stringValue === '' ? null : $stringValue;
        }

        if (is_array($value)) {
            $items = collect($value)->flatten()
                ->map(fn (mixed $item): ?string => is_scalar($item) ? trim((string) $item) : null)
                ->filter()
                ->values();

            if ($items->isNotEmpty()) {
                return $items->implode(', ');
            }
        }

        $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encodedValue === false ? null : $encodedValue;
    }
}
