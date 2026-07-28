<?php

use App\Models\Assessment;
use App\Models\Building;
use App\Models\Filter;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

it('shows grouped building filters based on survey sections', function () {
    $user = User::factory()->create();

    seedBuildingFilterOptions();

    Building::query()->create([
        'assignedto' => 'Engineer One',
        'globalid' => 'building-1',
        'objectid' => 1001,
        'building_name' => 'Al Amal Tower',
        'owner_name' => 'Owner One',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
    ]);

    Assessment::query()->create([
        'name' => 'building_name',
        'label' => 'Building name',
        'hint' => 'اسم المبنى',
        'type' => '0',
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/building');

    $response->assertOk();
    $response->assertSee('Building Filters');
    $response->assertSee('Damage, hazards, and debris');
    $response->assertSee('Building specifications');
    $response->assertSee('Risk summary');
    $response->assertSee('name="filters[field_status][]"', false);
    $response->assertSee('Completed');
    $response->assertSee('Totally Damaged');
    $response->assertSee('Concrete');
});

it('filters building datatable records with grouped filters and ranges', function () {
    $user = User::factory()->create();

    seedBuildingFilterOptions();

    Building::query()->create([
        'assignedto' => 'Engineer One',
        'globalid' => 'building-1',
        'objectid' => 1001,
        'building_name' => 'Al Amal Tower',
        'owner_name' => 'Owner One',
        'owner_id' => '900123456',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'building_material' => 'concrete',
        'units_nos' => 12,
        'damaged_units_nos' => 8,
        'floor_nos' => 4,
        'building_debris_exist' => 'yes',
        'uxo_present' => 'yes3',
        'bodies_present' => 'no3',
        'editdate' => '2026-06-01 10:00:00',
    ]);

    Building::query()->create([
        'assignedto' => 'Engineer Two',
        'globalid' => 'building-2',
        'objectid' => 1002,
        'building_name' => 'Al Noor House',
        'owner_name' => 'Owner Two',
        'owner_id' => '800123456',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Camp',
        'field_status' => 'Not_Completed',
        'building_damage_status' => 'partially_damaged',
        'building_material' => 'wood',
        'units_nos' => 3,
        'damaged_units_nos' => 1,
        'floor_nos' => 1,
        'building_debris_exist' => 'no',
        'uxo_present' => 'no3',
        'bodies_present' => 'no3',
        'editdate' => '2026-06-02 10:00:00',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'field_status' => ['COMPLETED'],
            'building_damage_status' => ['fully_damaged'],
            'building_material' => ['concrete'],
            'municipalitie' => ['Gaza'],
            'damaged_units_nos_from' => 5,
            'unsafe_column' => 'ignored',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/building/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Al Amal Tower');
    $response->assertSee('Totally Damaged');
    $response->assertSee('Debris');
    $response->assertSee('UXO');
    $response->assertDontSee('Al Noor House');
});

it('toggles a building field status locally and on arcgis', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ], 200),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/updateFeatures' => Http::response([
            'updateResults' => [['success' => true]],
        ], 200),
    ]);

    $user = User::factory()->create();

    $building = Building::query()->create([
        'assignedto' => 'Engineer One',
        'globalid' => 'building-toggle-1',
        'objectid' => 3001,
        'building_name' => 'Toggle Tower',
        'field_status' => 'COMPLETED',
    ]);

    $response = $this->actingAs($user)
        ->post('/damage-assessment/building/'.$building->globalid.'/toggle-field-status');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('field_status', 'Not_Completed');

    expect($building->refresh()->field_status)->toBe('Not_Completed');

    Http::assertSent(function ($request): bool {
        $features = json_decode((string) data_get($request->data(), 'features'), true);

        return str_contains($request->url(), '/FeatureServer/0/updateFeatures')
            && data_get($features, '0.attributes.objectid') === 3001
            && data_get($features, '0.attributes.field_status') === 'Not_Completed';
    });
});

function seedBuildingFilterOptions(): void
{
    collect([
        ['building_damage_status', 'fully_damaged', 'Totally Damaged', 'حالة ضرر المبنى'],
        ['building_damage_status', 'partially_damaged', 'Partially Damaged', 'حالة ضرر المبنى'],
        ['building_material', 'concrete', 'Concrete', 'مادة البناء'],
        ['building_material', 'wood', 'Wood', 'مادة البناء'],
        ['building_debris_exist', 'yes', 'Yes', 'وجود ركام'],
        ['building_debris_exist', 'no', 'No', 'وجود ركام'],
        ['uxo_present', 'yes3', 'Yes', 'وجود مخلفات حربية'],
        ['uxo_present', 'no3', 'No', 'وجود مخلفات حربية'],
        ['bodies_present', 'yes3', 'Yes', 'وجود جثث'],
        ['bodies_present', 'no3', 'No', 'وجود جثث'],
    ])->each(function (array $option): void {
        $attributes = [
            'list_name' => $option[0],
            'name' => $option[1],
            'label' => $option[2],
        ];

        if (Schema::hasColumn('filters', 'list_name_arabic')) {
            $attributes['list_name_arabic'] = $option[3];
        }

        Filter::query()->create($attributes);
    });
}
