<?php

declare(strict_types=1);

namespace App\services;

use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
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

    private const ORGANIZATION_FIELD_MAP = [
        'objectid' => 'objectid',
        'globalid' => 'globalid',
        'parentglobalid' => 'parentglobalid',
        'organization_name_en' => 'organization_name_en',
        'organization_name_ar' => 'organization_name_ar',
        'organization_acronym' => 'organization_acronym',
        'operational_status' => 'operational_status',
        'CreationDate' => 'creationdate',
        'creationdate' => 'creationdate',
        'Creator' => 'creator',
        'creator' => 'creator',
        'EditDate' => 'editdate',
        'editdate' => 'editdate',
        'Editor' => 'editor',
        'editor' => 'editor',
    ];

    private const UNIT_FIELD_MAP = [
        'objectid' => 'objectid',
        'globalid' => 'globalid',
        'parentglobalid' => 'parentglobalid',
        'unit_name' => 'unit_name',
        'unit_floor_number' => 'unit_floor_number',
        'unit_number' => 'unit_number',
        'unit_damage_status' => 'unit_damage_status',
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

            $survey = CsoSurvey::query()
                ->updateOrCreate($lookup, $attributes)
                ->fresh();

            $this->importChildren(
                survey: $survey,
                payload: $payload,
                keys: ['CSO_Organizations', 'cso_organizations', 'organizations'],
                modelClass: CsoSurveyOrganization::class,
                fieldMap: self::ORGANIZATION_FIELD_MAP,
            );

            $this->importChildren(
                survey: $survey,
                payload: $payload,
                keys: ['Unit_Information', 'unit_information', 'units'],
                modelClass: CsoSurveyUnit::class,
                fieldMap: self::UNIT_FIELD_MAP,
            );

            return $survey->fresh(['organizations', 'units']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mapPayload(array $payload, array $fieldMap = self::FIELD_MAP): array
    {
        $mapped = [];

        foreach ($fieldMap as $sourceKey => $targetKey) {
            $value = $this->payloadValue($payload, $sourceKey);

            if ($value === null) {
                continue;
            }

            if (in_array($targetKey, ['globalid', 'parentglobalid'], true)) {
                $mapped[$targetKey] = $this->normalizeGlobalId($value);

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
        foreach (['organization_name_en', 'organization_name_ar', 'organization_acronym'] as $key) {
            $value = $this->payloadValue($payload, $key);

            if (! is_array($value) && filled($value)) {
                return (string) $value;
            }
        }

        foreach ($this->nestedItems($payload, ['CSO_Organizations', 'cso_organizations', 'organizations']) as $organization) {
            foreach (['organization_name_en', 'organization_name_ar', 'organization_acronym'] as $key) {
                $value = $this->payloadValue($organization, $key);

                if (! is_array($value) && filled($value)) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<string, string>  $fieldMap
     */
    private function importChildren(CsoSurvey $survey, array $payload, array $keys, string $modelClass, array $fieldMap): void
    {
        $items = $this->nestedItems($payload, $keys);

        if ($items === []) {
            return;
        }

        $parentGlobalId = $survey->globalid;
        $seenObjectIds = [];

        foreach ($items as $index => $item) {
            $attributes = $this->mapPayload($item, $fieldMap);
            $attributes['parentglobalid'] = $attributes['parentglobalid'] ?? $parentGlobalId;
            $attributes['repeat_index'] = $index;
            $attributes['raw_payload'] = $item;

            $lookup = filled($attributes['objectid'] ?? null)
                ? ['objectid' => $attributes['objectid']]
                : ['globalid' => $attributes['globalid'] ?? Arr::get($item, 'globalid')];

            if (! filled($lookup[array_key_first($lookup)] ?? null)) {
                $lookup = [
                    'parentglobalid' => $attributes['parentglobalid'],
                    'repeat_index' => $index,
                ];
            }

            $model = $modelClass::query()->updateOrCreate($lookup, $attributes);

            if (filled($model->objectid)) {
                $seenObjectIds[] = $model->objectid;
            }
        }

        if ($parentGlobalId && $seenObjectIds !== []) {
            $modelClass::query()
                ->where('parentglobalid', $parentGlobalId)
                ->whereNotIn('objectid', $seenObjectIds)
                ->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function nestedItems(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $this->payloadValue($payload, $key);

            if (! is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
            }

            return [$value];
        }

        return [];
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

    private function normalizeGlobalId(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $globalId = trim((string) $value);

        if ($globalId === '') {
            return null;
        }

        return strtolower(trim($globalId, '{}'));
    }
}
