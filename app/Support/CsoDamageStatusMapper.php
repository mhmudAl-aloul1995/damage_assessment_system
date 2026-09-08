<?php

declare(strict_types=1);

namespace App\Support;

class CsoDamageStatusMapper
{
    public const FULLY_DAMAGED = 'fully_damaged';

    public const PARTIALLY_DAMAGED = 'partially_damaged';

    public const NO_DAMAGE = 'no_damage';

    public const COMMITTEE_REVIEW = 'committee_review';

    public const UNCLASSIFIED = 'unclassified';

    /**
     * @return list<string>
     */
    public static function valuesFor(string $bucket): array
    {
        return match ($bucket) {
            self::FULLY_DAMAGED => [
                '1',
                'total',
                'totally',
                'total_damage',
                'totally_damaged',
                'totally damaged',
                'fully_damaged',
                'fully_damaged2',
                'fully damaged',
            ],
            self::PARTIALLY_DAMAGED => [
                '2',
                'partial',
                'partial_damage',
                'partially_damaged',
                'partially_damaged2',
                'partially damaged',
            ],
            self::NO_DAMAGE => [
                'no_damage',
                'no_damaged',
                'no damage',
                'no damaged',
            ],
            self::COMMITTEE_REVIEW => [
                '3',
                'committee_review',
                'committee_review2',
                'commite_review',
                'commitee_review',
                'commitee_review2',
            ],
            default => [],
        };
    }

    public static function bucket(mixed $value): string
    {
        if (! is_scalar($value)) {
            return self::UNCLASSIFIED;
        }

        $normalizedValue = strtolower(trim((string) $value));

        foreach ([self::FULLY_DAMAGED, self::PARTIALLY_DAMAGED, self::NO_DAMAGE, self::COMMITTEE_REVIEW] as $bucket) {
            if (in_array($normalizedValue, self::valuesFor($bucket), true)) {
                return $bucket;
            }
        }

        return self::UNCLASSIFIED;
    }

    public static function sumSql(string $column, string $bucket): string
    {
        $values = self::quotedValues(self::valuesFor($bucket));

        return "SUM(CASE WHEN LOWER(TRIM(COALESCE({$column}, ''))) IN ({$values}) THEN 1 ELSE 0 END)";
    }

    public static function caseSql(string $column, string $bucket): string
    {
        $values = self::quotedValues(self::valuesFor($bucket));

        return "CASE WHEN LOWER(TRIM(COALESCE({$column}, ''))) IN ({$values}) THEN 1 ELSE 0 END";
    }

    public static function unclassifiedSumSql(string $column): string
    {
        $values = self::quotedValues([
            ...self::valuesFor(self::FULLY_DAMAGED),
            ...self::valuesFor(self::PARTIALLY_DAMAGED),
            ...self::valuesFor(self::NO_DAMAGE),
            ...self::valuesFor(self::COMMITTEE_REVIEW),
        ]);

        return "SUM(CASE WHEN {$column} IS NULL OR TRIM({$column}) = '' OR LOWER(TRIM({$column})) NOT IN ({$values}) THEN 1 ELSE 0 END)";
    }

    public static function unclassifiedCaseSql(string $column): string
    {
        $values = self::quotedValues([
            ...self::valuesFor(self::FULLY_DAMAGED),
            ...self::valuesFor(self::PARTIALLY_DAMAGED),
            ...self::valuesFor(self::NO_DAMAGE),
            ...self::valuesFor(self::COMMITTEE_REVIEW),
        ]);

        return "CASE WHEN {$column} IS NULL OR TRIM({$column}) = '' OR LOWER(TRIM({$column})) NOT IN ({$values}) THEN 1 ELSE 0 END";
    }

    /**
     * @param  list<string>  $values
     */
    private static function quotedValues(array $values): string
    {
        return collect($values)
            ->map(fn (string $value): string => "'".str_replace("'", "''", strtolower($value))."'")
            ->implode(', ');
    }
}
