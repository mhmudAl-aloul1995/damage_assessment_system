<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\CsoSurvey;
use App\Support\Forms\CsoSurveyLayout;
use Illuminate\Support\Collection;

trait CsoSurveyExportSupport
{
    private static function fieldLabel(array $field): string
    {
        return trim((string) ($field['label'] ?? '')) !== ''
            ? (string) $field['label']
            : (string) $field['name'];
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, string>  $availableColumns
     * @return array<int, string>
     */
    private function resolveColumns(array $columns, array $availableColumns): array
    {
        $selectedColumns = array_values(array_intersect($columns, array_keys($availableColumns)));

        return $selectedColumns !== [] ? $selectedColumns : array_keys($availableColumns);
    }

    /**
     * @param  array<string, array<string, string>>  $groups
     * @return array<string, string>
     */
    private static function layoutColumnsFromGroups(array $groups): array
    {
        return collect($groups)
            ->except('Summary')
            ->flatMap(fn (array $columns): array => $columns)
            ->all();
    }

    /**
     * @param  array<string, array<string, string>>  $groups
     * @return array<int, string>
     */
    private static function columnsFromGroups(array $groups): array
    {
        return array_keys(collect($groups)->flatMap(fn (array $columns): array => $columns)->all());
    }

    private function layoutValue(object $record, string $column, ?array $field = null): ?string
    {
        $value = CsoSurveyLayout::value($record, $column);

        if ($field === null) {
            return is_scalar($value) ? trim((string) $value) : null;
        }

        return CsoSurveyLayout::displayValue($value, $field);
    }

    private function fieldDefinition(string $column, array $sections): ?array
    {
        foreach ($sections as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['name'] ?? null) === $column) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function surveyForChild(object $row): ?CsoSurvey
    {
        return $this->surveys->firstWhere('globalid', $row->parentglobalid);
    }

    private function formattedDate(mixed $value, string $format = 'Y-m-d H:i'): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format($format) : $value;
    }

    private static function surveySections(): Collection
    {
        $repeatSectionNames = array_merge(
            CsoSurveyLayout::repeatSectionNames('CSO_Organizations'),
            CsoSurveyLayout::repeatSectionNames('Unit_Information'),
            ['CSO_Organizations', 'Unit_Information'],
        );

        return collect(CsoSurveyLayout::sections())
            ->reject(fn (array $section): bool => ($section['type'] ?? 'group') === 'repeat')
            ->reject(fn (array $section): bool => in_array($section['name'] ?? '', $repeatSectionNames, true));
    }
}
