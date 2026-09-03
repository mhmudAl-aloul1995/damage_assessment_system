<?php

use App\Models\Building;
use App\Models\CsoSurveyOrganization;
use App\Models\DashboardCard;
use App\Models\User;
use Database\Seeders\DashboardCardSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('seeds the default dashboard cards and items', function (): void {
    $this->seed(DashboardCardSeeder::class);

    expect(DashboardCard::query()->count())->toBe(5)
        ->and(DashboardCard::query()->where('key', 'buildings')->first()?->items)->toHaveCount(8)
        ->and(DashboardCard::query()->where('key', 'buildings')->first()?->items()->first()?->sort_order)->toBe(1)
        ->and(DashboardCard::query()->where('key', 'housing')->first()?->items)->toHaveCount(9)
        ->and(DashboardCard::query()->where('key', 'housing')->first()?->items()->first()?->sort_order)->toBe(1);
});

it('lets database officers manage dashboard card items', function (): void {
    $this->seed(DashboardCardSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));

    $card = DashboardCard::query()->where('key', 'buildings')->firstOrFail();
    $card->items()->create([
        'key' => 'count_by_damage_status',
        'title' => 'عد حسب الضرر',
        'source_bucket' => 'buildingStats',
        'stat_key' => 'building_damage_status',
        'icon' => 'ki-shield-cross',
        'calculation_type' => 'count_condition',
        'filter_field' => 'building_damage_status',
        'filter_operator' => '=',
        'filter_value' => 'fully_damaged',
        'sort_order' => 90,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard-cards.index'))
        ->assertOk()
        ->assertSee('إدارة بطاقات لوحة التحكم')
        ->assertSee('المباني')
        ->assertSee('ضرر كلي')
        ->assertDontSee('ui.damage_dashboard.fully_damaged')
        ->assertSee('data-control="select2"', false)
        ->assertSee('js-icon-select')
        ->assertSee('js-stat-key-group d-none', false)
        ->assertSee('ضرر - ki-shield-cross')
        ->assertSee('field_status')
        ->assertSee('csoOrganizationStats')
        ->assertSee('csoUnitStats');

    $this->actingAs($user)
        ->post(route('admin.dashboard-cards.items.store', $card), [
            'title' => 'بند ديناميكي',
            'source_bucket' => 'buildingStats',
            'stat_key' => 'completed',
            'link_group' => 'buildings',
            'link_key' => 'completed',
            'calculation_type' => 'stat_key',
            'conditions' => [
                ['field' => 'field_status', 'operator' => '=', 'value' => 'COMPLETED'],
                ['field' => 'building_damage_status', 'operator' => '=', 'value' => 'fully_damaged'],
            ],
            'decimal_places' => 0,
            'sort_order' => 99,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.dashboard-cards.index', ['card' => $card->id]));

    expect($card->items()->where('title', 'بند ديناميكي')->exists())->toBeTrue();

    $item = $card->items()->where('title', 'بند ديناميكي')->firstOrFail();

    expect($item->key)->toBe('completed_2')
        ->and($item->icon)->toBe('ki-dot')
        ->and($item->filter_field)->toBe('field_status')
        ->and($item->filter_value)->toBe('COMPLETED')
        ->and($item->options['conditions'])->toHaveCount(2);

    $this->actingAs($user)
        ->put(route('admin.dashboard-cards.items.update', [$card, $item]), [
            'key' => $item->key,
            'title' => 'بند ديناميكي محدث',
            'source_bucket' => 'buildingStats',
            'stat_key' => 'completed',
            'icon' => $item->icon,
            'link_group' => 'buildings',
            'link_key' => 'completed',
            'calculation_type' => 'stat_key',
            'filter_field' => 'field_status',
            'filter_operator' => '=',
            'filter_value' => 'COMPLETED',
            'decimal_places' => 0,
            'sort_order' => 100,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.dashboard-cards.index', ['card' => $card->id]));

    expect($item->refresh()->title)->toBe('بند ديناميكي محدث')
        ->and($item->sort_order)->toBe(100);
});

it('keeps item sort order independent for each dashboard card', function (): void {
    $this->seed(DashboardCardSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));

    $buildingCard = DashboardCard::query()->where('key', 'buildings')->firstOrFail();
    $housingCard = DashboardCard::query()->where('key', 'housing')->firstOrFail();

    $this->actingAs($user)
        ->get(route('admin.dashboard-cards.index', ['card' => $buildingCard->id]))
        ->assertOk()
        ->assertSee('value="9"', false);

    $this->actingAs($user)
        ->get(route('admin.dashboard-cards.index', ['card' => $housingCard->id]))
        ->assertOk()
        ->assertSee('value="10"', false);
});

it('returns distinct filter values for the selected table field', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));

    Building::query()->create([
        'objectid' => 9001,
        'globalid' => 'dashboard-filter-value-one',
        'field_status' => 'COMPLETED',
    ]);

    Building::query()->create([
        'objectid' => 9002,
        'globalid' => 'dashboard-filter-value-two',
        'field_status' => 'COMPLETED',
    ]);

    Building::query()->create([
        'objectid' => 9003,
        'globalid' => 'dashboard-filter-value-three',
        'field_status' => 'Not_Completed',
    ]);

    $this->actingAs($user)
        ->getJson(route('admin.dashboard-cards.filter-values', [
            'source_bucket' => 'buildingStats',
            'field' => 'field_status',
        ]))
        ->assertOk()
        ->assertJsonPath('values', ['COMPLETED', 'Not_Completed']);
});

it('returns distinct filter values for cso child tables', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));

    CsoSurveyOrganization::query()->create([
        'objectid' => 9101,
        'globalid' => 'dashboard-cso-org-value-one',
        'parentglobalid' => 'dashboard-cso-survey-one',
        'operational_status' => 'operational',
    ]);

    CsoSurveyOrganization::query()->create([
        'objectid' => 9102,
        'globalid' => 'dashboard-cso-org-value-two',
        'parentglobalid' => 'dashboard-cso-survey-two',
        'operational_status' => 'operational',
    ]);

    CsoSurveyOrganization::query()->create([
        'objectid' => 9103,
        'globalid' => 'dashboard-cso-org-value-three',
        'parentglobalid' => 'dashboard-cso-survey-three',
        'operational_status' => 'partially_operational',
    ]);

    $this->actingAs($user)
        ->getJson(route('admin.dashboard-cards.filter-values', [
            'source_bucket' => 'csoOrganizationStats',
            'field' => 'operational_status',
        ]))
        ->assertOk()
        ->assertJsonPath('values', ['operational', 'partially_operational']);
});
