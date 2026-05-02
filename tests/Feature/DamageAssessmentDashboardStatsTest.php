<?php

use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use App\Services\ArcgisService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('shows eight summary statistics for public buildings and road facilities on the main dashboard', function () {
    $user = User::factory()->create();

    $this->app->instance(ArcgisService::class, new class extends ArcgisService
    {
        public function getToken(): string
        {
            return 'fake-token';
        }
    });

    PublicBuildingSurvey::query()->create([
        'objectid' => 1001,
        'building_name' => 'Clinic A',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'assigned_to' => 'Field Team 1',
        'building_damage_status' => 'fully_damaged',
        'is_building_occupied' => 'yes',
        'is_bodies' => 'yes',
        'is_uxo' => 'yes',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 2001,
        'str_name' => 'Road A',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'severe',
        'potholes_exist' => 'yes',
        'obstacle_exist' => 'yes',
        'buried_bodies' => 'yes',
        'uxo_present' => 'yes',
    ]);

    $response = $this->actingAs($user)->get('/damageAssessment');

    $response
        ->assertOk()
        ->assertSee('Neighborhoods')
        ->assertSee('Assigned Staff')
        ->assertSee('Occupied')
        ->assertSee('Bodies')
        ->assertSee('UXO')
        ->assertSee('Potholes')
        ->assertSee('Obstacles')
        ->assertSee('Buried Bodies');
});
