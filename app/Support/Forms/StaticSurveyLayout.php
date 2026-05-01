<?php

declare(strict_types=1);

namespace App\Support\Forms;

use Illuminate\Support\Str;

abstract class StaticSurveyLayout
{
    /**
     * @return array<string, array{type: string, name: string, label: string, hint: ?string, fields: array<int, array{name: string, type: string, label: string, hint: ?string, list_name: ?string}>}>
     */
    abstract public static function sections(): array;

    /**
     * @return array<string, array<string, string>>
     */
    abstract public static function choices(): array;

    /**
     * @return array{type?: string, name: string, label: string, hint?: ?string, fields: array<int, array{name: string, type: string, label: string, hint: ?string, list_name: ?string}>}
     */
    public static function section(string $name): array
    {
        return static::sections()[$name] ?? [
            'name' => $name,
            'type' => 'group',
            'label' => $name,
            'hint' => null,
            'fields' => [],
        ];
    }

    /**
     * @return array<string, array{type: string, name: string, label: string, hint: ?string, fields: array<int, array{name: string, type: string, label: string, hint: ?string, list_name: ?string}>}>
     */
    public static function repeatSections(string $name): array
    {
        return [$name => static::section($name)];
    }

    /**
     * @return array<int, string>
     */
    public static function repeatSectionNames(string $name): array
    {
        return array_keys(static::repeatSections($name));
    }

    public static function value(object|array $record, string $field): mixed
    {
        foreach (static::fieldCandidates($field) as $candidate) {
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
            $normalizedPayload[(string) $key] = $value;
            $normalizedPayload[strtolower((string) $key)] = $value;
            $normalizedPayload[Str::snake((string) $key)] = $value;
        }

        foreach (static::fieldCandidates($field) as $candidate) {
            foreach ([$candidate, strtolower($candidate), Str::snake($candidate)] as $key) {
                if (array_key_exists($key, $normalizedPayload) && $normalizedPayload[$key] !== '') {
                    return $normalizedPayload[$key];
                }
            }
        }

        return null;
    }

    public static function displayValue(mixed $value, array $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_array($value)) {
            $items = collect($value)->flatten()->map(
                fn (mixed $item): ?string => is_scalar($item) ? trim((string) $item) : null
            )->filter()->values();

            if ($items->isEmpty()) {
                return null;
            }

            return $items
                ->map(fn (string $item): string => static::choiceLabel($field['list_name'] ?? null, $item))
                ->implode(', ');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        if (($field['type'] ?? null) === 'select_multiple') {
            return collect(preg_split('/[, ]+/', $stringValue) ?: [])
                ->map(fn (string $item): string => trim($item))
                ->filter()
                ->map(fn (string $item): string => static::choiceLabel($field['list_name'] ?? null, $item))
                ->implode(', ');
        }

        if (($field['type'] ?? null) === 'select_one') {
            return static::choiceLabel($field['list_name'] ?? null, $stringValue);
        }

        return $stringValue;
    }

    public static function choiceLabel(?string $listName, string $value): string
    {
        if ($listName === null || $listName === '') {
            return $value;
        }

        return static::choices()[$listName][$value] ?? Str::of($value)->replace('_', ' ')->headline()->toString();
    }

    /**
     * @return array<int, string>
     */
    protected static function fieldCandidates(string $field): array
    {
        return array_values(array_unique([
            $field,
            strtolower($field),
            Str::snake($field),
            Str::camel($field),
        ]));
    }
}
