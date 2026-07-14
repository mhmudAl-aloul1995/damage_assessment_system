<?php

namespace App\Modules\Heks\Services;

use App\Modules\Heks\Models\HeksKoboChoice;
use App\Modules\Heks\Models\HeksKoboFieldMapping;
use Illuminate\Support\Collection;

class HeksKoboValueDisplayService
{
    /**
     * @return array{raw: mixed, display: string, type: ?string, resolved: bool, choices: array<int, array{value: string, label: string, selected: bool}>, warning: ?string}
     */
    public function resolve(string $serviceName, string $questionKey, mixed $rawValue, string $locale = 'ar'): array
    {
        $mapping = $this->mapping($serviceName, $questionKey);
        $fieldType = $this->fieldType($mapping);

        if (! in_array($fieldType, ['select_one', 'select_multiple'], true)) {
            return [
                'raw' => $rawValue,
                'display' => $this->stringValue($rawValue),
                'type' => $fieldType,
                'resolved' => false,
                'choices' => [],
                'warning' => null,
            ];
        }

        $choices = $this->choices($serviceName, $questionKey, $mapping?->list_name, $locale);
        $selectedValues = $fieldType === 'select_multiple'
            ? $this->multipleValues($rawValue)
            : [$this->stringValue($rawValue)];

        $selectedLabels = [];
        $choiceOptions = $choices
            ->map(function (HeksKoboChoice $choice) use ($selectedValues, &$selectedLabels): array {
                $choiceName = (string) $choice->choice_name;
                $selected = in_array($choiceName, $selectedValues, true);
                $label = (string) ($choice->choice_label ?: $choiceName);

                if ($selected) {
                    $selectedLabels[] = $label;
                }

                return [
                    'value' => $choiceName,
                    'label' => $label,
                    'selected' => $selected,
                ];
            })
            ->values()
            ->all();

        if ($selectedLabels !== []) {
            return [
                'raw' => $rawValue,
                'display' => implode('، ', $selectedLabels),
                'type' => $fieldType,
                'resolved' => true,
                'choices' => $choiceOptions,
                'warning' => null,
            ];
        }

        $rawDisplay = $this->stringValue($rawValue);

        return [
            'raw' => $rawValue,
            'display' => $rawDisplay,
            'type' => $fieldType,
            'resolved' => false,
            'choices' => $choiceOptions,
            'warning' => $rawDisplay === '' ? null : "خيار غير معروف: {$rawDisplay}",
        ];
    }

    public function rawValueForStorage(mixed $value, string $fieldType): ?string
    {
        if ($fieldType === 'select_multiple' && is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => trim((string) $item))
                ->filter()
                ->implode(' ');
        }

        return $value !== null ? trim((string) $value) : null;
    }

    private function mapping(string $serviceName, string $questionKey): ?HeksKoboFieldMapping
    {
        foreach ($this->serviceLookupKeys($serviceName) as $service) {
            foreach ($this->fieldLookupKeys($questionKey) as $field) {
                $mapping = HeksKoboFieldMapping::query()
                    ->where('service_name', $service)
                    ->where('kobo_field', $field)
                    ->first();

                if ($mapping instanceof HeksKoboFieldMapping) {
                    return $mapping;
                }
            }
        }

        return null;
    }

    private function fieldType(?HeksKoboFieldMapping $mapping): ?string
    {
        if (! $mapping instanceof HeksKoboFieldMapping) {
            return null;
        }

        $type = trim((string) ($mapping->field_type ?: $mapping->data_type));

        if ($type === '') {
            return null;
        }

        return str_starts_with($type, 'select_multiple')
            ? 'select_multiple'
            : (str_starts_with($type, 'select_one') ? 'select_one' : strtok($type, ' '));
    }

    /**
     * @return Collection<int, HeksKoboChoice>
     */
    private function choices(string $serviceName, string $questionKey, ?string $listName, string $locale): Collection
    {
        foreach ($this->serviceLookupKeys($serviceName) as $service) {
            foreach ($this->fieldLookupKeys($questionKey) as $field) {
                $choices = HeksKoboChoice::query()
                    ->where('service_name', $service)
                    ->where('question_key', $field)
                    ->where('is_active', true)
                    ->where(function ($query) use ($locale): void {
                        $query->where('language', $locale)->orWhereNull('language');
                    })
                    ->orderBy('sort_order')
                    ->get();

                if ($choices->isNotEmpty()) {
                    return $choices;
                }
            }

            if ($listName !== null && $listName !== '') {
                $choices = HeksKoboChoice::query()
                    ->where('service_name', $service)
                    ->where('list_name', $listName)
                    ->where('is_active', true)
                    ->where(function ($query) use ($locale): void {
                        $query->where('language', $locale)->orWhereNull('language');
                    })
                    ->orderBy('sort_order')
                    ->get();

                if ($choices->isNotEmpty()) {
                    return $choices;
                }
            }
        }

        return collect();
    }

    /**
     * @return array<int, string>
     */
    private function multipleValues(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->map(fn (mixed $item): string => trim((string) $item))->filter()->values()->all();
        }

        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return collect($decoded)->map(fn (mixed $item): string => trim((string) $item))->filter()->values()->all();
        }

        return collect(preg_split('/\s+/', $value) ?: [])->filter()->values()->all();
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @return array<int, string>
     */
    private function serviceLookupKeys(string $serviceName): array
    {
        $normalizedServiceName = str($serviceName)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        return array_values(array_unique(array_filter([
            $serviceName,
            str_replace('_', '-', $serviceName),
            str_replace('-', '_', $serviceName),
            str_contains($normalizedServiceName, 'heks') && str_contains($normalizedServiceName, 'final') ? 'heks-main' : null,
            match ($serviceName) {
                'Heks Final V1' => 'heks-main',
                'heks_main' => 'heks-main',
                'heks-main' => 'heks_main',
                'heks_followup' => 'heks-followups',
                'heks-followups' => 'heks_followup',
                'heks_boq' => 'heks-boq',
                'heks-boq' => 'heks_boq',
                default => null,
            },
        ])));
    }

    /**
     * @return array<int, string>
     */
    private function fieldLookupKeys(string $field): array
    {
        $keys = [$field];
        $withoutListIndexes = preg_replace('/\[\d+\]/', '', $field);

        if (is_string($withoutListIndexes) && $withoutListIndexes !== $field) {
            $keys[] = $withoutListIndexes;
        }

        if (str_contains($field, '/')) {
            $keys[] = substr($field, strpos($field, '/') + 1);
            $keys[] = str($field)->beforeLast('/')->toString();
        }

        return array_values(array_unique(array_filter($keys)));
    }
}
