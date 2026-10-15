<?php

declare(strict_types=1);

namespace App\Support\Forms;

use JsonException;

class CsoAuditLocation
{
    /** @return array{status: string, source: string, geometry?: array<string, mixed>, center?: array{0: float, 1: float}, paths?: list<string>, google_url?: string, is_polygon?: bool} */
    public static function present(mixed $value): array
    {
        $source = is_string($value) ? trim($value) : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $result = ['status' => 'invalid', 'source' => $source];

        if ($value === null || $value === [] || (is_string($value) && $source === '')) {
            return ['status' => 'missing', 'source' => ''];
        }

        try {
            $geometry = is_string($value) ? json_decode($value, true, 64, JSON_THROW_ON_ERROR) : $value;
        } catch (JsonException) {
            return $result;
        }

        if (! is_array($geometry)) {
            return $result;
        }

        $reference = $geometry['spatialReference'] ?? [];
        if (! is_array($reference)) {
            return $result;
        }
        if ($reference !== [] && ! isset($reference['latestWkid']) && ! isset($reference['wkid'])) {
            return [...$result, 'status' => 'unsupported'];
        }
        $wkid = (int) ($reference['latestWkid'] ?? $reference['wkid'] ?? 4326);
        if (! in_array($wkid, [4326, 3857, 102100, 102113], true)) {
            return [...$result, 'status' => 'unsupported'];
        }

        $isPolygon = array_key_exists('rings', $geometry);
        $rings = $isPolygon ? $geometry['rings'] : [[[$geometry['x'] ?? null, $geometry['y'] ?? null]]];
        if (! is_array($rings) || $rings === [] || count($rings) > 500) {
            return $result;
        }

        $normalized = [];
        $points = [];
        foreach ($rings as $ring) {
            if (! is_array($ring) || ! array_is_list($ring) || ($isPolygon && count($ring) < 4)) {
                return $result;
            }
            $coordinates = [];
            foreach ($ring as $coordinate) {
                $point = self::coordinate($coordinate, $wkid);
                if ($point === null || count($points) >= 5000) {
                    return $result;
                }
                $coordinates[] = $point;
                $points[] = $point;
            }
            if ($isPolygon && ($coordinates[0] !== $coordinates[array_key_last($coordinates)] || ! self::hasArea($coordinates))) {
                return $result;
            }
            $normalized[] = $coordinates;
        }

        $longitudes = array_column($points, 0);
        $latitudes = array_column($points, 1);
        $center = [(min($longitudes) + max($longitudes)) / 2, (min($latitudes) + max($latitudes)) / 2];

        return [
            'status' => 'valid', 'source' => $source, 'is_polygon' => $isPolygon,
            'geometry' => $isPolygon
                ? ['rings' => $normalized, 'spatialReference' => ['wkid' => 4326]]
                : ['x' => $points[0][0], 'y' => $points[0][1], 'spatialReference' => ['wkid' => 4326]],
            'center' => $center, 'paths' => self::paths($normalized, $center),
            'google_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(round($center[1], 7).','.round($center[0], 7)),
        ];
    }

    /** @return array{0: float, 1: float}|null */
    private static function coordinate(mixed $coordinate, int $wkid): ?array
    {
        if (! is_array($coordinate) || ! isset($coordinate[0], $coordinate[1]) || ! is_numeric($coordinate[0]) || ! is_numeric($coordinate[1])) {
            return null;
        }
        [$x, $y] = [(float) $coordinate[0], (float) $coordinate[1]];
        if (! is_finite($x) || ! is_finite($y)) {
            return null;
        }
        if ($wkid !== 4326) {
            if (abs($x) > 20037508.342789244 || abs($y) > 20037508.342789244) {
                return null;
            }
            [$x, $y] = [rad2deg($x / 6378137), rad2deg(2 * atan(exp($y / 6378137)) - M_PI / 2)];
        }

        return abs($x) <= 180 && abs($y) <= 90 ? [$x, $y] : null;
    }

    /** @param list<array{0: float, 1: float}> $ring */
    private static function hasArea(array $ring): bool
    {
        $area = 0;
        for ($index = 1; $index < count($ring); $index++) {
            $area += ($ring[$index - 1][0] - $ring[0][0]) * ($ring[$index][1] - $ring[0][1])
                - ($ring[$index][0] - $ring[0][0]) * ($ring[$index - 1][1] - $ring[0][1]);
        }

        return abs($area) > 1.0e-16;
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $rings
     * @param  array{0: float, 1: float}  $center
     * @return list<string>
     */
    private static function paths(array $rings, array $center): array
    {
        $ratio = max(0.01, cos(deg2rad($center[1])));
        $points = array_merge(...$rings);
        $width = (max(array_column($points, 0)) - min(array_column($points, 0))) * $ratio;
        $height = max(array_column($points, 1)) - min(array_column($points, 1));
        $scale = min(576 / max($width, 1.0e-12), 296 / max($height, 1.0e-12));

        return array_map(fn (array $ring): string => implode(' ', array_map(
            fn (array $point, int $index): string => ($index === 0 ? 'M' : 'L').round(320 + ($point[0] - $center[0]) * $ratio * $scale, 2).' '.round(180 - ($point[1] - $center[1]) * $scale, 2),
            $ring, array_keys($ring),
        )).' Z', $rings);
    }
}
