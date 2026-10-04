<?php

use App\Models\AssessmentStatus;
use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyAuditStatus;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\InfAuditStatus;
use App\Models\PublicBuildingAuditStatus;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilityAuditStatus;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use App\Support\Navigation\SectorNavigation;
use App\Support\Navigation\Sidebar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['municipalitie', 'latitude', 'longitude', 'location'] as $column) {
        if (! Schema::hasColumn('audited_buildings', $column)) {
            Schema::table('audited_buildings', fn (Blueprint $table) => $table->text($column)->nullable());
        }
    }

    $this->overviewUser = User::factory()->create();
    $this->overviewUser->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($this->overviewUser);
});

it('renders a simple overview as the first tab and sidebar destination for each sector', function (string $sector): void {
    app()->setLocale('ar');
    $this->get(route('sector-overview.show', $sector))->assertOk()
        ->assertSee('نظرة عامة')->assertSee('التوزيع الجغرافي')->assertSee('توزيع حالات الضرر')
        ->assertSee('sector-progress-chart')->assertSee('https://js.arcgis.com/4.22/', false)
        ->assertDontSee('sector-workspace-title')
        ->assertDontSee('مساحة قطاع');
    $tabs = SectorNavigation::forUser($sector, $this->overviewUser);
    expect($tabs[0]['key'])->toBe('overview')->and($tabs[0]['url'])->toBe(route('sector-overview.show', $sector));
    $section = Sidebar::forUser($this->overviewUser)->firstWhere('key', 'damage_assessment')['sections']->firstWhere('sector', $sector);
    expect($section['url'])->toBe('damage-assessment/sectors/'.$sector);
})->with(['buildings', 'housing-units', 'public-buildings', 'road-facilities', 'cso-surveys']);

it('applies the same municipality neighborhood and damage filters to stats and map records', function (): void {
    foreach ([
        [101, 'Gaza', 'Rimal', 'partial_damage', 'COMPLETED'],
        [102, 'Gaza', 'Rimal', 'partially_damaged', 'completed'],
        [103, 'Gaza', 'Shujaiya', 'fully_damaged', 'COMPLETED'],
        [104, 'Rafah', 'Rimal', 'partially_damaged', 'Not_Completed'],
    ] as [$objectid, $municipality, $neighborhood, $damage, $fieldStatus]) {
        CsoSurvey::query()->create(['objectid' => $objectid, 'municipalitie' => $municipality, 'neighborhood' => $neighborhood,
            'building_damage_status' => $damage, 'field_status' => $fieldStatus, 'latitude' => 31.5, 'longitude' => 34.4]);
    }
    $filters = ['sector' => 'cso-surveys', 'municipality' => 'Gaza', 'neighborhood' => 'Rimal', 'damage_status' => 'partially_damaged'];
    $this->getJson(route('sector-overview.stats', $filters))->assertOk()->assertJsonPath('summary.total', 2)
        ->assertJsonPath('summary.completed', 2)->assertJsonPath('summary.pending', 2)->assertJsonPath('damage.partially_damaged', 2)
        ->assertJsonPath('neighborhoods', ['Rimal', 'Shujaiya']);
    $this->getJson(route('sector-overview.map', $filters))->assertOk()->assertJsonCount(2, 'features')
        ->assertJsonPath('features.0.attributes.objectid', 101)->assertJsonPath('features.0.geometry.spatialReference.wkid', 4326);
    $this->getJson(route('sector-overview.stats', ['sector' => 'cso-surveys', 'municipality' => '', 'damage_status' => '']))
        ->assertOk()->assertJsonPath('summary.total', 4);
});

it('uses the latest infrastructure audit state rather than an old approval', function (): void {
    $approved = InfAuditStatus::query()->create(['name' => 'final_approval', 'order_step' => 5]);
    $review = InfAuditStatus::query()->create(['name' => 'need_review', 'order_step' => 3]);
    $survey = CsoSurvey::query()->create(['objectid' => 101, 'field_status' => 'COMPLETED']);
    CsoSurveyAuditStatus::query()->create(['cso_survey_id' => $survey->id, 'status_id' => $approved->id]);
    $this->getJson(route('sector-overview.stats', 'cso-surveys'))->assertJsonPath('summary.approved', 1);
    CsoSurveyAuditStatus::query()->create(['cso_survey_id' => $survey->id, 'status_id' => $review->id]);
    $this->getJson(route('sector-overview.stats', 'cso-surveys'))->assertJsonPath('summary.approved', 0)
        ->assertJsonPath('summary.pending', 0)->assertJsonPath('audit.needs_action', 1)->assertJsonPath('summary.action_required', 1);
});

it('uses audited building values for overview statistics filters and map records', function (): void {
    Building::query()->forceCreate(['objectid' => 1401, 'globalid' => 'building-1401', 'field_status' => 'Not_Completed',
        'municipalitie' => 'Original municipality', 'neighborhood' => 'Original neighborhood', 'building_damage_status' => 'fully_damaged',
        'latitude' => 30.5, 'longitude' => 33.4]);
    Building::query()->forceCreate(['objectid' => 1402, 'globalid' => 'base-only', 'field_status' => 'COMPLETED']);
    AuditedBuilding::query()->create(['objectid' => 1401, 'globalid' => 'building-1401', 'field_status' => 'COMPLETED',
        'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'building_damage_status' => 'partially_damaged',
        'latitude' => 31.5, 'longitude' => 34.4]);
    AuditedBuilding::query()->create(['objectid' => 1402, 'globalid' => 'building-1402', 'field_status' => 'COMPLETED',
        'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'building_damage_status' => 'fully_damaged',
        'latitude' => 31.5, 'longitude' => 34.4]);
    AuditedBuilding::query()->create(['objectid' => 1403, 'globalid' => 'building-1403', 'field_status' => 'COMPLETED',
        'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'building_damage_status' => 'committee_review',
        'latitude' => 31.5, 'longitude' => 34.4]);
    AuditedBuilding::query()->create(['objectid' => 1404, 'globalid' => 'building-1404', 'field_status' => 'COMPLETED',
        'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'building_damage_status' => null,
        'latitude' => 31.5, 'longitude' => 34.4]);
    AuditedBuilding::query()->create(['objectid' => 1405, 'globalid' => 'building-1405', 'field_status' => 'Not_Completed',
        'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'building_damage_status' => null,
        'latitude' => 31.5, 'longitude' => 34.4]);

    $this->get(route('sector-overview.show', 'buildings'))->assertOk()
        ->assertViewHas('municipalities', ['Gaza'])
        ->assertViewHas('statistics', fn (array $statistics): bool => $statistics['summary']['completed'] === 4)
        ->assertSee('sector-overview-summary-row row g-4 mb-5', false)
        ->assertDontSee('sector-overview-summary-row d-flex flex-nowrap gap-4 overflow-auto', false)
        ->assertSee('data-metric="action_required"', false);
    $this->getJson(route('sector-overview.stats', 'buildings'))->assertOk()
        ->assertJsonPath('summary.total', 5)->assertJsonPath('summary.completed', 4)
        ->assertJsonPath('summary.pending', 4)->assertJsonPath('summary.fully_damaged', 1)
        ->assertJsonPath('summary.partially_damaged', 1)->assertJsonPath('summary.committee_review', 1)
        ->assertJsonPath('summary.assessment_blocked', 1)->assertJsonPath('damage.fully_damaged', 1)
        ->assertJsonPath('damage.unclassified', 2);

    $filters = ['sector' => 'buildings', 'municipality' => 'Gaza', 'neighborhood' => 'Rimal', 'damage_status' => 'partially_damaged'];
    $this->getJson(route('sector-overview.stats', $filters))->assertOk()
        ->assertJsonPath('summary.total', 1)->assertJsonPath('summary.completed', 1)
        ->assertJsonPath('damage.partially_damaged', 1)->assertJsonPath('neighborhoods', ['Rimal']);
    $this->getJson(route('sector-overview.map', $filters))->assertOk()->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.attributes.objectid', 1401)
        ->assertJsonPath('features.0.attributes.municipality', 'Gaza')
        ->assertJsonPath('features.0.geometry.x', 34.4);
    $this->getJson(route('sector-overview.stats', ['sector' => 'buildings', 'municipality' => 'Original municipality']))
        ->assertOk()->assertJsonPath('summary.total', 0);
});

it('does not fall back to original buildings when the audited cache is empty', function (): void {
    Building::query()->forceCreate(['objectid' => 1501, 'globalid' => 'base-only', 'field_status' => 'COMPLETED',
        'municipalitie' => 'Gaza', 'latitude' => 31.5, 'longitude' => 34.4]);

    $this->getJson(route('sector-overview.stats', 'buildings'))->assertOk()
        ->assertJsonPath('summary.total', 0)->assertJsonPath('summary.completed', 0);
    $this->getJson(route('sector-overview.map', 'buildings'))->assertOk()->assertJsonCount(0, 'features');
    $this->get(route('sector-overview.show', 'buildings'))->assertOk()->assertViewHas('municipalities', []);
});

it('preserves the selected phase across audited building statistics maps and filter options', function (): void {
    foreach ([1, 2] as $phase) {
        AuditedBuilding::query()->create(['objectid' => 1600 + $phase, 'globalid' => 'audited-phase-'.$phase,
            'phase_number' => $phase, 'municipalitie' => 'municipality-'.$phase, 'neighborhood' => 'neighborhood-'.$phase,
            'field_status' => 'COMPLETED', 'latitude' => 31.5, 'longitude' => 34.4]);
    }

    $this->withSession(['selected_phase_number' => 1]);
    $this->getJson(route('sector-overview.stats', 'buildings'))->assertOk()
        ->assertJsonPath('summary.total', 1)->assertJsonPath('summary.completed', 1)
        ->assertJsonPath('neighborhoods', ['neighborhood-1']);
    $this->getJson(route('sector-overview.map', 'buildings'))->assertOk()->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.attributes.objectid', 1601);
    $this->get(route('sector-overview.show', 'buildings'))->assertOk()->assertViewHas('municipalities', ['municipality-1']);
});

it('counts current building and unit approvals and locates units at their parent building', function (): void {
    $approved = AssessmentStatus::query()->create(['name' => 'final_approval', 'label_en' => 'Final Approval', 'label_ar' => 'اعتماد نهائي', 'stage' => 'team_leader', 'order_step' => 9]);
    $review = AssessmentStatus::query()->create(['name' => 'need_review', 'label_en' => 'Needs Review', 'label_ar' => 'بحاجة لمراجعة', 'stage' => 'engineer', 'order_step' => 5]);
    Building::query()->forceCreate(['objectid' => 201, 'globalid' => 'parent-201', 'field_status' => 'COMPLETED', 'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'latitude' => 31.5, 'longitude' => 34.4]);
    AuditedBuilding::query()->create(['objectid' => 201, 'globalid' => 'parent-201', 'field_status' => 'COMPLETED', 'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'latitude' => 31.5, 'longitude' => 34.4]);
    HousingUnit::query()->forceCreate(['objectid' => 301, 'globalid' => 'unit-301', 'parentglobalid' => 'parent-201', 'unit_municipalitie' => 'Gaza', 'unit_neighborhood' => 'Rimal', 'municipalitie' => 'Displaced residence', 'unit_damage_status' => 'fully_damaged2']);
    BuildingStatus::query()->create(['building_id' => 201, 'status_id' => $approved->id, 'type' => 'Team Leader']);
    HousingStatus::query()->create(['housing_id' => 301, 'status_id' => $approved->id, 'type' => 'Team Leader']);
    $this->getJson(route('sector-overview.stats', 'buildings'))->assertJsonPath('summary.approved', 1);
    BuildingStatus::query()->create(['building_id' => 201, 'status_id' => $review->id, 'type' => 'QC/QA Engineer']);
    $this->getJson(route('sector-overview.stats', 'buildings'))->assertJsonPath('summary.approved', 0)->assertJsonPath('audit.needs_action', 1);
    $filters = ['sector' => 'housing-units', 'municipality' => 'Gaza'];
    $this->getJson(route('sector-overview.stats', $filters))->assertJsonPath('summary.total', 1)
        ->assertJsonPath('summary.completed', 1)->assertJsonPath('summary.approved', 1)->assertJsonPath('damage.fully_damaged', 1);
    $this->getJson(route('sector-overview.map', $filters))->assertOk()->assertJsonPath('features.0.geometry.x', 34.4)
        ->assertJsonPath('features.0.attributes.municipality', 'Gaza');
});

it('shows four primary housing unit cards and retains damage statistics separately', function (): void {
    Building::query()->forceCreate(['objectid' => 231, 'globalid' => 'completed-parent-231', 'field_status' => 'COMPLETED', 'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'latitude' => 31.5, 'longitude' => 34.4]);
    Building::query()->forceCreate(['objectid' => 232, 'globalid' => 'not-completed-parent-232', 'field_status' => 'Not_Completed', 'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'latitude' => 31.5, 'longitude' => 34.4]);

    foreach ([
        [331, 'unit-fully-damaged', 'completed-parent-231', 'fully_damaged2', null],
        [332, 'unit-partially-damaged', 'completed-parent-231', 'partially_damaged2', null],
        [333, 'unit-committee-review', 'completed-parent-231', 'committee_review2', null],
        [334, 'unit-no-damage', 'completed-parent-231', 'no_damaged', null],
        [335, 'unit-with-obstacle', 'completed-parent-231', null, 'yes'],
        [336, 'unit-not-completed-parent', 'not-completed-parent-232', 'fully_damaged2', null],
    ] as [$objectid, $globalid, $parentglobalid, $damageStatus, $securitySituation]) {
        HousingUnit::query()->forceCreate([
            'objectid' => $objectid,
            'globalid' => $globalid,
            'parentglobalid' => $parentglobalid,
            'unit_damage_status' => $damageStatus,
            'security_situation_unit' => $securitySituation,
        ]);
    }

    $this->get(route('sector-overview.show', 'housing-units'))
        ->assertOk()
        ->assertSee('data-metric="total"', false)
        ->assertSee('data-metric="completed"', false)
        ->assertSee('data-metric="action_required"', false)
        ->assertSee('data-metric="approved"', false)
        ->assertDontSee('data-metric="fully_damaged"', false)
        ->assertViewHas('statistics', function (array $statistics): bool {
            return $statistics['summary']['total'] === 6
                && $statistics['summary']['completed'] === 5
                && $statistics['summary']['fully_damaged'] === 2
                && $statistics['summary']['partially_damaged'] === 1
                && $statistics['summary']['committee_review'] === 1
                && $statistics['summary']['no_damage'] === 1
                && $statistics['summary']['assessment_blocked'] === 1;
        });

    $this->getJson(route('sector-overview.stats', 'housing-units'))
        ->assertOk()
        ->assertJsonPath('summary.total', 6)
        ->assertJsonPath('summary.completed', 5)
        ->assertJsonPath('summary.fully_damaged', 2)
        ->assertJsonPath('summary.partially_damaged', 1)
        ->assertJsonPath('summary.committee_review', 1)
        ->assertJsonPath('summary.no_damage', 1)
        ->assertJsonPath('summary.assessment_blocked', 1);
});

it('uses audited housing unit values and dashboard damage criteria when the cache is available', function (): void {
    Building::query()->forceCreate(['objectid' => 240, 'globalid' => 'base-parent-240', 'field_status' => 'COMPLETED', 'municipalitie' => 'Original', 'neighborhood' => 'Original', 'latitude' => 30.5, 'longitude' => 33.4]);
    HousingUnit::query()->forceCreate(['objectid' => 340, 'globalid' => 'base-unit-340', 'parentglobalid' => 'base-parent-240', 'unit_damage_status' => 'fully_damaged2']);

    AuditedBuilding::query()->create(['objectid' => 241, 'globalid' => 'audited-parent-241', 'field_status' => 'COMPLETED', 'municipalitie' => 'Gaza', 'neighborhood' => 'Rimal', 'latitude' => 31.5, 'longitude' => 34.4]);

    foreach ([
        [341, 'audited-unit-full', 'fully_damaged2', null],
        [342, 'audited-unit-legacy-full', 'fully_damaged', null],
        [343, 'audited-unit-partial', 'partially_damaged2', null],
        [344, 'audited-unit-committee', 'committee_review2', null],
        [345, 'audited-unit-no-damage', 'no_damaged', null],
        [346, 'audited-unit-obstacle', null, 'yes'],
    ] as [$objectid, $globalid, $damageStatus, $securitySituation]) {
        AuditedHousingUnit::query()->create([
            'objectid' => $objectid,
            'globalid' => $globalid,
            'parentglobalid' => 'audited-parent-241',
            'unit_damage_status' => $damageStatus,
            'security_situation_unit' => $securitySituation,
        ]);
    }

    $this->getJson(route('sector-overview.stats', 'housing-units'))
        ->assertOk()
        ->assertJsonPath('summary.total', 6)
        ->assertJsonPath('summary.fully_damaged', 1)
        ->assertJsonPath('summary.partially_damaged', 1)
        ->assertJsonPath('summary.committee_review', 1)
        ->assertJsonPath('summary.no_damage', 1)
        ->assertJsonPath('summary.assessment_blocked', 1)
        ->assertJsonPath('damage.fully_damaged', 1)
        ->assertJsonPath('damage.unclassified', 2)
        ->assertJsonPath('neighborhoods', ['Rimal']);

    $this->getJson(route('sector-overview.map', 'housing-units'))
        ->assertOk()
        ->assertJsonPath('features.0.attributes.objectid', 341)
        ->assertJsonPath('features.0.attributes.municipality', 'Gaza')
        ->assertJsonPath('features.0.geometry.x', 34.4);
});

it('normalizes road damage and preserves ArcGIS line and polygon geometries', function (): void {
    RoadFacilitySurvey::query()->create(['objectid' => 501, 'field_status' => 'COMPLETED', 'road_damage_level' => 'No_Damage',
        'location' => json_encode(['paths' => [[[3830000, 3690000], [3830100, 3690100]]]])]);
    PublicBuildingSurvey::query()->create(['objectid' => 601, 'field_status' => 'COMPLETED',
        'location' => json_encode(['rings' => [[[34.4, 31.5], [34.5, 31.5], [34.5, 31.6], [34.4, 31.5]]], 'spatialReference' => ['wkid' => 4326]])]);
    $this->getJson(route('sector-overview.stats', ['sector' => 'road-facilities', 'damage_status' => 'no_damage']))
        ->assertJsonPath('summary.total', 1)->assertJsonPath('damage.no_damage', 1);
    $this->getJson(route('sector-overview.map', 'road-facilities'))->assertJsonPath('features.0.geometry.spatialReference.wkid', 3857)
        ->assertJsonCount(1, 'features.0.geometry.paths');
    $this->getJson(route('sector-overview.map', 'public-buildings'))->assertJsonCount(1, 'features.0.geometry.rings');
});

it('handles missing locations unknown damage and empty filtered results without changing totals', function (): void {
    CsoSurvey::query()->create(['objectid' => 701, 'building_damage_status' => 'unknown', 'location' => 'invalid json']);
    $this->getJson(route('sector-overview.stats', ['sector' => 'cso-surveys', 'damage_status' => 'unclassified']))
        ->assertJsonPath('summary.total', 1)->assertJsonPath('damage.unclassified', 1);
    $this->getJson(route('sector-overview.map', 'cso-surveys'))->assertJsonCount(0, 'features')->assertJsonPath('scanned', 1)->assertJsonPath('next_cursor', null);
    $this->getJson(route('sector-overview.stats', ['sector' => 'cso-surveys', 'municipality' => 'Nonexistent']))
        ->assertJsonPath('summary.total', 0)->assertJsonPath('summary.completed', 0);
});

it('paginates map records without losing or duplicating a record', function (string $sector, string $model): void {
    $rows = collect(range(1, 501))->map(fn (int $id): array => ['objectid' => $id, 'latitude' => 31.5, 'longitude' => 34.4])->all();
    $model::query()->insert($rows);
    $first = $this->getJson(route('sector-overview.map', $sector))->assertJsonCount(500, 'features')->assertJsonPath('scanned', 500);
    $last = $this->getJson(route('sector-overview.map', ['sector' => $sector, 'after_id' => $first->json('next_cursor')]))
        ->assertJsonCount(1, 'features')->assertJsonPath('next_cursor', null);
    expect($last->json('features.0.attributes.objectid'))->toBe(501);
})->with([
    'cso surveys' => ['cso-surveys', CsoSurvey::class],
    'audited buildings' => ['buildings', AuditedBuilding::class],
]);

it('maps buildings and their housing units when the legacy buildings table has no location column', function (): void {
    if (Schema::hasColumn('buildings', 'location')) {
        Schema::table('buildings', fn (Blueprint $table) => $table->dropColumn('location'));
    }
    Building::query()->forceCreate(['objectid' => 1101, 'globalid' => 'legacy-building', 'latitude' => 31.5, 'longitude' => 34.4]);
    Schema::table('audited_buildings', fn (Blueprint $table) => $table->dropColumn('location'));
    AuditedBuilding::query()->create(['objectid' => 1101, 'globalid' => 'legacy-building', 'latitude' => 31.5, 'longitude' => 34.4]);
    HousingUnit::query()->forceCreate(['objectid' => 1102, 'globalid' => 'legacy-unit', 'parentglobalid' => 'legacy-building']);

    foreach (['buildings', 'housing-units'] as $sector) {
        $this->getJson(route('sector-overview.map', $sector))->assertOk()->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.geometry.x', 34.4)->assertJsonPath('features.0.geometry.y', 31.5)
            ->assertJsonPath('features.0.geometry.spatialReference.wkid', 4326);
    }
});

it('maps stored geometries when latitude and longitude columns are absent', function (): void {
    Schema::table('cso_surveys', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude']));
    CsoSurvey::query()->create(['objectid' => 1201, 'location' => json_encode(['x' => 34.4, 'y' => 31.5, 'spatialReference' => ['wkid' => 4326]])]);

    $this->getJson(route('sector-overview.map', 'cso-surveys'))->assertOk()->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.geometry.x', 34.4);
});

it('returns an empty map rather than an error when a legacy table has no geographic columns', function (): void {
    $geographicColumns = array_values(array_intersect(['location', 'latitude', 'longitude'], Schema::getColumnListing('audited_buildings')));
    Schema::table('audited_buildings', fn (Blueprint $table) => $table->dropColumn($geographicColumns));
    AuditedBuilding::query()->create(['objectid' => 1301, 'globalid' => 'unlocated-building']);

    $this->getJson(route('sector-overview.map', 'buildings'))->assertOk()->assertJsonCount(0, 'features')
        ->assertJsonPath('scanned', 1)->assertJsonPath('next_cursor', null);
});

it('respects the selected phase for stats maps and municipality options', function (): void {
    Building::query()->forceCreate(['objectid' => 801, 'globalid' => 'phase-one', 'phase_number' => 1, 'municipalitie' => 'phase-one', 'field_status' => 'COMPLETED', 'latitude' => 31.5, 'longitude' => 34.4]);
    Building::query()->forceCreate(['objectid' => 802, 'globalid' => 'phase-two', 'phase_number' => 2, 'municipalitie' => 'phase-two', 'field_status' => 'COMPLETED', 'latitude' => 31.5, 'longitude' => 34.4]);
    foreach (['phase-one' => 901, 'phase-two' => 902] as $parent => $objectid) {
        HousingUnit::query()->forceCreate(['objectid' => $objectid, 'globalid' => 'unit-'.$objectid, 'parentglobalid' => $parent, 'unit_municipalitie' => $parent]);
    }
    $this->withSession(['selected_phase_number' => 1]);
    $this->getJson(route('sector-overview.stats', 'housing-units'))->assertJsonPath('summary.total', 1);
    $this->getJson(route('sector-overview.map', 'housing-units'))->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.attributes.objectid', 901);
    $this->get(route('sector-overview.show', 'housing-units'))->assertViewHas('municipalities', ['phase-one']);
});

it('enforces sector permissions on the page stats and map endpoints', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    foreach (['show', 'stats', 'map', 'records'] as $action) {
        $this->getJson(route('sector-overview.'.$action, 'buildings'))->assertForbidden();
    }
    $user->givePermissionTo(Permission::findOrCreate('cso-surveys.view', 'web'));
    $this->getJson(route('sector-overview.stats', 'cso-surveys'))->assertOk();
    $this->getJson(route('sector-overview.map', 'public-buildings'))->assertForbidden();
});

it('rejects invalid sectors filters and map cursors', function (): void {
    $this->get('/damage-assessment/sectors/unknown')->assertNotFound();
    $this->getJson(route('sector-overview.stats', ['sector' => 'buildings', 'damage_status' => 'severe']))->assertUnprocessable()->assertJsonValidationErrors('damage_status');
    $this->getJson(route('sector-overview.map', ['sector' => 'buildings', 'after_id' => -1]))->assertUnprocessable()->assertJsonValidationErrors('after_id');
    $this->getJson(route('sector-overview.stats', ['sector' => 'buildings', 'municipality' => ['Gaza']]))->assertUnprocessable();
});

it('separates assigned surveys from surveys awaiting audit', function (): void {
    $assigned = InfAuditStatus::query()->create(['name' => 'assigned', 'order_step' => 1]);
    $survey = CsoSurvey::query()->create(['objectid' => 1001, 'field_status' => 'COMPLETED']);
    CsoSurveyAuditStatus::query()->create(['cso_survey_id' => $survey->id, 'status_id' => $assigned->id]);
    $this->getJson(route('sector-overview.stats', 'cso-surveys'))->assertJsonPath('summary.pending', 0)->assertJsonPath('audit.assigned', 1)
        ->assertJsonPath('progress.in_review', 0)->assertJsonPath('summary.approved', 0);
});

it('highlights only the selected sector overview and requires a signed in user', function (): void {
    $this->get(route('sector-overview.show', 'road-facilities'))->assertOk();
    $request = Request::create(route('sector-overview.show', 'road-facilities'));
    $matchedRoute = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $matchedRoute);
    app()->instance('request', $request);
    expect(collect(SectorNavigation::forUser('road-facilities', $this->overviewUser))->firstWhere('key', 'overview')['is_active'])->toBeTrue()
        ->and(collect(SectorNavigation::forUser('buildings', $this->overviewUser))->firstWhere('key', 'overview')['is_active'])->toBeFalse();
    auth()->logout();
    $this->getJson(route('sector-overview.stats', 'road-facilities'))->assertUnauthorized();
});

it('partitions completed records by their latest audit state without counting unfinished approvals', function (string $sector, string $model, string $history, string $foreignKey): void {
    $buildingSector = in_array($sector, ['buildings', 'housing-units'], true);
    $states = $buildingSector
        ? ['assigned_to_engineer', 'accepted_by_engineer', 'assigned_to_lawyer', 'accepted_by_lawyer', 'legal_notes', 'final_reject', 'final_approval', 'undp_final_approve', 'unexpected']
        : ['assigned', 'accepted', 'need_review', 'rejected', 'final_approval', 'unexpected'];
    $statusModel = $buildingSector ? AssessmentStatus::class : InfAuditStatus::class;
    foreach ([null, ...$states, 'unfinished'] as $index => $name) {
        $completed = $name !== 'unfinished';
        if ($sector === 'housing-units') {
            AuditedBuilding::query()->create(['objectid' => 2000 + $index, 'globalid' => 'parent-'.$index, 'field_status' => $completed ? 'COMPLETED' : 'Not_Completed', 'latitude' => 31.5, 'longitude' => 34.4]);
        }
        $record = $model::query()->forceCreate(['objectid' => 3000 + $index, 'globalid' => 'record-'.$index,
            ...($sector === 'housing-units' ? ['parentglobalid' => 'parent-'.$index] : ['field_status' => $completed ? ' completed ' : 'Not_Completed', 'location' => json_encode(['x' => 34.4, 'y' => 31.5, 'spatialReference' => ['wkid' => 4326]])])]);
        if ($name !== null) {
            $status = $statusModel::query()->firstOrCreate(['name' => $completed ? $name : 'final_approval'], ['order_step' => $index,
                ...($buildingSector ? ['label_en' => $name, 'label_ar' => $name, 'stage' => 'engineer'] : [])]);
            $history::query()->create([$foreignKey => $foreignKey === 'globalid' ? $record->globalid : ($buildingSector ? $record->objectid : $record->id),
                'status_id' => $status->id, ...($buildingSector ? ['type' => 'QC/QA Engineer'] : [])]);
        }
    }
    $response = $this->getJson(route('sector-overview.stats', $sector))->assertOk()
        ->assertJsonPath('summary.total', count($states) + 2)->assertJsonPath('summary.completed', count($states) + 1)
        ->assertJsonPath('summary.pending', 1)->assertJsonPath('audit.unclassified', 1)
        ->assertJsonPath('summary.action_required', 2)->assertJsonPath('summary.approved', $buildingSector ? 2 : 1);
    expect(array_sum($response->json('audit')))->toBe(count($states) + 1);
    foreach ($response->json('audit') as $bucket => $count) {
        $this->getJson(route('sector-overview.records', ['sector' => $sector, 'audit_status' => $bucket]))
            ->assertOk()->assertJsonPath('total', $count);
    }
    $this->getJson(route('sector-overview.records', ['sector' => $sector, 'field_completion' => 'not_completed']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.audit_status', 'not_completed');
})->with([
    ['buildings', AuditedBuilding::class, BuildingStatus::class, 'building_id'],
    ['housing-units', AuditedHousingUnit::class, HousingStatus::class, 'housing_id'],
    ['public-buildings', PublicBuildingSurvey::class, PublicBuildingAuditStatus::class, 'public_building_survey_id'],
    ['road-facilities', RoadFacilitySurvey::class, RoadFacilityAuditStatus::class, 'globalid'],
    ['cso-surveys', CsoSurvey::class, CsoSurveyAuditStatus::class, 'cso_survey_id'],
]);

it('keeps card drilldowns within selected geography damage and audit filters', function (): void {
    foreach (['need_review', 'rejected', 'final_approval', 'need_review'] as $index => $name) {
        $survey = CsoSurvey::query()->create(['objectid' => 4000 + $index, 'municipalitie' => $index === 3 ? 'Rafah' : 'Gaza',
            'neighborhood' => 'Rimal', 'building_damage_status' => 'partial_damage', 'field_status' => 'COMPLETED', 'latitude' => 31.5, 'longitude' => 34.4]);
        $status = InfAuditStatus::query()->firstOrCreate(['name' => $name], ['order_step' => $index]);
        CsoSurveyAuditStatus::query()->create(['cso_survey_id' => $survey->id, 'status_id' => $status->id]);
    }
    $filters = ['sector' => 'cso-surveys', 'municipality' => 'Gaza', 'neighborhood' => 'Rimal', 'damage_status' => 'partially_damaged',
        'audit_status' => 'needs_action', 'metric' => 'action_required'];
    $this->getJson(route('sector-overview.stats', $filters))->assertOk()->assertJsonPath('summary.total', 1)->assertJsonPath('audit.needs_action', 1);
    $this->getJson(route('sector-overview.map', $filters))->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.attributes.audit_status', 'needs_action');
    $this->getJson(route('sector-overview.records', $filters))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.objectid', 4000);
    $this->getJson(route('sector-overview.records', ['sector' => 'cso-surveys', 'metric' => 'approved']))->assertOk()->assertJsonPath('total', 1);
});

it('paginates matching records and exposes only dashboard attributes', function (): void {
    CsoSurvey::query()->insert(collect(range(1, 21))->map(fn (int $id): array => ['objectid' => $id, 'field_status' => 'COMPLETED'])->all());
    $first = $this->getJson(route('sector-overview.records', ['sector' => 'cso-surveys', 'metric' => 'completed']))
        ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 21)->assertJsonPath('last_page', 2);
    expect(array_keys($first->json('data.0')))->toBe(['record_id', 'objectid', 'parentglobalid', 'municipality', 'neighborhood', 'damage_status', 'field_completed', 'audit_status']);
    $this->getJson(route('sector-overview.records', ['sector' => 'cso-surveys', 'page' => 2]))->assertJsonCount(1, 'data')->assertJsonPath('data.0.objectid', 21);
});

it('applies optional map bounds to all dashboard counts and records in geographic and projected coordinates', function (): void {
    $x = deg2rad(34.4) * 6378137;
    $y = log(tan(M_PI / 4 + deg2rad(31.5) / 2)) * 6378137;
    foreach ([
        ['latitude' => 31.5, 'longitude' => 34.4],
        ['location' => json_encode(['x' => $x, 'y' => $y, 'spatialReference' => ['wkid' => 102100]])],
        ['location' => json_encode(['rings' => [[[34.4, 31.5], [34.45, 31.5], [34.45, 31.55], [34.4, 31.5]]], 'spatialReference' => ['wkid' => 4326]])],
        ['latitude' => 32, 'longitude' => 35],
        [],
    ] as $index => $geometry) {
        CsoSurvey::query()->create(['objectid' => 5000 + $index, 'field_status' => 'COMPLETED', ...$geometry]);
    }
    $this->getJson(route('sector-overview.stats', 'cso-surveys'))->assertJsonPath('summary.total', 5);
    $filters = ['sector' => 'cso-surveys', 'west' => 34.3, 'south' => 31.4, 'east' => 34.6, 'north' => 31.6];
    $this->getJson(route('sector-overview.stats', $filters))->assertOk()->assertJsonPath('summary.total', 3)->assertJsonPath('summary.completed', 3);
    $this->getJson(route('sector-overview.map', $filters))->assertOk()->assertJsonCount(3, 'features')->assertJsonPath('scanned', 3);
    $this->getJson(route('sector-overview.records', $filters))->assertOk()->assertJsonPath('total', 3);
    $filters['west'] = 0;
    $filters['east'] = 1;
    $this->getJson(route('sector-overview.stats', $filters))->assertOk()->assertJsonPath('summary.total', 0);
});

it('uses parent locations for unit bounds and retains the selected phase in drilldowns', function (): void {
    foreach ([1, 2] as $phase) {
        AuditedBuilding::query()->create(['objectid' => 6000 + $phase, 'globalid' => 'phase-parent-'.$phase, 'phase_number' => $phase,
            'field_status' => 'COMPLETED', 'latitude' => 31.5, 'longitude' => 34.4]);
        AuditedHousingUnit::query()->create(['objectid' => 7000 + $phase, 'globalid' => 'phase-unit-'.$phase, 'parentglobalid' => 'phase-parent-'.$phase]);
    }
    $this->withSession(['selected_phase_number' => 1]);
    $filters = ['sector' => 'housing-units', 'west' => 34.3, 'south' => 31.4, 'east' => 34.6, 'north' => 31.6, 'metric' => 'completed'];
    $this->getJson(route('sector-overview.stats', $filters))->assertOk()->assertJsonPath('summary.total', 1);
    $this->getJson(route('sector-overview.records', $filters))->assertOk()->assertJsonPath('data.0.objectid', 7001)->assertJsonPath('data.0.parentglobalid', 'phase-parent-1');
    $this->getJson(route('sector-overview.map', $filters))->assertOk()->assertJsonCount(1, 'features');
});

it('counts multi-select road types once per survey per type and explains the chart denominator', function (): void {
    RoadFacilitySurvey::query()->create(['objectid' => 8001, 'road_type' => ['primary', 'secondary', 'primary']]);
    RoadFacilitySurvey::query()->create(['objectid' => 8002, 'road_type' => ['primary']]);
    RoadFacilitySurvey::query()->create(['objectid' => 8003]);
    $response = $this->getJson(route('sector-overview.stats', 'road-facilities'))->assertOk()->assertJsonPath('summary.total', 3);
    expect(array_column($response->json('chart.rows'), 'count'))->toBe([2, 1, 1]);
    expect($response->json('chart.note'))->toBe(__('sector-overview.chart_notes.road-facilities'));
});

it('uses survey choice labels in sector charts and distinguishes surveys from unique organizations', function (): void {
    App\Models\PublicBuildingFilter::query()->create(['list_name' => 'building_use', 'name' => 'clinic', 'label' => 'عيادة']);
    PublicBuildingSurvey::query()->create(['objectid' => 9001, 'building_use' => 'clinic']);
    $this->getJson(route('sector-overview.stats', 'public-buildings'))->assertOk()->assertJsonPath('chart.rows.0.label', 'عيادة')->assertJsonPath('chart.rows.0.count', 1);
    CsoSurvey::query()->create(['objectid' => 9002, 'operational_status' => 'active']);
    $this->getJson(route('sector-overview.stats', 'cso-surveys'))->assertOk()->assertJsonPath('chart.note', __('sector-overview.chart_notes.cso-surveys'));
});

it('rejects malformed dashboard drilldown and geographic filters', function (array $filters, string $field): void {
    $this->getJson(route('sector-overview.records', ['sector' => 'cso-surveys', ...$filters]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['audit_status' => 'accepted_engineer'], 'audit_status'],
    [['audit_status' => 'action_required'], 'audit_status'],
    [['audit_status' => ['pending']], 'audit_status'],
    [['metric' => ['total']], 'metric'],
    [['field_completion' => 'assigned'], 'field_completion'],
    [['page' => 0], 'page'],
    [['west' => 34.3], 'east'],
    [['west' => 35, 'east' => 34, 'south' => 31, 'north' => 32], 'east'],
    [['west' => 34, 'east' => 35, 'south' => 32, 'north' => 31], 'north'],
]);

it('rejects invisible metric filters on the overview page', function (): void {
    $this->getJson(route('sector-overview.show', ['sector' => 'buildings', 'metric' => 'approved']))
        ->assertUnprocessable()->assertJsonValidationErrors('metric');
    $this->getJson(route('sector-overview.show', ['sector' => 'buildings', 'audit_status' => 'approved']))
        ->assertUnprocessable()->assertJsonValidationErrors('audit_status');
});
