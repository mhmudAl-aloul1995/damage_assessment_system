<?php

use App\Models\CsoSurveyUnit;
use App\Models\PublicBuildingSurvey;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

it('uses the configured public building referer in sync arcgis layers command', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);

    if (! Schema::connection('mysql')->hasColumn('public_building_surveys', 'arcgis_hash')) {
        Schema::connection('mysql')->table('public_building_surveys', function (Blueprint $table): void {
            $table->string('arcgis_hash', 64)->nullable();
        });
    }

    if (! Schema::connection('mysql')->hasColumn('public_building_surveys', 'arcgis_synced_at')) {
        Schema::connection('mysql')->table('public_building_surveys', function (Blueprint $table): void {
            $table->timestamp('arcgis_synced_at')->nullable();
        });
    }

    config()->set('app.url', 'http://localhost:8000');
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.public_building_survey_layer_url', 'https://example.com/FeatureServer');
    config()->set('services.arcgis.public_building_survey_referer', 'https://example.com/FeatureServer/0');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => function ($request) {
            expect($request['referer'])->toBe('https://example.com/FeatureServer/0');
            expect($request['username'])->toBe('tester');
            expect($request['password'])->toBe('secret');

            return Http::response([
                'token' => 'arcgis-token',
            ]);
        },
        'https://example.com/FeatureServer/0/query*' => function ($request) {
            expect($request['returnGeometry'])->toBe('true');

            return Http::response([
                'features' => [
                    [
                        'attributes' => [
                            'objectid' => 8801,
                            'building_name' => 'Community Hall',
                            'building_damage_status' => 'fully_damaged',
                            'benef_type' => 'all',
                        ],
                        'geometry' => [
                            'rings' => [[[34.1, 31.5], [34.2, 31.5], [34.2, 31.6], [34.1, 31.5]]],
                        ],
                    ],
                ],
                'exceededTransferLimit' => false,
            ]);
        },
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', [
        'table' => 'public_building_surveys',
    ]);

    expect($exitCode)->toBe(0);

    $survey = PublicBuildingSurvey::withoutGlobalScopes()->where('objectid', 8801)->first();

    expect($survey)->not->toBeNull();
    expect($survey->building_name)->toBe('Community Hall');
    expect($survey->building_damage_status)->toBe('fully_damaged');
    expect($survey->benef_type)->toBe(['all']);
    expect($survey->location)->toContain('rings');
});

it('uses the configured cso unit layer url when syncing cso survey units', function (): void {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);

    config()->set('app.url', 'http://localhost:8000');
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.cso_survey_layer_url', 'https://example.com/wrong/FeatureServer');
    config()->set('services.arcgis.cso_survey_units_layer_url', 'https://example.com/cso-units/FeatureServer/2');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/cso-units/FeatureServer/2/addToDefinition*' => Http::response(['success' => true]),
        'https://example.com/cso-units/FeatureServer/2/calculate*' => Http::response(['success' => true]),
        'https://example.com/cso-units/FeatureServer/2' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'globalid', 'type' => 'esriFieldTypeString'],
                ['name' => 'parentglobalid', 'type' => 'esriFieldTypeString'],
                ['name' => 'unit_name', 'type' => 'esriFieldTypeString'],
            ],
        ]),
        'https://example.com/cso-units/FeatureServer/2/query*' => Http::sequence()
            ->push([
                'features' => [
                    [
                        'attributes' => [
                            'objectid' => 9901,
                            'globalid' => '{CSO-UNIT-GLOBAL-ID}',
                            'parentglobalid' => '{CSO-SURVEY-PARENT-ID}',
                            'unit_name' => 'Imported CSO Unit',
                        ],
                    ],
                ],
                'exceededTransferLimit' => false,
            ])
            ->push([
                'features' => [],
                'exceededTransferLimit' => false,
            ]),
        'https://example.com/wrong/FeatureServer/2*' => Http::response(['error' => ['message' => 'Wrong URL']], 500),
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', [
        'table' => 'cso_survey_units',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);

    $unit = CsoSurveyUnit::query()->where('objectid', 9901)->first();

    expect($unit)->not->toBeNull()
        ->and($unit->globalid)->toBe('cso-unit-global-id')
        ->and($unit->parentglobalid)->toBe('cso-survey-parent-id')
        ->and($unit->unit_name)->toBe('Imported CSO Unit');
});
