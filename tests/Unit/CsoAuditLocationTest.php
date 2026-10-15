<?php

declare(strict_types=1);

use App\Support\Forms\CsoAuditLocation;

it('renders the provided building polygon without changing its geometry', function (): void {
    $source = '{"rings":[[[34.458237535,31.5250917480001],[34.4583436790001,31.5249942890001],[34.4581663660001,31.5248348750001],[34.4579802010001,31.5250013510001],[34.4580732960001,31.5250931890001],[34.4580958970001,31.5250695230001],[34.458146034,31.525116994],[34.4582027000001,31.525067866],[34.458237535,31.5250917480001]]]}';
    $location = CsoAuditLocation::present($source);
    expect($location['status'])->toBe('valid')->and($location['is_polygon'])->toBeTrue()
        ->and($location['source'])->toBe($source)
        ->and($location['geometry']['rings'])->toEqual(json_decode($source, true)['rings'])
        ->and($location['geometry']['spatialReference']['wkid'])->toBe(4326)
        ->and($location['paths'])->toHaveCount(1)
        ->and($location['paths'][0])->toStartWith('M')->toEndWith(' Z');
    preg_match_all('/[ML]([\d.]+) ([\d.]+)/', $location['paths'][0], $matches);
    foreach ($matches[1] as $index => $x) {
        expect((float) $x)->toBeGreaterThanOrEqual(32)->toBeLessThanOrEqual(608)
            ->and((float) $matches[2][$index])->toBeGreaterThanOrEqual(32)->toBeLessThanOrEqual(328);
    }
    parse_str(parse_url($location['google_url'], PHP_URL_QUERY), $query);
    expect($query['api'])->toBe('1')->and($query['query'])->toBe('31.5249759,34.4581619');
});

it('supports numeric ArcGIS points including zero and supported web mercator references', function (array $source, array $expected): void {
    $location = CsoAuditLocation::present($source);
    expect($location['status'])->toBe('valid')->and($location['is_polygon'])->toBeFalse()
        ->and(round($location['geometry']['x'], 6))->toBe($expected[0])
        ->and(round($location['geometry']['y'], 6))->toBe($expected[1]);
})->with([
    [['x' => 0, 'y' => 0], [0.0, 0.0]],
    [['x' => '34.45', 'y' => '31.52'], [34.45, 31.52]],
    [['x' => 1113194.9079327357, 'y' => 1118889.9748579594, 'spatialReference' => ['wkid' => 3857]], [10.0, 10.0]],
    [['x' => 1113194.9079327357, 'y' => 1118889.9748579594, 'spatialReference' => ['wkid' => 102100]], [10.0, 10.0]],
]);

it('preserves multiple rings and holes for both mapping and the fallback outline', function (): void {
    $rings = [[[34, 31], [35, 31], [35, 32], [34, 31]], [[34.4, 31.2], [34.5, 31.3], [34.6, 31.2], [34.4, 31.2]]];
    $location = CsoAuditLocation::present(['rings' => $rings]);
    expect($location['geometry']['rings'])->toEqual($rings)->and($location['paths'])->toHaveCount(2);
});

it('returns a clear state instead of inventing a location for malformed or missing data', function (mixed $source, string $status): void {
    $location = CsoAuditLocation::present($source);
    expect($location['status'])->toBe($status)->and($location)->not->toHaveKey('geometry')->not->toHaveKey('google_url');
})->with([
    [null, 'missing'], ['', 'missing'], [[], 'missing'],
    ['{invalid-json', 'invalid'], ['null', 'invalid'],
    [['rings' => []], 'invalid'], [['rings' => 'text'], 'invalid'],
    [['rings' => [[[34, 31], [35, 32], [34, 31]]]], 'invalid'],
    [['rings' => [[[34, 31], [35, 31], [35, 32], [34, 32]]]], 'invalid'],
    [['rings' => [[[34, 31], [35, 31], [36, 31], [34, 31]]]], 'invalid'],
    [['x' => 181, 'y' => 31], 'invalid'], [['x' => 34, 'y' => 91], 'invalid'],
    [['x' => INF, 'y' => 31], 'invalid'], [['x' => [], 'y' => 31], 'invalid'],
    [['x' => 34, 'y' => 31, 'spatialReference' => 'invalid'], 'invalid'],
    [['x' => 20037509, 'y' => 0, 'spatialReference' => ['wkid' => 3857]], 'invalid'],
    [['x' => 34, 'y' => 31, 'spatialReference' => ['wkid' => 28191]], 'unsupported'],
    [['x' => 34, 'y' => 31, 'spatialReference' => ['wkt' => 'unknown projection']], 'unsupported'],
    [['rings' => array_fill(0, 501, [[34, 31], [35, 31], [35, 32], [34, 31]])], 'invalid'],
]);
