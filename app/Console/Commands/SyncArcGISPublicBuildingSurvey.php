<?php

namespace App\Console\Commands;

use App\services\PublicBuildingSurveyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncArcGISPublicBuildingSurvey extends Command
{
    protected $signature = 'sync:public-building-survey {--days=400} {--url=}';

    protected $description = 'Sync public building survey records from ArcGIS into the local database';

    public function __construct(private readonly PublicBuildingSurveyImporter $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        set_time_limit(0);

        $configuredUrl = $this->option('url') ?: config('services.arcgis.public_building_survey_layer_url');

        if (! is_string($configuredUrl) || trim($configuredUrl) === '') {
            $this->error('ArcGIS public building survey layer URL is not configured.');

            return self::FAILURE;
        }

        $serviceUrl = $this->normalizeQueryUrl($configuredUrl);
        $referer = $this->resolveReferer($configuredUrl, $serviceUrl);

        $days = max((int) $this->option('days'), 0);
        $targetDateString = date('m-d-Y', strtotime('-'.$days.' days')).' 12:00:00 AM';
        $whereClause = "editdate >= '{$targetDateString}'";

        $token = $this->generateToken($referer);

        if ($token === null) {
            return self::FAILURE;
        }

        $offset = 0;
        $limit = 500;
        $hasMore = true;
        $processed = 0;

        while ($hasMore) {
            $this->info("Fetching public building survey records from offset: {$offset}...");

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

                foreach (['creationdate', 'editdate', 'Date_of_damage', 'today', 'start', 'end'] as $dateField) {
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

        $this->info("Public building survey sync completed successfully. Processed {$processed} record(s).");

        return self::SUCCESS;
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

    private function normalizeQueryUrl(string $configuredUrl): string
    {
        $normalizedUrl = rtrim($configuredUrl, '/');

        if (str_ends_with(strtolower($normalizedUrl), '/query')) {
            return $normalizedUrl;
        }

        if (preg_match('#/featureserver$#i', $normalizedUrl)) {
            return $normalizedUrl.'/0/query';
        }

        if (preg_match('#/featureserver/\d+$#i', $normalizedUrl)) {
            return $normalizedUrl.'/query';
        }

        return $normalizedUrl;
    }

    private function resolveReferer(string $configuredUrl, string $serviceUrl): string
    {
        $configuredReferer = config('services.arcgis.public_building_survey_referer');

        if (is_string($configuredReferer) && trim($configuredReferer) !== '') {
            return trim($configuredReferer);
        }

        if (str_ends_with(strtolower($serviceUrl), '/query')) {
            return substr($serviceUrl, 0, -strlen('/query'));
        }

        return rtrim($configuredUrl, '/');
    }
}
