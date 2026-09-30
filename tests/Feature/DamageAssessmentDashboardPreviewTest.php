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
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('renders the damage assessment dashboard preview page', function (string $routeName): void {
    Http::fake();

    $response = $this->actingAs(User::factory()->create())->get(route($routeName));

    $response
        ->assertOk()
        ->assertViewIs('damage-assessment::dashboard.preview')
        ->assertSee('معاينة الصفحة الرئيسية')
        ->assertSee('معاينة Metronic')
        ->assertSee('معاينة تصميم · بيانات توضيحية.')
        ->assertSee('فلاتره مستقلة عن البطاقات الفعلية أعلاه.')
        ->assertSee('فلاتر البطاقات الفعلية')
        ->assertSee('فلاتر المعاينة')
        ->assertSee('لا توجد نتائج مطابقة')
        ->assertSee('بحث في السجلات')
        ->assertSee('dashboard_preview_gis_map')
        ->assertSee('dashboard-preview.js')
        ->assertSee('href="'.route('damageAssessment.index').'"', false);

    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-preview-sector]'))->toHaveCount(6);
    expect($xpath->query('//*[@data-preview-sector and @aria-selected="true"]'))->toHaveCount(1);
    expect($xpath->query('//*[@data-preview-stat]'))->toHaveCount(4);
    expect($xpath->query('//*[@id="preview-records"]/tr'))->toHaveCount(8);
    expect($xpath->query('//*[@data-preview-view="table" and @aria-pressed="true"]'))->toHaveCount(1);
    expect($xpath->query('//*[@id="preview-map-panel" and @hidden]'))->toHaveCount(1);

    foreach (['overview', 'buildings', 'housing', 'cso', 'public', 'roads'] as $sector) {
        $tab = $xpath->query('//*[@id="preview-tab-'.$sector.'"]')->item(0);
        $pane = $xpath->query('//*[@id="preview-pane-'.$sector.'"]')->item(0);

        expect($tab)->not->toBeNull();
        expect($pane)->not->toBeNull();
        expect($tab->getAttribute('aria-controls'))->toBe($pane->getAttribute('id'));
        expect($pane->getAttribute('aria-labelledby'))->toBe($tab->getAttribute('id'));
    }

    foreach (['building.index', 'housing.index', 'cso-surveys.index', 'public-buildings.index', 'road-facilities.index'] as $sectorRoute) {
        $response->assertSee('href="'.route($sectorRoute).'"', false);
    }

    $data = json_decode($xpath->query('//*[@id="preview-dashboard-data"]')->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
    expect($data['records'])->toHaveCount(30);
    expect(array_unique(array_column($data['records'], 'id')))->toHaveCount(30);
    foreach (array_keys($data['sectors']) as $sector) {
        expect(array_filter($data['records'], fn (array $record): bool => $record['sector'] === $sector))->toHaveCount(6);
    }
    foreach (['completed', 'review', 'blocked'] as $status) {
        $expected = count(array_filter($data['records'], fn (array $record): bool => $record['status'] === $status));
        expect((int) $xpath->query('//*[@data-preview-stat="'.$status.'"]')->item(0)->textContent)->toBe($expected);
    }

    Http::assertNothingSent();
})->with(['damageAssessment.preview', 'damageAssessment.dashboard-preview']);

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

    $card->update(['title' => 'عنوان محدث من الإدارة']);
    $this->get(route($routeName, $filters))->assertOk()->assertSee('عنوان محدث من الإدارة');
    $empty = $this->get(route($routeName, [...$filters, 'from_date' => '2026-09-22', 'to_date' => '2026-09-22']))->assertOk();
    expect($empty['publicBuildingStats']['total_surveys'])->toBe(0);
    expect($empty['dashboardCardItemValues'][$customItem->id])->toBe(0);
    Http::assertNothingSent();
})->with(['damageAssessment.preview', 'damageAssessment.dashboard-preview']);

it('limits preview cards to the same sectors allowed on the dashboard', function (): void {
    $this->seed(DashboardCardSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('CSO Officer', 'web'));

    $response = $this->actingAs($user)->get(route('damageAssessment.preview'))->assertOk();
    expect($response['dashboardCards']->pluck('key')->all())->toBe(['cso_surveys']);
    $response->assertDontSee('data-dashboard-card="buildings"', false);
});

it('shows an empty state when no dashboard cards are active', function (): void {
    $this->seed(DashboardCardSeeder::class);
    DashboardCard::query()->update(['is_active' => false]);

    $this->actingAs(User::factory()->create())->get(route('damageAssessment.preview'))
        ->assertOk()->assertSee('لا توجد بطاقات مفعّلة لعرضها.');
});

it('requires authentication to view the dashboard design preview', function (string $routeName): void {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['damageAssessment.preview', 'damageAssessment.dashboard-preview']);
