<?php

use App\Models\Building;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('returns grouped global search results for buildings surveys and road facilities', function () {
    $user = User::factory()->create();

    $building = Building::query()->create([
        'objectid' => 5001,
        'globalid' => 'building-alpha-globalid',
        'building_name' => 'Alpha Tower',
        'owner_name' => 'Owner One',
        'neighborhood' => 'Rimal',
    ]);

    $publicBuildingSurvey = PublicBuildingSurvey::query()->create([
        'objectid' => 7001,
        'building_name' => 'Alpha School',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Tal Al Hawa',
    ]);

    $roadFacilitySurvey = RoadFacilitySurvey::query()->create([
        'objectid' => 9001,
        'str_name' => 'Alpha Street',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Sheikh Radwan',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->getJson(route('global-search', ['search' => 'Alpha']));

    $response
        ->assertOk()
        ->assertJsonCount(3, 'results')
        ->assertJsonFragment([
            'group' => 'Buildings',
            'title' => 'Alpha Tower',
            'url' => route('assessment.show', $building->globalid),
        ])
        ->assertJsonFragment([
            'group' => 'Public buildings',
            'title' => 'Alpha School',
            'url' => route('public-buildings.show', $publicBuildingSurvey),
        ])
        ->assertJsonFragment([
            'group' => 'Road facilities',
            'title' => 'Alpha Street',
            'url' => route('road-facilities.show', $roadFacilitySurvey),
        ]);
});
