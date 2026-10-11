<?php

use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    foreach (['municipalitie', 'latitude', 'longitude', 'location', 'building_name'] as $column) {
        if (! Schema::hasColumn('audited_buildings', $column)) {
            Schema::table('audited_buildings', fn (Blueprint $table) => $table->text($column)->nullable());
        }
    }
    $this->mapUser = User::factory()->create(['allowed_phase_numbers' => [1]]);
    $this->mapUser->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->mapToken = $this->mapUser->createToken('map-fixture', ['mobile:read'])->plainTextToken;
    $this->mapPath = '/api/v1/damage-assessment/buildings';
});

it('scans a bounded map batch and advances past records without coordinates', function (): void {
    $rows = array_map(fn (int $index): array => ['objectid' => 1000 + $index, 'globalid' => 'map-'.$index, 'phase_number' => 1], range(1, 501));
    AuditedBuilding::query()->insert($rows);
    AuditedBuilding::query()->where('objectid', 1501)->update(['latitude' => 31.5, 'longitude' => 34.4, 'building_name' => 'آخر مبنى']);
    AuditedBuilding::query()->create(['objectid' => 2000, 'globalid' => 'hidden', 'phase_number' => 2, 'latitude' => 31.5, 'longitude' => 34.4]);
    $first = $this->withToken($this->mapToken)->getJson($this->mapPath.'/map')
        ->assertOk()->assertJsonPath('scanned', 500)->assertJsonCount(0, 'features');
    $cursor = $first->json('next_cursor');
    expect($cursor)->toBeInt()->toBeGreaterThan(0);
    $this->getJson($this->mapPath.'/map?after_id='.$cursor)->assertOk()->assertJsonPath('scanned', 1)
        ->assertJsonPath('next_cursor', null)->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.attributes.name', 'آخر مبنى')->assertJsonMissingPath('features.0.attributes.owner_id');
});

it('applies mobile extents within each cursor batch instead of rescanning all records', function (): void {
    AuditedBuilding::query()->insert(array_map(fn (int $index): array => ['objectid' => 3000 + $index, 'globalid' => 'extent-'.$index, 'phase_number' => 1, 'latitude' => 30, 'longitude' => 30], range(1, 500)));
    $inside = AuditedBuilding::query()->create(['objectid' => 4000, 'globalid' => 'inside', 'phase_number' => 1, 'latitude' => 31.5, 'longitude' => 34.4]);
    $extent = '?west=34&south=31&east=35&north=32';
    $first = $this->withToken($this->mapToken)->getJson($this->mapPath.'/map'.$extent)
        ->assertOk()->assertJsonPath('scanned', 500)->assertJsonCount(0, 'features');
    $this->getJson($this->mapPath.'/map'.$extent.'&after_id='.$first->json('next_cursor'))
        ->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.attributes.record_id', AuditedBuilding::query()->where('objectid', 4000)->value('id'))->assertJsonPath('next_cursor', null);
});

it('matches points polygons and web mercator extents with normalized damage colors', function (): void {
    foreach ([
        ['x' => 34.4, 'y' => 31.5, 'spatialReference' => ['wkid' => 4326]],
        ['x' => 3829390.489, 'y' => 3697855.330, 'spatialReference' => ['wkid' => 102100, 'latestWkid' => 3857]],
        ['rings' => [[[34.39, 31.49], [34.41, 31.49], [34.41, 31.51], [34.39, 31.49]]], 'spatialReference' => ['wkid' => 4326]],
    ] as $index => $geometry) {
        AuditedBuilding::query()->create(['objectid' => 5000 + $index, 'globalid' => 'shape-'.$index, 'phase_number' => 1, 'location' => json_encode($geometry), 'building_damage_status' => 'fully_damaged', 'field_status' => 'Completed']);
    }
    $this->withToken($this->mapToken)->getJson($this->mapPath.'/map?west=34&south=31&east=35&north=32')
        ->assertOk()->assertJsonCount(3, 'features')->assertJsonPath('features.0.attributes.damage_status', 'fully_damaged')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('keeps statistics complete when records lack coordinates and scopes all filters', function (): void {
    foreach ([
        ['phase_number' => 1, 'municipalitie' => 'غزة', 'building_damage_status' => 'fully_damaged', 'field_status' => 'Completed'],
        ['phase_number' => 1, 'municipalitie' => 'غزة', 'building_damage_status' => 'no_damage', 'field_status' => 'Not_Completed'],
        ['phase_number' => 2, 'municipalitie' => 'غزة', 'building_damage_status' => 'fully_damaged', 'field_status' => 'Completed'],
    ] as $index => $row) {
        AuditedBuilding::query()->create([...$row, 'objectid' => 6000 + $index, 'globalid' => 'stats-'.$index, 'building_name' => 'الأمل']);
    }
    $query = '?'.http_build_query(['search' => 'الأمل', 'municipality' => 'غزة']);
    $this->withToken($this->mapToken)->getJson($this->mapPath.'/stats'.$query)->assertOk()
        ->assertJsonPath('summary.total', 2)->assertJsonPath('summary.completed', 1)
        ->assertJsonPath('damage.fully_damaged', 1)->assertJsonPath('damage.no_damage', 1)
        ->assertJsonPath('fieldwork.not_completed', 1)->assertJsonMissingPath('data.owner_id');
    $this->getJson($this->mapPath.'/stats'.$query.'&damage_status=fully_damaged&field_completion=completed')->assertOk()->assertJsonPath('summary.total', 1);
    $this->getJson($this->mapPath.'/map'.$query)->assertOk()->assertJsonCount(0, 'features');
});

it('maps housing at its allowed parent location while retaining distinct internal unit ids', function (): void {
    $building = AuditedBuilding::query()->create(['objectid' => 7000, 'globalid' => 'parent-map', 'phase_number' => 1, 'building_name' => 'برج الأمل', 'building_damage_status' => 'no_damage', 'latitude' => 31.5, 'longitude' => 34.4]);
    $units = collect([1, 2])->map(fn (int $index) => AuditedHousingUnit::query()->create(['objectid' => 7100 + $index, 'globalid' => 'unit-map-'.$index, 'parentglobalid' => $building->globalid, 'unit_damage_status' => 'fully_damaged2']));
    $response = $this->withToken($this->mapToken)->getJson('/api/v1/damage-assessment/housing-units/map?search='.urlencode('الأمل'))
        ->assertOk()->assertJsonCount(2, 'features')->assertJsonPath('features.0.geometry.x', 34.4)
        ->assertJsonPath('features.0.attributes.damage_status', 'fully_damaged')
        ->assertJsonPath('features.0.building', ['globalid' => 'parent-map', 'objectid' => 7000, 'name' => 'برج الأمل', 'damage_status' => 'no_damage'])
        ->assertJsonPath('features.1.building.damage_status', 'no_damage');
    expect(array_column(array_column($response->json('features'), 'attributes'), 'record_id'))->toBe(AuditedHousingUnit::query()->orderBy('id')->pluck('id')->all());
});

it('keeps the parent building classification when filtering and paging housing units', function (): void {
    AuditedBuilding::query()->create(['objectid' => 7200, 'globalid' => 'classification-parent', 'phase_number' => 1,
        'building_damage_status' => 'partially_damaged', 'latitude' => 31.5, 'longitude' => 34.4]);
    foreach (['fully_damaged2', 'no_damaged'] as $index => $damage) {
        AuditedHousingUnit::query()->create(['objectid' => 7210 + $index, 'globalid' => 'classification-unit-'.$index,
            'parentglobalid' => 'classification-parent', 'unit_damage_status' => $damage]);
    }
    $this->withToken($this->mapToken)->getJson('/api/v1/damage-assessment/housing-units/map?damage_status=no_damage')
        ->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.attributes.objectid', 7211)
        ->assertJsonPath('features.0.attributes.damage_status', 'no_damage')
        ->assertJsonPath('features.0.building.damage_status', 'partially_damaged');
    $cursor = AuditedHousingUnit::query()->where('objectid', 7210)->value('id');
    $this->getJson('/api/v1/damage-assessment/housing-units/map?after_id='.$cursor)
        ->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.building.objectid', 7200)
        ->assertJsonPath('features.0.building.damage_status', 'partially_damaged');
});

it('does not derive missing parent damage from units or expose buildings outside account phases', function (): void {
    foreach ([1, 2] as $phase) {
        AuditedBuilding::query()->create(['objectid' => 7300 + $phase, 'globalid' => 'missing-damage-'.$phase,
            'phase_number' => $phase, 'latitude' => 31.5, 'longitude' => 34.4]);
        AuditedHousingUnit::query()->create(['objectid' => 7310 + $phase, 'globalid' => 'missing-unit-'.$phase,
            'parentglobalid' => 'missing-damage-'.$phase, 'unit_damage_status' => 'fully_damaged2']);
    }
    $this->withToken($this->mapToken)->getJson('/api/v1/damage-assessment/housing-units/map')
        ->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.building.objectid', 7301)
        ->assertJsonPath('features.0.building.damage_status', 'unclassified')
        ->assertJsonMissingPath('features.0.building.owner_id')->assertJsonMissingPath('features.0.building.record_id');
});

it('returns distinct building identities even when their coordinates match', function (): void {
    foreach ([1 => 'fully_damaged', 2 => 'no_damage'] as $index => $damage) {
        AuditedBuilding::query()->create(['objectid' => 7400 + $index, 'globalid' => 'separate-building-'.$index,
            'phase_number' => 1, 'latitude' => 31.5, 'longitude' => 34.4, 'building_damage_status' => $damage]);
    }
    $this->withToken($this->mapToken)->getJson($this->mapPath.'/map')
        ->assertOk()->assertJsonCount(2, 'features')
        ->assertJsonPath('features.0.building.globalid', 'separate-building-1')
        ->assertJsonPath('features.0.building.damage_status', 'fully_damaged')
        ->assertJsonPath('features.1.building.globalid', 'separate-building-2')
        ->assertJsonPath('features.1.building.damage_status', 'no_damage');
});

it('rejects incomplete reversed and excessive extents and forbidden mobile map access', function (): void {
    $this->withToken($this->mapToken)->getJson($this->mapPath.'/map?west=34')->assertUnprocessable();
    $this->getJson($this->mapPath.'/map?west=35&south=31&east=34&north=32')->assertUnprocessable();
    $this->getJson($this->mapPath.'/map?west=-181&south=31&east=34&north=32&after_id=-1')->assertUnprocessable();
    $user = User::factory()->create();
    app('auth')->forgetGuards();
    $this->withToken($user->createToken('no-sector', ['mobile:read'])->plainTextToken)->getJson($this->mapPath.'/map')->assertForbidden();
    $this->getJson($this->mapPath.'/stats')->assertForbidden();
});

it('removes unexpected geometry metadata and bounds oversized shapes without losing the cursor', function (): void {
    AuditedBuilding::query()->create(['objectid' => 8000, 'globalid' => 'safe-geometry', 'phase_number' => 1,
        'location' => json_encode(['x' => 34.4, 'y' => 31.5, 'owner_id' => 'fixture-private-id', 'token' => 'fixture-private-token', 'spatialReference' => ['wkid' => 4326, 'extra' => 'private']])]);
    AuditedBuilding::query()->create(['objectid' => 8001, 'globalid' => 'huge-geometry', 'phase_number' => 1,
        'location' => json_encode(['rings' => [array_fill(0, 5001, [34.4, 31.5])], 'spatialReference' => ['wkid' => 4326]])]);
    AuditedBuilding::query()->create(['objectid' => 8002, 'globalid' => 'bad-geometry', 'phase_number' => 1,
        'location' => json_encode(['rings' => [[[34.4, 31.5], 'invalid']], 'spatialReference' => ['wkid' => 4326]])]);
    $response = $this->withToken($this->mapToken)->getJson($this->mapPath.'/map?west=34&south=31&east=35&north=32')
        ->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('omitted', 2)->assertJsonPath('scanned', 3)->assertJsonPath('next_cursor', null)
        ->assertJsonMissingPath('features.0.geometry.owner_id')->assertJsonMissingPath('features.0.geometry.token')->assertJsonMissingPath('features.0.geometry.spatialReference.extra');
    expect($response->json('features.0.geometry'))->toBe(['x' => 34.4, 'y' => 31.5, 'spatialReference' => ['wkid' => 4326]]);
});
