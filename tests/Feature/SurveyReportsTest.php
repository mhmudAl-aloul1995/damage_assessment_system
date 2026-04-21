<?php

use App\Models\PublicBuildingSurvey;
use App\Models\PublicBuildingSurveyUnit;
use App\Models\RoadFacilitySurvey;
use App\Models\RoadFacilitySurveyItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

it('shows the public buildings report page with summary metrics and curve chart data', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $firstSurvey = PublicBuildingSurvey::query()->create([
        'objectid' => 1801,
        'building_name' => 'School A',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'building_damage_status' => 'partially_damaged',
        'date_of_damage' => '2026-03-02',
    ]);

    PublicBuildingSurveyUnit::query()->create([
        'public_building_survey_id' => $firstSurvey->id,
        'repeat_index' => 0,
        'unit_name' => 'Ground Floor',
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 1802,
        'building_name' => 'Clinic B',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Camp',
        'building_damage_status' => 'fully_damaged',
        'date_of_damage' => '2026-04-10',
    ]);

    $response = $this->actingAs($viewer)->get(route('reports.public-buildings', [
        'start_date' => '2026-03-01',
        'end_date' => '2026-04-30',
    ]));

    $response->assertOk();
    $response->assertViewHas('reportTitle', 'Public Buildings Report');
    $response->assertViewHas('primaryChart', fn (array $chart) => $chart['title'] === 'Damage Status Distribution');
    $response->assertViewHas('curveChart', fn (array $curveChart) => $curveChart['title'] === 'Daily Public Buildings Curve');
    $response->assertViewHas('summaryCards', function (array $summaryCards) {
        return collect($summaryCards)->contains(fn (array $card) => $card['label'] === 'Total Surveys' && $card['value'] === 2)
            && collect($summaryCards)->contains(fn (array $card) => $card['label'] === 'Damaged Buildings' && $card['value'] === 2)
            && collect($summaryCards)->contains(fn (array $card) => $card['label'] === 'Total Units' && $card['value'] === 1);
    });
    $response->assertViewHas('curveChart', function (array $curveChart) {
        return $curveChart['title'] === 'Daily Public Buildings Curve'
            && in_array('2026-03-02', $curveChart['labels'], true)
            && in_array(1, $curveChart['series'], true);
    });
});

it('shows the road facilities report page with summary metrics and curve chart data', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $firstSurvey = RoadFacilitySurvey::query()->create([
        'objectid' => 2901,
        'str_name' => 'Coastal Road',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'severe',
        'road_access' => 'partial',
        'submission_date' => '2026-03-05 09:30:00',
    ]);

    RoadFacilitySurveyItem::query()->create([
        'road_facility_survey_id' => $firstSurvey->id,
        'repeat_index' => 0,
        'item_required' => 'Signage',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 2902,
        'str_name' => 'North Road',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Camp',
        'road_damage_level' => 'minor',
        'road_access' => 'open',
        'submission_date' => '2026-04-11 13:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 2903,
        'str_name' => 'Fallback Road',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Zeitoun',
        'road_damage_level' => 'moderate',
        'road_access' => 'partial',
        'submission_date' => null,
        'created_at' => '2026-03-05 10:00:00',
        'updated_at' => '2026-03-05 10:00:00',
    ]);

    $response = $this->actingAs($viewer)->get(route('reports.road-facilities', [
        'start_date' => '2026-03-01',
        'end_date' => '2026-04-30',
    ]));

    $response->assertOk();
    $response->assertViewHas('reportTitle', 'Road Facilities Report');
    $response->assertViewHas('primaryChart', fn (array $chart) => $chart['title'] === 'Road Damage Level Distribution');
    $response->assertViewHas('curveChart', fn (array $curveChart) => $curveChart['title'] === 'Daily Road Facilities Curve');
    $response->assertViewHas('summaryCards', function (array $summaryCards) {
        return collect($summaryCards)->contains(fn (array $card) => $card['label'] === 'Total Surveys' && $card['value'] === 3)
            && collect($summaryCards)->contains(fn (array $card) => $card['label'] === 'Damaged Roads' && $card['value'] === 3)
            && collect($summaryCards)->contains(fn (array $card) => $card['label'] === 'Total Items' && $card['value'] === 1);
    });
    $response->assertViewHas('curveChart', function (array $curveChart) {
        return $curveChart['title'] === 'Daily Road Facilities Curve'
            && in_array('2026-03-05', $curveChart['labels'], true)
            && in_array(2, $curveChart['series'], true);
    });
});
