<?php

declare(strict_types=1);

namespace App\services;

use App\Models\CsoSurvey;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CsoSurveyImporter
{
    private const FIELD_MAP = [
        'objectid' => 'objectid',
        'globalid' => 'globalid',
        'location' => 'location',
        'field_status' => 'field_status',
        'assignedto' => 'assignedto',
        'governorate' => 'governorate',
        'municipalitie' => 'municipalitie',
        'neighborhood' => 'neighborhood',
        'longitude' => 'longitude',
        'latitude' => 'latitude',
        'building_name' => 'building_name',
        'building_damage_status' => 'building_damage_status',
        'operational_status' => 'operational_status',
        'damage_date' => 'damage_date',
        'CreationDate' => 'creationdate',
        'creationdate' => 'creationdate',
        'Creator' => 'creator',
        'creator' => 'creator',
        'EditDate' => 'editdate',
        'editdate' => 'editdate',
        'Editor' => 'editor',
        'editor' => 'editor',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function import(array $payload): CsoSurvey
    {
        return DB::transaction(function () use ($payload): CsoSurvey {
            $attributes = $this->mapPayload($payload);
            $attributes['organization_name'] = $this->organizationName($payload);
            $attributes['raw_payload'] = $payload;

            $lookup = filled($attributes['objectid'] ?? null)
                ? ['objectid' => $attributes['objectid']]
                : ['globalid' => $attributes['globalid'] ?? Arr::get($payload, 'globalid')];

            return CsoSurvey::query()
                ->updateOrCreate($lookup, $attributes)
                ->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mapPayload(array $payload): array
    {
        $mapped = [];

        foreach (self::FIELD_MAP as $sourceKey => $targetKey) {
            $value = $this->payloadValue($payload, $sourceKey);

            if ($value === null) {
                continue;
            }

            $mapped[$targetKey] = in_array($targetKey, ['creationdate', 'editdate', 'damage_date'], true)
                ? $this->normalizeDate($value)
                : $value;
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function organizationName(array $payload): ?string
    {
        foreach (['organization_name_en', 'organization_name_ar', 'organization_acronym', 'CSO_Organizations'] as $key) {
            $value = $this->payloadValue($payload, $key);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadValue(array $payload, string $key): mixed
    {
        if (array_key_exists($key, $payload)) {
            return $payload[$key];
        }

        $lowerKey = strtolower($key);

        foreach ($payload as $payloadKey => $value) {
            if (strtolower((string) $payloadKey) === $lowerKey) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeDate(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;

            if ($timestamp > 100000000000) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return now()->setTimestamp($timestamp);
        }

        return $value;
    }
}
