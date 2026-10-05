<?php

declare(strict_types=1);

use App\Console\Commands\SyncArcGISLayers;

test('cso arcgis string metadata columns use text to avoid mysql row size limits', function (): void {
    $command = new SyncArcGISLayers;
    $method = new ReflectionMethod($command, 'laravelColumnTypeForArcgisField');

    $field = [
        'name' => 'garage_type',
        'type' => 'esriFieldTypeString',
        'length' => 255,
    ];

    expect($method->invoke($command, $field, 'cso_surveys'))->toBe('text')
        ->and($method->invoke($command, $field, 'buildings'))->toBe('string');
});
