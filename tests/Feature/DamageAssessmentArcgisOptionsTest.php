<?php

use App\Models\Building;
use App\Models\User;
use App\Services\ArcgisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns distinct arcgis options for allowed fields', function () {
    $this->mock(ArcgisService::class, function ($mock) {
        $mock->shouldReceive('getToken')->once()->andReturn('fake-token');
    });

    Http::fake(function (Request $request) {
        expect(parse_url($request->url(), PHP_URL_PATH))->toEndWith('/FeatureServer/0/query')
            ->and($request['outFields'])->toBe('assignedto')
            ->and($request['returnDistinctValues'])->toBe('true')
            ->and($request['returnGeometry'])->toBe('false')
            ->and($request['orderByFields'])->toBe('assignedto')
            ->and($request['token'])->toBe('fake-token');

        return Http::response([
            'features' => [
                ['attributes' => ['assignedto' => 'engineer-a']],
                ['attributes' => ['assignedto' => 'engineer-b']],
                ['attributes' => ['assignedto' => 'engineer-a']],
                ['attributes' => ['assignedto' => null]],
            ],
        ]);
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('phc.damageAssessment.arcgis.options', ['field' => 'assignedto']))
        ->assertOk()
        ->assertExactJson([
            ['id' => 'engineer-a', 'text' => 'engineer-a'],
            ['id' => 'engineer-b', 'text' => 'engineer-b'],
        ]);
});

it('rejects arcgis option fields outside the allow list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('phc.damageAssessment.arcgis.options', ['field' => 'globalid']))
        ->assertUnprocessable();
});

it('falls back to local building options when arcgis returns no values', function () {
    $this->mock(ArcgisService::class, function ($mock) {
        $mock->shouldReceive('getToken')->once()->andReturn('fake-token');
    });

    Http::fake([
        '*' => Http::response(['features' => []]),
    ]);

    Building::query()->create([
        'objectid' => 101,
        'globalid' => 'building-101',
        'building_name' => 'Building 101',
        'municipalitie' => 'Gaza',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('damageAssessment.arcgis.options', ['field' => 'municipalitie']))
        ->assertOk()
        ->assertExactJson([
            ['id' => 'Gaza', 'text' => 'Gaza'],
        ]);
});

it('returns building name options for the hud map filter', function () {
    $this->mock(ArcgisService::class, function ($mock) {
        $mock->shouldReceive('getToken')->once()->andReturn('fake-token');
    });

    Http::fake([
        '*' => Http::response(['features' => []]),
    ]);

    Building::query()->create([
        'objectid' => 102,
        'globalid' => 'building-102',
        'building_name' => 'Tower A',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('damageAssessment.arcgis.options', ['field' => 'building_name']))
        ->assertOk()
        ->assertExactJson([
            ['id' => 'Tower A', 'text' => 'Tower A'],
        ]);
});
