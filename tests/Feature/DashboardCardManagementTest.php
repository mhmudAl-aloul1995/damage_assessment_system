<?php

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
        ->and(DashboardCard::query()->where('key', 'housing')->first()?->items)->toHaveCount(9);
});

it('lets database officers manage dashboard card items', function (): void {
    $this->seed(DashboardCardSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));

    $card = DashboardCard::query()->where('key', 'buildings')->firstOrFail();

    $this->actingAs($user)
        ->get(route('admin.dashboard-cards.index'))
        ->assertOk()
        ->assertSee('إدارة بطاقات لوحة التحكم')
        ->assertSee('Buildings')
        ->assertSee('ui.damage_dashboard.fully_damaged')
        ->assertSee('field_status');

    $this->actingAs($user)
        ->post(route('admin.dashboard-cards.items.store', $card), [
            'key' => 'new_dynamic_item',
            'title' => 'بند ديناميكي',
            'source_bucket' => 'buildingStats',
            'stat_key' => 'completed',
            'icon' => 'ki-check-circle',
            'link_group' => 'buildings',
            'link_key' => 'completed',
            'calculation_type' => 'stat_key',
            'filter_field' => 'field_status',
            'filter_operator' => '=',
            'filter_value' => 'COMPLETED',
            'decimal_places' => 0,
            'sort_order' => 99,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.dashboard-cards.index', ['card' => $card->id]));

    expect($card->items()->where('key', 'new_dynamic_item')->exists())->toBeTrue();

    $item = $card->items()->where('key', 'new_dynamic_item')->firstOrFail();

    $this->actingAs($user)
        ->put(route('admin.dashboard-cards.items.update', [$card, $item]), [
            'key' => 'new_dynamic_item',
            'title' => 'بند ديناميكي محدث',
            'source_bucket' => 'buildingStats',
            'stat_key' => 'completed',
            'icon' => 'ki-check-circle',
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
