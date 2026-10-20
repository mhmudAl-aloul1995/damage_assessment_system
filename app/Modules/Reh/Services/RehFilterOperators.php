<?php

namespace App\Modules\Reh\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class RehFilterOperators
{
    public const LABELS = ['is' => 'يساوي', 'is_not' => 'لا يساوي', 'includes' => 'يتضمن أيًّا من', 'excludes' => 'لا يتضمن أيًّا من', 'contains' => 'يحتوي على', 'not_contains' => 'لا يحتوي على', 'gt' => 'أكبر من', 'gte' => 'أكبر من أو يساوي', 'lt' => 'أصغر من', 'lte' => 'أصغر من أو يساوي', 'between' => 'بين قيمتين', 'blank' => 'فارغ', 'not_blank' => 'غير فارغ'];

    public static function choices(string $type): array
    {
        $keys = match ($type) {
            'choice' => ['is', 'is_not', 'blank', 'not_blank'],
            'multiple' => ['includes', 'excludes', 'blank', 'not_blank'],
            'integer', 'decimal', 'date', 'datetime' => ['between', 'is', 'is_not', 'gt', 'gte', 'lt', 'lte', 'blank', 'not_blank'],
            'presence' => ['blank', 'not_blank'],
            default => ['contains', 'not_contains', 'is', 'is_not', 'blank', 'not_blank'],
        };

        $labels = array_intersect_key(self::LABELS, array_flip($keys));
        if (in_array($type, ['date', 'datetime'], true)) {
            $labels = [...$labels, 'gt' => 'بعد', 'gte' => 'في أو بعد', 'lt' => 'قبل', 'lte' => 'في أو قبل', 'between' => 'بين تاريخين'];
        }

        return $labels;
    }

    public static function default(string $type): string
    {
        return match ($type) {
            'choice' => 'is', 'multiple' => 'includes', 'integer', 'decimal', 'date', 'datetime' => 'between', 'presence' => '', default => 'contains',
        };
    }

    public static function active(array $filters, string $key): bool
    {
        return in_array($filters[$key.'_op'] ?? '', ['blank', 'not_blank'], true)
            || RehFilterChoices::values($filters[$key] ?? null) !== []
            || isset($filters[$key.'_min']) && $filters[$key.'_min'] !== ''
            || isset($filters[$key.'_max']) && $filters[$key.'_max'] !== '';
    }

    public function apply(Builder $query, string $column, array $definition, array $filters, string $key): void
    {
        $operator = $filters[$key.'_op'] ?? self::default($definition['type']);
        if (in_array($operator, ['blank', 'not_blank'], true)) {
            $this->blank($query, $column, $operator === 'not_blank', $definition['field'] === 'location');

            return;
        }
        if ($operator === 'between') {
            foreach (['min' => '>=', 'max' => '<='] as $bound => $comparison) {
                if (isset($filters[$key.'_'.$bound]) && $filters[$key.'_'.$bound] !== '') {
                    $query->where($column, $comparison, $this->value($filters[$key.'_'.$bound], $definition['type']));
                }
            }

            return;
        }
        $values = RehFilterChoices::values($filters[$key] ?? null);
        if ($values === []) {
            return;
        }
        if (in_array($operator, ['gt', 'gte', 'lt', 'lte'], true)) {
            $query->where($column, ['gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='][$operator], $this->value($values[0], $definition['type']));

            return;
        }
        $negative = in_array($operator, ['is_not', 'not_contains', 'excludes'], true);
        if ($negative && ! in_array('__missing', $values, true)) {
            $this->blank($query, $column, true);
        }
        $method = $negative ? 'whereNot' : 'where';
        $query->$method(function (Builder $matches) use ($values, $definition, $column, $operator): void {
            foreach ($values as $value) {
                $matches->orWhere(function (Builder $part) use ($value, $definition, $column, $operator): void {
                    if ($value === '__missing' && in_array($definition['type'], ['choice', 'multiple'], true)) {
                        $this->blank($part, $column);
                    } elseif (in_array($operator, ['includes', 'excludes'], true)) {
                        $this->token($part, $column, (string) $value);
                    } elseif (in_array($operator, ['contains', 'not_contains'], true)) {
                        $wrapped = $part->getQuery()->getGrammar()->wrap($column);
                        $part->whereRaw("$wrapped LIKE ? ESCAPE '!'", ['%'.$this->escape((string) $value).'%']);
                    } else {
                        $part->where($column, $this->value($value, $definition['type']));
                    }
                });
            }
        });
    }

    private function value(mixed $value, string $type): mixed
    {
        return $type === 'datetime' ? CarbonImmutable::parse($value)->toDateTimeString() : $value;
    }

    private function blank(Builder $query, string $column, bool $negate = false, bool $geometry = false): void
    {
        $method = $negate ? 'whereNot' : 'where';
        $query->$method(function (Builder $part) use ($column, $geometry): void {
            $wrapped = $part->getQuery()->getGrammar()->wrap($column);
            $part->whereNull($column)->orWhereRaw("TRIM($wrapped) = ?", ['']);
            if ($geometry) {
                $part->orWhereIn($column, ['null', '{}', '[]']);
            }
        });
    }

    public function token(Builder $query, string $column, string $value): void
    {
        $column = $query->getQuery()->getGrammar()->wrap($column);
        $normalized = "REPLACE(REPLACE(REPLACE(REPLACE($column, ',', ' '), CHAR(9), ' '), CHAR(10), ' '), CHAR(13), ' ')";
        $expression = $query->getConnection()->getDriverName() === 'sqlite' ? "' ' || $normalized || ' '" : "CONCAT(' ', $normalized, ' ')";
        $query->whereRaw("($expression) LIKE ? ESCAPE '!'", ['% '.$this->escape($value).' %']);
    }

    private function escape(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
