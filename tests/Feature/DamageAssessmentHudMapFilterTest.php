<?php

use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\User;
use App\Services\ArcgisService;

it('renders the hud arcgis map filter controls', function () {
    $user = User::factory()->create();

    $this->app->instance(ArcgisService::class, new class extends ArcgisService
    {
        public function getToken(): string
        {
            return 'fake-token';
        }
    });

    Building::query()->create([
        'objectid' => 100,
        'globalid' => 'building-global-id',
        'building_name' => 'Filtered Building',
        'assignedto' => 'Field Engineer',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'end' => '2026-05-19 08:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 200,
        'globalid' => 'unit-global-id',
        'parentglobalid' => 'building-global-id',
        'unit_damage_status' => 'fully_damaged2',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
    ]);

    $response = $this->actingAs($user)->get(route('damageAssessment.hud'));

    $response
        ->assertOk()
        ->assertSee('id="hudMapFilterPanel"', false)
        ->assertSee('id="hudMapFilterCount"', false)
        ->assertSee('data-field="assignedto"', false)
        ->assertSee('id="hud_filter_building_name"', false)
        ->assertSee('data-field="field_status"', false)
        ->assertSee('data-field="building_damage_status"', false)
        ->assertSee('data-field="municipalitie"', false)
        ->assertSee('data-field="neighborhood"', false)
        ->assertSee("replace(/\\/hud\\/?$/, '/arcgis/options')", false)
        ->assertSee('buildHudArcgisWhere', false)
        ->assertSee("building_name LIKE '%", false)
        ->assertSee('buildingsLayer.definitionExpression = whereExpression', false)
        ->assertSee("'esri/widgets/BasemapGallery'", false)
        ->assertSee("'esri/widgets/Expand'", false)
        ->assertSee('new BasemapGallery', false)
        ->assertSee("expandIconClass: 'esri-icon-basemap'", false);
});
