<?php

use App\Models\AuditedBuilding;
use App\Models\CsoSurvey;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['municipalitie', 'latitude', 'longitude', 'location', 'building_name'] as $column) {
        if (! Schema::hasColumn('audited_buildings', $column)) {
            Schema::table('audited_buildings', fn (Blueprint $table) => $table->text($column)->nullable());
        }
    }
    $this->inquiryUser = User::factory()->create();
    $this->inquiryUser->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->inquiryToken = $this->inquiryUser->createToken('test', ['mobile:read'])->plainTextToken;
});

it('requires bearer authentication for inquiries', function (): void {
    $this->getJson('/api/v1/damage-assessment/buildings')->assertUnauthorized();
});

it('searches Arabic building names and returns bounded safe records and geometry', function (): void {
    AuditedBuilding::query()->create(['objectid' => 901, 'globalid' => 'hope', 'building_name' => 'برج الأمل', 'latitude' => 31.5, 'longitude' => 34.4]);
    AuditedBuilding::query()->create(['objectid' => 902, 'globalid' => 'other', 'building_name' => 'مبنى آخر']);
    $response = $this->withToken($this->inquiryToken)->getJson('/api/v1/damage-assessment/buildings?'.http_build_query(['search' => 'الأمل']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'برج الأمل')
        ->assertJsonPath('data.0.geometry.x', 34.4)->assertJsonMissingPath('data.0.owner_id');
    $this->getJson('/api/v1/damage-assessment/buildings/'.$response->json('data.0.record_id'))
        ->assertOk()->assertJsonPath('data.objectid', 901);
    $this->getJson('/api/v1/damage-assessment/buildings?search=902')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.objectid', 902);
    $this->getJson('/api/v1/damage-assessment/buildings/999999')->assertNotFound();
});

it('searches infrastructure sector names with matching map and statistics', function (string $sector, string $model, string $column): void {
    $model::query()->create(['objectid' => 101, 'globalid' => 'match', $column => 'الأمل', 'location' => json_encode(['x' => 34.4, 'y' => 31.5, 'spatialReference' => ['wkid' => 4326]])]);
    $model::query()->create(['objectid' => 102, 'globalid' => 'other', $column => 'آخر']);
    $path = '/api/v1/damage-assessment/'.$sector;
    $query = '?'.http_build_query(['search' => 'الأمل']);
    $this->withToken($this->inquiryToken)->getJson($path.$query)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'الأمل');
    $this->getJson($path.'/map'.$query)->assertOk()->assertJsonCount(1, 'features');
    $this->getJson($path.'/stats'.$query)->assertOk()->assertJsonPath('summary.total', 1);
})->with([
    ['public-buildings', PublicBuildingSurvey::class, 'building_name'],
    ['road-facilities', RoadFacilitySurvey::class, 'str_name'],
    ['cso-surveys', CsoSurvey::class, 'organization_name'],
]);

it('denies forbidden sectors and omits them from the catalog', function (): void {
    $user = User::factory()->create();
    $this->withToken($user->createToken('test', ['mobile:read'])->plainTextToken)
        ->getJson('/api/v1/damage-assessment/buildings')->assertForbidden();
    $this->getJson('/api/v1/damage-assessment/sectors')->assertOk()->assertJsonCount(0, 'data');
});

it('enforces account phases for records detail statistics and maps without a web session', function (): void {
    $this->inquiryUser->update(['allowed_phase_numbers' => [1]]);
    foreach ([1, 2] as $phase) {
        AuditedBuilding::query()->create(['objectid' => 100 + $phase, 'globalid' => 'phase-'.$phase, 'building_name' => 'الأمل', 'phase_number' => $phase, 'latitude' => 31.5, 'longitude' => 34.4]);
    }
    $hiddenId = AuditedBuilding::query()->where('objectid', 102)->value('id');
    $path = '/api/v1/damage-assessment/buildings';
    $this->withToken($this->inquiryToken)->getJson($path)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.objectid', 101);
    $this->getJson($path.'/'.$hiddenId)->assertNotFound();
    $this->getJson($path.'/map')->assertOk()->assertJsonCount(1, 'features');
    $this->getJson($path.'/stats')->assertOk()->assertJsonPath('summary.total', 1);
});

it('validates search filters and treats SQL wildcard input literally', function (): void {
    AuditedBuilding::query()->create(['objectid' => 901, 'globalid' => 'ordinary', 'building_name' => 'ordinary']);
    $path = '/api/v1/damage-assessment/buildings';
    $this->withToken($this->inquiryToken)->getJson($path.'?search[]=x')->assertUnprocessable()->assertJsonValidationErrors('search');
    $this->getJson($path.'?search='.str_repeat('x', 151))->assertUnprocessable();
    $this->getJson($path.'?page=0&damage_status=invalid')->assertUnprocessable()->assertJsonValidationErrors(['page', 'damage_status']);
    $this->getJson($path.'?search=%25')->assertOk()->assertJsonPath('total', 0);
    $this->getJson('/api/v1/damage-assessment/unknown')->assertNotFound();
});

it('finds housing units by the parent building name while respecting phase restrictions', function (): void {
    $this->inquiryUser->update(['allowed_phase_numbers' => [1]]);
    foreach ([1, 2] as $phase) {
        AuditedBuilding::query()->create(['objectid' => 200 + $phase, 'globalid' => 'parent-'.$phase, 'building_name' => 'برج الأمل', 'phase_number' => $phase, 'latitude' => 31.5, 'longitude' => 34.4]);
        \App\Models\AuditedHousingUnit::query()->create(['objectid' => 300 + $phase, 'globalid' => 'unit-'.$phase, 'parentglobalid' => 'parent-'.$phase]);
    }
    $this->withToken($this->inquiryToken)->getJson('/api/v1/damage-assessment/housing-units?'.http_build_query(['search' => 'الأمل']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.objectid', 301)->assertJsonPath('data.0.geometry.x', 34.4);
});
