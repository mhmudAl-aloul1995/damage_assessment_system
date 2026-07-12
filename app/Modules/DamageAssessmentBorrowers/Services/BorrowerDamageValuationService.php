<?php

namespace App\Modules\DamageAssessmentBorrowers\Services;

class BorrowerDamageValuationService
{
    private const GROUND_FLOOR_RATE_USD = 325;

    private const REPEATED_FLOOR_RATE_USD = 280;

    /**
     * @var array<string, string>
     */
    private const FLOOR_TYPES = [
        'ground' => 'ground',
        'repeated' => 'repeated',
        'ارضي' => 'ground',
        'أرضي' => 'ground',
        'الأرضي' => 'ground',
        'الارضي' => 'ground',
        'طابق ارضي' => 'ground',
        'طابق أرضي' => 'ground',
        'متكرر' => 'repeated',
        'الطابق المتكرر' => 'repeated',
        'طابق متكرر' => 'repeated',
    ];

    public function normalizeFloorType(mixed $floorType): ?string
    {
        if ($floorType === null || is_array($floorType)) {
            return null;
        }

        $floorType = trim((string) $floorType);

        return $floorType === '' ? null : (self::FLOOR_TYPES[$floorType] ?? null);
    }

    public function fullDemolitionValueUsd(?float $area, mixed $floorType, ?string $damageStatus): ?float
    {
        if ($damageStatus !== 'destroyed' || $area === null || $area <= 0) {
            return null;
        }

        $normalizedFloorType = $this->normalizeFloorType($floorType);
        $rate = match ($normalizedFloorType) {
            'ground' => self::GROUND_FLOOR_RATE_USD,
            'repeated' => self::REPEATED_FLOOR_RATE_USD,
            default => null,
        };

        return $rate === null ? null : round($area * $rate, 2);
    }
}
