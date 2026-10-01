<?php

use App\Models\User;
use App\Support\Navigation\SectorNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;

it('builds sector workspaces around records audit reports and exports', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(collect(SectorNavigation::forUser('buildings', $user))->pluck('key')->all())->toBe([
        'records',
        'audit',
        'reports',
        'export',
    ])->and(collect(SectorNavigation::forUser('public-buildings', $user))->pluck('key')->all())->toBe([
        'records',
        'audit',
        'reports',
        'productivity',
        'export',
    ]);
});

it('only shows sector tools available to the user role', function () {
    $role = Role::findOrCreate('Team Leader', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(collect(SectorNavigation::forUser('buildings', $user))->pluck('key')->all())->toBe([
        'records',
    ]);
});

it('preserves the selected sector on shared audit and export pages', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $tabs = collect(SectorNavigation::forUser('housing-units', $user))->keyBy('key');

    expect($tabs['audit']['url'])->toContain('sector=housing-units')
        ->and($tabs['export']['url'])->toContain('sector=housing-units');
});

it('renders an accessible sector navigation with localized labels', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);
    app()->setLocale('ar');

    $html = Blade::render("@include('damage-assessment::components.sector-navigation', ['sector' => 'road-facilities'])");

    expect($html)
        ->toContain('مساحة قطاع الطرق')
        ->toContain('التنقل داخل قطاع الطرق')
        ->toContain('السجلات')
        ->toContain('التدقيق')
        ->toContain('التقارير')
        ->toContain('الإنتاجية')
        ->toContain('تصدير البيانات');
});

it('marks the current sector tab as active', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $request = Request::create('/damage-assessment/road-facilities');
    $route = app('router')->getRoutes()->getByName('road-facilities.index');
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    $tabs = collect(SectorNavigation::forUser('road-facilities', $user))->keyBy('key');

    expect($tabs['records']['is_active'])->toBeTrue()
        ->and($tabs['audit']['is_active'])->toBeFalse()
        ->and(SectorNavigation::sectorForCurrentRoute())->toBe('road-facilities');
});
