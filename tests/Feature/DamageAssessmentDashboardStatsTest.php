<?php

use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use App\Services\ArcgisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shows eight summary statistics for public buildings and road facilities on the main dashboard', function () {
    $user = User::factory()->create();

    $this->app->instance(ArcgisService::class, new class extends ArcgisService
    {
        public function getToken(): string
        {
            return 'fake-token';
        }
    });

    PublicBuildingSurvey::query()->create([
        'objectid' => 1001,
        'building_name' => 'Clinic A',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'creationdate' => Carbon::today()->toDateString(),
        'assignedto' => 'Field Team 1',
        'building_damage_status' => 'fully_damaged',
        'is_building_occupied' => 'yes',
        'is_bodies' => 'yes',
        'is_uxo' => 'yes',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 2001,
        'str_name' => 'Road A',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'creationdate' => Carbon::today()->toDateString(),
        'road_damage_level' => 'severe',
        'potholes_exist' => 'yes',
        'obstacle_exist' => 'yes',
        'buried_bodies' => 'yes',
        'uxo_present' => 'yes',
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/damageAssessment');

    $response
        ->assertOk()
        ->assertSee('Neighborhoods')
        ->assertSee('Assigned Staff')
        ->assertSee('Occupied')
        ->assertSee('Bodies')
        ->assertSee('UXO')
        ->assertSee('Potholes')
        ->assertSee('Obstacles')
        ->assertSee('Buried Bodies')
        ->assertSee('Period by neighborhood')
        ->assertSee('Filter By')
        ->assertSee('Select neighborhood')
        ->assertSee('All neighborhoods')
        ->assertSee('Date range')
        ->assertSee('data-period="day"', false)
        ->assertSee('data-period="all"', false)
        ->assertSee('Rimal');
});

it('prevents field engineers from viewing the main damage assessment dashboard', function () {
    $role = Role::query()->create([
        'name' => 'Field Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('damageAssessment.index'))
        ->assertForbidden();
});

it('keeps the main damage assessment dashboard visible to non field engineers', function () {
    $user = User::factory()->create();

    $this->app->instance(ArcgisService::class, new class extends ArcgisService
    {
        public function getToken(): string
        {
            return 'fake-token';
        }
    });

    $this->actingAs($user)
        ->get(route('damageAssessment.index'))
        ->assertOk();
});

it('treats the dashboard yesterday shortcut as the previous day', function () {
    $user = User::factory()->create();
    Carbon::setTestNow('2026-05-16 10:00:00');

    $this->app->instance(ArcgisService::class, new class extends ArcgisService
    {
        public function getToken(): string
        {
            return 'fake-token';
        }
    });

    Building::query()->create([
        'objectid' => 901,
        'globalid' => 'yesterday-building',
        'building_name' => 'Yesterday Building',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'neighborhood' => 'Rimal',
        'end' => '2026-05-15 09:00:00',
    ]);

    Building::query()->create([
        'objectid' => 902,
        'globalid' => 'today-building',
        'building_name' => 'Today Building',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'partially_damaged',
        'neighborhood' => 'Rimal',
        'end' => '2026-05-16 09:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('damageAssessment.index', ['period' => 'day']))
        ->assertOk()
        ->assertViewHas('buildingStats', function (array $buildingStats): bool {
            return (int) $buildingStats['completed'] === 1
                && (int) $buildingStats['fully_damaged'] === 1
                && (int) $buildingStats['partially_damaged'] === 0;
        });

    $this->actingAs($user)
        ->get(route('damageAssessment.index', ['period' => 'yesterday']))
        ->assertOk()
        ->assertViewHas('dashboardFilters', function (array $dashboardFilters): bool {
            return $dashboardFilters['period'] === 'day'
                && $dashboardFilters['startDate'] === '2026-05-15'
                && $dashboardFilters['endDate'] === '2026-05-15';
        });
});

it('filters dashboard map tables by period and neighborhood', function () {
    $user = User::factory()->create();
    $today = Carbon::today()->toDateString();

    Building::query()->create([
        'objectid' => 501,
        'globalid' => 'building-rimal',
        'building_name' => 'Home A',
        'neighborhood' => 'Rimal',
        'creationdate' => '2026-01-01',
        'editdate' => $today,
        'end' => $today,
    ]);

    Building::query()->create([
        'objectid' => 502,
        'globalid' => 'building-sabra',
        'building_name' => 'Home B',
        'neighborhood' => 'Sabra',
        'creationdate' => '2026-01-01',
        'editdate' => $today,
        'end' => $today,
    ]);

    HousingUnit::query()->create([
        'objectid' => 601,
        'globalid' => 'unit-rimal',
        'parentglobalid' => 'building-rimal',
        'unit_damage_status' => 'fully_damaged2',
        'creationdate' => '2026-01-01',
        'editdate' => $today,
    ]);

    HousingUnit::query()->create([
        'objectid' => 602,
        'globalid' => 'unit-sabra',
        'parentglobalid' => 'building-sabra',
        'unit_damage_status' => 'partially_damaged2',
        'creationdate' => '2026-01-01',
        'editdate' => $today,
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 1001,
        'building_name' => 'Clinic A',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'creationdate' => $today,
        'building_damage_status' => 'fully_damaged',
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 1002,
        'building_name' => 'Clinic B',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Sabra',
        'creationdate' => $today,
        'building_damage_status' => 'partially_damaged',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 2001,
        'str_name' => 'Road A',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'creationdate' => $today,
        'road_damage_level' => 'severe',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 2002,
        'str_name' => 'Road B',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Sabra',
        'creationdate' => $today,
        'road_damage_level' => 'minor',
    ]);

    $filters = [
        'neighborhood' => 'Rimal',
        'from_date' => $today,
        'to_date' => $today,
    ];

    $this->actingAs($user)->getJson(route('housing-units-map', $filters))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.neighborhood', 'Rimal');

    $this->actingAs($user)->getJson(route('public-buildings-map', $filters))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.neighborhood', 'Rimal');

    $this->actingAs($user)->getJson(route('road-facilities-map', $filters))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.neighborhood', 'Rimal');
});

it('accepts a flatpickr date range string in dashboard filters', function () {
    $user = User::factory()->create();

    PublicBuildingSurvey::query()->create([
        'objectid' => 1001,
        'building_name' => 'Clinic A',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'creationdate' => '2026-05-15',
        'building_damage_status' => 'fully_damaged',
    ]);

    $this->actingAs($user)->getJson(route('public-buildings-map', [
        'neighborhood' => 'Rimal',
        'from_date' => '2026-05-12 to 2026-05-21',
    ]))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.neighborhood', 'Rimal');
});

it('filters the homepage building map table by the building end date', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 701,
        'globalid' => 'building-with-current-end',
        'building_name' => 'Current End Building',
        'neighborhood' => 'Rimal',
        'end' => '2026-05-18 10:00:00',
        'editdate' => '2026-01-01',
    ]);

    Building::query()->create([
        'objectid' => 702,
        'globalid' => 'building-with-old-end',
        'building_name' => 'Old End Building',
        'neighborhood' => 'Rimal',
        'end' => '2026-05-10 10:00:00',
        'editdate' => '2026-05-18',
    ]);

    HousingUnit::query()->create([
        'objectid' => 801,
        'globalid' => 'unit-current-end',
        'parentglobalid' => 'building-with-current-end',
        'unit_damage_status' => 'fully_damaged2',
        'editdate' => '2026-01-01',
    ]);

    HousingUnit::query()->create([
        'objectid' => 802,
        'globalid' => 'unit-old-end',
        'parentglobalid' => 'building-with-old-end',
        'unit_damage_status' => 'partially_damaged2',
        'editdate' => '2026-05-18',
    ]);

    $this->actingAs($user)->getJson(route('housing-units-map', [
        'from_date' => '2026-05-18',
        'to_date' => '2026-05-18',
        'neighborhood' => 'Rimal',
    ]))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.building_name', 'Current End Building');
});

it('returns latest dashboard stats as json', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 701,
        'globalid' => 'latest-building',
        'building_name' => 'Latest Building',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'security_situation' => 'Safe',
        'uxo_present' => 'no3',
        'bodies_present' => 'no3',
        'building_debris_exist' => 'no',
    ]);

    HousingUnit::query()->create([
        'objectid' => 801,
        'globalid' => 'latest-unit',
        'parentglobalid' => 'latest-building',
        'unit_damage_status' => 'committee_review2',
        'has_fire' => 'no',
        'unit_stripping' => 'no',
        'is_the_housing_unit_or_living_habitable' => 'yes',
        'security_situation_unit' => 'Safe',
        'unit_support_needed' => 'no',
    ]);

    $this->actingAs($user)
        ->getJson(route('damageAssessment.latest-stats'))
        ->assertOk()
        ->assertJsonPath('buildingStats.completed', 1)
        ->assertJsonPath('buildingStats.fully_damaged', 1)
        ->assertJsonPath('unitStats.total_units', 1)
        ->assertJsonPath('unitStats.committee_review', 1);
});

it('renders the live hud dashboard from database statistics', function () {
    $user = User::factory()->create();

    $this->app->instance(ArcgisService::class, new class extends ArcgisService
    {
        public function getToken(): string
        {
            return 'fake-token';
        }
    });

    Building::query()->create([
        'objectid' => 1101,
        'globalid' => 'hud-building-1',
        'building_name' => 'HUD Building A',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza Municipality',
        'neighborhood' => 'Rimal',
        'building_debris_qty' => '120.5',
        'latitude' => 31.52,
        'longitude' => 34.45,
    ]);

    Building::query()->create([
        'objectid' => 1102,
        'globalid' => 'hud-building-2',
        'building_name' => 'HUD Building B',
        'field_status' => 'Not_Completed',
        'building_damage_status' => 'partially_damaged',
        'governorate' => 'North Gaza',
        'municipalitie' => 'North Municipality',
        'neighborhood' => 'Jabalia',
        'building_debris_qty' => '30',
    ]);

    HousingUnit::query()->create([
        'objectid' => 1201,
        'globalid' => 'hud-unit-1',
        'parentglobalid' => 'hud-building-1',
        'unit_damage_status' => 'fully_damaged2',
        'unit_support_needed' => 'yes',
        'is_the_housing_unit_or_living_habitable' => 'no',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza Municipality',
        'neighborhood' => 'Rimal',
    ]);

    HousingUnit::query()->create([
        'objectid' => 1202,
        'globalid' => 'hud-unit-2',
        'parentglobalid' => 'hud-building-2',
        'unit_damage_status' => 'partially_damaged2',
        'unit_support_needed' => 'no',
        'is_the_housing_unit_or_living_habitable' => 'yes',
        'governorate' => 'North Gaza',
        'municipalitie' => 'North Municipality',
        'neighborhood' => 'Jabalia',
    ]);

    $this->actingAs($user)
        ->get(route('damageAssessment.hud'))
        ->assertOk()
        ->assertSee('LIVE GIS HUD')
        ->assertSee('https://js.arcgis.com/4.22/', false)
        ->assertSee("'esri/views/MapView'", false)
        ->assertSee("'esri/layers/FeatureLayer'", false)
        ->assertSee('new MapView', false)
        ->assertSee('new FeatureLayer', false)
        ->assertSee("type: 'simple-fill'", false)
        ->assertSee('assessmentBaseUrl', false)
        ->assertSee('const gazaStripExtent = new Extent({', false)
        ->assertSee('view.goTo(gazaStripExtent', false)
        ->assertSee('fake-token')
        ->assertDontSee('L.map', false)
        ->assertDontSee('unpkg.com/leaflet', false)
        ->assertSee('إجمالي مباني القطاع')
        ->assertSee('المباني المقيّمة ميدانياً')
        ->assertSee('وحدات مدمرة كلياً')
        ->assertSee('HUD Building A')
        ->assertSee('تقارير البلديات والأحياء')
        ->assertSee('Gaza Municipality')
        ->assertSee('North Municipality')
        ->assertSee('Rimal')
        ->assertSee('Jabalia')
        ->assertSee('municipalityChart0')
        ->assertSee('data: [1,1,0,0]', false);
});
