<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the road facility survey tables with the expected columns', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    expect(Schema::connection('mysql')->hasTable('road_facility_surveys'))->toBeTrue();
    expect(Schema::connection('mysql')->hasColumns('road_facility_surveys', [
        'location',
        'field_status',
        'objectid',
        'str_name',
        'road_damage_level',
        'blockage_reason',
        'road_type',
        'submission_date',
        'final_comments',
        'raw_payload',
    ]))->toBeTrue();

    expect(Schema::connection('mysql')->hasTable('road_facility_survey_items'))->toBeTrue();
    expect(Schema::connection('mysql')->hasColumns('road_facility_survey_items', [
        'road_facility_survey_id',
        'repeat_index',
        'item_required',
        'description',
        'unit',
        'quantity',
        'other_comments',
        'raw_payload',
    ]))->toBeTrue();

    expect(Schema::connection('mysql')->hasTable('road_facility_filters'))->toBeTrue();
    expect(Schema::connection('mysql')->hasColumns('road_facility_filters', [
        'list_name',
        'name',
        'label',
        'group_value',
        'sort_order',
    ]))->toBeTrue();
});
