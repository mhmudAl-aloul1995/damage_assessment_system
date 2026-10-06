<?php

use App\Models\DashboardCard;
use App\Models\PublicBuildingSurvey;
use App\Models\User;
use App\Services\ArcgisService;
use Database\Seeders\DashboardCardSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Cache::flush();
    $this->mock(ArcgisService::class)->shouldReceive('getToken')->andReturn('test-gis-token');
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('renders the official damage assessment dashboard with live GIS layers', function (string $routeName): void {
    Http::fake();
    config()->set('services.arcgis.public_building_survey_layer_url', 'https://example.com/ArcGIS/rest/services/public/FeatureServer');
    $response = $this->actingAs(User::factory()->create())->get(route($routeName));
    $response->assertOk()->assertViewIs('damage-assessment::dashboard.preview')
        ->assertSee('لوحة متابعة تقييم الأضرار')->assertSee('خرائط GIS للقطاعات')
        ->assertSee('تصدير البطاقات')->assertSee('dashboard_cards_export_modal', false)
        ->assertSee('id="cards-municipality"', false)
        ->assertSee('class="damage-dashboard-filter-row"', false)
        ->assertSee('data-dashboard-export-download', false)
        ->assertSee('dashboard-preview.js')
        ->assertDontSee('معاينة Metronic')->assertDontSee('بيانات فعلية')
        ->assertDontSee('خلفية البطاقات')->assertDontSee('البطاقات والبنود المفعّلة حسب إعدادات الإدارة')
        ->assertDontSee('DEMO-')->assertDontSee('مواقع افتراضية');
    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@id="preview-live-cards"]')->item(0)->getAttribute('data-card-theme'))->toBe('soft');
    expect($xpath->query('//*[@aria-label="خيارات خلفية البطاقات"]//a'))->toHaveCount(0);
    expect($xpath->query('//*[@data-gis-sector]'))->toHaveCount(5);
    expect($xpath->query('//*[@data-gis-sector and @aria-pressed="true"]'))->toHaveCount(1);
    expect($xpath->query('//*[@id="dashboard_preview_gis_map"]/ancestor::details'))->toHaveCount(0);
    $data = json_decode($xpath->query('//*[@id="preview-gis-data"]')->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
    expect($data['token'])->toBe('test-gis-token');
    expect($data['sectors']['public']['url'])->toBe('https://example.com/ArcGIS/rest/services/public/FeatureServer/0');
    expect($data['sectors']['housing']['url'])->toBe(config('services.arcgis.housing_units_url'));
    expect($data['sectors']['buildings']['scopeObjectIds'])->toBeNull();
    expect($data)->not->toHaveKey('records');
    Http::assertNothingSent();
})->with(['damageAssessment.index', 'damageAssessment.preview', 'damageAssessment.dashboard-preview']);

it('uses the managed cards with live counts ordering links and expandable items', function (string $routeName): void {
    Http::fake();
    $this->seed(DashboardCardSeeder::class);
    $card = DashboardCard::query()->where('key', 'public_buildings')->firstOrFail();
    $card->update(['title' => 'بطاقة المنشآت المختارة', 'sort_order' => 0]);
    $card->items()->where('key', 'damaged_buildings')->update(['title' => 'بند مخفي', 'is_active' => false]);
    $customItem = $card->items()->create([
        'key' => 'custom_occupied', 'title' => 'منشآت مشغولة مخصصة', 'source_bucket' => 'publicBuildingStats',
        'stat_key' => 'occupied_buildings', 'calculation_type' => 'count_condition',
        'filter_field' => 'is_building_occupied', 'filter_operator' => '=', 'filter_value' => 'yes',
        'sort_order' => 0, 'is_active' => true, 'decimal_places' => 2, 'value_suffix' => 'منشأة',
    ]);
    DashboardCard::query()->where('key', 'housing')->update(['is_active' => false]);
    foreach (['Gaza', 'Rafah'] as $index => $governorate) {
        PublicBuildingSurvey::query()->create([
            'objectid' => 7000 + $index, 'globalid' => 'preview-public-'.$index,
            'governorate' => $governorate, 'neighborhood' => 'Rimal',
            'creationdate' => '2026-09-21', 'field_status' => 'COMPLETED', 'is_building_occupied' => 'yes',
        ]);
    }

    $this->app->instance(ArcgisService::class, new class extends ArcgisService
    {
        public function getToken(): string
        {
            return 'fake-token';
        }
    });
    $this->actingAs(User::factory()->create());
    $filters = ['governorate' => 'Gaza', 'neighborhood' => 'Rimal', 'from_date' => '2026-09-21', 'to_date' => '2026-09-21'];
    $response = $this->get(route($routeName, $filters))->assertOk();
    $original = $this->get(route('damageAssessment.index', $filters))->assertOk();
    expect($response['publicBuildingStats'])->toBe($original['publicBuildingStats']);
    expect($response['dashboardCardItemValues'])->toBe($original['dashboardCardItemValues']);
    expect($response['dashboardCardItemValues'][$customItem->id])->toBe(1);
    expect($response['dashboardCards']->first()->id)->toBe($card->id);
    $response->assertSee('بطاقة المنشآت المختارة')->assertSee('1.00 منشأة')->assertDontSee('بند مخفي');
    $response->assertDontSee('data-dashboard-card="housing"', false);
    $response->assertSee('href="'.route('public-buildings.index').'?filters%5Bis_building_occupied%5D[]=yes"', false);
    $response->assertSee('href="'.route('public-buildings.index').'?with_units=1"', false);
    $response->assertSeeInOrder(['data-dashboard-item="custom_occupied"', '<details class="mt-3">', 'data-dashboard-item="occupied_buildings"'], false);
    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);
    $cardsRow = $xpath->query('//*[@id="preview-live-cards"]')->item(0);
    expect($cardsRow->getAttribute('data-card-theme'))->toBe('soft');
    expect($cardsRow->getAttribute('class'))->toContain('flex-nowrap');
    expect($cardsRow->getAttribute('class'))->not->toContain('row');
    $previewCard = $xpath->query('//*[@data-dashboard-card="public_buildings"]//article[contains(concat(" ", normalize-space(@class), " "), " preview-summary-card ")]')->item(0);
    $previewCardWrapper = $xpath->query('//*[@data-dashboard-card="public_buildings"]')->item(0);
    expect($previewCard)->not->toBeNull();
    expect($previewCardWrapper->getAttribute('class'))->not->toContain('col-');
    expect($previewCard->getAttribute('style'))->toContain('--preview-card-color: '.$card->color);

    $card->update(['title' => 'عنوان محدث من الإدارة']);
    $this->get(route($routeName, $filters))->assertOk()->assertSee('عنوان محدث من الإدارة');
    $empty = $this->get(route($routeName, [...$filters, 'from_date' => '2026-09-22', 'to_date' => '2026-09-22']))->assertOk();
    expect($empty['publicBuildingStats']['total_surveys'])->toBe(0);
    expect($empty['dashboardCardItemValues'][$customItem->id])->toBe(0);
    Http::assertNothingSent();
})->with(['damageAssessment.index', 'damageAssessment.preview', 'damageAssessment.dashboard-preview']);

it('limits preview cards to the same sectors allowed on the dashboard', function (): void {
    $this->seed(DashboardCardSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('CSO Officer', 'web'));

    $response = $this->actingAs($user)->get(route('damageAssessment.preview'))->assertOk();
    expect($response['dashboardCards']->pluck('key')->all())->toBe(['cso_surveys']);
    expect(array_keys($response['previewGis']['sectors']))->toBe(['cso']);
    $response->assertDontSee('data-dashboard-card="buildings"', false);
});

it('shows an empty state when no dashboard cards are active', function (): void {
    $this->seed(DashboardCardSeeder::class);
    DashboardCard::query()->update(['is_active' => false]);

    $this->actingAs(User::factory()->create())->get(route('damageAssessment.preview'))
        ->assertOk()->assertSee('لا توجد بطاقات مفعّلة لعرضها.');
});

it('requires authentication to view the dashboard', function (string $routeName): void {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['damageAssessment.index', 'damageAssessment.preview', 'damageAssessment.dashboard-preview']);

it('keeps dashboard cards available when GIS authentication fails', function (): void {
    $this->mock(ArcgisService::class)->shouldReceive('getToken')->andThrow(new RuntimeException('Unavailable'));
    $response = $this->actingAs(User::factory()->create())->get(route('damageAssessment.preview'))->assertOk();
    expect($response['previewGis']['token'])->toBeNull();
    $response->assertSee('تعذّر الاتصال بخدمة GIS.');
});

it('scopes GIS features to the selected phase and passes the active filters', function (): void {
    foreach ([1, 2] as $phase) {
        PublicBuildingSurvey::query()->create(['objectid' => 9000 + $phase, 'globalid' => 'preview-phase-'.$phase, 'phase_number' => $phase]);
    }
    $user = User::factory()->create(['allowed_phase_numbers' => [2], 'default_phase_number' => 2]);
    $response = $this->actingAs($user)->get(route('damageAssessment.preview', [
        'governorate' => 'Gaza', 'municipalitie' => 'Gaza Municipality', 'neighborhood' => 'Rimal', 'from_date' => '2026-09-01', 'to_date' => '2026-09-30',
    ]))->assertOk();
    expect($response['previewGis']['sectors']['public']['scopeObjectIds'])->toBe([9002]);
    expect($response['previewGis']['filters'])->toBe(['governorate' => 'Gaza', 'municipalitie' => 'Gaza Municipality', 'neighborhood' => 'Rimal', 'from' => '2026-09-01', 'to' => '2026-09-30']);
});
