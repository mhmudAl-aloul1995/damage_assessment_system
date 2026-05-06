<?php

namespace App\Console\Commands;

use App\Models\SystemOperationLog;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncArcGISLayers extends Command
{
    protected $signature = 'sync:arcgis-layers {table?} {--chunk=1000}';

    protected $description = 'Sync ArcGIS layers';

    public function handle(): int
    {
        $layers = [
            'buildings' => [
                'table' => 'buildings',
                'url' => config('services.arcgis.buildings_url', env('ARCGIS_BUILDINGS_URL')),
                'unique' => 'objectid',
                'referer' => config('app.url'),
                'returnGeometry' => true,
                'outSR' => 4326,
            ],
            'housing_units' => [
                'table' => 'housing_units',
                'url' => config('services.arcgis.housing_units_url', env('ARCGIS_HOUSING_UNITS_URL')),
                'unique' => 'objectid',
                'referer' => config('app.url'),
            ],

            'public_building_survey_units' => [
                'table' => 'public_building_survey_units',
                'url' => config(
                    'services.arcgis.public_building_survey_units_layer_url',
                    'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_409593086b6249549601f0f8c6a3007a/FeatureServer/1'
                ),
                'unique' => 'objectid',
                'map' => [
                    'parentglobalid' => 'parentglobalid',
                ],
            ],
            'road_facility_surveys' => [
                'table' => 'road_facility_surveys',
                'url' => config(
                    'services.arcgis.road_facility_survey_layer_url',
                    env(
                        'ARCGIS_ROAD_FACILITY_SURVEY_LAYER_URL',
                        'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_8d4df706500f47a8864206fd1b251739_form/FeatureServer/0'
                    )
                ),
                'unique' => 'objectid',
                'map' => [],
            ],
            'road_facility_survey_items' => [
                'table' => 'road_facility_survey_items',
                'url' => 'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_8d4df706500f47a8864206fd1b251739_form/FeatureServer/1',
                'unique' => 'objectid',
                'map' => [
                    'unit_001' => 'unit',
                    'quantity_001' => 'quantity',
                    'parentglobalid' => 'parentglobalid',
                ],
            ],
            'public_building_surveys' => [
                'table' => 'public_building_surveys',
                'url' => config(
                    'services.arcgis.public_building_survey_layer_url',
                    'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_409593086b6249549601f0f8c6a3007a/FeatureServer/0'
                ),
                'unique' => 'objectid',
                'map' => [],
            ],
        ];

        $tableOnly = $this->argument('table');

        if ($tableOnly) {
            if (!isset($layers[$tableOnly])) {
                $this->error("Table '{$tableOnly}' not found in sync config.");
                $this->info('Available tables: ' . implode(', ', array_keys($layers)));

                return self::FAILURE;
            }

            $this->syncLayer($tableOnly, $layers[$tableOnly]);
        } else {
            foreach ($layers as $name => $config) {
                $this->syncLayer($name, $config);
            }
        }

        $this->info('Sync finished.');

        return self::SUCCESS;
    }

    private function syncLayer(string $name, array $config): void
    {
        $startedAt = now();

        $log = SystemOperationLog::create([
            'operation_type' => 'sync_layer',
            'status' => 'processing',
            'layer_name' => $name,
            'started_at' => $startedAt,
            'message' => "Sync started for {$name}.",
        ]);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $deleted = 0;
        $arcgisObjectIds = [];

        try {
            $table = $config['table'];
            $unique = $config['unique'];
            $url = $config['url'] ?? null;

            if (empty($url)) {
                throw new \RuntimeException("Missing ArcGIS URL for {$name}. Check .env/services.php");
            }

            if (!Schema::hasTable($table)) {
                throw new \RuntimeException("Table not found: {$table}");
            }

            $referer = $this->resolveReferer($config, $url);
            $token = $this->getArcgisToken($referer);

            if (!$token) {
                throw new \RuntimeException("Could not retrieve ArcGIS token for {$name}.");
            }

            $serviceUrl = $this->normalizeQueryUrl($url);
            $this->syncSchemaFromArcgisMetadata($table, $url, $token);

            $tableColumns = Schema::getColumnListing($table);
            $tableColumns = $this->ensureBaseSyncColumns($table, $tableColumns);

            $ignoredColumns = [
                'id',
                'created_at',
                'updated_at',
                'arcgis_hash',
                'arcgis_synced_at',
            ];

            $hasArcgisHashColumn = in_array('arcgis_hash', $tableColumns, true);
            $hasArcgisSyncedAtColumn = in_array('arcgis_synced_at', $tableColumns, true);

            $syncColumns = $this->syncColumns($tableColumns, $ignoredColumns);

            $offset = 0;
            $limit = (int) $this->option('chunk');

            $this->newLine();
            $this->info("Syncing {$name}...");
            $this->line("Using referer: {$referer}");

            while (true) {
                $this->line("Fetching {$name} offset: {$offset}");

                $returnGeometry = $this->normalizeBooleanQueryValue($config['returnGeometry'] ?? false);
                $queryParams = [
                    'where' => $config['where'] ?? '1=1',
                    'outFields' => '*',
                    'f' => 'json',
                    'token' => $token,
                    'resultOffset' => $offset,
                    'resultRecordCount' => $limit,
                    'orderByFields' => 'objectid ASC',
                    'returnGeometry' => $returnGeometry,
                ];

                if ($returnGeometry === 'true') {
                    $queryParams['outSR'] = $config['outSR'] ?? 4326;
                }

                $response = Http::timeout(120)->get($serviceUrl, $queryParams);

                if (!$response->successful()) {
                    throw new \RuntimeException("ArcGIS query failed for {$name}: " . $response->body());
                }

                $data = $response->json();

                if (isset($data['error'])) {
                    $message = $data['error']['message'] ?? '';
                    $details = $data['error']['details'] ?? [];
                    $detailsText = is_array($details) ? implode(' | ', array_filter($details)) : '';
                    $errorText = trim($message !== '' ? $message : $detailsText);

                    throw new \RuntimeException(
                        "ArcGIS Query Error in {$name}: " . ($errorText !== '' ? $errorText : 'Unknown error')
                    );
                }

                $features = $data['features'] ?? [];

                if (empty($features)) {
                    break;
                }

                foreach ($features as $feature) {
                    $attributes = $feature['attributes'] ?? [];

                    if (
                        in_array('location', $tableColumns, true)
                        && ($attributes['location'] ?? null) === null
                        && isset($feature['geometry'])
                    ) {
                        $attributes['location'] = json_encode($feature['geometry'], JSON_UNESCAPED_UNICODE);
                    }

                    $arcgisMap = [];

                    foreach ($attributes as $key => $value) {
                        $arcgisMap[strtolower($key)] = $value;
                        $arcgisMap[$this->normalizeArcgisColumnName((string) $key)] = $value;
                    }

                    $objectId = $arcgisMap[strtolower($unique)] ?? null;

                    if (!$objectId) {
                        continue;
                    }
                    $arcgisObjectIds[] = $objectId;
                    $row = [];

                    foreach ($syncColumns as $column) {
                        $key = strtolower($column);

                        if (array_key_exists($key, $arcgisMap)) {
                            $row[$column] = $this->normalizeValue($arcgisMap[$key], $column, $table);
                        }
                    }

                    foreach ($config['map'] ?? [] as $targetColumn => $sourceColumn) {
                        $targetColumn = $this->normalizeArcgisColumnName((string) $targetColumn);
                        $sourceColumn = $this->normalizeArcgisColumnName((string) $sourceColumn);

                        if (
                            in_array($targetColumn, $syncColumns, true)
                            && array_key_exists($sourceColumn, $arcgisMap)
                        ) {
                            $row[$targetColumn] = $this->normalizeValue($arcgisMap[$sourceColumn], $targetColumn, $table);
                        }
                    }

                    if ($table === 'buildings') {
                        $coords = $this->extractLatLngFromGeometry($feature['geometry'] ?? null);

                        if (in_array('latitude', $tableColumns, true)) {
                            $row['latitude'] = $coords['latitude'];
                        }

                        if (in_array('longitude', $tableColumns, true)) {
                            $row['longitude'] = $coords['longitude'];
                        }
                    }

                    if ($table === 'housing_units') {
                        if (
                            in_array('unit_owner', $tableColumns, true)
                            && empty($row['unit_owner'])
                        ) {
                            $row['unit_owner'] = trim(implode(' ', array_filter([
                                $row['q_9_3_1_first_name'] ?? null,
                                $row['q_9_3_2_second_name__father'] ?? null,
                                $row['q_9_3_3_third_name__grandfather'] ?? null,
                                $row['q_9_3_4_last_name'] ?? null,
                            ]))) ?: null;
                        }

                        if (
                            in_array('housing_unit', $tableColumns, true)
                            && empty($row['housing_unit'])
                        ) {
                            $buildingAssigneto = DB::table('buildings')
                                ->where('objectid', $row['building_id'] ?? null)
                                ->value('assigneto');

                            if (!empty($buildingAssigneto)) {
                                $row['housing_unit'] = $buildingAssigneto;
                            }
                        }
                    }

                    // Handle _v1 fallback columns: if main column is null, use _v1 value
                    foreach ($row as $column => $value) {
                        if (($value === null || $value === '') && !str_ends_with($column, '_v1')) {
                            $v1Key = strtolower($column . '_v1');
                            if (array_key_exists($v1Key, $arcgisMap)) {
                                $v1Value = $this->normalizeValue($arcgisMap[$v1Key], $column . '_v1', $table);
                                if ($v1Value !== null && $v1Value !== '') {
                                    $row[$column] = $v1Value;
                                }
                            }
                        }
                    }

                    $row[$unique] = $objectId;

                    if (in_array('all_data', $tableColumns, true)) {
                        $row['all_data'] = json_encode($attributes, JSON_UNESCAPED_UNICODE);
                    }

                    $newHash = $this->makeHash($row);

                    $existing = DB::table($table)
                        ->where($unique, $objectId)
                        ->first();

                    if (!$existing) {
                        if ($hasArcgisHashColumn) {
                            $row['arcgis_hash'] = $newHash;
                        }

                        if ($hasArcgisSyncedAtColumn) {
                            $row['arcgis_synced_at'] = now();
                        }

                        if (in_array('created_at', $tableColumns, true)) {
                            $row['created_at'] = now();
                        }

                        if (in_array('updated_at', $tableColumns, true)) {
                            $row['updated_at'] = now();
                        }

                        DB::table($table)->insert($row);
                        $inserted++;

                        continue;
                    }

                    if ($hasArcgisHashColumn && ($existing->arcgis_hash ?? null) === $newHash) {
                        $skipped++;

                        continue;
                    }

                    if ($hasArcgisHashColumn) {
                        $row['arcgis_hash'] = $newHash;
                    }

                    if ($hasArcgisSyncedAtColumn) {
                        $row['arcgis_synced_at'] = now();
                    }

                    if (in_array('updated_at', $tableColumns, true)) {
                        $row['updated_at'] = now();
                    }

                    DB::table($table)
                        ->where($unique, $objectId)
                        ->update($row);

                    $updated++;
                }

                $offset += $limit;

                if (!($data['exceededTransferLimit'] ?? false)) {
                    break;
                }
            }

            $deleted = $this->deleteMissingArcgisRows(
                table: $table,
                unique: $unique,
                arcgisIds: $arcgisObjectIds
            );
            $finishedAt = now();
            $duration = $finishedAt->diffInSeconds($startedAt);

            $totalRecords = $inserted + $updated + $skipped;
            $speed = $totalRecords > 0 ? round($totalRecords / max($duration, 1), 2) : 0;
            /**x */
            $log->update([
                'status' => 'success',
                'finished_at' => $finishedAt,
                'total_records' => $totalRecords,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'duration_seconds' => $speed,
                'message' => "Inserted: {$inserted} | Updated: {$updated} | Skipped: {$skipped} | Deleted: {$deleted} | Speed: {$speed}/s | Duration: {$duration}s",
            ]);

            $this->info("{$name} done.");
            $this->info("Inserted: {$inserted}");
            $this->info("Updated : {$updated}");
            $this->info("Skipped : {$skipped}");
            $this->info("Deleted : {$deleted}");
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'finished_at' => now(),
                'total_records' => $inserted + $updated + $skipped,
                'message' => $exception->getMessage(),
            ]);

            $this->error($exception->getMessage());
        }
    }

    private function getArcgisToken(string $referer): ?string
    {
        $username = config('services.arcgis.username');
        $password = config('services.arcgis.password');

        $response = Http::asForm()
            ->timeout(60)
            ->post('https://www.arcgis.com/sharing/rest/generateToken', [
                'f' => 'json',
                'username' => $username,
                'password' => $password,
                'client' => 'referer',
                'referer' => $referer,
                'expiration' => 60,
            ]);

        $data = $response->json();

        if (isset($data['error'])) {
            $this->error('ArcGIS Token Error: ' . ($data['error']['message'] ?? 'Unknown error'));

            return null;
        }

        return $data['token'] ?? null;
    }

    private function normalizeQueryUrl(string $url): string
    {
        $url = rtrim($url, '/');

        if (str_ends_with(strtolower($url), '/query')) {
            return $url;
        }

        if (preg_match('#/featureserver$#i', $url)) {
            return $url . '/0/query';
        }

        if (preg_match('#/featureserver/\d+$#i', $url)) {
            return $url . '/query';
        }

        return $url;
    }

    private function makeHash(array $row): string
    {
        unset(
            $row['id'],
            $row['created_at'],
            $row['updated_at'],
            $row['arcgis_hash'],
            $row['arcgis_synced_at'],
            $row['all_data'],
        );

        ksort($row);

        return hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE));
    }

    private function ensureBaseSyncColumns(string $table, array $tableColumns): array
    {
        if ($table !== 'buildings') {
            return $tableColumns;
        }

        $missingColumns = array_values(array_diff(['latitude', 'longitude'], $tableColumns));

        if ($missingColumns === []) {
            return $tableColumns;
        }

        Schema::table($table, function ($schema) use ($missingColumns): void {
            foreach ($missingColumns as $column) {
                $schema->double($column)->nullable();
            }
        });

        return Schema::getColumnListing($table);
    }

    private function syncSchemaFromArcgisMetadata(string $table, string $url, string $token): void
    {
        try {
            $metadataUrl = $this->normalizeLayerMetadataUrl($url);
            $response = Http::timeout(60)->get($metadataUrl, [
                'f' => 'json',
                'token' => $token,
            ]);

            if (!$response->successful()) {
                $message = "ArcGIS metadata request failed for {$table}: " . $response->body();
                $this->warn($message);
                Log::warning($message);

                return;
            }

            $metadata = $response->json();

            if (isset($metadata['error'])) {
                $message = "ArcGIS metadata error for {$table}: " . ($metadata['error']['message'] ?? 'Unknown error');
                $this->warn($message);
                Log::warning($message);

                return;
            }

            $fields = $metadata['fields'] ?? [];

            if (!is_array($fields) || $fields === []) {
                return;
            }

            $missingFields = collect($fields)
                ->filter(fn(array $field): bool => !$this->isSystemArcgisField((string) ($field['name'] ?? '')))
                ->mapWithKeys(function (array $field): array {
                    $column = $this->normalizeArcgisColumnName((string) ($field['name'] ?? ''));

                    return $column === '' ? [] : [$column => $field];
                })
                ->reject(fn(array $field, string $column): bool => Schema::hasColumn($table, $column))
                ->all();

            if ($missingFields === []) {
                return;
            }

            Schema::table($table, function (Blueprint $schema) use ($missingFields): void {
                foreach ($missingFields as $column => $field) {
                    $this->addArcgisMetadataColumn($schema, $column, $field);
                }
            });

            $this->line('Added missing metadata columns to ' . $table . ': ' . implode(', ', array_keys($missingFields)));
        } catch (\Throwable $exception) {
            $message = "Could not sync ArcGIS schema for {$table}: " . $exception->getMessage();
            $this->warn($message);
            Log::warning($message, ['exception' => $exception]);
        }
    }

    private function addArcgisMetadataColumn(Blueprint $schema, string $column, array $field): void
    {
        match ($this->laravelColumnTypeForArcgisField($field)) {
            'integer' => $schema->integer($column)->nullable(),
            'double' => $schema->double($column)->nullable(),
            'timestamp' => $schema->timestamp($column)->nullable(),
            'string' => $schema->string($column)->nullable(),
            default => $schema->text($column)->nullable(),
        };
    }

    private function laravelColumnTypeForArcgisField(array $field): string
    {
        $type = $field['type'] ?? null;

        if ($type === 'esriFieldTypeString') {
            return (int) ($field['length'] ?? 255) > 255 ? 'text' : 'string';
        }

        return match ($type) {
            'esriFieldTypeInteger', 'esriFieldTypeSmallInteger', 'esriFieldTypeOID' => 'integer',
            'esriFieldTypeDouble', 'esriFieldTypeSingle' => 'double',
            'esriFieldTypeDate' => 'timestamp',
            default => 'text',
        };
    }

    private function normalizeLayerMetadataUrl(string $url): string
    {
        $url = rtrim($url, '/');

        if (str_ends_with(strtolower($url), '/query')) {
            return preg_replace('#/query$#i', '', $url) ?: $url;
        }

        if (preg_match('#/featureserver$#i', $url)) {
            return $url . '/0';
        }

        return $url;
    }

    private function isSystemArcgisField(string $fieldName): bool
    {
        return in_array(strtolower($fieldName), [
            'objectid',
            'shape',
            'shape__area',
        ], true);
    }

    private function syncColumns(array $tableColumns, array $ignoredColumns): array
    {
        return collect($tableColumns)
            ->reject(fn($col) => in_array($col, $ignoredColumns, true))
            ->values()
            ->toArray();
    }

    private function shouldSkipDynamicColumn(string $column): bool
    {
        return $column === ''
            || in_array($column, ['id', 'created_at', 'updated_at', 'arcgis_hash', 'arcgis_synced_at'], true);
    }

    private function normalizeArcgisColumnName(string $name): string
    {
        $column = strtolower(trim($name));
        $column = preg_replace('/[^a-z0-9_]+/', '_', $column) ?: '';
        $column = trim($column, '_');

        if ($column === '') {
            return '';
        }

        if (preg_match('/^[0-9]/', $column)) {
            $column = 'field_' . $column;
        }

        return substr($column, 0, 60);
    }

    private function normalizeValue($value, string $column, string $table)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        $column = strtolower($column);

        if ($this->isJsonColumn($table, $column)) {
            return $this->normalizeJsonValue($value);
        }

        if (
            is_numeric($value)
            && (
                $this->isLikelyDateColumn($column)
            )
        ) {
            return date('Y-m-d H:i:s', intval($value / 1000));
        }

        return $value;
    }

    private function isJsonColumn(string $table, string $column): bool
    {
        $jsonColumns = [
            'public_building_surveys' => [
                'benef_type',
                'building_roof_type',
                'ground_floor_use',
            ],
            'road_facility_surveys' => [
                'blockage_reason',
                'road_type',
                'sidewalk_damage_type',
                'pole_type',
                'traffic_signs_type',
            ],
        ];

        return in_array($column, $jsonColumns[$table] ?? [], true);
    }

    private function isLikelyDateColumn(string $column): bool
    {
        $column = strtolower($column);

        return str_contains($column, 'date')
            || str_contains($column, 'time')
            || in_array($column, ['today', 'start', 'end', 'editdate', 'creationdate'], true);
    }

    private function normalizeJsonValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
        }

        if (is_string($value)) {
            $trimmedValue = trim($value);

            if ($trimmedValue === '') {
                return null;
            }

            $decodedValue = json_decode($trimmedValue, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decodedValue, JSON_UNESCAPED_UNICODE);
            }

            $items = array_values(array_filter(
                array_map('trim', explode(',', $trimmedValue)),
                static fn($item) => $item !== ''
            ));

            return json_encode($items === [] ? [$trimmedValue] : $items, JSON_UNESCAPED_UNICODE);
        }

        return json_encode([$value], JSON_UNESCAPED_UNICODE);
    }

    private function normalizeBooleanQueryValue(bool|string|int|null $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 'true' : 'false';
    }

    private function extractLatLngFromGeometry(?array $geometry): array
    {
        $latitude = null;
        $longitude = null;

        if (!$geometry) {
            return [
                'latitude' => null,
                'longitude' => null,
            ];
        }

        if (isset($geometry['x'], $geometry['y'])) {
            return [
                'latitude' => (float) $geometry['y'],
                'longitude' => (float) $geometry['x'],
            ];
        }

        if (!empty($geometry['rings'][0]) && is_array($geometry['rings'][0])) {
            $points = $geometry['rings'][0];

            $lngs = [];
            $lats = [];

            foreach ($points as $point) {
                if (is_array($point) && isset($point[0], $point[1])) {
                    $lngs[] = (float) $point[0];
                    $lats[] = (float) $point[1];
                }
            }

            if (count($lngs) > 0 && count($lats) > 0) {
                $longitude = array_sum($lngs) / count($lngs);
                $latitude = array_sum($lats) / count($lats);
            }
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function resolveReferer(array $config, string $url): string
    {
        $configuredReferer = $config['referer'] ?? null;

        if (is_string($configuredReferer) && trim($configuredReferer) !== '') {
            return trim($configuredReferer);
        }

        $normalizedUrl = rtrim($url, '/');

        if (preg_match('#/featureserver/\d+$#i', $normalizedUrl)) {
            return $normalizedUrl;
        }

        if (preg_match('#/featureserver$#i', $normalizedUrl)) {
            return $normalizedUrl . '/0';
        }

        return (string) config('app.url');
    }
    private function deleteMissingArcgisRows(string $table, string $unique, array $arcgisIds): int
    {
        $arcgisIds = array_values(array_unique(array_filter($arcgisIds)));

        if (empty($arcgisIds)) {
            $this->warn("Skip delete missing rows for {$table}: ArcGIS returned 0 records.");

            return 0;
        }

        $deleted = 0;

        DB::table($table)
            ->whereNotNull($unique)
            ->whereNotIn($unique, $arcgisIds)
            ->orderBy($unique)
            ->chunkById(500, function ($rows) use ($table, $unique, &$deleted) {
                $idsToDelete = $rows->pluck($unique)->filter()->values()->all();

                if (empty($idsToDelete)) {
                    return;
                }

                $count = DB::table($table)
                    ->whereIn($unique, $idsToDelete)
                    ->delete();

                $deleted += $count;
            }, $unique);

        return $deleted;
    }
}
