<?php

use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    app(RolesAndPermissionsSeeder::class)->run();
});

it('renders separated area productivity reports for all supported datasets with filtering', function () {
    $user = User::factory()->create();
    $user->assignRole('Database Officer');

    Building::query()->create([
        'objectid' => 1001,
        'globalid' => 'building-1',
        'building_name' => 'Building 1',
        'assignedto' => 'eng-1',
        'building_damage_status' => 'fully_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-1',
        'creationdate' => '2026-04-10 10:00:00',
    ]);

    Building::query()->create([
        'objectid' => 1002,
        'globalid' => 'building-2',
        'building_name' => 'Building 2',
        'assignedto' => 'eng-1',
        'building_damage_status' => 'partially_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-9',
        'creationdate' => '2026-04-11 10:00:00',
    ]);

    Building::query()->create([
        'objectid' => 1003,
        'globalid' => 'building-3',
        'building_name' => 'Building 3',
        'assignedto' => 'eng-2',
        'building_damage_status' => 'committee_review',
        'governorate' => 'North Gaza',
        'municipalitie' => 'Jabalia',
        'neighborhood' => 'Camp',
        'zone_code' => 'Z-2',
        'creationdate' => '2026-04-11 10:00:00',
    ]);

    Building::query()->create([
        'objectid' => 1004,
        'globalid' => 'building-4',
        'building_name' => 'Building 4',
        'assignedto' => 'eng-3',
        'building_damage_status' => 'partially_damaged',
        'governorate' => null,
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-7',
        'creationdate' => '2026-04-12 10:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 2001,
        'globalid' => 'housing-1',
        'parentglobalid' => 'building-1',
        'unit_damage_status' => 'fully_damaged2',
        'creationdate' => '2026-04-10 12:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 2002,
        'globalid' => 'housing-2',
        'parentglobalid' => 'building-1',
        'unit_damage_status' => 'partially_damaged2',
        'creationdate' => '2026-04-10 13:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 2003,
        'globalid' => 'housing-3',
        'parentglobalid' => 'building-2',
        'unit_damage_status' => 'committee_review2',
        'creationdate' => '2026-04-11 13:00:00',
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 3001,
        'building_name' => 'School A',
        'assigned_to' => 'eng-1',
        'building_damage_status' => 'fully_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'created_at' => '2026-04-10 09:00:00',
        'updated_at' => '2026-04-10 09:00:00',
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 3002,
        'building_name' => 'School B',
        'assigned_to' => 'eng-2',
        'building_damage_status' => 'committee_review',
        'governorate' => 'North Gaza',
        'municipalitie' => 'Jabalia',
        'neighborhood' => 'Camp',
        'created_at' => '2026-04-10 09:00:00',
        'updated_at' => '2026-04-10 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4001,
        'str_name' => 'Street A',
        'assigned_to' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'destroyed',
        'zone_code' => 'RZ-1',
        'created_at' => '2026-04-10 09:00:00',
        'updated_at' => '2026-04-10 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4002,
        'str_name' => 'Street B',
        'assigned_to' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'moderate',
        'zone_code' => 'RZ-2',
        'created_at' => '2026-04-11 09:00:00',
        'updated_at' => '2026-04-11 09:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('reports.area-productivity.housing-units', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'assignedto' => 'eng-1',
        ]))
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.housing_units'), false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('Grand Totals:', false)
        ->assertSee('3', false)
        ->assertSee('1', false);

    $this->actingAs($user)
        ->get(route('reports.area-productivity.buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'municipalitie' => 'Gaza',
        ]))
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.buildings'), false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('3', false)
        ->assertDontSee('<td>Camp</td>', false)
        ->assertSee('Grand Totals:', false)
        ->assertSee(__('multilingual.area_productivity_reports.sectors.buildings'), false)
        ->assertSeeInOrder(['<td>Gaza</td>', '<td>Buildings</td>'], false);

    $this->actingAs($user)
        ->get(route('reports.area-productivity.public-buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'assignedto' => 'eng-1',
        ]))
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.public_buildings'), false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('1', false)
        ->assertDontSee('<td>Camp</td>', false)
        ->assertSee('Grand Totals:', false);

    $this->actingAs($user)
        ->get(route('reports.area-productivity.road-facilities', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'assignedto' => 'eng-1',
        ]))
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.road_facilities'), false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('2', false)
        ->assertSee('Grand Totals:', false)
        ->assertSee(__('multilingual.area_productivity_reports.sectors.road_facilities'), false);

    $this->actingAs($user)
        ->get(route('reports.area-productivity.export.buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))
        ->assertOk();
});
