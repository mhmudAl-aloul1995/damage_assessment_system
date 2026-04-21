<?php

use App\Models\RoadFacilitySurvey;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('auto-detects a populated layer from the feature server root url and syncs road facility records', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.road_facility_survey_layer_url', 'https://example.com/FeatureServer');
    config()->set('services.arcgis.road_facility_survey_referer', null);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer?*' => Http::response([
            'layers' => [
                ['id' => 0],
                ['id' => 1],
            ],
            'tables' => [],
        ]),
        'https://example.com/FeatureServer/0/query*' => Http::response(['count' => 0]),
        'https://example.com/FeatureServer/1/query*' => function ($request) {
            $data = $request->data();

            if (($data['returnCountOnly'] ?? null) === 'true') {
                return Http::response(['count' => 1]);
            }

            return Http::response([
                'features' => [
                    [
                        'attributes' => [
                            'objectid' => 9101,
                            'Field_status' => 'COMPLETED',
                            'Str_Name' => 'Salah Al Din Road',
                            'road_damage_level' => 'moderate',
                            'final_comments' => 'Needs asphalt repair',
                        ],
                        'geometry' => [
                            'paths' => [[[34.1, 31.5], [34.2, 31.6]]],
                        ],
                    ],
                ],
                'exceededTransferLimit' => false,
            ]);
        },
    ]);

    $exitCode = Artisan::call('sync:road-facility-survey', ['--all' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Using detected layer: 1');
    expect($output)->toContain('ArcGIS matched records count: 1');

    $survey = RoadFacilitySurvey::query()->where('objectid', 9101)->first();

    expect($survey)->not->toBeNull();
    expect($survey->str_name)->toBe('Salah Al Din Road');
    expect($survey->road_damage_level)->toBe('moderate');
    expect($survey->final_comments)->toBe('Needs asphalt repair');
    expect($survey->location)->toContain('paths');
});

it('supports syncing an explicit road facility layer with the layer option', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.road_facility_survey_layer_url', 'https://example.com/FeatureServer');
    config()->set('services.arcgis.road_facility_survey_referer', null);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer/2/query*' => function ($request) {
            $data = $request->data();

            if (($data['returnCountOnly'] ?? null) === 'true') {
                expect($data['where'])->toBe('1=1');

                return Http::response(['count' => 1]);
            }

            expect($data['where'])->toBe('1=1');

            return Http::response([
                'features' => [
                    [
                        'attributes' => [
                            'objectid' => 9102,
                            'Str_Name' => 'Al Bahr Road',
                        ],
                    ],
                ],
                'exceededTransferLimit' => false,
            ]);
        },
    ]);

    $exitCode = Artisan::call('sync:road-facility-survey', ['--all' => true, '--layer' => 2]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Using query URL: https://example.com/FeatureServer/2/query');
    expect(RoadFacilitySurvey::query()->where('objectid', 9102)->exists())->toBeTrue();
});

it('reports zero matched records clearly so another layer can be tried', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.road_facility_survey_layer_url', 'https://example.com/FeatureServer');
    config()->set('services.arcgis.road_facility_survey_referer', null);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer?*' => Http::response([
            'layers' => [
                ['id' => 0],
                ['id' => 1],
            ],
            'tables' => [],
        ]),
        'https://example.com/FeatureServer/0/query*' => Http::response(['count' => 0]),
        'https://example.com/FeatureServer/1/query*' => Http::response(['count' => 0]),
    ]);

    $exitCode = Artisan::call('sync:road-facility-survey', ['--all' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('ArcGIS matched records count: 0');
    expect($output)->toContain('Try an explicit layer');
    expect(RoadFacilitySurvey::query()->count())->toBe(0);
});
