<?php

use App\Models\PublicBuildingSurvey;
use App\services\PublicBuildingSurveyImporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('imports a public building survey payload with repeated units', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    $importer = app(PublicBuildingSurveyImporter::class);

    $survey = $importer->import([
        'objectid' => 7001,
        'Field_status' => 'COMPLETED',
        'building_name' => 'Municipality HQ',
        'building_damage_status' => 'partially_damaged',
        'benef_type' => ['staff', 'visitors'],
        'building_roof_type' => ['concrete', 'clay_tile'],
        'Comments_Recommendations' => 'Needs structural review',
        'Unit_Information' => [
            [
                'unit_name' => 'First Floor',
                'Occupied' => 'yes',
                'Damaged_Area_m2' => 125.5,
                'select_document' => ['ID_photo', 'municipal_permit'],
                'DM1' => 10,
                'EL25' => 2,
                'PV6' => 220.5,
                'final_comments' => 'Repair doors and electrical fixtures',
            ],
        ],
    ]);

    expect($survey)->toBeInstanceOf(PublicBuildingSurvey::class);
    expect($survey->objectid)->toBe(7001);
    expect($survey->building_name)->toBe('Municipality HQ');
    expect($survey->benef_type)->toBe(['staff', 'visitors']);
    expect($survey->building_roof_type)->toBe(['concrete', 'clay_tile']);
    expect($survey->units)->toHaveCount(1);
    expect($survey->units->first()->unit_name)->toBe('First Floor');
    expect((float) $survey->units->first()->damaged_area_m2)->toBe(125.5);
    expect($survey->units->first()->select_document)->toBe(['ID_photo', 'municipal_permit']);
    expect((float) $survey->units->first()->dm1)->toBe(10.0);
    expect((float) $survey->units->first()->el25)->toBe(2.0);
    expect((float) $survey->units->first()->pv6)->toBe(220.5);
});
