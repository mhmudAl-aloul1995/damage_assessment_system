<?php

namespace App\services;

use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Support\CsoSurveyDamageStatusNormalizer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ArcgisCsoWebhookChangeSyncService
{
    private const LAYERS = [
        0 => [
            'model' => CsoSurvey::class,
            'table' => 'cso_surveys',
            'map' => [
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
                'organization_name' => 'organization_name',
                'organization_name_en' => 'organization_name',
                'organization_name_ar' => 'organization_name',
                'organization_acronym' => 'organization_name',
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
            ],
        ],
        1 => [
            'model' => CsoSurveyOrganization::class,
            'table' => 'cso_survey_organizations',
            'map' => [
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
            ],
        ],
        2 => [
            'model' => CsoSurveyUnit::class,
            'table' => 'cso_survey_units',
            'map' => [
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
            ],
        ],
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    public function sync(array $payload): array
    {
        $changesUrl = $this->changesUrl($payload);

        if ($changesUrl === null) {
            throw new RuntimeException('ArcGIS webhook payload does not include changesUrl.');
        }

        return $this->applyChanges($this->fetchChanges($changesUrl));
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchChanges(string $changesUrl): array
    {
        $token = app(ArcgisService::class)->getToken();
        $response = Http::asForm()
            ->timeout(120)
            ->withoutVerifying()
            ->post($changesUrl, [
                'f' => 'json',
                'token' => $token,
                'layers' => json_encode([0, 1, 2], JSON_THROW_ON_ERROR),
                'returnInserts' => 'true',
                'returnUpdates' => 'true',
                'returnDeletes' => 'true',
                'returnDeletedFeatures' => 'true',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('ArcGIS extractChanges failed: '.$response->body());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('ArcGIS extractChanges returned an invalid response.');
        }

        if (is_array($data['error'] ?? null)) {
            throw new RuntimeException('ArcGIS extractChanges error: '.($data['error']['message'] ?? $response->body()));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int>
     */
    private function applyChanges(array $data): array
    {
        $summary = [
            'upserted' => 0,
            'deleted' => 0,
            'skipped' => 0,
        ];

        foreach ($this->layerChanges($data) as $layerChange) {
            $layerId = (int) ($layerChange['id'] ?? $layerChange['layerId'] ?? -1);
            $config = self::LAYERS[$layerId] ?? null;

            if ($config === null) {
                continue;
            }

            foreach ($this->featuresToUpsert($layerChange) as $feature) {
                $this->upsertFeature($layerId, $config, $feature) ? $summary['upserted']++ : $summary['skipped']++;
            }

            foreach ($this->featuresToDelete($layerChange) as $identifier) {
                $this->deleteFeature($config, $identifier) ? $summary['deleted']++ : $summary['skipped']++;
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function layerChanges(array $data): array
    {
        foreach (['edits', 'layers'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values(array_filter($data[$key], static fn (mixed $item): bool => is_array($item)));
            }
        }

        return isset($data['id']) || isset($data['layerId']) ? [$data] : [];
    }

    /**
     * @param  array<string, mixed>  $layerChange
     * @return array<int, array<string, mixed>>
     */
    private function featuresToUpsert(array $layerChange): array
    {
        return $this->featureLists($layerChange, [
            'features',
            'adds',
            'inserts',
            'updates',
            'features.adds',
            'features.inserts',
            'features.updates',
            'features.features',
        ]);
    }

    /**
     * @param  array<string, mixed>  $layerChange
     * @return array<int, mixed>
     */
    private function featuresToDelete(array $layerChange): array
    {
        return $this->featureLists($layerChange, [
            'deletes',
            'deleteResults',
            'deletedFeatures',
            'features.deletes',
            'features.deleteResults',
            'features.deletedFeatures',
        ]);
    }

    /**
     * @param  array<string, mixed>  $layerChange
     * @param  array<int, string>  $paths
     * @return array<int, mixed>
     */
    private function featureLists(array $layerChange, array $paths): array
    {
        $features = [];

        foreach ($paths as $path) {
            $value = data_get($layerChange, $path);

            if (! is_array($value)) {
                continue;
            }

            if (! array_is_list($value)) {
                continue;
            }

            array_push($features, ...$value);
        }

        return $features;
    }

    /**
     * @param  array{model: class-string<Model>, table: string, map: array<string, string>}  $config
     */
    private function upsertFeature(int $layerId, array $config, mixed $feature): bool
    {
        if (! is_array($feature)) {
            return false;
        }

        $attributes = $feature['attributes'] ?? $feature;

        if (! is_array($attributes)) {
            return false;
        }

        $row = $this->mapAttributes($attributes, $config['map'], $config['table']);

        if ($layerId === 0 && isset($feature['geometry']) && is_array($feature['geometry'])) {
            $row['location'] = json_encode($feature['geometry'], JSON_UNESCAPED_UNICODE);
            $coords = $this->extractLatLngFromGeometry($feature['geometry']);
            $row['latitude'] = $row['latitude'] ?? $coords['latitude'];
            $row['longitude'] = $row['longitude'] ?? $coords['longitude'];
        }

        if ($config['table'] === 'cso_survey_units') {
            $row = $this->applyCsoUnitSurveyParentFallback($row);
        }

        $row['raw_payload'] = $attributes;
        $row['updated_at'] = now();

        if (Schema::hasColumn($config['table'], 'arcgis_synced_at')) {
            $row['arcgis_synced_at'] = now();
        }

        if (Schema::hasColumn($config['table'], 'arcgis_hash')) {
            $row['arcgis_hash'] = $this->makeHash($row);
        }

        $lookup = $this->lookup($row);

        if ($lookup === null) {
            return false;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $model = $modelClass::query()->firstOrNew($lookup);

        if (! $model->exists && Schema::hasColumn($config['table'], 'created_at')) {
            $row['created_at'] = now();
        }

        $model->fill($row)->save();

        return true;
    }

    /**
     * @param  array{model: class-string<Model>, table: string, map: array<string, string>}  $config
     */
    private function deleteFeature(array $config, mixed $feature): bool
    {
        $identifier = $this->deleteIdentifier($feature);

        if ($identifier === null) {
            return false;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return (bool) $modelClass::query()
            ->when(isset($identifier['objectid']), fn ($query) => $query->where('objectid', $identifier['objectid']))
            ->when(isset($identifier['globalid']), fn ($query) => $query->where('globalid', $identifier['globalid']))
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $fieldMap
     * @return array<string, mixed>
     */
    private function mapAttributes(array $attributes, array $fieldMap, string $table): array
    {
        $row = [];

        foreach ($fieldMap as $sourceKey => $targetKey) {
            $value = $this->attributeValue($attributes, $sourceKey);

            if ($value === null) {
                continue;
            }

            if (in_array($targetKey, ['globalid', 'parentglobalid'], true)) {
                $row[$targetKey] = $this->normalizeGlobalId($value);

                continue;
            }

            if ($table === 'cso_surveys' && $targetKey === 'building_damage_status') {
                $row[$targetKey] = CsoSurveyDamageStatusNormalizer::normalize($value);

                continue;
            }

            $row[$targetKey] = in_array($targetKey, ['creationdate', 'editdate', 'damage_date'], true)
                ? $this->normalizeDate($value)
                : $value;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function attributeValue(array $attributes, string $key): mixed
    {
        if (array_key_exists($key, $attributes)) {
            return $attributes[$key];
        }

        $lowerKey = strtolower($key);

        foreach ($attributes as $attributeKey => $value) {
            if (strtolower((string) $attributeKey) === $lowerKey) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function lookup(array $row): ?array
    {
        if (filled($row['objectid'] ?? null)) {
            return ['objectid' => $row['objectid']];
        }

        if (filled($row['globalid'] ?? null)) {
            return ['globalid' => $row['globalid']];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function deleteIdentifier(mixed $feature): ?array
    {
        if (is_numeric($feature)) {
            return ['objectid' => (int) $feature];
        }

        if (! is_array($feature)) {
            return null;
        }

        $attributes = $feature['attributes'] ?? $feature;

        if (! is_array($attributes)) {
            return null;
        }

        $objectId = $this->attributeValue($attributes, 'objectid')
            ?? $this->attributeValue($attributes, 'objectId')
            ?? $this->attributeValue($attributes, 'OBJECTID');
        $globalId = $this->attributeValue($attributes, 'globalid')
            ?? $this->attributeValue($attributes, 'globalId')
            ?? $this->attributeValue($attributes, 'GLOBALID');

        if (filled($objectId)) {
            return ['objectid' => $objectId];
        }

        if (filled($globalId)) {
            return ['globalid' => $this->normalizeGlobalId($globalId)];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function changesUrl(array $payload): ?string
    {
        $changesUrl = $payload['changesUrl']
            ?? $payload['changesURL']
            ?? $payload['changes_url']
            ?? null;

        if (! is_string($changesUrl) || trim($changesUrl) === '') {
            return null;
        }

        return urldecode($changesUrl);
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

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyCsoUnitSurveyParentFallback(array $row): array
    {
        if (blank($row['parentglobalid'] ?? null)) {
            return $row;
        }

        if (CsoSurvey::query()->where('globalid', $row['parentglobalid'])->exists()) {
            return $row;
        }

        $surveyGlobalId = CsoSurveyOrganization::query()
            ->where('globalid', $row['parentglobalid'])
            ->value('parentglobalid');

        if (filled($surveyGlobalId)) {
            $row['parentglobalid'] = $this->normalizeGlobalId($surveyGlobalId);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function extractLatLngFromGeometry(array $geometry): array
    {
        if (isset($geometry['x'], $geometry['y'])) {
            return [
                'latitude' => (float) $geometry['y'],
                'longitude' => (float) $geometry['x'],
            ];
        }

        if (empty($geometry['rings'][0]) || ! is_array($geometry['rings'][0])) {
            return [
                'latitude' => null,
                'longitude' => null,
            ];
        }

        $lngs = [];
        $lats = [];

        foreach ($geometry['rings'][0] as $point) {
            if (is_array($point) && isset($point[0], $point[1])) {
                $lngs[] = (float) $point[0];
                $lats[] = (float) $point[1];
            }
        }

        return [
            'latitude' => $lats === [] ? null : array_sum($lats) / count($lats),
            'longitude' => $lngs === [] ? null : array_sum($lngs) / count($lngs),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function makeHash(array $row): string
    {
        unset($row['created_at'], $row['updated_at'], $row['arcgis_hash'], $row['arcgis_synced_at']);
        ksort($row);

        return hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE));
    }
}
