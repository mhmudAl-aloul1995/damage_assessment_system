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

    Building::query()->create([
        'objectid' => 101,
        'globalid' => 'other-building-global-id',
        'building_name' => 'Other Building',
        'assignedto' => 'Other Engineer',
        'field_status' => 'Not_Completed',
        'building_damage_status' => 'partially_damaged',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Jabalia',
        'end' => '2026-05-18 08:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 201,
        'globalid' => 'other-unit-global-id',
        'parentglobalid' => 'other-building-global-id',
        'unit_damage_status' => 'partially_damaged2',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Jabalia',
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
        ->assertSee('id="hud_filter_security_priority"', false)
        ->assertSee('data-field="municipalitie"', false)
        ->assertSee('data-field="neighborhood"', false)
        ->assertSee("replace(/\\/hud\\/?$/, '/arcgis/options')", false)
        ->assertSee('buildHudArcgisWhere', false)
        ->assertSee("hudArcgisFieldName('building_name') + \" LIKE", false)
        ->assertSee('hudArcgisFieldName', false)
        ->assertSee('hudArcgisSecurityPriorityExpression', false)
        ->assertSee('buildingsLayer.definitionExpression = whereExpression', false)
        ->assertSee('assessment_obstacle', false)
        ->assertSee('security_situation', false)
        ->assertSee('Building Name', false)
        ->assertSee('building_damage_status', false)
        ->assertSee('auditBaseUrl', false)
        ->assertSee('/showAssessmentAudit', false)
        ->assertSee('is-audit', false)
        ->assertSee('security_priority', false)
        ->assertSee('Lower(Trim(DefaultValue($feature.assessment_obstacle', false)
        ->assertSee('Lower(Trim(DefaultValue($feature.security_situation', false)
        ->assertSee("['Unsafe', 'unsafe', 'UNSAFE']", false)
        ->assertSee('يوجد عائق', false)
        ->assertSee('مراجعة لجنة', false)
        ->assertSee('178, 92, 255', false)
        ->assertSee("'esri/widgets/BasemapGallery'", false)
        ->assertSee("'esri/widgets/Expand'", false)
        ->assertSee('new BasemapGallery', false)
        ->assertSee("expandIconClass: 'esri-icon-basemap'", false)
        ->assertSee('id="hudBasemapSelect"', false)
        ->assertSee('ArcGIS Satellite', false)
        ->assertSee('map.basemap = event.target.value', false)
        ->assertSee('hudStatsUrl', false)
        ->assertSee('refreshHudDashboardData', false)
        ->assertSee('id="hudTotalBuildings"', false)
        ->assertSee('multiple', false)
        ->assertSee('select2', false)
        ->assertSee('hudArcgisInExpression', false)
        ->assertSee("params.append(element.dataset.field + '[]'", false)
        ->assertSee('url.searchParams.append(key, value)', false);
});

it('returns hud stats for all data by default and filtered data when filters are present', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 300,
        'globalid' => 'gaza-building',
        'building_name' => 'Gaza Building',
        'assignedto' => 'Field Engineer',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'building_debris_qty' => '10',
        'end' => '2026-05-19 08:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 400,
        'globalid' => 'gaza-unit',
        'parentglobalid' => 'gaza-building',
        'unit_damage_status' => 'fully_damaged2',
        'unit_support_needed' => 'yes',
        'is_the_housing_unit_or_living_habitable' => 'no',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
    ]);

    Building::query()->create([
        'objectid' => 301,
        'globalid' => 'north-building',
        'building_name' => 'North Building',
        'assignedto' => 'Other Engineer',
        'field_status' => 'Not_Completed',
        'building_damage_status' => 'partially_damaged',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Jabalia',
        'building_debris_qty' => '5',
        'end' => '2026-05-18 08:00:00',
    ]);

    Building::query()->create([
        'objectid' => 302,
        'globalid' => 'obstacle-building',
        'building_name' => 'Obstacle Building',
        'assignedto' => 'Security Engineer',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'committee_review',
        'assessment_obstacle' => 'yes',
        'security_situation' => 'Unsafe',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'building_debris_qty' => '7',
        'end' => '2026-05-17 08:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 401,
        'globalid' => 'north-unit',
        'parentglobalid' => 'north-building',
        'unit_damage_status' => 'partially_damaged2',
        'unit_support_needed' => 'no',
        'is_the_housing_unit_or_living_habitable' => 'yes',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Jabalia',
    ]);

    HousingUnit::query()->create([
        'objectid' => 402,
        'globalid' => 'obstacle-unit',
        'parentglobalid' => 'obstacle-building',
        'unit_damage_status' => 'committee_review2',
        'unit_support_needed' => 'no',
        'is_the_housing_unit_or_living_habitable' => 'no',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
    ]);

    $this->actingAs($user)
        ->getJson(route('damageAssessment.hud.stats'))
        ->assertOk()
        ->assertJsonPath('summaryStats.total_buildings', 3)
        ->assertJsonPath('summaryStats.fully_damaged_units', 1)
        ->assertJsonPath('assessedUnitsTotal', 3);

    $this->actingAs($user)
        ->getJson(route('damageAssessment.hud.stats', ['municipalitie' => 'Gaza']))
        ->assertOk()
        ->assertJsonPath('summaryStats.total_buildings', 2)
        ->assertJsonPath('summaryStats.assessed_buildings', 2)
        ->assertJsonPath('summaryStats.fully_damaged_units', 1)
        ->assertJsonPath('damageChart.data.0', 1)
        ->assertJsonPath('damageChart.data.1', 0)
        ->assertJsonPath('damageChart.data.2', 1)
        ->assertJsonPath('municipalityReports.0.name', 'Gaza');

    $this->actingAs($user)
        ->getJson(route('damageAssessment.hud.stats', [
            'municipalitie' => ['Gaza', 'North Gaza'],
        ]))
        ->assertOk()
        ->assertJsonPath('summaryStats.total_buildings', 3)
        ->assertJsonPath('assessedUnitsTotal', 3);

    $this->actingAs($user)
        ->getJson(route('damageAssessment.hud.stats', ['security_priority' => '1']))
        ->assertOk()
        ->assertJsonPath('summaryStats.total_buildings', 1)
        ->assertJsonPath('summaryStats.assessed_buildings', 1)
        ->assertJsonPath('damageChart.data.0', 0)
        ->assertJsonPath('damageChart.data.2', 1)
        ->assertJsonPath('municipalityReports.0.summary.units', 1);
});
