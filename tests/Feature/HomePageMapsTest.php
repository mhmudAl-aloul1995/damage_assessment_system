<?php

use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use App\Services\ArcgisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('shows the homepage public building and road maps', function () {
    $this->mock(ArcgisService::class, function ($mock) {
        $mock->shouldReceive('getToken')->andReturn('fake-token');
    });

    $user = User::factory()->create();

    Building::query()->create([
        'globalid' => 'building-globalid-1',
        'objectid' => 101,
        'building_name' => 'Main Building',
        'neighborhood' => 'Alpha',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'security_situation' => 'Safe',
        'uxo_present' => 'no3',
        'bodies_present' => 'no3',
        'building_debris_exist' => 'no',
    ]);

    HousingUnit::query()->create([
        'objectid' => 501,
        'globalid' => 'housing-globalid-1',
        'parentglobalid' => 'building-globalid-1',
        'q_9_3_1_first_name' => 'Ahmad',
        'q_9_3_4_last_name' => 'Salem',
        'unit_damage_status' => 'fully_damaged2',
        'has_fire' => 'no',
        'unit_stripping' => 'no',
        'is_the_housing_unit_or_living_habitable' => 'yes',
        'security_situation_unit' => 'Safe',
        'unit_support_needed' => 'no',
    ]);

    $publicBuilding = PublicBuildingSurvey::query()->create([
        'objectid' => 201,
        'building_name' => 'Public School',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'building_damage_status' => 'partially_damaged',
    ]);

    $roadFacility = RoadFacilitySurvey::query()->create([
        'objectid' => 301,
        'str_name' => 'Al Rashid Road',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'moderate',
    ]);

    $this->actingAs($user)
        ->get(route('damageAssessment.index'))
        ->assertOk()
        ->assertSee('Public Buildings')
        ->assertSee('Road Facilities')
        ->assertSee('Public Buildings Map')
        ->assertSee('Road Facilities Map')
        ->assertSee('publicBuildingViewDiv', false)
        ->assertSee('roadFacilityViewDiv', false);

    $this->actingAs($user)
        ->getJson(route('public-buildings-map'))
        ->assertOk()
        ->assertSee($publicBuilding->building_name);

    $this->actingAs($user)
        ->getJson(route('road-facilities-map'))
        ->assertOk()
        ->assertSee($roadFacility->str_name);
});

it('shows buildings on the homepage map table even without housing units', function () {
    $this->mock(ArcgisService::class, function ($mock) {
        $mock->shouldReceive('getToken')->andReturn('fake-token');
    });

    $user = User::factory()->create();

    $building = Building::query()->create([
        'globalid' => 'building-only-globalid',
        'objectid' => 777,
        'building_name' => 'Standalone Building',
        'neighborhood' => 'Tal Al Hawa',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'partially_damaged',
        'security_situation' => 'Safe',
        'uxo_present' => 'no3',
        'bodies_present' => 'no3',
        'building_debris_exist' => 'no',
    ]);

    $this->actingAs($user)
        ->getJson(route('housing-units-map'))
        ->assertOk()
        ->assertSee($building->building_name)
        ->assertSee('No Units');
});
