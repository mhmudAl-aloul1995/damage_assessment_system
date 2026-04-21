<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the public building survey tables with the expected columns', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    expect(Schema::connection('mysql')->hasTable('public_building_surveys'))->toBeTrue();
    expect(Schema::connection('mysql')->hasColumns('public_building_surveys', [
        'location',
        'field_status',
        'objectid',
        'building_name',
        'building_damage_status',
        'benef_type',
        'building_roof_type',
        'comments_recommendations',
        'raw_payload',
    ]))->toBeTrue();

    expect(Schema::connection('mysql')->hasTable('public_building_survey_units'))->toBeTrue();
    expect(Schema::connection('mysql')->hasColumns('public_building_survey_units', [
        'public_building_survey_id',
        'repeat_index',
        'unit_name',
        'damaged_area_m2',
        'select_document',
        'dm1',
        'el25',
        'pv6',
        'final_comments',
        'raw_payload',
    ]))->toBeTrue();
});
