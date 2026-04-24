<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            ],
            'housing_units' => [
                'table' => 'housing_units',
                'url' => config('services.arcgis.housing_units_url', env('ARCGIS_HOUSING_UNITS_URL')),
                'unique' => 'objectid',
                'referer' => config('app.url'),
            ],
            'public_building_surveys' => [
                'table' => 'public_building_surveys',
                'url' => config('services.arcgis.public_building_survey_layer_url'),
                'unique' => 'objectid',
                'returnGeometry' => true,
                'where' => '1=1',
                'referer' => config('services.arcgis.public_building_survey_referer'),
            ],
            'road_facility_surveys' => [
                'table' => 'road_facility_surveys',
                'url' => config('services.arcgis.road_facility_survey_layer_url', env('ARCGIS_ROAD_FACILITIES_URL')),
                'unique' => 'objectid',
                'referer' => config('services.arcgis.road_facility_survey_referer', config('app.url')),
            ],
        ];

        $tableOnly = $this->argument('table');

        if ($tableOnly) {
            if (! isset($layers[$tableOnly])) {
                $this->error("Table '{$tableOnly}' not found in sync config.");
                $this->info('Available tables: '.implode(', ', array_keys($layers)));

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
        $table = $config['table'];
        $unique = $config['unique'];
        $url = $config['url'] ?? null;

        if (empty($url)) {
            $this->error("Missing ArcGIS URL for {$name}. Check .env/services.php");

            return;
        }

        if (! Schema::hasTable($table)) {
            $this->error("Table not found: {$table}");

            return;
        }

        $referer = $this->resolveReferer($config, $url);
        $token = $this->getArcgisToken($referer);

        if (! $token) {
            $this->error("Could not retrieve ArcGIS token for {$name}.");

            return;
        }

        $serviceUrl = $this->normalizeQueryUrl($url);

        $tableColumns = Schema::getColumnListing($table);

        $ignoredColumns = [
            'id',
            'created_at',
            'updated_at',
            'arcgis_hash',
            'arcgis_synced_at',
        ];
        $hasArcgisHashColumn = in_array('arcgis_hash', $tableColumns, true);
        $hasArcgisSyncedAtColumn = in_array('arcgis_synced_at', $tableColumns, true);

        $syncColumns = collect($tableColumns)
            ->reject(fn ($col) => in_array($col, $ignoredColumns, true))
            ->values()
            ->toArray();

        $offset = 0;
        $limit = (int) $this->option('chunk');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $this->newLine();
        $this->info("Syncing {$name}...");
        $this->line("Using referer: {$referer}");

        while (true) {
            $this->line("Fetching {$name} offset: {$offset}");

            $response = Http::timeout(120)->get($serviceUrl, [
                'where' => $config['where'] ?? '1=1',
                'outFields' => '*',
                'f' => 'json',
                'token' => $token,
                'resultOffset' => $offset,
                'resultRecordCount' => $limit,
                'orderByFields' => 'objectid ASC',
                'returnGeometry' => $this->normalizeBooleanQueryValue($config['returnGeometry'] ?? false),
            ]);

            if (! $response->successful()) {
                $this->error("ArcGIS query failed for {$name}: ".$response->body());

                return;
            }

            $data = $response->json();

            if (isset($data['error'])) {
                $message = $data['error']['message'] ?? '';
                $details = $data['error']['details'] ?? [];
                $detailsText = is_array($details) ? implode(' | ', array_filter($details)) : '';
                $errorText = trim($message !== '' ? $message : $detailsText);

                $this->error("ArcGIS Query Error in {$name}: ".($errorText !== '' ? $errorText : 'Unknown error'));

                return;
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
                }

                $objectId = $arcgisMap[strtolower($unique)] ?? null;

                if (! $objectId) {
                    continue;
                }

                $row = [];

                foreach ($syncColumns as $column) {
                    $key = strtolower($column);

                    if (array_key_exists($key, $arcgisMap)) {
                        $row[$column] = $this->normalizeValue($arcgisMap[$key], $column, $table);
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

                if (! $existing) {
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

            if (! ($data['exceededTransferLimit'] ?? false)) {
                break;
            }
        }

        $this->info("{$name} done.");
        $this->info("Inserted: {$inserted}");
        $this->info("Updated : {$updated}");
        $this->info("Skipped : {$skipped}");
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
            $this->error('ArcGIS Token Error: '.($data['error']['message'] ?? 'Unknown error'));

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
            return $url.'/0/query';
        }

        if (preg_match('#/featureserver/\d+$#i', $url)) {
            return $url.'/query';
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
            is_numeric($value) &&
            (
                str_contains($column, 'date')
                || str_contains($column, 'time')
                || in_array($column, ['today', 'start', 'end', 'editdate', 'creationdate'], true)
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

            $items = array_values(array_filter(array_map('trim', explode(',', $trimmedValue)), static fn ($item) => $item !== ''));

            return json_encode($items === [] ? [$trimmedValue] : $items, JSON_UNESCAPED_UNICODE);
        }

        return json_encode([$value], JSON_UNESCAPED_UNICODE);
    }

    private function normalizeBooleanQueryValue(bool|string|int|null $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 'true' : 'false';
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
            return $normalizedUrl.'/0';
        }

        return (string) config('app.url');
    }
}
