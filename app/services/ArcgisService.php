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
        $url = "{$this->baseUrl}/{$layerId}/{$objectId}/attachments";

        $response = Http::asForm()->withoutVerifying()->post($url, [
            'f' => 'json',
            'token' => $token,
        ]);

        if (! $response->successful()) {
            return [];
        }

        return $response->json()['attachmentInfos'] ?? [];
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
            ];
        }

        return [
            'success' => true,
            'message' => 'Attachment downloaded.',
            'body' => $response->body(),
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
}
