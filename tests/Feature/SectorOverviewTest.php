<?php

use App\Models\AssessmentStatus;
use App\Models\AuditedBuilding;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyAuditStatus;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\InfAuditStatus;
use App\Models\PublicBuildingSurvey;
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
        ->assertSee('sector-progress-chart')->assertSee('https://js.arcgis.com/4.22/', false);
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
        ->assertJsonPath('summary.pending', 0)->assertJsonPath('progress.in_review', 1);
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
        ->assertSee('sector-overview-summary-row d-flex flex-nowrap', false)
        ->assertSee('data-metric="assessment_blocked"', false);
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
    $this->getJson(route('sector-overview.stats', 'buildings'))->assertJsonPath('summary.approved', 0)->assertJsonPath('progress.in_review', 1);
    $filters = ['sector' => 'housing-units', 'municipality' => 'Gaza'];
    $this->getJson(route('sector-overview.stats', $filters))->assertJsonPath('summary.total', 1)
        ->assertJsonPath('summary.completed', 1)->assertJsonPath('summary.approved', 1)->assertJsonPath('damage.fully_damaged', 1);
    $this->getJson(route('sector-overview.map', $filters))->assertOk()->assertJsonPath('features.0.geometry.x', 34.4)
        ->assertJsonPath('features.0.attributes.municipality', 'Gaza');
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
    foreach (['show', 'stats', 'map'] as $action) {
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

it('keeps assigned surveys awaiting audit until a reviewer changes their state', function (): void {
    $assigned = InfAuditStatus::query()->create(['name' => 'assigned', 'order_step' => 1]);
    $survey = CsoSurvey::query()->create(['objectid' => 1001, 'field_status' => 'COMPLETED']);
    CsoSurveyAuditStatus::query()->create(['cso_survey_id' => $survey->id, 'status_id' => $assigned->id]);
    $this->getJson(route('sector-overview.stats', 'cso-surveys'))->assertJsonPath('summary.pending', 1)
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
