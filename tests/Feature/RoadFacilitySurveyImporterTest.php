<?php

use App\Models\RoadFacilitySurvey;
use App\services\RoadFacilitySurveyImporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('imports a road facility survey payload with repeated items', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    $importer = app(RoadFacilitySurveyImporter::class);

    $survey = $importer->import([
        'objectid' => 8101,
        'Field_status' => 'COMPLETED',
        'Str_Name' => 'Al Rasheed Road',
        'road_damage_level' => 'severe',
        'road_access' => 'partial',
        'blockage_reason' => ['rubble', 'craters'],
        'road_type' => ['water_network', 'sewer_network'],
        'submissionDate' => '2026-04-14 09:30:00',
        'R2' => [
            [
                'item_required' => 'Traffic light replacement',
                'description' => 'Main junction light damaged',
                'unit_001' => 'item',
                'quantity_001' => 2,
                'other_comments' => 'Urgent restoration needed',
            ],
        ],
    ]);

    expect($survey)->toBeInstanceOf(RoadFacilitySurvey::class);
    expect($survey->objectid)->toBe(8101);
    expect($survey->str_name)->toBe('Al Rasheed Road');
    expect($survey->blockage_reason)->toBe(['rubble', 'craters']);
    expect($survey->road_type)->toBe(['water_network', 'sewer_network']);
    expect($survey->items)->toHaveCount(1);
    expect($survey->items->first()->item_required)->toBe('Traffic light replacement');
    expect($survey->items->first()->unit)->toBe('item');
    expect($survey->items->first()->quantity)->toBe(2);
});
