<?php

use App\Models\PublicBuildingSurvey;
use App\Models\PublicBuildingSurveyUnit;
use App\Models\User;
use Database\Seeders\PublicBuildingFilterSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('shows the public building survey pages and datatable data with dynamic filters and exports', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    app(PublicBuildingFilterSeeder::class)->run();

    Carbon::setTestNow('2026-04-14 10:30:00');

    $user = User::factory()->create();

    $survey = PublicBuildingSurvey::query()->create([
        'objectid' => 801,
        'globalid' => 'public-building-survey-801',
        'weather' => 'fine',
        'security_situation' => 'Safe',
        'building_name' => 'Public School',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'assignedto' => 'Engineer Public',
        'building_damage_status' => 'partially_damaged',
        'date_of_damage' => '2026-03-02',
        'building_type' => 'stan_alone_building',
        'building_age' => 'years21_50',
        'sector' => 'health',
        'facility_type' => 'hospital',
        'building_use' => 'work',
        'building_status' => 'dangerous',
        'building_roof_type' => ['concrete'],
        'comments_recommendations' => 'Immediate maintenance required',
        'other_building_use' => 'Emergency public shelter',
        'raw_payload' => ['arcgis_extra_field' => 'Hidden extra value'],
    ]);

    PublicBuildingSurveyUnit::query()->create([
        'parentglobalid' => $survey->globalid,
        'repeat_index' => 0,
        'unit_name' => 'Ground Floor',
        'floor_number' => 0,
        'damaged_area_m2' => 75.5,
        'occupied' => 'yes',
        'final_comments' => 'Repair windows',
        'raw_payload' => [
            'PM1' => 2,
            'ID_photo' => 'unit-id-photo.jpg',
        ],
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 802,
        'globalid' => 'public-building-survey-802',
        'weather' => 'windy',
        'security_situation' => 'Unsafe',
        'building_name' => 'Health Center',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Camp',
        'assignedto' => 'Engineer Other',
        'building_damage_status' => 'fully_damaged',
        'date_of_damage' => '2026-04-11',
        'building_type' => 'apartment',
        'building_age' => 'years0_5',
        'sector' => 'education',
        'facility_type' => 'university',
        'building_use' => 'residential',
        'building_status' => 'removed',
        'building_roof_type' => ['clay_tile'],
    ]);

    $indexResponse = $this->actingAs($user)->get(route('public-buildings.index'));
    $indexResponse->assertOk();
    $indexResponse->assertSee('Public Building Surveys');
    $indexResponse->assertSee('Public Building Filters');
    $indexResponse->assertSee('Select municipality');
    $indexResponse->assertSee('Select neighborhood');
    $indexResponse->assertSee('Security');
    $indexResponse->assertSee('Sector');
    $indexResponse->assertSee('Facility Type');
    $indexResponse->assertSee('Roof Type');
    $indexResponse->assertSee('Stand alone building');
    $indexResponse->assertSee('Hospital');

    $dataResponse = $this->actingAs($user)->get(route('public-buildings.data', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'filters' => [
            'security' => 'Safe',
            'building_type' => 'stan_alone_building',
            'sector' => 'health',
            'facility_type' => 'hospital',
            'roof_type' => 'concrete',
            'building_status' => 'dangerous',
        ],
        'from_date' => '2026-03-01',
        'to_date' => '2026-03-31',
    ]));
    $dataResponse->assertOk();
    $dataResponse->assertSee('Public School');
    $dataResponse->assertDontSee('Health Center');
    $dataResponse->assertSee('2026-03-02');

    $csvResponse = $this->actingAs($user)->get(route('public-buildings.export', [
        'format' => 'csv',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'filters' => [
            'security' => 'Safe',
            'facility_type' => 'hospital',
        ],
    ]));
    $csvResponse->assertOk();
    $csvResponse->assertHeader('content-disposition', 'attachment; filename=public_buildings_20260414_103000.csv');

    $pdfResponse = $this->actingAs($user)->get(route('public-buildings.export', [
        'format' => 'pdf',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'filters' => [
            'building_type' => 'stan_alone_building',
        ],
    ]));
    $pdfResponse->assertOk();
    $pdfResponse->assertHeader('content-disposition', 'attachment; filename=public_buildings_20260414_103000.pdf');

    $showResponse = $this->actingAs($user)->get(route('public-buildings.show', $survey));
    $showResponse->assertOk();
    $showResponse->assertSee('Public School');
    $showResponse->assertSee('Immediate maintenance required');
    $showResponse->assertSee('Ground Floor');
    $showResponse->assertSee('Repair windows');
    $showResponse->assertSee('PM1-Complete solar heating system');
    $showResponse->assertSee('unit-id-photo.jpg');
    $showResponse->assertSee('Linked Units');
    $showResponse->assertSee('2.4.1 If Other, Specify');
    $showResponse->assertSee('Emergency public shelter');
    $showResponse->assertDontSee('Additional DB Fields');
    $showResponse->assertDontSee('Hidden extra value');

    expect(PublicBuildingSurvey::query()->withCount('units')->find($survey->id)->units_count)->toBe(1);

    $objectIdResponse = $this->actingAs($user)->get('/public-buildings/'.$survey->objectid);
    $objectIdResponse->assertOk();
    $objectIdResponse->assertSee('Public School');

    Carbon::setTestNow();
});
