<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('updates total and partial cso survey damage statuses in arcgis', function (): void {
    config()->set('app.url', 'http://localhost:8000');
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.cso_survey_layer_url', 'https://example.com/cso/FeatureServer');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/cso/FeatureServer/0/query*' => Http::sequence()
            ->push(['objectIds' => [101, 102]])
            ->push(['objectIds' => [201]]),
        'https://example.com/cso/FeatureServer/0/updateFeatures' => Http::sequence()
            ->push([
                'updateResults' => [
                    ['objectId' => 101, 'success' => true],
                    ['objectId' => 102, 'success' => true],
                ],
            ])
            ->push([
                'updateResults' => [
                    ['objectId' => 201, 'success' => true],
                ],
            ]),
    ]);

    $exitCode = Artisan::call('arcgis:normalize-cso-survey-damage-status');

    expect($exitCode)->toBe(0);

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://example.com/cso/FeatureServer/0/updateFeatures') {
            return false;
        }

        $features = json_decode((string) $request['features'], true);

        return data_get($features, '0.attributes.objectid') === 101
            && data_get($features, '0.attributes.building_damage_status') === '1';
    });

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://example.com/cso/FeatureServer/0/updateFeatures') {
            return false;
        }

        $features = json_decode((string) $request['features'], true);

        return data_get($features, '0.attributes.objectid') === 201
            && data_get($features, '0.attributes.building_damage_status') === '2';
    });
});
