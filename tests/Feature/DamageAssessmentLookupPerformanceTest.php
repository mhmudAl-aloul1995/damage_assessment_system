<?php

use App\Models\Assessment;
use App\Models\Building;
use App\Models\Filter;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('loads grouped filters for building and housing pages without blade-level filter queries', function () {
    $user = User::factory()->create();

    Filter::query()->insert([
        [
            'list_name' => 'building_status',
            'name' => 'completed',
            'label' => 'Completed',
        ],
        [
            'list_name' => 'building_status',
            'name' => 'pending',
            'label' => 'Pending',
        ],
        [
            'list_name' => 'unit_damage_status',
            'name' => 'minor',
            'label' => 'Minor',
        ],
    ]);

    Assessment::query()->create([
        'name' => 'sample_assessment',
        'label' => 'Sample Assessment',
        'hint' => 'Sample hint',
    ]);

    Building::query()->create([
        'objectid' => 1,
        'globalid' => 'building-1',
        'assignedto' => 'Engineer One',
        'owner_name' => 'Owner One',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
    ]);

    $buildingResponse = $this->actingAs($user)->get('/building');
    $buildingResponse->assertOk();
    $buildingResponse->assertViewHas('groupedFilters', function ($groupedFilters) {
        return isset($groupedFilters['building_status'])
            && $groupedFilters['building_status']->count() === 2;
    });

    $housingResponse = $this->actingAs($user)->get('/housing');
    $housingResponse->assertOk();
    $housingResponse->assertViewHas('groupedFilters', function ($groupedFilters) {
        return isset($groupedFilters['unit_damage_status'])
            && $groupedFilters['unit_damage_status']->count() === 1;
    });
});

it('returns assigned engineer for housing datatable rows through the loaded building relation', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 10,
        'globalid' => 'building-10',
        'assignedto' => 'Engineer Two',
        'owner_name' => 'Owner Two',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Camp',
    ]);

    HousingUnit::query()->create([
        'objectid' => 20,
        'globalid' => 'housing-20',
        'parentglobalid' => 'building-10',
        'q_9_3_1_first_name' => 'Ahmad',
    ]);

    $response = $this->actingAs($user)->get('/housing/show?draw=1&start=0&length=10');

    $response->assertOk();
    $response->assertSee('Engineer Two');
});
