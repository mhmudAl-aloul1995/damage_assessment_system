<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\services\ArcgisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NormalizeCsoSurveyDamageStatusInArcgis extends Command
{
    protected $signature = 'arcgis:normalize-cso-survey-damage-status
        {--dry-run : Show matching records without updating ArcGIS}
        {--chunk=200 : Number of records to update per request}';

    protected $description = 'Normalize CSO Survey building_damage_status values in ArcGIS: total => 1, partial => 2.';

    public function handle(ArcgisService $arcgisService): int
    {
        $layerUrl = $this->featureServerLayerUrl((string) config('services.arcgis.cso_survey_layer_url'), 0);

        if ($layerUrl === '') {
            $this->error('Missing services.arcgis.cso_survey_layer_url.');

            return self::FAILURE;
        }

        $token = $arcgisService->getToken();
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info('CSO Survey ArcGIS layer: '.$layerUrl);

        $summary = [
            'total_to_1' => $this->normalizeValue($layerUrl, $token, 'total', '1', $chunkSize, $dryRun),
            'partial_to_2' => $this->normalizeValue($layerUrl, $token, 'partial', '2', $chunkSize, $dryRun),
        ];

        $this->newLine();
        $this->table(['Change', 'Matched', 'Updated', 'Failed'], [
            ['total => 1', $summary['total_to_1']['matched'], $summary['total_to_1']['updated'], $summary['total_to_1']['failed']],
            ['partial => 2', $summary['partial_to_2']['matched'], $summary['partial_to_2']['updated'], $summary['partial_to_2']['failed']],
        ]);

        return ($summary['total_to_1']['failed'] + $summary['partial_to_2']['failed']) > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @return array{matched: int, updated: int, failed: int}
     */
    private function normalizeValue(string $layerUrl, string $token, string $from, string $to, int $chunkSize, bool $dryRun): array
    {
        $objectIds = $this->matchingObjectIds($layerUrl, $token, $from);

        $this->line('Found '.count($objectIds)." record(s) with building_damage_status = {$from}.");

        if ($dryRun || $objectIds === []) {
            return [
                'matched' => count($objectIds),
                'updated' => 0,
                'failed' => 0,
            ];
        }

        $updated = 0;
        $failed = 0;

        foreach (array_chunk($objectIds, $chunkSize) as $objectIdChunk) {
            $response = Http::asForm()
                ->timeout(120)
                ->withoutVerifying()
                ->acceptJson()
                ->post($layerUrl.'/updateFeatures', [
                    'f' => 'json',
                    'token' => $token,
                    'features' => json_encode(
                        collect($objectIdChunk)
                            ->map(fn (int|string $objectId): array => [
                                'attributes' => [
                                    'objectid' => $objectId,
                                    'building_damage_status' => $to,
                                ],
                            ])
                            ->values()
                            ->all(),
                        JSON_THROW_ON_ERROR
                    ),
                ]);

            $body = $response->json();
            $results = data_get($body, 'updateResults', []);

            if (! $response->successful() || isset($body['error']) || ! is_array($results)) {
                $failed += count($objectIdChunk);
                $this->error('ArcGIS updateFeatures failed: '.$response->body());

                continue;
            }

            foreach ($results as $result) {
                if ((bool) ($result['success'] ?? false)) {
                    $updated++;
                } else {
                    $failed++;
                }
            }
        }

        return [
            'matched' => count($objectIds),
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<int, int|string>
     */
    private function matchingObjectIds(string $layerUrl, string $token, string $value): array
    {
        $response = Http::timeout(120)
            ->withoutVerifying()
            ->acceptJson()
            ->get($layerUrl.'/query', [
                'f' => 'json',
                'token' => $token,
                'where' => "building_damage_status = '{$value}'",
                'outFields' => 'objectid',
                'returnGeometry' => 'false',
                'returnIdsOnly' => 'true',
            ]);

        $body = $response->json();

        if (! $response->successful() || isset($body['error'])) {
            throw new RuntimeException('ArcGIS query failed: '.$response->body());
        }

        return collect(data_get($body, 'objectIds', []))
            ->filter(fn (mixed $objectId): bool => filled($objectId))
            ->values()
            ->all();
    }

    private function featureServerLayerUrl(string $url, int $layer): string
    {
        $url = rtrim($url, '/');
        $url = preg_replace('#/query$#i', '', $url) ?: $url;

        if ($url === '') {
            return '';
        }

        if (preg_match('#/featureserver$#i', $url)) {
            return $url.'/'.$layer;
        }

        if (preg_match('#/featureserver/\d+$#i', $url)) {
            return preg_replace('#/featureserver/\d+$#i', '/FeatureServer/'.$layer, $url) ?: $url;
        }

        return $url;
    }
}
