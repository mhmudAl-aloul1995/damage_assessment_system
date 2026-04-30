<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SyncArcGISLayers extends Command
{
    protected $signature = 'sync:arcgis-layers {table?} {--chunk=1000}';

    protected $description = 'Full ArcGIS Sync لجميع الجداول';

    public function handle(): int
    {
        $layers = [

            'buildings' => [
                'table' => 'buildings',
                'url' => config('services.arcgis.buildings_url'),
            ],

            'housing_units' => [
                'table' => 'housing_units',
                'url' => config('services.arcgis.housing_units_url'),
            ],

            'public_building_surveys' => [
                'table' => 'public_building_surveys',
                'url' => config('services.arcgis.public_building_survey_layer_url'),
            ],

            // 🔥 FeatureServer/1
            'public_building_survey_units' => [
                'table' => 'public_building_survey_units',
                'url' => config(
                    'services.arcgis.public_building_survey_units_layer_url',
                    'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_409593086b6249549601f0f8c6a3007a/FeatureServer/1'
                ),
            ],

            // 🔥 FeatureServer/1
            'road_facility_survey_layer_url' => env(
                'ARCGIS_ROAD_FACILITY_SURVEY_LAYER_URL',
                'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_8d4df706500f47a8864206fd1b251739_form/FeatureServer/0'
            ),

            // ================= ROAD =================
            'road_facility_surveys' => [
                'table' => 'road_facility_surveys',
                'url' => config('services.arcgis.road_facility_survey_layer_url'),
            ],

            // 🔥 FeatureServer/1 (items)
            'road_facility_survey_items' => [
                'table' => 'road_facility_survey_items',
                'url' => 'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_8d4df706500f47a8864206fd1b251739_form/FeatureServer/1',
            ],
        ];

        $only = $this->argument('table');

        foreach ($layers as $name => $cfg) {
            if ($only && $only !== $name) {
                continue;
            }
            $this->sync($name, $cfg);
        }

        $this->info('Sync finished.');

        return self::SUCCESS;
    }

    // =====================================================
    private function sync(string $name, array $cfg)
    {
        $table = $cfg['table'];
        $url = $this->normalizeUrl($cfg['url']);
        $columns = Schema::getColumnListing($table);

        $token = $this->getToken();

        $offset = 0;
        $limit = (int) $this->option('chunk');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $this->newLine();
        $this->info("Syncing {$name}...");
        $this->line("URL: {$url}");

        while (true) {

            $this->line("Fetching {$name} offset: {$offset}");

            $res = Http::get($url, [
                'where' => '1=1',
                'outFields' => '*',
                'f' => 'json',
                'token' => $token,
                'resultOffset' => $offset,
                'resultRecordCount' => $limit,
                'orderByFields' => 'objectid ASC',
            ]);

            $features = $res->json()['features'] ?? [];

            if (empty($features)) {
                break;
            }

            foreach ($features as $f) {

                $attr = $f['attributes'];
                $objectid = $attr['objectid'] ?? null;

                if (! $objectid) {
                    continue;
                }

                $row = [];

                foreach ($columns as $col) {
                    if (isset($attr[$col])) {
                        $row[$col] = $this->normalize($attr[$col]);
                    }
                }

                // 🔥 raw payload
                if (in_array('raw_payload', $columns)) {
                    $row['raw_payload'] = json_encode($attr);
                }

                // 🔥 parentglobalid generic
                if (isset($attr['parentglobalid']) && in_array('parentglobalid', $columns)) {
                    $row['parentglobalid'] = $attr['parentglobalid'];
                }

                // ================= CUSTOM =================

                // ROAD ITEMS
                if ($table === 'road_facility_survey_items') {

                    if (isset($attr['unit_001']) && in_array('unit', $columns)) {
                        $row['unit'] = $attr['unit_001'];
                    }

                    if (isset($attr['quantity_001']) && in_array('quantity', $columns)) {
                        $row['quantity'] = $attr['quantity_001'];
                    }
                }

                // ================= UPSERT =================

                $existing = DB::table($table)->where('objectid', $objectid)->first();

                if (! $existing) {
                    DB::table($table)->insert($row + ['objectid' => $objectid]);
                    $inserted++;
                } else {

                    $newHash = md5(json_encode($row));
                    $oldHash = md5(json_encode((array) $existing));

                    if ($newHash === $oldHash) {
                        $skipped++;
                    } else {
                        DB::table($table)->where('objectid', $objectid)->update($row);
                        $updated++;
                    }
                }
            }

            $offset += $limit;
        }

        $this->info("{$name} done.");
        $this->info("Inserted: {$inserted}");
        $this->info("Updated : {$updated}");
        $this->info("Skipped : {$skipped}");
    }

    // =====================================================
    private function getToken()
    {
        return Http::asForm()->post(
            'https://www.arcgis.com/sharing/rest/generateToken',
            [
                'f' => 'json',
                'username' => config('services.arcgis.username'),
                'password' => config('services.arcgis.password'),
                'client' => 'referer',
                'referer' => config('app.url'),
                'expiration' => 60,
            ]
        )->json()['token'] ?? null;
    }

    // =====================================================
    private function normalizeUrl($url)
    {
        if (! is_string($url) || trim($url) === '') {
            throw new \RuntimeException('ArcGIS URL is empty. Check config/services.php and .env key.');
        }

        $url = rtrim(trim($url), '/');

        if (str_ends_with(strtolower($url), '/query')) {
            return $url;
        }

        if (preg_match('#/featureserver$#i', $url)) {
            return $url.'/0/query';
        }

        if (preg_match('#/featureserver/\d+$#i', $url)) {
            return $url.'/query';
        }

        return $url.'/query';
    }

    // =====================================================
    private function normalize($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        // timestamp ArcGIS
        if (is_numeric($value) && $value > 1000000000000) {
            return date('Y-m-d H:i:s', $value / 1000);
        }

        return $value;
    }
}
