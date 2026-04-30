<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncArcGISLayers extends Command
{
    protected $signature = 'sync:arcgis-layers 
                            {table? : layer key مثل buildings أو housing_units}
                            {--chunk=500}
                            {--debug-fields : عرض حقول ArcGIS وحقول DB غير المطابقة}
                            {--only-objectid= : جلب objectid محدد للتجربة}';

    protected $description = 'Professional ArcGIS full sync with smart field mapping';

    private array $systemColumns = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function handle(): int
    {
        $layers = $this->layers();

        $only = $this->argument('table');

        foreach ($layers as $name => $cfg) {
            if ($only && $only !== $name) {
                continue;
            }

            if (!is_array($cfg) || empty($cfg['table']) || empty($cfg['url'])) {
                $this->warn("Skipped invalid layer config: {$name}");
                continue;
            }

            $this->syncLayer($name, $cfg);
        }

        $this->info('Sync finished.');

        return self::SUCCESS;
    }

    private function layers(): array
    {
        return [
            'buildings' => [
                'table' => 'buildings',
                'url' => config('services.arcgis.buildings_url'),
                'map' => [],
            ],

            'housing_units' => [
                'table' => 'housing_units',
                'url' => config('services.arcgis.housing_units_url'),
                'map' => [
                    // example:
                    // 'parentglobalid' => 'parentglobalid',
                ],
            ],

            'public_building_surveys' => [
                'table' => 'public_building_surveys',
                'url' => config('services.arcgis.public_building_survey_layer_url'),
                'map' => [],
            ],

            'public_building_survey_units' => [
                'table' => 'public_building_survey_units',
                'url' => config(
                    'services.arcgis.public_building_survey_units_layer_url',
                    'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_409593086b6249549601f0f8c6a3007a/FeatureServer/1'
                ),
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
                'map' => [
                    // ضع هنا أي حقل ArcGIS مختلف عن اسم DB
                    // 'arcgis_field_name' => 'database_column_name',
                ],
            ],

            'road_facility_survey_items' => [
                'table' => 'road_facility_survey_items',
                'url' => 'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_8d4df706500f47a8864206fd1b251739_form/FeatureServer/1',
                'map' => [
                    'unit_001' => 'unit',
                    'quantity_001' => 'quantity',
                    'parentglobalid' => 'parentglobalid',
                ],
            ],
        ];
    }

    private function syncLayer(string $name, array $cfg): void
    {
        $table = $cfg['table'];
        $url = $this->normalizeUrl($cfg['url']);
        $explicitMap = $cfg['map'] ?? [];

        if (!Schema::hasTable($table)) {
            $this->error("Table not found: {$table}");
            return;
        }

        $columns = collect(Schema::getColumnListing($table))
            ->reject(fn($col) => in_array($col, $this->systemColumns, true))
            ->values()
            ->all();

        if (!in_array('objectid', $columns, true)) {
            $this->error("Table {$table} must have objectid column.");
            return;
        }

        $token = $this->getToken();

        if (!$token) {
            $this->error('ArcGIS token is empty. Check username/password/referer.');
            return;
        }

        $offset = 0;
        $limit = max(1, (int) $this->option('chunk'));

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $this->newLine();
        $this->info("Syncing {$name}");
        $this->line("Table: {$table}");
        $this->line("URL  : {$url}");

        while (true) {
            $where = '1=1';

            if ($this->option('only-objectid')) {
                $where = 'objectid = ' . (int) $this->option('only-objectid');
            }

            $this->line("Fetching offset: {$offset}");

            try {
                $response = Http::timeout(120)
                    ->retry(3, 1500)
                    ->get($url, [
                        'where' => $where,
                        'outFields' => '*',
                        'f' => 'json',
                        'token' => $token,
                        'resultOffset' => $offset,
                        'resultRecordCount' => $limit,
                        'orderByFields' => 'objectid ASC',
                        'returnGeometry' => 'false',
                    ]);

                $json = $response->json();

                if (isset($json['error'])) {
                    $this->error(json_encode($json['error'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    return;
                }

                $features = $json['features'] ?? [];

                if (empty($features)) {
                    break;
                }

                foreach ($features as $feature) {
                    try {
                        $attr = $feature['attributes'] ?? [];
                        $objectid = $this->valueFromAttributes($attr, 'objectid');

                        if (!$objectid) {
                            $skipped++;
                            continue;
                        }

                        if ($offset === 0 && $this->option('debug-fields')) {
                            $this->debugFields($table, $columns, $attr);
                        }

                        $row = $this->buildRow(
                            attributes: $attr,
                            columns: $columns,
                            explicitMap: $explicitMap
                        );

                        $row['objectid'] = $objectid;

                        if (in_array('raw_payload', $columns, true)) {
                            $row['raw_payload'] = json_encode($attr, JSON_UNESCAPED_UNICODE);
                        }

                        $this->upsertRow($table, $row, $objectid, $inserted, $updated, $skipped);
                    } catch (Throwable $e) {
                        $failed++;
                        $this->error("Object failed: " . $e->getMessage());
                    }
                }

                if ($this->option('only-objectid')) {
                    break;
                }

                $offset += $limit;
            } catch (Throwable $e) {
                $this->error("Request failed: " . $e->getMessage());
                break;
            }
        }

        $this->info("{$name} done.");
        $this->line("Inserted: {$inserted}");
        $this->line("Updated : {$updated}");
        $this->line("Skipped : {$skipped}");
        $this->line("Failed  : {$failed}");
    }

    private function buildRow(array $attributes, array $columns, array $explicitMap): array
    {
        $row = [];
        $normalized = $this->normalizeAttributeKeys($attributes);

        foreach ($columns as $column) {
            if (in_array($column, $this->systemColumns, true)) {
                continue;
            }

            if ($column === 'raw_payload') {
                continue;
            }

            // 1) explicit mapping: ArcGIS field => DB column
            foreach ($explicitMap as $arcField => $dbColumn) {
                if ($dbColumn === $column) {
                    $value = $this->valueFromNormalized($normalized, $arcField);

                    if ($value !== '__FIELD_NOT_FOUND__') {
                        $row[$column] = $this->normalizeValue($value);
                        continue 2;
                    }
                }
            }

            // 2) automatic smart candidates
            foreach ($this->columnCandidates($column) as $candidate) {
                $value = $this->valueFromNormalized($normalized, $candidate);

                if ($value !== '__FIELD_NOT_FOUND__') {
                    $row[$column] = $this->normalizeValue($value);
                    continue 2;
                }
            }
        }

        return $row;
    }

    private function upsertRow(
        string $table,
        array $row,
        mixed $objectid,
        int &$inserted,
        int &$updated,
        int &$skipped
    ): void {
        $existing = DB::table($table)->where('objectid', $objectid)->first();

        if (!$existing) {
            DB::table($table)->insert($row);
            $inserted++;
            return;
        }

        $existingArray = (array) $existing;

        $updateData = [];

        foreach ($row as $key => $value) {
            if (in_array($key, ['id', 'created_at'], true)) {
                continue;
            }

            $oldValue = $existingArray[$key] ?? null;

            if ((string) $oldValue !== (string) $value) {
                $updateData[$key] = $value;
            }
        }

        if (empty($updateData)) {
            $skipped++;
            return;
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $updateData['updated_at'] = now();
        }

        DB::table($table)->where('objectid', $objectid)->update($updateData);
        $updated++;
    }

    private function getToken(): ?string
    {
        $json = Http::asForm()
            ->timeout(60)
            ->retry(3, 1000)
            ->post('https://www.arcgis.com/sharing/rest/generateToken', [
                'f' => 'json',
                'username' => config('services.arcgis.username'),
                'password' => config('services.arcgis.password'),
                'client' => 'referer',
                'referer' => config('app.url'),
                'expiration' => 60,
            ])
            ->json();

        return $json['token'] ?? null;
    }

    private function normalizeUrl(?string $url): string
    {
        if (!is_string($url) || trim($url) === '') {
            throw new \RuntimeException('ArcGIS URL is empty. Check config/services.php or .env.');
        }

        $url = rtrim(trim($url), '/');

        if (str_ends_with(strtolower($url), '/query')) {
            return $url;
        }

        if (preg_match('#/featureserver$#i', $url)) {
            return $url . '/0/query';
        }

        if (preg_match('#/featureserver/\d+$#i', $url)) {
            return $url . '/query';
        }

        return $url . '/query';
    }

    private function normalizeAttributeKeys(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            $original = (string) $key;
            $lower = strtolower($original);
            $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $original));
            $flat = str_replace('_', '', $snake);

            $normalized[$original] = $value;
            $normalized[$lower] = $value;
            $normalized[$snake] = $value;
            $normalized[$flat] = $value;
        }

        return $normalized;
    }

    private function columnCandidates(string $column): array
    {
        $lower = strtolower($column);
        $flat = str_replace('_', '', $lower);

        $without001 = preg_replace('/_001$/', '', $lower) ?: $lower;
        $with001 = $lower . '_001';

        return array_values(array_unique([
            $column,
            $lower,
            $flat,

            $without001,
            str_replace('_', '', $without001),

            $with001,
            str_replace('_', '', $with001),

            // common ArcGIS variations
            ucfirst($column),
            strtoupper($column),
        ]));
    }

    private function valueFromNormalized(array $normalized, string $field): mixed
    {
        $candidates = [
            $field,
            strtolower($field),
            str_replace('_', '', strtolower($field)),
        ];

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $normalized)) {
                return $normalized[$candidate];
            }
        }

        return '__FIELD_NOT_FOUND__';
    }

    private function valueFromAttributes(array $attributes, string $field): mixed
    {
        $normalized = $this->normalizeAttributeKeys($attributes);

        $value = $this->valueFromNormalized($normalized, $field);

        return $value === '__FIELD_NOT_FOUND__' ? null : $value;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }
        }

        // ArcGIS milliseconds timestamp
        if (is_numeric($value) && (float) $value > 1000000000000) {
            return \Carbon\Carbon::createFromTimestamp((int) ($value / 1000))
                ->format('Y-m-d H:i:s');
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }

    private function debugFields(string $table, array $columns, array $attributes): void
    {
        $this->warn("Debug fields for table: {$table}");

        $arcFields = array_keys($attributes);

        $this->line('ArcGIS fields count: ' . count($arcFields));
        $this->line('DB columns count    : ' . count($columns));

        $normalized = $this->normalizeAttributeKeys($attributes);

        $notMatched = [];

        foreach ($columns as $column) {
            if (in_array($column, $this->systemColumns, true) || $column === 'raw_payload') {
                continue;
            }

            $found = false;

            foreach ($this->columnCandidates($column) as $candidate) {
                if ($this->valueFromNormalized($normalized, $candidate) !== '__FIELD_NOT_FOUND__') {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $notMatched[] = $column;
            }
        }

        if (!empty($notMatched)) {
            $this->warn('DB columns not matched with ArcGIS fields:');
            foreach ($notMatched as $field) {
                $this->line('- ' . $field);
            }
        }
    }
}