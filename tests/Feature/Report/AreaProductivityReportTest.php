<?php

use App\Exports\AreaProductivityExport;
use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use App\Services\DamageAssessment\Reports\AreaProductivityReportService;
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

    Building::query()->create([
        'objectid' => 1005,
        'globalid' => 'building-unclassified',
        'building_name' => 'Building Unclassified',
        'assignedto' => 'eng-4',
        'building_damage_status' => 'no_damage',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-8',
        'creationdate' => '2026-04-13 10:00:00',
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

    HousingUnit::query()->create([
        'objectid' => 2004,
        'globalid' => 'housing-unclassified',
        'parentglobalid' => 'building-2',
        'unit_damage_status' => 'no_damage2',
        'creationdate' => '2026-04-11 14:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 2005,
        'globalid' => 'housing-for-unclassified-building',
        'parentglobalid' => 'building-unclassified',
        'unit_damage_status' => 'partially_damaged2',
        'creationdate' => '2026-04-13 14:00:00',
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 3001,
        'building_name' => 'School A',
        'assignedto' => 'eng-1',
        'building_damage_status' => 'fully_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'creationdate' => '2026-04-10 09:00:00',
        'created_at' => '2026-04-10 09:00:00',
        'updated_at' => '2026-04-10 09:00:00',
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 3002,
        'building_name' => 'School B',
        'assignedto' => 'eng-2',
        'building_damage_status' => 'committee_review',
        'governorate' => 'North Gaza',
        'municipalitie' => 'Jabalia',
        'neighborhood' => 'Camp',
        'creationdate' => '2026-04-10 09:00:00',
        'created_at' => '2026-04-10 09:00:00',
        'updated_at' => '2026-04-10 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4001,
        'str_name' => 'Street A',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'destroyed',
        'zone_code' => 'RZ-1',
        'creationdate' => '2026-04-10 09:00:00',
        'created_at' => '2026-04-10 09:00:00',
        'updated_at' => '2026-04-10 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4002,
        'str_name' => 'Street B',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'moderate',
        'zone_code' => 'RZ-2',
        'creationdate' => '2026-04-11 09:00:00',
        'created_at' => '2026-04-11 09:00:00',
        'updated_at' => '2026-04-11 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4003,
        'str_name' => 'Street C',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'severe',
        'zone_code' => 'RZ-3',
        'creationdate' => '2026-04-12 09:00:00',
        'created_at' => '2026-04-12 09:00:00',
        'updated_at' => '2026-04-12 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4004,
        'str_name' => 'Street D',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'minor',
        'zone_code' => 'RZ-4',
        'creationdate' => '2026-04-13 09:00:00',
        'created_at' => '2026-04-13 09:00:00',
        'updated_at' => '2026-04-13 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4005,
        'str_name' => 'Street E',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'No_Damage',
        'zone_code' => 'RZ-5',
        'creationdate' => '2026-04-14 09:00:00',
        'created_at' => '2026-04-14 09:00:00',
        'updated_at' => '2026-04-14 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4006,
        'str_name' => 'Street F',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'not_classified',
        'zone_code' => 'RZ-6',
        'creationdate' => '2026-04-15 09:00:00',
        'created_at' => '2026-04-15 09:00:00',
        'updated_at' => '2026-04-15 09:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('reports.area-productivity.housing-units', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'assignedto' => 'eng-1',
        ]))
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.housing_units'), false)
        ->assertSee('Location Pie Charts')
        ->assertSee('area-productivity-table-tab', false)
        ->assertSee('area-productivity-location-charts-tab', false)
        ->assertSee('area-productivity-location-charts-pane', false)
        ->assertSee('Municipality | 2 housing units')
        ->assertSee('Neighborhoods under Gaza')
        ->assertSee('Totally Damaged')
        ->assertSee('Partially Damaged')
        ->assertSee('location-pie-section-toggle', false)
        ->assertSee('location-pie-card', false)
        ->assertSee('housing_units_municipality', false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('Grand Totals', false)
        ->assertSee('3', false)
        ->assertSee('1', false)
        ->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_records'] === 3
                && $summary['tda'] === 1
                && $summary['pda'] === 1
                && $summary['cra'] === 1;
        })
        ->assertViewHas('charts', function (array $charts): bool {
            $municipalityNode = $charts['location_pies'][0] ?? null;

            return $municipalityNode !== null
                && $municipalityNode['pie']['title'] === 'Gaza'
                && $municipalityNode['pie']['series'] === [1, 1]
                && $municipalityNode['pie']['units_count'] === 2
                && count($municipalityNode['neighborhoods']) === 1
                && $municipalityNode['neighborhoods'][0]['title'] === 'Rimal';
        });

    $buildingResponse = $this->actingAs($user)
        ->get(route('reports.area-productivity.buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'municipalitie' => 'Gaza',
        ]));

    $buildingResponse
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.buildings'), false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('3', false)
        ->assertDontSee('<th>'.__('multilingual.area_productivity_reports.columns.housing_units_count').'</th>', false)
        ->assertDontSee('<td>Camp</td>', false)
        ->assertSee('Grand Totals', false)
        ->assertSee(__('multilingual.area_productivity_reports.sectors.buildings'), false)
        ->assertSeeInOrder(['<td>Gaza</td>', '<td>Buildings</td>'], false);

    $buildingResponse->assertViewHas('summary', function (array $summary): bool {
        return $summary['total_records'] === 3
            && $summary['tda'] === 1
            && $summary['pda'] === 2
            && $summary['cra'] === 0
            && $summary['housing_units_count'] === 4;
    });

    $buildingResponse->assertViewHas('rows', function ($rows): bool {
        $rimal = $rows->firstWhere('neighborhood', 'Rimal');

        return $rimal !== null
            && (int) $rimal->total_count === 3
            && (int) $rimal->tda_range === 1
            && (int) $rimal->pda_range === 2
            && (int) $rimal->cra_range === 0
            && (int) $rimal->housing_units_count === 4;
    });

    $publicBuildingsResponse = $this->actingAs($user)
        ->get(route('reports.area-productivity.public-buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'assignedto' => 'eng-1',
        ]));

    $publicBuildingsResponse
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.public_buildings'), false)
        ->assertSee('Location Pie Charts')
        ->assertSee('Municipality | 1 public buildings')
        ->assertSee('public_buildings_municipality', false)
        ->assertDontSee('public_buildings_neighborhood', false)
        ->assertDontSee('Neighborhoods under Gaza')
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('1', false)
        ->assertDontSee('<td>Camp</td>', false)
        ->assertSee('Grand Totals', false);

    $publicBuildingsResponse->assertViewHas('charts', function (array $charts): bool {
        $municipalityNode = $charts['location_pies'][0] ?? null;

        return $municipalityNode !== null
            && $municipalityNode['pie']['title'] === 'Gaza'
            && $municipalityNode['pie']['series'] === [1, 0]
            && $municipalityNode['pie']['items_count'] === 1
            && count($municipalityNode['neighborhoods']) === 0;
    });

    $roadFacilitiesResponse = $this->actingAs($user)
        ->get(route('reports.area-productivity.road-facilities', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'assignedto' => 'eng-1',
        ]));

    $roadFacilitiesResponse
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.road_facilities'), false)
        ->assertSee('Location Pie Charts')
        ->assertSee('Municipality | 5 road facilities')
        ->assertSee('road_facilities_municipality', false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.destroyed'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.severe'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.moderate'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.minor'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.no_damage'), false)
        ->assertDontSee(__('multilingual.area_productivity_reports.columns.cra'), false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('5', false)
        ->assertSee('Grand Totals', false)
        ->assertSee(__('multilingual.area_productivity_reports.sectors.road_facilities'), false);

    $roadFacilitiesResponse->assertViewHas('summary', function (array $summary): bool {
        return $summary['total_records'] === 5;
    });

    $roadFacilitiesResponse->assertViewHas('rows', function ($rows): bool {
        $rimal = $rows->firstWhere('neighborhood', 'Rimal');

        return $rimal !== null
            && (int) $rimal->total_count === 5
            && (int) $rimal->destroyed_count === 1
            && (int) $rimal->severe_count === 1
            && (int) $rimal->moderate_count === 1
            && (int) $rimal->minor_count === 1
            && (int) $rimal->no_damage_count === 1;
    });

    $roadFacilitiesResponse->assertViewHas('charts', function (array $charts): bool {
        $municipalityNode = $charts['location_pies'][0] ?? null;

        return $municipalityNode !== null
            && $municipalityNode['pie']['title'] === 'Gaza'
            && $municipalityNode['pie']['series'] === [1, 1, 1, 1, 1]
            && $municipalityNode['pie']['labels'] === ['Destroyed', 'Severe', 'Moderate', 'Minor', 'No Damage']
            && $municipalityNode['pie']['colors'] === ['#F1416C', '#E879F9', '#FFC700', '#009EF7', '#50CD89']
            && array_column($municipalityNode['pie']['summary_items'], 'color') === ['#F1416C', '#E879F9', '#FFC700', '#009EF7', '#50CD89']
            && $municipalityNode['pie']['items_count'] === 5
            && count($municipalityNode['neighborhoods']) === 1;
    });

    $this->actingAs($user)
        ->get(route('reports.area-productivity.road-facilities', [
            'assignedto' => 'eng-1',
        ]))
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.road_facilities').':', false)
        ->assertSee('All')
        ->assertViewHas('date_range_label', 'All');

    $this->actingAs($user)
        ->get(route('reports.area-productivity.export.buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('reports.area-productivity.export.road-facilities', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))
        ->assertOk();

    $reportService = app(AreaProductivityReportService::class);
    $exportRows = $reportService->exportRows(AreaProductivityReportService::TYPE_ROAD_FACILITIES, [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-30',
    ]);
    $export = new AreaProductivityExport(
        $exportRows,
        '2026-04-01',
        '2026-04-30',
        __('multilingual.area_productivity_reports.titles.road_facilities'),
        __('multilingual.area_productivity_reports.sectors.road_facilities'),
        AreaProductivityReportService::TYPE_ROAD_FACILITIES,
    );
    $exportCollection = $export->collection();

    expect($export->map($exportCollection->firstWhere('neighborhood', 'Rimal')))->toBe([
        5,
        1,
        1,
        1,
        1,
        1,
        1,
        'Rimal',
        'Gaza',
        'Gaza',
        __('multilingual.area_productivity_reports.sectors.road_facilities'),
    ]);
    expect($export->map($exportCollection->last()))->toBe([
        5,
        1,
        1,
        1,
        1,
        1,
        1,
        '',
        '',
        '',
        __('multilingual.area_productivity_reports.labels.grand_totals'),
    ]);
});
