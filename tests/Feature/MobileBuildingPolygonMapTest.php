<?php

use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\User;
use App\services\ArcgisService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    foreach (['location', 'latitude', 'longitude', 'building_name', 'municipalitie'] as $column) {
        if (! Schema::hasColumn('audited_buildings', $column)) {
            Schema::table('audited_buildings', fn (Blueprint $table) => $table->text($column)->nullable());
        }
    }
    config(['services.arcgis.source_service' => 'https://services.arcgis.com/polygon-fixture/FeatureServer', 'services.arcgis.source_buildings_layer' => 0]);
    $arcgis = Mockery::mock(ArcgisService::class);
    $arcgis->shouldReceive('getVerifiedToken')->andReturn('fixture-token');
    $this->app->instance(ArcgisService::class, $arcgis);
    $user = User::factory()->create(['allowed_phase_numbers' => [1]]);
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->withToken($user->createToken('polygon-fixture', ['mobile:read'])->plainTextToken);
    $this->polygonId = 'a1111111-1111-4111-8111-111111111111';
    $this->hiddenPolygonId = 'a2222222-2222-4222-8222-222222222222';
    $this->polygon = ['rings' => [[[34.4, 31.5], [34.401, 31.5], [34.401, 31.501], [34.4, 31.5]]], 'spatialReference' => ['wkid' => 4326]];
});

it('returns stored real polygon boundaries without querying the provider', function (): void {
    AuditedBuilding::query()->create(['objectid' => 8001, 'globalid' => $this->polygonId, 'phase_number' => 1, 'location' => json_encode($this->polygon), 'building_damage_status' => 'fully_damaged']);
    $this->getJson('/api/v1/damage-assessment/buildings/map?geometry=polygon')->assertOk()->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.geometry.rings', $this->polygon['rings'])->assertJsonMissingPath('features.0.geometry.x');
    Http::assertNothingSent();
});
it('requests only authorized globalids and rejects unrelated provider polygons', function (): void {
    AuditedBuilding::query()->create(['objectid' => 8001, 'globalid' => $this->polygonId, 'phase_number' => 1, 'building_damage_status' => 'fully_damaged']);
    AuditedBuilding::query()->create(['objectid' => 8002, 'globalid' => $this->hiddenPolygonId, 'phase_number' => 2]);
    Http::fake(['*/query' => function ($request, array $options) {
        expect($options['verify'])->toBeTrue();
        expect($request['where'])->toContain($this->polygonId)->not->toContain($this->hiddenPolygonId);
        expect($request['outFields'])->toBe('globalid');
        expect($request['returnGeometry'])->toBe('true');

        return Http::response(['features' => [
            ['attributes' => ['GLOBALID' => '{'.strtoupper($this->polygonId).'}', 'owner_id' => 'never-return'], 'geometry' => $this->polygon],
            ['attributes' => ['globalid' => $this->hiddenPolygonId], 'geometry' => $this->polygon],
        ]]);
    }]);
    $this->getJson('/api/v1/damage-assessment/buildings/map?geometry=polygon')->assertOk()->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.building.damage_status', 'fully_damaged')->assertJsonPath('features.0.attributes.objectid', 8001)
        ->assertJsonMissingPath('features.0.attributes.owner_id')->assertJsonMissingPath('features.0.token');
    Http::assertSentCount(1);
});
it('uses a shared parent polygon and parent damage for housing while keeping unit navigation distinct', function (): void {
    AuditedBuilding::query()->create(['objectid' => 8100, 'globalid' => $this->polygonId, 'phase_number' => 1, 'building_damage_status' => 'no_damage']);
    foreach ([1, 2] as $index) {
        AuditedHousingUnit::query()->create(['objectid' => 8100 + $index, 'globalid' => 'unit-'.$index, 'parentglobalid' => $this->polygonId, 'unit_damage_status' => 'fully_damaged2']);
    }
    Http::fake(['*/query' => Http::response(['features' => [['attributes' => ['globalid' => $this->polygonId], 'geometry' => $this->polygon]]])]);
    $response = $this->getJson('/api/v1/damage-assessment/housing-units/map?geometry=polygon')->assertOk()->assertJsonCount(2, 'features')
        ->assertJsonPath('features.0.building.damage_status', 'no_damage')->assertJsonPath('features.1.geometry.rings', $this->polygon['rings']);
    expect($response->json('features.0.attributes.record_id'))->not->toBe($response->json('features.1.attributes.record_id'));
    Http::assertSentCount(1);
});
it('reports missing boundaries instead of turning point locations into buildings', function (): void {
    AuditedBuilding::query()->create(['objectid' => 8201, 'globalid' => $this->polygonId, 'phase_number' => 1, 'latitude' => 31.5, 'longitude' => 34.4]);
    Http::fake(['*/query' => Http::response(['error' => ['code' => 499, 'message' => 'private provider details']])]);
    $this->getJson('/api/v1/damage-assessment/buildings/map?geometry=polygon')->assertOk()->assertJsonCount(0, 'features')
        ->assertJsonPath('omitted', 1)->assertJsonPath('scanned', 1)->assertJsonMissingPath('error');
    $this->getJson('/api/v1/damage-assessment/buildings/map?geometry=point')->assertUnprocessable()->assertJsonValidationErrors('geometry');
});
it('caches boundaries while reading current building damage independently', function (): void {
    $building = AuditedBuilding::query()->create(['objectid' => 8301, 'globalid' => $this->polygonId, 'phase_number' => 1, 'building_damage_status' => 'no_damage']);
    Http::fake(['*/query' => Http::response(['features' => [['attributes' => ['globalid' => $this->polygonId], 'geometry' => $this->polygon]]])]);
    $this->getJson('/api/v1/damage-assessment/buildings/map?geometry=polygon')->assertOk()->assertJsonPath('features.0.building.damage_status', 'no_damage');
    $building->update(['building_damage_status' => 'fully_damaged']);
    $this->getJson('/api/v1/damage-assessment/buildings/map?geometry=polygon')->assertOk()->assertJsonPath('features.0.building.damage_status', 'fully_damaged');
    Http::assertSentCount(1);
});
it('rejects malformed open rings and untrusted globalid queries without provider writes', function (): void {
    $this->polygon['rings'][0][3] = [34.4, 31.501];
    AuditedBuilding::query()->create(['objectid' => 8401, 'globalid' => "invalid' OR 1=1", 'phase_number' => 1, 'location' => json_encode($this->polygon)]);
    $this->getJson('/api/v1/damage-assessment/buildings/map?geometry=polygon')->assertOk()->assertJsonCount(0, 'features')->assertJsonPath('omitted', 1);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});
it('never caches oversized provider polygons and allows retry when boundaries fail', function (): void {
    AuditedBuilding::query()->create(['objectid' => 8501, 'globalid' => $this->polygonId, 'phase_number' => 1]);
    $this->polygon['rings'] = [array_fill(0, 5001, [34.4, 31.5])];
    Http::fake(['*/query' => Http::response(['features' => [['attributes' => ['globalid' => $this->polygonId], 'geometry' => $this->polygon]]])]);
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $this->getJson('/api/v1/damage-assessment/buildings/map?geometry=polygon')->assertOk()->assertJsonCount(0, 'features')->assertJsonPath('omitted', 1);
    }
    Http::assertSentCount(2);
});
