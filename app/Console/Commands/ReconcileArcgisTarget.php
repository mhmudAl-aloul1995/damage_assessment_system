<?php

namespace App\Console\Commands;

use App\Services\ArcgisAuditedCacheService;
use App\Services\ArcgisAuditedUploadService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ReconcileArcgisTarget extends Command
{
    protected $signature = 'arcgis:reconcile-target
        {--run : Apply changes. Without this option the command only reports what would happen.}
        {--delete-extra : With --run, delete target rows whose old object id no longer exists in source.}
        {--only= : Reconcile only buildings or units.}
        {--skip-cache-refresh : Do not rebuild audited cache tables before uploading missing rows.}
        {--without-attachments : Upload missing features without copying attachments.}
        {--chunk=1000 : ArcGIS query page size.}';

    protected $description = 'Reconcile audited ArcGIS target counts against source, using local database audit values for uploads.';

    public function handle(
        ArcgisAuditedCacheService $cacheService,
        ArcgisAuditedUploadService $uploadService,
    ): int {
        $applyChanges = (bool) $this->option('run');
        $deleteExtra = (bool) $this->option('delete-extra');
        $only = $this->option('only');

        if (! in_array($only, [null, '', 'buildings', 'units'], true)) {
            $this->error('The --only option must be buildings or units.');

            return self::FAILURE;
        }

        $this->line('Mode: '.($applyChanges ? 'RUN' : 'DRY-RUN'));
        $this->line('Delete extra target rows: '.($applyChanges && $deleteExtra ? 'yes' : 'no'));

        $token = $this->generateToken();
        $jobs = $this->jobs($only);
        $summary = [];

        foreach ($jobs as $job) {
            $this->newLine();
            $this->info('Checking '.$job['name'].'...');

            $sourceIds = $this->sourceObjectIds($job, $token);
            $targetRows = $this->targetRows($job, $token);
            $targetIds = array_fill_keys(array_column($targetRows, 'old_objectid'), true);

            $missing = array_values(array_diff(array_keys($sourceIds), array_keys($targetIds)));
            $extraRows = array_values(array_filter(
                $targetRows,
                fn (array $row): bool => ! isset($sourceIds[$row['old_objectid']])
            ));

            $available = $this->availableLocalCount($job['cache_table'], $missing);

            $summary[$job['name']] = [
                'source' => count($sourceIds),
                'target' => count($targetRows),
                'missing' => count($missing),
                'extra' => count($extraRows),
                'available_for_upload' => $available,
            ];

            $this->table(['Metric', 'Value'], collect($summary[$job['name']])
                ->map(fn (int $value, string $metric): array => [$metric, (string) $value])
                ->values()
                ->all());

            if ($missing !== []) {
                $this->line('Missing examples: '.implode(', ', array_slice($missing, 0, 20)));
            }

            if ($extraRows !== []) {
                $this->line('Extra old object id examples: '.implode(', ', array_slice(array_column($extraRows, 'old_objectid'), 0, 20)));
            }

            $summary[$job['name']]['missing_ids'] = $missing;
            $summary[$job['name']]['extra_objectids'] = array_column($extraRows, 'objectid');
        }

        if (! $applyChanges) {
            $this->warn('Dry-run only. Re-run with --run to upload missing records.');
            $this->warn('Add --delete-extra with --run only when you also want to remove target extras.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('skip-cache-refresh')) {
            $this->newLine();
            $this->info('Refreshing audited cache from this server database...');
            $cacheSummary = $cacheService->refresh();
            $this->line('Cached buildings: '.$cacheSummary['buildings_cached']);
            $this->line('Cached units: '.$cacheSummary['housing_units_cached']);
        }

        if ($deleteExtra) {
            foreach ($jobs as $job) {
                $objectIds = $summary[$job['name']]['extra_objectids'] ?? [];

                if ($objectIds === []) {
                    continue;
                }

                $this->warn('Deleting extra '.$job['name'].' target rows: '.count($objectIds));
                $deleted = $this->deleteTargetRows($job, $objectIds, $token);
                $this->line('Deleted '.$job['name'].': '.$deleted);
            }
        }

        $buildingIds = in_array('buildings', array_column($jobs, 'name'), true)
            ? ($summary['buildings']['missing_ids'] ?? [])
            : [];
        $unitIds = in_array('units', array_column($jobs, 'name'), true)
            ? ($summary['units']['missing_ids'] ?? [])
            : [];

        $this->newLine();
        $this->info('Uploading missing records from audited cache...');

        $uploadSummary = $uploadService->uploadObjectIds(
            buildingObjectIds: $buildingIds,
            unitObjectIds: $unitIds,
            withoutAttachments: (bool) $this->option('without-attachments'),
        );

        $this->table(['Metric', 'Value'], collect($uploadSummary)
            ->map(fn (mixed $value, string $metric): array => [$metric, is_scalar($value) ? (string) $value : json_encode($value)])
            ->values()
            ->all());

        $this->info('Reconciliation run finished. Re-run without --run to verify final missing/extra counts.');

        return ((int) ($uploadSummary['errors'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, source_layer: int|string, target_layer: int|string, old_field: string, cache_table: string}>
     */
    private function jobs(?string $only): array
    {
        $jobs = [
            [
                'name' => 'buildings',
                'source_layer' => config('services.arcgis.source_buildings_layer'),
                'target_layer' => config('services.arcgis.target_buildings_layer'),
                'old_field' => 'old_objectid_B',
                'cache_table' => 'audited_buildings',
            ],
            [
                'name' => 'units',
                'source_layer' => config('services.arcgis.source_units_layer'),
                'target_layer' => config('services.arcgis.target_units_layer'),
                'old_field' => 'old_objectid_U',
                'cache_table' => 'audited_housing_units',
            ],
        ];

        if ($only === null || $only === '') {
            return $jobs;
        }

        return array_values(array_filter($jobs, fn (array $job): bool => $job['name'] === $only));
    }

    /**
     * @param  array{name: string, source_layer: int|string, target_layer: int|string, old_field: string, cache_table: string}  $job
     * @return array<string, bool>
     */
    private function sourceObjectIds(array $job, string $token): array
    {
        return $this->fetchIdSet($this->sourceLayerUrl($job['source_layer']), 'objectid', $token);
    }

    /**
     * @param  array{name: string, source_layer: int|string, target_layer: int|string, old_field: string, cache_table: string}  $job
     * @return array<int, array{objectid: int, old_objectid: string}>
     */
    private function targetRows(array $job, string $token): array
    {
        $rows = [];

        foreach ($this->queryLayerRows($this->targetLayerUrl($job['target_layer']), 'objectid,'.$job['old_field'], $token, 'objectid ASC') as $attributes) {
            $objectId = $attributes['objectid'] ?? null;
            $oldObjectId = $attributes[$job['old_field']] ?? null;

            if (! is_numeric($objectId) || $oldObjectId === null || $oldObjectId === '') {
                continue;
            }

            $rows[] = [
                'objectid' => (int) $objectId,
                'old_objectid' => (string) $oldObjectId,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, bool>
     */
    private function fetchIdSet(string $layerUrl, string $field, string $token): array
    {
        $ids = [];

        foreach ($this->queryLayerRows($layerUrl, $field, $token, $field.' ASC') as $attributes) {
            $value = $attributes[$field] ?? null;

            if ($value !== null && $value !== '') {
                $ids[(string) $value] = true;
            }
        }

        return $ids;
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function queryLayerRows(string $layerUrl, string $outFields, string $token, string $orderByFields): iterable
    {
        $chunk = max(1, (int) $this->option('chunk'));

        for ($offset = 0; ; $offset += $chunk) {
            $response = $this->http()->get($layerUrl.'/query', [
                'f' => 'json',
                'token' => $token,
                'where' => '1=1',
                'outFields' => $outFields,
                'returnGeometry' => 'false',
                'resultOffset' => $offset,
                'resultRecordCount' => $chunk,
                'orderByFields' => $orderByFields,
            ]);

            $this->throwIfArcgisError($response->json(), 'ArcGIS query failed for '.$layerUrl);

            if (! $response->successful()) {
                throw new RuntimeException('ArcGIS query failed: '.$response->body());
            }

            $features = $response->json('features') ?? [];

            if (! is_array($features) || $features === []) {
                break;
            }

            foreach ($features as $feature) {
                $attributes = $feature['attributes'] ?? [];

                if (is_array($attributes)) {
                    yield $attributes;
                }
            }
        }
    }

    private function availableLocalCount(string $table, array $objectIds): int
    {
        if ($objectIds === []) {
            return 0;
        }

        $count = 0;

        foreach (array_chunk($objectIds, 1000) as $chunk) {
            $count += (int) DB::table($table)->whereIn('objectid', $chunk)->count();
        }

        return $count;
    }

    /**
     * @param  array{name: string, source_layer: int|string, target_layer: int|string, old_field: string, cache_table: string}  $job
     * @param  array<int, int>  $objectIds
     */
    private function deleteTargetRows(array $job, array $objectIds, string $token): int
    {
        $deleted = 0;

        foreach (array_chunk($objectIds, 200) as $chunk) {
            $response = $this->http()
                ->asForm()
                ->post($this->targetLayerUrl($job['target_layer']).'/deleteFeatures', [
                    'f' => 'json',
                    'token' => $token,
                    'objectIds' => implode(',', $chunk),
                ]);

            $data = $response->json();
            $this->throwIfArcgisError($data, 'ArcGIS delete failed for '.$job['name']);

            if (! $response->successful()) {
                throw new RuntimeException('ArcGIS delete failed: '.$response->body());
            }

            foreach (($data['deleteResults'] ?? []) as $result) {
                if (($result['success'] ?? false) === true) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function generateToken(): string
    {
        $response = $this->http()
            ->asForm()
            ->post('https://www.arcgis.com/sharing/rest/generateToken', [
                'username' => $this->requiredConfig('username'),
                'password' => $this->requiredConfig('password'),
                'client' => 'referer',
                'referer' => $this->requiredConfig('referer'),
                'expiration' => 60,
                'f' => 'json',
            ]);

        $data = $response->json();
        $this->throwIfArcgisError($data, 'ArcGIS token failed');

        if (! $response->successful() || ! is_string($data['token'] ?? null)) {
            throw new RuntimeException('ArcGIS token failed: '.$response->body());
        }

        return $data['token'];
    }

    private function sourceLayerUrl(int|string $layer): string
    {
        return $this->serviceUrl('source_service').'/'.$layer;
    }

    private function targetLayerUrl(int|string $layer): string
    {
        return $this->serviceUrl('target_service').'/'.$layer;
    }

    private function serviceUrl(string $key): string
    {
        return rtrim($this->requiredConfig($key), '/');
    }

    private function requiredConfig(string $key): string
    {
        $value = config('services.arcgis.'.$key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Missing ArcGIS config services.arcgis.{$key}.");
        }

        return $value;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['Referer' => $this->requiredConfig('referer')])
            ->timeout(180)
            ->connectTimeout(30)
            ->retry(2, 1000, throw: false)
            ->withoutVerifying();
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function throwIfArcgisError(?array $data, string $message): void
    {
        if (! is_array($data) || ! is_array($data['error'] ?? null)) {
            return;
        }

        throw new RuntimeException($message.': '.json_encode($data['error'], JSON_UNESCAPED_UNICODE));
    }
}
