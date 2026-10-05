<?php

declare(strict_types=1);

namespace App\Support;

class CsoSurveyDamageStatusNormalizer
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $normalizedValue = strtolower(trim((string) $value));

        return match ($normalizedValue) {
            'total', 'totally', 'total_damage', 'totally_damaged', 'totally damaged', 'fully_damaged', 'fully damaged' => '1',
            'partial', 'partial_damage', 'partially_damaged', 'partially damaged' => '2',
            default => $value,
        };
    }
}
