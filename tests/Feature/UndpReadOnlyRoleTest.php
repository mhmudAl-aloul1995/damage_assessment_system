<?php

use App\Http\Middleware\EnforceDamageAssessmentReadOnly;
use App\Models\User;
use App\Support\Access\PermissionCatalog;
use App\Support\Navigation\SectorNavigation;
use App\Support\Navigation\Sidebar;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Route::middleware(EnforceDamageAssessmentReadOnly::class)->group(function (): void {
        Route::get('/_undp-test/damage-assessment/api/get-latest-stats', fn () => response()->json(['ok' => true]));
        Route::post('/_undp-test/damage-assessment/api/get-latest-stats', fn () => response()->json(['changed' => true]));
        Route::get('/_undp-test/damage-assessment/reports/damage-statistics/export', fn () => response()->json(['exported' => true]))
            ->name('undp-test.reports.damage-statistics.export');
        Route::get('/_undp-test/damage-assessment/reports/damage-statistics', fn () => response()->json(['report' => true]))
            ->name('undp-test.reports.damage-statistics');
    });
});

function undpUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findByName('UNDP', 'web'));

    return $user;
}

function undpManager(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));

    return $user;
}

it('creates the UNDP role with only the approved damage assessment view permissions', function (): void {
    $role = Role::findByName('UNDP', 'web');
    $expectedPermissions = collect(config('access.role_profiles.UNDP.allowed_permissions'))->sort()->values()->all();

    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe($expectedPermissions)
        ->and($role->permissions->pluck('name'))->not->toContain('reports.export')
        ->and($role->permissions->pluck('name'))->not->toContain('buildings.update');
});

it('blocks mutations and export links while allowing an authorized read route', function (): void {
    $user = undpUser();

    $this->actingAs($user)
        ->getJson('/_undp-test/damage-assessment/api/get-latest-stats')
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->postJson('/_undp-test/damage-assessment/api/get-latest-stats')->assertForbidden();
    $this->getJson('/_undp-test/damage-assessment/reports/damage-statistics/export')->assertForbidden();
});

it('applies each configurable view permission to its matching read route', function (): void {
    $user = undpUser();

    $this->actingAs($user)
        ->getJson('/_undp-test/damage-assessment/reports/damage-statistics')
        ->assertOk();

    Role::findByName('UNDP', 'web')->revokePermissionTo('reports.damage-statistics.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->getJson('/_undp-test/damage-assessment/reports/damage-statistics')->assertForbidden();
});

it('shows UNDP read sections in navigation and omits every export destination', function (): void {
    $user = undpUser();
    $urls = Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => ($section['is_direct'] ?? false) ? [$section] : $section['items'])
        ->flatMap(fn (array $item) => $item['children'] ?? [$item])
        ->pluck('url');
    $buildingTabs = collect(SectorNavigation::forUser('buildings', $user));

    expect($urls)->toContain('damage-assessment/sectors/buildings')
        ->and($urls)->not->toContain('damage-assessment/export-data')
        ->and($buildingTabs->pluck('key'))->toContain('overview', 'records', 'reports')
        ->and($buildingTabs->pluck('key'))->not->toContain('export');
});

it('allows the system manager to edit only the approved UNDP view permissions', function (): void {
    $manager = undpManager();
    $role = Role::findByName('UNDP', 'web')->load('permissions');
    $revision = app(PermissionCatalog::class)->revision($role);

    $this->actingAs($manager)->putJson(route('roles.update', $role), [
        'name' => 'UNDP',
        'permissions' => ['damage-assessments.view', 'reports.view'],
        'revision' => $revision,
    ])->assertOk()
        ->assertJsonPath('role.profile.read_only', true)
        ->assertJsonPath('role.name', 'UNDP');

    Permission::findOrCreate('reports.export', 'web');
    $role = $role->fresh()->load('permissions');
    $this->putJson(route('roles.update', $role), [
        'name' => 'UNDP',
        'permissions' => ['reports.export'],
        'revision' => app(PermissionCatalog::class)->revision($role),
    ])->assertUnprocessable()->assertJsonValidationErrors('permissions');

    $this->deleteJson(route('roles.destroy', $role))->assertForbidden();
});

it('prevents combining UNDP with another role or direct permissions', function (): void {
    $manager = undpManager();
    $user = User::factory()->create(['phone' => '0599000000']);
    $otherRole = Role::findOrCreate('Other role', 'web');
    Permission::findOrCreate('reports.export', 'web');
    $payload = [
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'roles' => ['UNDP', $otherRole->name],
        'permissions' => [],
    ];

    $this->actingAs($manager)->putJson(route('users.update', $user), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('roles');

    $payload['roles'] = ['UNDP'];
    $payload['permissions'] = ['reports.export'];
    $this->putJson(route('users.update', $user), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('roles');
});
