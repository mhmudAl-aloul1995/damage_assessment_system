<?php

namespace App\Console\Commands;

use App\Models\Building;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SyncArcGISBuildings extends Command
{
    protected $signature = 'sync:buildings';

    public function handle()
    {
        $response = Http::asForm()->post('https://www.arcgis.com/sharing/rest/generateToken', [
            'f' => 'json',
            'username' => env('ARCGIS_USERNAME'),
            'password' => env('ARCGIS_PASSWORD'),
            'client' => 'referer',
            'referer' => config('app.url'),
            'expiration' => 60,
        ]);

        $tokenData = $response->json();

        if (isset($tokenData['error'])) {
            $this->error('ArcGIS Token Error: '.$tokenData['error']['message']);
            return self::FAILURE;
        }

        $token = $tokenData['token'] ?? null;

        if (! $token) {
            $this->error('Could not retrieve token.');
            return self::FAILURE;
        }

        $serviceUrl = 'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/query';

        $tableColumns = Schema::getColumnListing('buildings');

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
        $limit = 1000;

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        while (true) {
            $this->info("Fetching records from offset: {$offset}");

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
                $this->error('ArcGIS Query Error: '.($data['error']['message'] ?? 'Unknown error'));
                return self::FAILURE;
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

                $objectId = $arcgisMap['objectid'] ?? null;

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

                $row['objectid'] = $objectId;

                if (in_array('all_data', $tableColumns)) {
                    $row['all_data'] = json_encode($attributes, JSON_UNESCAPED_UNICODE);
                }

                $newHash = $this->makeHash($row);

                $building = Building::where('objectid', $objectId)->first();

                if (! $building) {
                    $row['arcgis_hash'] = $newHash;
                    $row['arcgis_synced_at'] = now();

                    Building::create($row);
                    $inserted++;
                    continue;
                }

                if ($building->arcgis_hash === $newHash) {
                    $skipped++;
                    continue;
                }

                $row['arcgis_hash'] = $newHash;
                $row['arcgis_synced_at'] = now();

                $building->update($row);
                $updated++;
            }

            $offset += $limit;

            if (! ($data['exceededTransferLimit'] ?? false)) {
                break;
            }
        }

        $this->info("Inserted: {$inserted}");
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");
        $this->info('Sync completed successfully! '.now());

        return self::SUCCESS;
    }

    private function makeHash(array $row): string
    {
        unset(
            $row['created_at'],
            $row['updated_at'],
            $row['arcgis_hash'],
            $row['arcgis_synced_at']
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
                in_array($column, ['today', 'start', 'end', 'editdate', 'creationdate'])
            )
        ) {
            return date('Y-m-d H:i:s', intval($value / 1000));
        }

        return $value;
    }
}