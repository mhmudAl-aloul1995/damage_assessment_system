<?php

use App\Modules\DamageAssessment\Services\Reports\phcPdfReportService;
use Illuminate\Support\Collection;

test('phc report arcgis map uses the selected area bounds', function () {
    $service = new phcPdfReportService;
    $method = new ReflectionMethod($service, 'arcgisMap');
    $method->setAccessible(true);

    $firstBuilding = (object) [
        'latitude' => 31.34,
        'longitude' => 34.30,
        'building_damage_status' => 'fully_damaged',
    ];
    $secondBuilding = (object) [
        'latitude' => 31.38,
        'longitude' => 34.36,
        'building_damage_status' => 'partially_damaged',
    ];

    $map = $method->invoke($service, new Collection([$firstBuilding, $secondBuilding]), 'Test Area');

    parse_str((string) parse_url($map['image_url'], PHP_URL_QUERY), $query);

    expect($query['bbox'])->toBe('34.285,31.325,34.375,31.395')
        ->and($map['has_points'])->toBeTrue()
        ->and($map['points'])->toHaveCount(2)
        ->and($map['points'][0]['color'])->toBe('#f58220')
        ->and($map['points'][1]['color'])->toBe('#16a6d9');
});
