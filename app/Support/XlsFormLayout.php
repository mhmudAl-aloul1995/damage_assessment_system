<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class XlsFormLayout
{
    /**
     * @var array<string, array<int, array{title: string, fields: array<int, array{name: string, label: string}>}>>
     */
    private static array $cache = [];

    /**
     * @return array<int, array{title: string, fields: array<int, array{name: string, label: string}>}>
     */
    public static function sections(string $path, ?string $repeatName = null): array
    {
        $cacheKey = $path.'|'.($repeatName ?? 'main');

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        if (! is_file($path)) {
            return self::$cache[$cacheKey] = [];
        }

        $rows = IOFactory::load($path)
            ->getSheetByName('survey')
            ?->toArray(null, true, true, true) ?? [];

        $sections = [];
        $sectionIndex = null;
        $groupStack = [];
        $insideTargetRepeat = $repeatName === null;
        $repeatDepth = 0;

        foreach ($rows as $row) {
            $type = trim((string) ($row['A'] ?? ''));
            $normalizedType = str_replace('_', ' ', $type);
            $name = trim((string) ($row['B'] ?? ''));
            $label = self::cleanLabel($row['C'] ?? null) ?: self::cleanLabel($row['D'] ?? null) ?: self::labelFromName($name);

            if ($type === '') {
                continue;
            }

            if (str_starts_with($normalizedType, 'begin repeat')) {
                if ($repeatName !== null && self::sameName($name, $repeatName)) {
                    $insideTargetRepeat = true;
                    $repeatDepth = 1;
                    $groupStack = [];
                    $sectionIndex = null;
                } elseif ($insideTargetRepeat && $repeatName !== null) {
                    $repeatDepth++;
                }

                continue;
            }

            if (str_starts_with($normalizedType, 'end repeat')) {
                if ($repeatName !== null && $insideTargetRepeat) {
                    $repeatDepth--;

                    if ($repeatDepth <= 0) {
                        break;
                    }
                }

                continue;
            }

            if (! $insideTargetRepeat) {
                continue;
            }

            if (str_starts_with($normalizedType, 'begin group')) {
                $groupStack[] = $label;
                $sections[] = [
                    'title' => $label,
                    'fields' => [],
                ];
                $sectionIndex = array_key_last($sections);

                continue;
            }

            if (str_starts_with($normalizedType, 'end')) {
                array_pop($groupStack);
                $sectionIndex = array_key_last($sections);

                continue;
            }

            if (self::isQuestionType($normalizedType) && $name !== '') {
                if ($sectionIndex === null) {
                    $sections[] = [
                        'title' => $repeatName === null ? 'Survey' : self::labelFromName($repeatName),
                        'fields' => [],
                    ];
                    $sectionIndex = array_key_last($sections);
                }

                $sections[$sectionIndex]['fields'][] = [
                    'name' => $name,
                    'label' => $label,
                ];
            }
        }

        return self::$cache[$cacheKey] = collect($sections)
            ->filter(fn (array $section): bool => $section['fields'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function candidates(string $field): array
    {
        $snake = Str::snake($field);
        $lower = strtolower($field);
        $withoutRepeatSuffix = preg_replace('/_001$/', '', $snake) ?: $snake;

        return collect([
            $field,
            $lower,
            $snake,
            strtolower($snake),
            str_replace('_', '', strtolower($snake)),
            $withoutRepeatSuffix,
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function value(object $record, string $field): mixed
    {
        foreach (self::candidates($field) as $candidate) {
            $value = data_get($record, $candidate);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        $payload = data_get($record, 'raw_payload');

        if (is_string($payload)) {
            $decodedPayload = json_decode($payload, true);
            $payload = json_last_error() === JSON_ERROR_NONE ? $decodedPayload : null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $normalizedPayload = [];

        foreach ($payload as $key => $value) {
            $normalizedPayload[strtolower((string) $key)] = $value;
            $normalizedPayload[strtolower(Str::snake((string) $key))] = $value;
        }

        foreach (self::candidates($field) as $candidate) {
            $normalizedCandidate = strtolower($candidate);

            if (array_key_exists($normalizedCandidate, $normalizedPayload)) {
                return $normalizedPayload[$normalizedCandidate];
            }
        }

        return null;
    }

    private static function isQuestionType(string $type): bool
    {
        return ! str_starts_with($type, 'begin')
            && ! str_starts_with($type, 'end')
            && ! in_array($type, ['note'], true);
    }

    private static function sameName(string $left, string $right): bool
    {
        return strtolower($left) === strtolower($right);
    }

    private static function cleanLabel(mixed $label): ?string
    {
        if (! is_scalar($label)) {
            return null;
        }

        $label = trim((string) $label);

        return $label === '' ? null : $label;
    }

    private static function labelFromName(string $name): string
    {
        return Str::of($name)->replace('_', ' ')->headline()->toString();
    }
}
