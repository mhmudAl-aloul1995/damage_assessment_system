<?php

namespace App\services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ArcgisService
{
    protected string $baseUrl = 'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer';

    // =========================
    // GET TOKEN (WITH CACHE)
    // =========================
    public function getToken(): string
    {
        return Cache::remember('arcgis_token', 50 * 60, function () {

            $response = Http::asForm()->withoutVerifying()->post(
                'https://www.arcgis.com/sharing/rest/generateToken',
                [
                    'username' => config('services.arcgis.username'),
                    'password' => config('services.arcgis.password'),
                    'client' => 'referer',
                    'referer' => config('app.url'),
                    'expiration' => 60,
                    'f' => 'json',
                ]
            );

            if (! $response->successful()) {
                throw new \Exception('ArcGIS token failed: '.$response->body());
            }

            return $response->json()['token'];
        });
    }

    // =========================
    // GET LAYER
    // =========================
    public function getLayerId(string $modelClass): int
    {
        return match ($modelClass) {
            \App\Models\Building::class => 0,
            \App\Models\HousingUnit::class => 1,
            default => 0,
        };
    }

    // =========================
    // GET ATTACHMENTS
    // =========================
    public function getAttachments($objectId, $layerId, $token): array
    {
        $result = $this->getAttachmentsResult($objectId, $layerId, $token);

        return $result['attachments'] ?? [];
    }

    public function getAttachmentsResult($objectId, $layerId, $token): array
    {
        $url = "{$this->baseUrl}/{$layerId}/{$objectId}/attachments";

        $response = Http::asForm()->withoutVerifying()->post($url, [
            'f' => 'json',
            'token' => $token,
        ]);

        if (! $response->successful()) {
            return [
                'success' => false,
                'attachments' => [],
                'message' => $response->body(),
                'token_expired' => $this->isTokenError($response->body(), $response->status()),
            ];
        }

        $body = $response->json();
        $error = data_get($body, 'error');

        if (is_array($error)) {
            $message = (string) data_get($error, 'message', $response->body());
            $code = (int) data_get($error, 'code', 0);

            return [
                'success' => false,
                'attachments' => [],
                'message' => $message,
                'token_expired' => $this->isTokenError($message, $code),
            ];
        }

        return [
            'success' => true,
            'attachments' => $body['attachmentInfos'] ?? [],
            'message' => null,
            'token_expired' => false,
        ];
    }

    public function getAttachmentsFromLayerUrl(string $layerUrl, int|string|null $objectId, string $token): array
    {
        if (! filled($layerUrl) || ! filled($objectId)) {
            return [];
        }

        $url = $this->normalizeLayerUrl($layerUrl).'/'.$objectId.'/attachments';

        $response = Http::asForm()->withoutVerifying()->post($url, [
            'f' => 'json',
            'token' => $token,
        ]);

        if (! $response->successful()) {
            return [];
        }

        return $response->json('attachmentInfos') ?? [];
    }

    public function addAttachment(int|string $objectId, int|string $layerId, UploadedFile $file, string $token): array
    {
        if (! filled($objectId)) {
            return [
                'success' => false,
                'message' => 'Missing ArcGIS object id.',
            ];
        }

        $response = Http::withoutVerifying()
            ->asMultipart()
            ->post("{$this->baseUrl}/{$layerId}/{$objectId}/addAttachment", [
                [
                    'name' => 'f',
                    'contents' => 'json',
                ],
                [
                    'name' => 'token',
                    'contents' => $token,
                ],
                [
                    'name' => 'attachment',
                    'contents' => $file->getContent(),
                    'filename' => $file->getClientOriginalName(),
                ],
            ]);

        $body = $response->json();
        $success = $response->successful() && (bool) data_get($body, 'addAttachmentResult.success', false);

        return [
            'success' => $success,
            'message' => $success ? 'Attachment uploaded.' : $response->body(),
            'attachment_id' => data_get($body, 'addAttachmentResult.objectId'),
            'response' => $body,
        ];
    }

    public function deleteAttachment(int|string $objectId, int|string $layerId, int|string $attachmentId, string $token): array
    {
        if (! filled($objectId) || ! filled($attachmentId)) {
            return [
                'success' => false,
                'message' => 'Missing ArcGIS object id or attachment id.',
            ];
        }

        $response = Http::asForm()
            ->withoutVerifying()
            ->post("{$this->baseUrl}/{$layerId}/{$objectId}/deleteAttachments", [
                'f' => 'json',
                'token' => $token,
                'attachmentIds' => $attachmentId,
            ]);

        $body = $response->json();
        $success = $response->successful() && (bool) data_get($body, 'deleteAttachmentResults.0.success', false);

        return [
            'success' => $success,
            'message' => $success ? 'Attachment deleted.' : $response->body(),
            'response' => $body,
        ];
    }

    public function deleteFeatures(array $objectIds, int|string $layerId, string $token): array
    {
        $objectIds = collect($objectIds)
            ->filter(fn ($objectId): bool => filled($objectId))
            ->map(fn ($objectId): int|string => is_numeric($objectId) ? (int) $objectId : (string) $objectId)
            ->values()
            ->all();

        if ($objectIds === []) {
            return [
                'success' => false,
                'message' => 'Missing ArcGIS object ids.',
            ];
        }

        return $this->deleteFeaturesFromLayerUrl("{$this->baseUrl}/{$layerId}", $objectIds, $token);
    }

    public function deleteFeaturesFromLayerUrl(string $layerUrl, array $objectIds, string $token): array
    {
        $objectIds = collect($objectIds)
            ->filter(fn ($objectId): bool => filled($objectId))
            ->map(fn ($objectId): int|string => is_numeric($objectId) ? (int) $objectId : (string) $objectId)
            ->values()
            ->all();

        if (! filled($layerUrl) || $objectIds === []) {
            return [
                'success' => false,
                'message' => 'Missing ArcGIS layer URL or object ids.',
            ];
        }

        $response = Http::asForm()
            ->withoutVerifying()
            ->post($this->normalizeLayerUrl($layerUrl).'/deleteFeatures', [
                'f' => 'json',
                'token' => $token,
                'objectIds' => implode(',', $objectIds),
            ]);

        $body = $response->json();
        $deleteResults = data_get($body, 'deleteResults', []);
        $success = $response->successful()
            && $deleteResults !== []
            && collect($deleteResults)->every(fn (array $result): bool => (bool) ($result['success'] ?? false));

        return [
            'success' => $success,
            'message' => $success ? 'Features deleted.' : $response->body(),
            'response' => $body,
        ];
    }

    public function downloadAttachment(int|string $objectId, int|string $layerId, int|string $attachmentId, string $token): array
    {
        if (! filled($objectId) || ! filled($attachmentId)) {
            return [
                'success' => false,
                'message' => 'Missing ArcGIS object id or attachment id.',
                'body' => null,
            ];
        }

        $response = Http::withoutVerifying()
            ->get("{$this->baseUrl}/{$layerId}/{$objectId}/attachments/{$attachmentId}", [
                'token' => $token,
            ]);

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => $response->body(),
                'body' => null,
                'token_expired' => $this->isTokenError($response->body(), $response->status()),
            ];
        }

        $json = $response->json();

        if (is_array($json) && is_array(data_get($json, 'error'))) {
            $message = (string) data_get($json, 'error.message', $response->body());
            $code = (int) data_get($json, 'error.code', 0);

            return [
                'success' => false,
                'message' => $message,
                'body' => null,
                'token_expired' => $this->isTokenError($message, $code),
            ];
        }

        return [
            'success' => true,
            'message' => 'Attachment downloaded.',
            'body' => $response->body(),
            'token_expired' => false,
        ];
    }

    // =========================
    // BUILD URL
    // =========================
    public function buildUrl($objectId, $attachmentId, $layerId, $token): string
    {
        return "{$this->baseUrl}/{$layerId}/{$objectId}/attachments/{$attachmentId}?token={$token}";
    }

    public function updateBuildingFieldStatus(int|string $objectId, string $status = 'Not_Completed'): array
    {
        $token = $this->getToken();
        $layerId = $this->getLayerId(\App\Models\Building::class);

        $response = Http::asForm()
            ->withoutVerifying()
            ->acceptJson()
            ->post("{$this->baseUrl}/{$layerId}/updateFeatures", [
                'f' => 'json',
                'token' => $token,
                'features' => json_encode([
                    [
                        'attributes' => [
                            'objectid' => $objectId,
                            'field_status' => $status,
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $body = $response->json();
        $success = $response->successful() && (bool) data_get($body, 'updateResults.0.success', false);

        return [
            'success' => $success,
            'status' => $success ? 'synced' : 'failed',
            'message' => $response->body(),
        ];
    }

    public function updateHousingUnitIdentity(int|string|null $objectId, string $idNumber): array
    {
        return $this->updateHousingUnitIdentityField($objectId, 'id_number1', $idNumber);
    }

    public function updateHousingUnitIdentityField(int|string|null $objectId, string $field, string $idNumber): array
    {
        return $this->updateHousingUnitFields($objectId, [
            $field => $idNumber,
        ]);
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public function updateHousingUnitFields(int|string|null $objectId, array $attributes): array
    {
        if (! filled($objectId)) {
            return [
                'success' => true,
                'status' => 'skipped',
                'message' => 'Housing unit has no ArcGIS objectid.',
                'response' => null,
            ];
        }

        $token = $this->getToken();
        $layerUrl = rtrim((string) config('services.arcgis.housing_units_url'), '/');

        $response = Http::asForm()
            ->timeout(30)
            ->withoutVerifying()
            ->acceptJson()
            ->post($layerUrl.'/updateFeatures', [
                'f' => 'json',
                'token' => $token,
                'features' => json_encode([
                    [
                        'attributes' => [
                            'objectid' => $objectId,
                            ...$attributes,
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $body = $response->json();
        $success = $response->successful() && (bool) data_get($body, 'updateResults.0.success', false);

        return [
            'success' => $success,
            'status' => $success ? 'synced' : 'failed',
            'message' => $success ? 'ArcGIS identity updated.' : $response->body(),
            'response' => $body,
        ];
    }

    public function ensurePhaseNumberField(string $layerUrl, string $token): array
    {
        $layerUrl = $this->normalizeLayerUrl($layerUrl);
        $metadata = $this->layerMetadata($layerUrl, $token);

        if (! ($metadata['success'] ?? false)) {
            return $metadata;
        }

        $hasPhaseField = collect($metadata['fields'] ?? [])
            ->contains(fn (array $field): bool => strtolower((string) ($field['name'] ?? '')) === 'phase_number');

        $fieldCreated = $hasPhaseField;

        if (! $hasPhaseField) {
            $created = $this->addPhaseNumberField($layerUrl, $token);

            if (! ($created['success'] ?? false)) {
                return $created;
            }

            $fieldCreated = true;
        }

        $backfill = $this->backfillMissingPhaseNumber($layerUrl, $token);

        return [
            'success' => $fieldCreated && (bool) ($backfill['success'] ?? false),
            'status' => $hasPhaseField ? 'already_exists' : 'created',
            'message' => $backfill['message'] ?? 'Phase number field is ready.',
            'response' => [
                'field_exists' => $hasPhaseField,
                'backfill' => $backfill['response'] ?? null,
            ],
        ];
    }

    private function layerMetadata(string $layerUrl, string $token): array
    {
        $response = Http::timeout(60)
            ->withoutVerifying()
            ->get($layerUrl, [
                'f' => 'json',
                'token' => $token,
            ]);

        $body = $response->json();
        $error = data_get($body, 'error');

        if (! $response->successful() || is_array($error)) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => is_array($error) ? (string) data_get($error, 'message', $response->body()) : $response->body(),
                'response' => $body,
            ];
        }

        return [
            'success' => true,
            'status' => 'loaded',
            'message' => 'Layer metadata loaded.',
            'fields' => $body['fields'] ?? [],
            'response' => $body,
        ];
    }

    private function addPhaseNumberField(string $layerUrl, string $token): array
    {
        $adminLayerUrl = $this->adminLayerUrl($layerUrl);

        $response = Http::asForm()
            ->timeout(60)
            ->withoutVerifying()
            ->acceptJson()
            ->post($adminLayerUrl.'/addToDefinition', [
                'f' => 'json',
                'token' => $token,
                'addToDefinition' => json_encode([
                    'fields' => [
                        [
                            'name' => 'phase_number',
                            'type' => 'esriFieldTypeSmallInteger',
                            'alias' => 'Phase Number',
                            'nullable' => true,
                            'editable' => true,
                            'defaultValue' => 1,
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $body = $response->json();
        $success = $response->successful() && empty($body['error']) && (bool) ($body['success'] ?? true);

        return [
            'success' => $success,
            'status' => $success ? 'created' : 'failed',
            'message' => $success ? 'Phase number field created.' : $response->body(),
            'response' => $body,
        ];
    }

    private function backfillMissingPhaseNumber(string $layerUrl, string $token): array
    {
        $response = Http::asForm()
            ->timeout(120)
            ->withoutVerifying()
            ->acceptJson()
            ->post($layerUrl.'/calculate', [
                'f' => 'json',
                'token' => $token,
                'where' => 'phase_number IS NULL',
                'calcExpression' => json_encode([
                    [
                        'field' => 'phase_number',
                        'value' => 1,
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $body = $response->json();
        $success = $response->successful() && empty($body['error']) && (bool) ($body['success'] ?? true);

        return [
            'success' => $success,
            'status' => $success ? 'backfilled' : 'failed',
            'message' => $success ? 'Missing phase numbers backfilled.' : $response->body(),
            'response' => $body,
        ];
    }

    public function buildUrlFromLayerUrl(string $layerUrl, int|string $objectId, int|string $attachmentId, string $token): string
    {
        return $this->normalizeLayerUrl($layerUrl).'/'.$objectId.'/attachments/'.$attachmentId.'?token='.urlencode($token);
    }

    private function normalizeLayerUrl(string $layerUrl): string
    {
        $url = rtrim($layerUrl, '/');

        if (Str::endsWith($url, '/FeatureServer')) {
            return $url.'/0';
        }

        return $url;
    }

    private function adminLayerUrl(string $layerUrl): string
    {
        $adminUrl = preg_replace('~/arcgis/rest/services/~i', '/arcgis/admin/services/', $layerUrl) ?? $layerUrl;

        return preg_replace('~/FeatureServer(?=/|$)~i', '.FeatureServer', $adminUrl) ?? $adminUrl;
    }

    private function isTokenError(string $message, int $code = 0): bool
    {
        if (in_array($code, [498, 499], true)) {
            return true;
        }

        return Str::of($message)->lower()->contains([
            'token',
            'expired',
            'invalid token',
        ]);
    }
}
