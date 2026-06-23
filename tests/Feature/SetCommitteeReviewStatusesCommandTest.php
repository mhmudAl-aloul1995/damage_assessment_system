<?php

declare(strict_types=1);

use App\Models\Building;
use App\Models\HousingUnit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('previews the selected records without changing them', function () {
    Building::query()->create(['objectid' => 16708, 'globalid' => 'building-16708', 'building_damage_status' => 'partially_damaged', 'field_status' => 'COMPLETED']);
    HousingUnit::query()->create(['objectid' => 11239, 'globalid' => 'unit-11239', 'unit_damage_status' => 'partially_damaged2']);

    $this->artisan('committee:set-review-statuses')
        ->expectsOutput('No changes were made because some requested records were not found.')
        ->assertFailed();

    expect(Building::query()->where('objectid', 16708)->value('building_damage_status'))->toBe('partially_damaged')
        ->and(HousingUnit::query()->where('objectid', 11239)->value('unit_damage_status'))->toBe('partially_damaged2');
});

it('updates the requested records and synchronizes them with ArcGIS', function () {
    $buildingObjectIds = [
        16708, 17363, 17310, 16856, 16833, 8889, 8254, 7724, 9663, 1523,
        781, 865, 8189, 1239, 9831, 18362, 18351, 13577, 11207, 11164,
        11140, 11064, 10493, 10487, 19308, 17521, 17906, 18654,
    ];
    $housingUnitObjectIds = [11239, 11267, 10891, 11370, 18992];
    $parentBuilding = Building::query()->create(['objectid' => 25000, 'globalid' => 'parent-building-globalid', 'field_status' => 'COMPLETED']);

    foreach ($buildingObjectIds as $objectId) {
        Building::query()->create(['objectid' => $objectId, 'globalid' => 'building-'.$objectId]);
    }

    foreach ($housingUnitObjectIds as $objectId) {
        HousingUnit::query()->create(['objectid' => $objectId, 'globalid' => 'unit-'.$objectId, 'parentglobalid' => $parentBuilding->globalid]);
    }

    config()->set('services.arcgis.buildings_url', 'https://arcgis.test/FeatureServer/0');
    config()->set('services.arcgis.housing_units_url', 'https://arcgis.test/FeatureServer/1');
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'test-token']),
        'https://arcgis.test/FeatureServer/*/updateFeatures' => function (\Illuminate\Http\Client\Request $request) {
            $features = json_decode((string) $request['features'], true, flags: JSON_THROW_ON_ERROR);

            return Http::response(['updateResults' => array_fill(0, count($features), ['success' => true])]);
        },
    ]);

    $this->artisan('committee:set-review-statuses --force')
        ->expectsOutput('Database and ArcGIS statuses were updated successfully.')
        ->assertSuccessful();

    expect(Building::query()->whereIn('objectid', $buildingObjectIds)->pluck('building_damage_status')->unique()->all())->toBe(['committee_review'])
        ->and(Building::query()->whereIn('objectid', $buildingObjectIds)->pluck('field_status')->unique()->all())->toBe(['COMPLETED'])
        ->and(HousingUnit::query()->whereIn('objectid', $housingUnitObjectIds)->pluck('unit_damage_status')->unique()->all())->toBe(['committee_review2'])
        ->and($parentBuilding->refresh()->field_status)->toBe('COMPLETED');

    Http::assertSentCount(3);
});
