<?php

namespace App\Console\Commands;

use App\services\RoadFacilitySurveyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class SyncArcGISRoadFacilitySurvey extends Command
{
    protected $signature = 'sync:road-facility-survey {--days=400} {--url=} {--all} {--layer=}';

    protected $description = 'Sync road facilities survey records from ArcGIS into the local database';

    public function __construct(private readonly RoadFacilitySurveyImporter $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        set_time_limit(0);

        $configuredUrl = $this->option('url') ?: config('services.arcgis.road_facility_survey_layer_url');

        if (! is_string($configuredUrl) || trim($configuredUrl) === '') {
            $this->error('ArcGIS road facility survey layer URL is not configured.');

            return self::FAILURE;
        }

        [$whereClause, $whereDescription] = $this->buildWhereClause();
        $explicitLayer = $this->option('layer');
        $layer = is_numeric($explicitLayer) ? max((int) $explicitLayer, 0) : null;
        $serviceUrl = $this->normalizeQueryUrl($configuredUrl, $layer);
        $referer = $this->resolveReferer($configuredUrl, $serviceUrl);

        $this->line('Using filter: '.$whereDescription);

        $token = $this->generateToken($referer);

        if ($token === null) {
            return self::FAILURE;
        }

        [$serviceUrl, $matchedCount, $discoveredLayer] = $this->resolveQueryUrlAndCount(
            $configuredUrl,
            $serviceUrl,
            $token,
            $whereClause,
            $layer,
        );

        $this->line('Using query URL: '.$serviceUrl);

        if ($discoveredLayer !== null) {
            $this->line('Using detected layer: '.$discoveredLayer);
        }

        $this->line('ArcGIS matched records count: '.$matchedCount);

        if ($matchedCount === 0) {
            $this->warn('No ArcGIS records matched the current filter.');
            $this->warn('This usually means the target layer is empty or the records live in another table/layer inside the same FeatureServer.');
            $this->warn('Try an explicit layer, for example: php artisan sync:road-facility-survey --all --layer=1');
            $this->info('Road facility survey sync completed successfully. Processed 0 record(s).');

            return self::SUCCESS;
        }

        $offset = 0;
        $limit = 500;
        $hasMore = true;
        $processed = 0;

        while ($hasMore) {
            $this->info("Fetching road facility survey records from offset: {$offset}...");

            $response = Http::timeout(120)->get($serviceUrl, [
                'where' => $whereClause,
                'outFields' => '*',
                'returnGeometry' => 'true',
                'f' => 'json',
                'token' => $token,
                'resultOffset' => $offset,
                'resultRecordCount' => $limit,
                'orderByFields' => 'objectid ASC',
            ]);

            if (! $response->successful()) {
                $this->error('ArcGIS query failed: '.$response->body());

                return self::FAILURE;
            }

            $data = $response->json();
            $features = $data['features'] ?? [];

            if ($features === []) {
                break;
            }

            foreach ($features as $feature) {
                $payload = $feature['attributes'] ?? [];

                if (($payload['location'] ?? null) === null && isset($feature['geometry'])) {
                    $payload['location'] = json_encode($feature['geometry'], JSON_UNESCAPED_UNICODE);
                }

                foreach (['creationdate', 'editdate', 'CreationDate', 'EditDate', 'submissionDate', 'today', 'start', 'end'] as $dateField) {
                    if (isset($payload[$dateField]) && is_numeric($payload[$dateField])) {
                        $payload[$dateField] = date('Y-m-d H:i:s', (int) ($payload[$dateField] / 1000));
                    }
                }

                $this->importer->import($payload);
                $processed++;
            }

            $hasMore = (bool) ($data['exceededTransferLimit'] ?? false);
            $offset += $limit;
        }

        $this->info("Road facility survey sync completed successfully. Processed {$processed} record(s).");

        return self::SUCCESS;
    }

    private function buildWhereClause(): array
    {
        if ((bool) $this->option('all')) {
            return ['1=1', 'all records'];
        }

        $days = max((int) $this->option('days'), 0);
        $targetDateString = date('m-d-Y', strtotime('-'.$days.' days')).' 12:00:00 AM';

        return [
            "editdate >= '{$targetDateString}'",
            "records updated since {$targetDateString}",
        ];
    }

    private function generateToken(string $referer): ?string
    {
        $response = Http::asForm()->timeout(60)->post('https://www.arcgis.com/sharing/rest/generateToken', [
            'f' => 'json',
            'username' => config('services.arcgis.username'),
            'password' => config('services.arcgis.password'),
            'client' => 'referer',
            'referer' => $referer,
        ]);

        if (! $response->successful()) {
            $this->error('ArcGIS token request failed: '.$response->body());

            return null;
        }

        $tokenData = $response->json();

        if (isset($tokenData['error'])) {
            $this->error('ArcGIS Token Error: '.$tokenData['error']['message']);

            return null;
        }

        $token = $tokenData['token'] ?? null;

        if (! is_string($token) || $token === '') {
            $this->error('Could not retrieve ArcGIS token. Check credentials and service configuration.');

            return null;
        }

        return $token;
    }

    private function resolveQueryUrlAndCount(string $configuredUrl, string $defaultServiceUrl, string $token, string $whereClause, ?int $explicitLayer): array
    {
        if ($explicitLayer !== null || ! preg_match('#/featureserver$#i', rtrim($configuredUrl, '/'))) {
            return [$defaultServiceUrl, $this->fetchCount($defaultServiceUrl, $token, $whereClause), $explicitLayer];
        }

        $featureServerUrl = rtrim($configuredUrl, '/');
        $metadataResponse = Http::timeout(120)->get($featureServerUrl, [
            'f' => 'json',
            'token' => $token,
        ]);

        if (! $metadataResponse->successful()) {
            return [$defaultServiceUrl, $this->fetchCount($defaultServiceUrl, $token, $whereClause), null];
        }

        $metadata = $metadataResponse->json();
        $candidateLayers = collect(array_merge(
            Arr::get($metadata, 'layers', []),
            Arr::get($metadata, 'tables', []),
        ))
            ->pluck('id')
            ->filter(static fn ($id) => is_numeric($id))
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($candidateLayers->isEmpty()) {
            return [$defaultServiceUrl, $this->fetchCount($defaultServiceUrl, $token, $whereClause), null];
        }

        foreach ($candidateLayers as $candidateLayer) {
            $candidateUrl = $featureServerUrl.'/'.$candidateLayer.'/query';
            $count = $this->fetchCount($candidateUrl, $token, $whereClause, false);

            if ($count > 0) {
                return [$candidateUrl, $count, $candidateLayer];
            }
        }

        return [$featureServerUrl.'/'.$candidateLayers->first().'/query', 0, $candidateLayers->first()];
    }

    private function fetchCount(string $serviceUrl, string $token, string $whereClause, bool $failLoudly = true): int
    {
        $countResponse = Http::timeout(120)->get($serviceUrl, [
            'where' => $whereClause,
            'returnCountOnly' => 'true',
            'f' => 'json',
            'token' => $token,
        ]);

        if (! $countResponse->successful()) {
            if ($failLoudly) {
                $this->error('ArcGIS count query failed: '.$countResponse->body());
            }

            return 0;
        }

        return (int) ($countResponse->json('count') ?? 0);
    }

    private function normalizeQueryUrl(string $configuredUrl, ?int $layer): string
    {
        $normalizedUrl = rtrim($configuredUrl, '/');

        if (str_ends_with(strtolower($normalizedUrl), '/query')) {
            return $normalizedUrl;
        }

        if (preg_match('#/featureserver$#i', $normalizedUrl)) {
            return $normalizedUrl.'/'.max($layer ?? 0, 0).'/query';
        }

        if (preg_match('#/featureserver/\d+$#i', $normalizedUrl)) {
            return $normalizedUrl.'/query';
        }

        return $normalizedUrl;
    }

    private function resolveReferer(string $configuredUrl, string $serviceUrl): string
    {
        $configuredReferer = config('services.arcgis.road_facility_survey_referer');

        if (is_string($configuredReferer) && trim($configuredReferer) !== '') {
            return trim($configuredReferer);
        }

        if (str_ends_with(strtolower($serviceUrl), '/query')) {
            return substr($serviceUrl, 0, -strlen('/query'));
        }

        return rtrim($configuredUrl, '/');
    }
}
