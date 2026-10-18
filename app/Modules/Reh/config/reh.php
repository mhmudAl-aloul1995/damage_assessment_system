<?php

return [
    'service_url' => env('REH_ARCGIS_SERVICE_URL', 'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/REH_Phase2_2026_PHC_Pro/FeatureServer'),
    'buildings_layer' => 0,
    'units_layer' => 1,
    'username' => env('REH_ARCGIS_USERNAME', env('ARCGIS_USERNAME')),
    'password' => env('REH_ARCGIS_PASSWORD', env('ARCGIS_PASSWORD')),
    'referer' => env('REH_ARCGIS_REFERER', env('ARCGIS_REFERER', env('APP_URL'))),
    'queue_connection' => env('REH_QUEUE_CONNECTION', 'reh'),
];
