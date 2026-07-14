<?php

namespace App\Modules\Heks\Services;

use App\Modules\Heks\Models\HeksKoboChoice;
use Illuminate\Support\Arr;

class HeksKoboChoiceSyncService
{
    /**
     * @param  array<int, mixed>  $survey
     * @param  array<int, mixed>  $choices
     * @return array{select_one: int, select_multiple: int, choices: int, inactive: int}
     */
    public function sync(string $serviceName, array $survey, array $choices, ?string $version = null, bool $dryRun = false): array
    {
        $choiceRowsByList = $this->choicesByList($choices);
        $groupStack = [];
        $seen = [];
        $stats = ['select_one' => 0, 'select_multiple' => 0, 'choices' => 0, 'inactive' => 0];
        $formOrder = 0;

        foreach ($survey as $row) {
            $formOrder++;

            if (! is_array($row)) {
                continue;
            }

            $type = strtolower(trim((string) Arr::get($row, 'type', '')));
            $name = trim((string) Arr::get($row, 'name', ''));

            if ($this->startsGroup($type)) {
                if ($name !== '') {
                    $groupStack[] = $name;
                }

                continue;
            }

            if ($this->endsGroup($type)) {
                array_pop($groupStack);

                continue;
            }

            [$fieldType, $listName] = $this->selectTypeAndList($type);

            if ($fieldType === null || $listName === '' || $name === '') {
                continue;
            }

            $stats[$fieldType]++;
            $questionKey = $this->fieldKey($name, $groupStack);

            foreach ($choiceRowsByList[$listName] ?? [] as $choiceOrder => $choice) {
                $choiceName = trim((string) Arr::get($choice, 'name', ''));

                if ($choiceName === '') {
                    continue;
                }

                foreach ($this->labels($choice) as $language => $label) {
                    $seenKey = $serviceName.'|'.$questionKey.'|'.$choiceName.'|'.$language;
                    $seen[$seenKey] = true;
                    $stats['choices']++;

                    if ($dryRun) {
                        continue;
                    }

                    HeksKoboChoice::query()->updateOrCreate([
                        'service_name' => $serviceName,
                        'question_key' => $questionKey,
                        'choice_name' => $choiceName,
                        'language' => $language,
                    ], [
                        'list_name' => $listName,
                        'choice_label' => $label,
                        'version' => $version,
                        'sort_order' => $choiceOrder + 1,
                        'is_active' => true,
                        'raw_data' => $choice,
                    ]);
                }
            }
        }

        if (! $dryRun) {
            HeksKoboChoice::query()
                ->where('service_name', $serviceName)
                ->whereNotNull('raw_data')
                ->get()
                ->each(function (HeksKoboChoice $choice) use ($seen, &$stats): void {
                    $key = $choice->service_name.'|'.$choice->question_key.'|'.$choice->choice_name.'|'.$choice->language;

                    if (! isset($seen[$key]) && $choice->is_active) {
                        $choice->forceFill(['is_active' => false])->save();
                        $stats['inactive']++;
                    }
                });
        }

        return $stats;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    public function selectTypeAndList(string $type): array
    {
        $parts = preg_split('/\s+/', strtolower(trim($type))) ?: [];
        $fieldType = $parts[0] ?? null;

        if (! in_array($fieldType, ['select_one', 'select_multiple'], true)) {
            return [null, ''];
        }

        return [$fieldType, (string) ($parts[1] ?? '')];
    }

    /**
     * @param  array<int, mixed>  $choices
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function choicesByList(array $choices): array
    {
        $grouped = [];

        foreach ($choices as $choice) {
            if (! is_array($choice)) {
                continue;
            }

            $listName = trim((string) Arr::get($choice, 'list_name', ''));

            if ($listName !== '') {
                $grouped[$listName][] = $choice;
            }
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $choice
     * @return array<string, string>
     */
    private function labels(array $choice): array
    {
        $labels = [];
        $label = Arr::get($choice, 'label');

        if (is_array($label)) {
            foreach ($label as $language => $value) {
                if (is_string($value) && trim($value) !== '') {
                    $labels[$this->normalizeLanguage((string) $language)] = trim($value);
                }
            }
        } elseif (is_string($label) && trim($label) !== '') {
            $labels['ar'] = trim($label);
        }

        foreach ($choice as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'label') || ! is_string($value) || trim($value) === '') {
                continue;
            }

            $labels[$this->normalizeLanguage($key)] = trim($value);
        }

        if ($labels === []) {
            $choiceName = trim((string) Arr::get($choice, 'name', ''));
            $labels['ar'] = $choiceName;
        }

        return $labels;
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtolower($language);

        return str_contains($language, 'en') ? 'en' : 'ar';
    }

    /**
     * @param  array<int, string>  $groupStack
     */
    private function fieldKey(string $name, array $groupStack): string
    {
        if ($groupStack !== [] && ! str_contains($name, '/')) {
            return implode('/', [...$groupStack, $name]);
        }

        return $name;
    }

    private function startsGroup(string $type): bool
    {
        return str_starts_with($type, 'begin_group')
            || str_starts_with($type, 'begin repeat')
            || str_starts_with($type, 'begin_repeat');
    }

    private function endsGroup(string $type): bool
    {
        return str_starts_with($type, 'end_group')
            || str_starts_with($type, 'end repeat')
            || str_starts_with($type, 'end_repeat');
    }
}
