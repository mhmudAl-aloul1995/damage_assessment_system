<?php

use App\Models\RoadFacilityFilter;
use App\Models\RoadFacilitySurvey;
use App\Models\RoadFacilitySurveyItem;
use App\Models\User;
use Database\Seeders\RoadFacilityFilterSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('shows the road facility survey page with all dynamic road filters and exports', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    app(RoadFacilityFilterSeeder::class)->run();

    Carbon::setTestNow('2026-04-14 11:30:00');

    $user = User::factory()->create();

    $survey = RoadFacilitySurvey::query()->create([
        'objectid' => 9201,
        'governorate' => 'Gaza',
        'str_name' => 'Coastal Road',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'assigned_to' => 'Engineer Roads',
        'road_damage_level' => 'severe',
        'road_access' => 'partial',
        'lane_count' => 'two',
        'blockage_reason' => ['debris'],
        'traffic_signs_type' => ['guide'],
        'demolition_scope' => 'whole',
        'pole_material' => 'galvanized',
        'pole_voltage_level' => 'high',
        'submission_date' => '2026-03-05 09:30:00',
        'final_comments' => 'Clear rubble and restore asphalt',
        'raw_payload' => ['network_item' => 'pipe'],
    ]);

    RoadFacilitySurveyItem::query()->create([
        'road_facility_survey_id' => $survey->id,
        'repeat_index' => 0,
        'item_required' => 'Traffic sign replacement',
        'description' => 'Damaged stop sign',
        'unit' => 'item',
        'quantity' => 3,
        'other_comments' => 'Replace immediately',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 9202,
        'governorate' => 'North',
        'str_name' => 'Northern Street',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Camp',
        'assigned_to' => 'Engineer Other',
        'road_damage_level' => 'minor',
        'road_access' => 'open',
        'lane_count' => 'one',
        'blockage_reason' => ['landfill'],
        'traffic_signs_type' => ['warning'],
        'demolition_scope' => 'partial',
        'pole_material' => 'wooden',
        'pole_voltage_level' => 'low',
        'submission_date' => '2026-04-11 13:00:00',
        'raw_payload' => ['network_item' => 'fittings'],
    ]);

    $indexResponse = $this->actingAs($user)->get(route('road-facilities.index'));
    $indexResponse->assertOk();
    $indexResponse->assertSee('Road Facilities Filters');
    $indexResponse->assertSee('Select municipality');
    $indexResponse->assertSee('Select neighborhood');
    $indexResponse->assertSee('Select traffic signs type');
    $indexResponse->assertSee('Neighborhood');
    $indexResponse->assertSee('Rimal');
    $indexResponse->assertSee('Camp');
    $indexResponse->assertSee('Traffic Signs Type');
    $indexResponse->assertSee('Demolition Type');
    $indexResponse->assertSee('Pole Material');
    $indexResponse->assertSee('Voltage Level');
    $indexResponse->assertSee('Guide signs');
    $indexResponse->assertSee('Whole');
    $indexResponse->assertSee('Galvanized steel pole');
    $indexResponse->assertSee('High voltage');

    $dataResponse = $this->actingAs($user)->get(route('road-facilities.data', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'filters' => [
            'traffic_signs_type' => 'guide',
            'demolition_type' => 'whole',
            'pole_material' => 'galvanized',
            'voltage_level' => 'high',
            'governorate' => 'Gaza',
        ],
        'from_date' => '2026-03-01',
        'to_date' => '2026-03-31',
    ]));
    $dataResponse->assertOk();
    $dataResponse->assertSee('Coastal Road');
    $dataResponse->assertDontSee('Northern Street');

    $csvResponse = $this->actingAs($user)->get(route('road-facilities.export', [
        'format' => 'csv',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'filters' => [
            'traffic_signs_type' => 'guide',
            'demolition_type' => 'whole',
            'pole_material' => 'galvanized',
        ],
    ]));
    $csvResponse->assertOk();
    $csvResponse->assertHeader('content-disposition', 'attachment; filename=road_facilities_20260414_113000.csv');

    $showResponse = $this->actingAs($user)->get(route('road-facilities.show', $survey));
    $showResponse->assertOk();
    $showResponse->assertSee('Coastal Road');
    $showResponse->assertSee('Traffic sign replacement');

    $objectIdResponse = $this->actingAs($user)->get('/road-facilities/'.$survey->objectid);
    $objectIdResponse->assertOk();
    $objectIdResponse->assertSee('Coastal Road');

    expect(RoadFacilityFilter::query()->count())->toBeGreaterThan(10);
    expect(RoadFacilityFilter::query()->where('list_name', 'traffic_signs_type')->count())->toBeGreaterThan(0);
    expect(RoadFacilityFilter::query()->where('list_name', 'demolition_type')->count())->toBeGreaterThan(0);

    Carbon::setTestNow();
});
