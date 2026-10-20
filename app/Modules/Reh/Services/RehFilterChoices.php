<?php

namespace App\Modules\Reh\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class RehFilterChoices
{
    public static function values(mixed $value): array
    {
        return array_values(array_filter(is_array($value) ? $value : [$value], fn ($item): bool => $item !== null && $item !== ''));
    }

    public static function contains(mixed $value, string $choice): bool
    {
        return in_array($choice, self::values($value), true);
    }

    public static function rules(array $input, string $key, array $choices, bool $required = false): array
    {
        $presence = $required ? 'required' : 'nullable';
        if (is_array($input[$key] ?? null)) {
            return [$key => [$presence, 'array', 'max:100'], $key.'.*' => ['required', 'string', 'distinct', Rule::in(array_map('strval', $choices))]];
        }

        return [$key => [$presence, 'string', Rule::in(array_map('strval', $choices))]];
    }

    public static function apply(Builder $query, string $column, mixed $value): Builder
    {
        $values = self::values($value);
        if ($values === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($column, $values): void {
            $known = array_values(array_diff($values, ['__missing']));
            if ($known !== []) {
                $q->whereIn($column, $known);
            }
            if (in_array('__missing', $values, true)) {
                $q->orWhere(fn (Builder $missing) => $missing->whereNull($column)->orWhere($column, ''));
            }
        });
    }
}
