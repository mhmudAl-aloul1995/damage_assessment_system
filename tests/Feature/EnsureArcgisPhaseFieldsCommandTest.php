<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('ensures phase number fields for configured arcgis layers', function (): void {
    Cache::forget('arcgis_token');

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.buildings_url', 'https://example.com/arcgis/rest/services/Buildings/FeatureServer/0');
    config()->set('services.arcgis.housing_units_url', 'https://example.com/arcgis/rest/services/Buildings/FeatureServer/1');
    config()->set('services.arcgis.public_building_survey_layer_url', 'https://example.com/arcgis/rest/services/PublicBuildings/FeatureServer');
    config()->set('services.arcgis.public_building_survey_units_layer_url', 'https://example.com/arcgis/rest/services/PublicBuildings/FeatureServer/1');
    config()->set('services.arcgis.road_facility_survey_layer_url', 'https://example.com/arcgis/rest/services/Roads/FeatureServer');
    config()->set('services.arcgis.road_facility_survey_items_layer_url', 'https://example.com/arcgis/rest/services/Roads/FeatureServer/1');
    config()->set('services.arcgis.cso_survey_layer_url', 'https://example.com/arcgis/rest/services/Cso/FeatureServer');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/*/addToDefinition' => Http::response(['success' => true]),
        'https://example.com/*/calculate' => Http::response(['success' => true]),
        'https://example.com/*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
            ],
        ]),
    ]);

    expect(Artisan::call('arcgis:ensure-phase-fields'))->toBe(0);

    Http::assertSentCount(28);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.com/arcgis/admin/services/Cso.FeatureServer/2/addToDefinition');
});
