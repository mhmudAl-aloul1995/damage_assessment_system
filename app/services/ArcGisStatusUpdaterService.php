<?php

declare(strict_types=1);

namespace App\services;

use App\Models\Building;
use App\Models\CommitteeDecision;
use App\Models\HousingUnit;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ArcGisStatusUpdaterService
{
    public function syncDecisionStatus(CommitteeDecision $decision): array
    {
        try {
            [$featureRecord, $layerId] = $this->resolveFeatureRecord($decision);

            if ($featureRecord === null || $layerId === null) {
                return [
                    'success' => false,
                    'status' => 'skipped',
                    'message' => 'No ArcGIS target record could be resolved.',
                ];
            }

            $identifierField = (string) config('services.committee_decisions.arcgis.identifier_field', 'objectid');
            [$statusField, $statusValue] = $this->resolveDecisionStatusAttributes($decision);
            $baseUrl = $this->resolveBaseUrl();

            if ($baseUrl === '') {
                return [
                    'success' => false,
                    'status' => 'not_configured',
                    'message' => 'ArcGIS base URL is not configured.',
                ];
            }

            $token = $this->resolveToken();
            $result = $this->sendArcGisUpdate($baseUrl, (int) $layerId, [
                $identifierField => data_get($featureRecord, $identifierField),
                ...$this->decisionStatusAttributes($decision, $statusField, $statusValue),
            ], $token, $decision);

            if (! $result['success']) {
                return $result;
            }

            if ($featureRecord instanceof HousingUnit) {
                $parentResult = $this->sendParentBuildingStatusUpdate($featureRecord, $baseUrl, $token, $decision);

                if (! $parentResult['success']) {
                    return $parentResult;
                }

                return [
                    'success' => true,
                    'status' => 'synced',
                    'message' => $result['message']."\n".$parentResult['message'],
                ];
            }

            return [
                'success' => true,
                'status' => 'synced',
                'message' => $result['message'],
            ];
        } catch (Throwable $exception) {
            Log::error('Committee ArcGIS sync exception.', [
                'committee_decision_id' => $decision->id,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function resolveToken(): string
    {
        $staticToken = (string) config('services.committee_decisions.arcgis.token', '');

        if ($staticToken !== '') {
            return $staticToken;
        }

        $username = (string) config('services.arcgis.username', '');
        $password = (string) config('services.arcgis.password', '');
        $tokenUrl = (string) config('services.committee_decisions.arcgis.token_url', 'https://www.arcgis.com/sharing/rest/generateToken');
        $referer = (string) config('services.committee_decisions.arcgis.referer', config('app.url'));

        if ($username === '' || $password === '') {
            return '';
        }

        return Cache::remember('committee_arcgis_token', now()->addMinutes(50), function () use ($tokenUrl, $username, $password, $referer): string {
            $response = $this->arcGisHttpClient()->post($tokenUrl, [
                'username' => $username,
                'password' => $password,
                'client' => 'referer',
                'referer' => $referer,
                'expiration' => 60,
                'f' => 'json',
            ]);

            if (! $response->successful()) {
                return '';
            }

            return (string) data_get($response->json(), 'token', '');
        });
    }

    private function resolveBaseUrl(): string
    {
        $configuredBaseUrl = rtrim((string) config('services.committee_decisions.arcgis.base_url', ''), '/');

        if ($configuredBaseUrl !== '') {
            return $configuredBaseUrl;
        }

        $buildingsLayerUrl = rtrim((string) config('services.arcgis.buildings_url', ''), '/');

        if ($buildingsLayerUrl === '') {
            return '';
        }

        return preg_replace('/\/\d+$/', '', $buildingsLayerUrl) ?: '';
    }

    private function resolveFeatureRecord(CommitteeDecision $decision): array
    {
        $decisionable = $decision->decisionable;

        if ($decisionable instanceof Building) {
            return [$decisionable, config('services.committee_decisions.arcgis.building_layer_id', 0)];
        }

        if (! $decisionable instanceof HousingUnit) {
            return [null, null];
        }

        return [$decisionable, config('services.committee_decisions.arcgis.housing_unit_layer_id', 1)];
    }

    private function resolveDecisionStatusAttributes(CommitteeDecision $decision): array
    {
        $decisionable = $decision->decisionable;

        if ($decisionable instanceof HousingUnit) {
            return [
                'unit_damage_status',
                $decision->decision_type === 'fully_damaged' ? 'fully_damaged2' : 'partially_damaged2',
            ];
        }

        return [
            'building_damage_status',
            $decision->decision_type === 'fully_damaged' ? 'fully_damaged' : 'partially_damaged',
        ];
    }

    private function decisionStatusAttributes(CommitteeDecision $decision, string $statusField, string $statusValue): array
    {
        $attributes = [
            $statusField => $statusValue,
        ];

        if ($decision->decisionable instanceof Building) {
            $attributes[(string) config('services.committee_decisions.arcgis.status_field', 'field_status')]
                = (string) config('services.committee_decisions.arcgis.status_value', 'Not_Completed');
        }

        return $attributes;
    }

    private function sendParentBuildingStatusUpdate(HousingUnit $housingUnit, string $baseUrl, string $token, CommitteeDecision $decision): array
    {
        if (blank($housingUnit->parentglobalid)) {
            return [
                'success' => false,
                'status' => 'missing_identifier',
                'message' => 'The parent building globalid is missing.',
            ];
        }

        return $this->sendArcGisUpdate($baseUrl, (int) config('services.committee_decisions.arcgis.building_layer_id', 0), [
            'globalid' => $housingUnit->parentglobalid,
            (string) config('services.committee_decisions.arcgis.status_field', 'field_status') => (string) config('services.committee_decisions.arcgis.status_value', 'Not_Completed'),
        ], $token, $decision);
    }

    private function sendArcGisUpdate(string $baseUrl, int $layerId, array $attributes, string $token, CommitteeDecision $decision): array
    {
        if (collect($attributes)->contains(fn ($value): bool => $value === null || $value === '')) {
            return [
                'success' => false,
                'status' => 'missing_identifier',
                'message' => 'The target ArcGIS identifier is missing.',
            ];
        }

        $payload = [
            'f' => 'json',
            'features' => json_encode([
                [
                    'attributes' => $attributes,
                ],
            ], JSON_THROW_ON_ERROR),
        ];

        if ($token !== '') {
            $payload['token'] = $token;
        }

        /** @var Response $response */
        $response = $this->arcGisHttpClient()
            ->post(sprintf('%s/%s/updateFeatures', $baseUrl, $layerId), $payload);

        $success = $response->successful() && (bool) data_get($response->json(), 'updateResults.0.success', false);

        if (! $success) {
            Log::error('Committee ArcGIS sync failed.', [
                'committee_decision_id' => $decision->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return [
            'success' => $success,
            'status' => $success ? 'synced' : 'failed',
            'message' => $response->body(),
        ];
    }

    private function arcGisHttpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('services.committee_decisions.arcgis.timeout', 90))
            ->connectTimeout((int) config('services.committee_decisions.arcgis.connect_timeout', 30))
            ->retry(
                (int) config('services.committee_decisions.arcgis.retry_times', 2),
                (int) config('services.committee_decisions.arcgis.retry_sleep', 1000),
                throw: false,
            );

        if (! (bool) config('services.committee_decisions.arcgis.verify_ssl', false)) {
            $request->withoutVerifying();
        }

        return $request;
    }
}
