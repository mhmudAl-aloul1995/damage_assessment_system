<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ArcgisService
{
    protected string $baseUrl = "https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer";

    // =========================
    // GET TOKEN (WITH CACHE)
    // =========================
    public function getToken(): string
    {
        return Cache::remember('arcgis_token', 50 * 60, function () {

            $response = Http::asForm()->post(
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

            if (!$response->successful()) {
                throw new \Exception('ArcGIS token failed');
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

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['attachmentInfos'] ?? [];
    }

    // =========================
    // BUILD URL
    // =========================
    public function buildUrl($objectId, $attachmentId, $layerId, $token): string
    {
        return "{$this->baseUrl}/{$layerId}/{$objectId}/attachments/{$attachmentId}?token={$token}";
    }
}