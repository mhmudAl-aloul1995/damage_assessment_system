<?php

namespace App\services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MobileBuildingFootprintService
{
    public function __construct(private ArcgisService $arcgis) {}

    /** @param list<string|null> $globalids
     * @return array<string, array<string, mixed>>
     */
    public function geometries(string $sector, array $globalids): array
    {
        $url = match ($sector) {
            'buildings', 'housing-units' => rtrim((string) config('services.arcgis.source_service'), '/').'/'.(int) config('services.arcgis.source_buildings_layer', 0),
            'public-buildings' => (string) config('services.arcgis.public_building_survey_layer_url'),
            'cso-surveys' => (string) config('services.arcgis.cso_survey_layer_url'),
            default => '',
        };
        if (str_ends_with(strtolower($url), '/featureserver')) {
            $url .= '/0';
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || (! str_ends_with($host, '.arcgis.com') && $host !== 'arcgis.com')) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map($this->identity(...), $globalids))));
        $ids = array_slice($ids, 0, 500);
        $geometries = [];
        $missing = [];
        foreach ($ids as $id) {
            $geometry = Cache::get($this->cacheKey($url, $id));
            if (is_array($geometry) && isset($geometry['rings'])) {
                $geometries[$id] = $geometry;
            } else {
                $missing[] = $id;
            }
        }
        if ($missing === []) {
            return $geometries;
        }
        try {
            $response = Http::asForm()->withOptions(['verify' => true])->connectTimeout(3)->timeout(10)->post($url.'/query', [
                'f' => 'json', 'token' => $this->arcgis->getVerifiedToken(),
                'where' => "globalid IN ('".implode("','", $missing)."')",
                'outFields' => 'globalid', 'returnGeometry' => 'true', 'outSR' => 4326, 'geometryPrecision' => 7,
            ]);
            if (! $response->successful() || $response->json('error') !== null) {
                return $geometries;
            }
            foreach (array_slice($response->json('features') ?? [], 0, 500) as $feature) {
                $attributes = array_change_key_case($feature['attributes'] ?? [], CASE_LOWER);
                $id = $this->identity($attributes['globalid'] ?? null);
                $geometry = $feature['geometry'] ?? null;
                if ($id === null || ! in_array($id, $missing, true) || ! is_array($geometry) || ! isset($geometry['rings']) || ! is_array($geometry['rings'])) {
                    continue;
                }
                $geometry = array_intersect_key($geometry, array_flip(['rings', 'spatialReference']));
                $geometry['spatialReference'] ??= $response->json('spatialReference') ?? ['wkid' => 4326];
                if (! $this->validPolygon($geometry)) {
                    continue;
                }
                $geometries[$id] = $geometry;
                Cache::put($this->cacheKey($url, $id), $geometry, now()->addMinutes(15));
            }
        } catch (\Throwable) {
            return $geometries;
        }

        return $geometries;
    }

    public function identity(?string $globalid): ?string
    {
        $id = strtolower(trim((string) $globalid, "{} \t\r\n"));

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id) === 1 ? $id : null;
    }

    private function cacheKey(string $url, string $id): string
    {
        return 'mobile_building_polygon:'.hash('sha256', $url.'|'.$id);
    }

    /** @param array<string, mixed> $geometry */
    private function validPolygon(array $geometry): bool
    {
        $count = 0;
        if (($geometry['rings'] ?? []) === []) {
            return false;
        }
        foreach ($geometry['rings'] as $ring) {
            if (! is_array($ring) || count($ring) < 4 || $ring[0] !== $ring[array_key_last($ring)]) {
                return false;
            }
            foreach ($ring as $coordinate) {
                $count++;
                if ($count > 5000 || ! is_array($coordinate) || ! isset($coordinate[0], $coordinate[1]) || ! is_numeric($coordinate[0]) || ! is_numeric($coordinate[1])
                    || ! is_finite((float) $coordinate[0]) || ! is_finite((float) $coordinate[1])) {
                    return false;
                }
            }
        }

        return true;
    }
}
