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

    public function handle()
    {
        $token = $this->getArcgisToken();

        if (! $token) {
            $this->error('Could not retrieve ArcGIS token.');
            return self::FAILURE;
        }

        $layers = [
            'buildings' => [
                'table' => 'buildings',
                'url' => env('ARCGIS_BUILDINGS_URL'),
                'unique' => 'objectid',
            ],

            'housing_units' => [
                'table' => 'housing_units',
                'url' => env('ARCGIS_HOUSING_UNITS_URL'),
                'unique' => 'objectid',
            ],

            'public_building' => [
                'table' => 'public_building_survays',
                'url' => env('ARCGIS_PUBLIC_BUILDINGS_URL'),
                'unique' => 'objectid',
            ],

            'road_facilites' => [
                'table' => 'road_facility_surveys',
                'url' => env('ARCGIS_ROAD_FACILITIES_URL'),
                'unique' => 'objectid',
            ],
        ];

        $tableOnly = $this->argument('table');

        if ($tableOnly) {
            if (! isset($layers[$tableOnly])) {
                $this->error("Table '{$tableOnly}' not found in sync config.");
                $this->info('Available tables: '.implode(', ', array_keys($layers)));
                return self::FAILURE;
            }

            $this->syncLayer($tableOnly, $layers[$tableOnly], $token);
        } else {
            foreach ($layers as $name => $config) {
                $this->syncLayer($name, $config, $token);
            }
        }

        $this->info('Sync finished.');

        return self::SUCCESS;
    }

    private function syncLayer(string $name, array $config, string $token): void
    {
        $table = $config['table'];
        $unique = $config['unique'];
        $url = $config['url'] ?? null;

        if (empty($url)) {
            $this->error("Missing ArcGIS URL for {$name}. Check .env");
            return;
        }

        if (! Schema::hasTable($table)) {
            $this->error("Table not found: {$table}");
            return;
        }

        $serviceUrl = rtrim($url, '/') . '/query';

        $tableColumns = Schema::getColumnListing($table);

        $ignoredColumns = [
            'id',
            'created_at',
            'updated_at',
            'arcgis_hash',
            'arcgis_synced_at',
        ];

        $syncColumns = collect($tableColumns)
            ->reject(fn ($col) => in_array($col, $ignoredColumns))
            ->values()
            ->toArray();

        $offset = 0;
        $limit = (int) $this->option('chunk');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $this->newLine();
        $this->info("Syncing {$name}...");

        while (true) {
            $this->line("Fetching {$name} offset: {$offset}");

            $response = Http::timeout(120)->get($serviceUrl, [
                'where' => '1=1',
                'outFields' => '*',
                'f' => 'json',
                'token' => $token,
                'resultOffset' => $offset,
                'resultRecordCount' => $limit,
                'orderByFields' => 'objectid ASC',
                'returnGeometry' => 'false',
            ]);

            $data = $response->json();

            if (isset($data['error'])) {
                $this->error("ArcGIS Query Error in {$name}: ".($data['error']['message'] ?? 'Unknown error'));
                return;
            }

            $features = $data['features'] ?? [];

            if (empty($features)) {
                break;
            }

            foreach ($features as $feature) {
                $attributes = $feature['attributes'] ?? [];

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
                        $row[$column] = $this->normalizeValue($arcgisMap[$key], $column);
                    }
                }

                $row[$unique] = $objectId;

                if (in_array('all_data', $tableColumns)) {
                    $row['all_data'] = json_encode($attributes, JSON_UNESCAPED_UNICODE);
                }

                $newHash = $this->makeHash($row);

                $existing = DB::table($table)
                    ->where($unique, $objectId)
                    ->first();

                if (! $existing) {
                    $row['arcgis_hash'] = $newHash;
                    $row['arcgis_synced_at'] = now();

                    if (in_array('created_at', $tableColumns)) {
                        $row['created_at'] = now();
                    }

                    if (in_array('updated_at', $tableColumns)) {
                        $row['updated_at'] = now();
                    }

                    DB::table($table)->insert($row);

                    $inserted++;
                    continue;
                }

                if (($existing->arcgis_hash ?? null) === $newHash) {
                    $skipped++;
                    continue;
                }

                $row['arcgis_hash'] = $newHash;
                $row['arcgis_synced_at'] = now();

                if (in_array('updated_at', $tableColumns)) {
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

    private function getArcgisToken(): ?string
    {
        $response = Http::asForm()
            ->timeout(60)
            ->post('https://www.arcgis.com/sharing/rest/generateToken', [
                'f' => 'json',
                'username' => env('ARCGIS_USERNAME'),
                'password' => env('ARCGIS_PASSWORD'),
                'client' => 'referer',
                'referer' => config('app.url'),
                'expiration' => 60,
            ]);

        $data = $response->json();

        if (isset($data['error'])) {
            $this->error('ArcGIS Token Error: '.($data['error']['message'] ?? 'Unknown error'));
            return null;
        }

        return $data['token'] ?? null;
    }

    private function makeHash(array $row): string
    {
        unset(
            $row['id'],
            $row['created_at'],
            $row['updated_at'],
            $row['arcgis_hash'],
            $row['arcgis_synced_at'],
            $row['all_data']
        );

        ksort($row);

        return hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE));
    }

    private function normalizeValue($value, string $column)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        $column = strtolower($column);

        if (
            is_numeric($value) &&
            (
                str_contains($column, 'date') ||
                str_contains($column, 'time') ||
                in_array($column, ['today', 'start', 'end', 'editdate', 'creationdate'])
            )
        ) {
            return date('Y-m-d H:i:s', intval($value / 1000));
        }

        return $value;
    }
}