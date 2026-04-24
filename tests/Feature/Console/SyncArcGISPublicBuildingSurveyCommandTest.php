<?php

use App\Models\PublicBuildingSurvey;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('syncs public building survey records from ArcGIS feature server root url', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.public_building_survey_layer_url', 'https://example.com/FeatureServer');
    config()->set('services.arcgis.public_building_survey_referer', null);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer/0/query*' => Http::response([
            'features' => [
                [
                    'attributes' => [
                        'objectid' => 9901,
                        'globalid' => '{PUB-9901}',
                        'Field_status' => 'COMPLETED',
                        'building_name' => 'Public Clinic',
                        'building_damage_status' => 'partially_damaged',
                        'occupied_stakeholders' => 'Yes',
                        'Is_displaced' => 'No',
                        'building_boundaries' => 'North/South',
                        'CreationDate' => 1713960000000,
                        'EditDate' => 1714046400000,
                        'Creator' => 'arcgis.editor',
                        'Editor' => 'arcgis.reviewer',
                        'Comments_Recommendations' => 'Repair the facade',
                    ],
                    'geometry' => [
                        'rings' => [[[34.1, 31.5], [34.2, 31.5], [34.2, 31.6], [34.1, 31.5]]],
                    ],
                ],
            ],
            'exceededTransferLimit' => false,
        ]),
    ]);

    $exitCode = Artisan::call('sync:public-building-survey', ['--days' => 2]);

    expect($exitCode)->toBe(0);

    $survey = PublicBuildingSurvey::query()->where('objectid', 9901)->first();

    expect($survey)->not->toBeNull();
    expect($survey->building_name)->toBe('Public Clinic');
    expect($survey->globalid)->toBe('{PUB-9901}');
    expect($survey->building_damage_status)->toBe('partially_damaged');
    expect($survey->occupied_stakeholders)->toBe('Yes');
    expect($survey->is_displaced)->toBe('No');
    expect($survey->building_boundaries)->toBe('North/South');
    expect($survey->creator)->toBe('arcgis.editor');
    expect($survey->editor)->toBe('arcgis.reviewer');
    expect($survey->creationdate)->not->toBeNull();
    expect($survey->editdate)->not->toBeNull();
    expect($survey->comments_recommendations)->toBe('Repair the facade');
    expect($survey->location)->toContain('rings');
});
