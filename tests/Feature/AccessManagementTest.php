<?php

use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\Access\PermissionCatalog;
use App\Support\Navigation\Sidebar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function accessManager(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));

    return $user;
}

it('renders the role editor in both supported languages with safe role names', function (string $locale): void {
    $manager = accessManager();
    $manager->update(['preferred_locale' => $locale]);
    foreach (['roles.view', 'users.view', 'audit.view', 'audit.assign', 'audit.actions', 'audit.final-approve', 'reports.view', 'reports.export', 'buildings.view', 'buildings.create', 'buildings.update', 'buildings.delete'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $role = Role::findOrCreate('مشرف التدقيق', 'web');
    $role->givePermissionTo(['audit.view', 'audit.assign', 'reports.view']);
    Role::findOrCreate('<script>alert(1)</script>', 'web');

    $this->actingAs($manager)->get(route('roles.index'))->assertOk()
        ->assertSee('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false)
        ->assertSee('id="create-role"', false)
        ->assertSee(__('access.title'), false)
        ->assertDontSee('<script>alert(1)</script>', false);
})->with(['ar', 'en']);

it('renders the access center for a role viewer without write controls', function (): void {
    $viewer = User::factory()->create(['preferred_locale' => 'ar']);
    $viewer->givePermissionTo(Permission::findOrCreate('roles.view', 'web'));
    Permission::findOrCreate('audit.actions', 'web');
    Permission::findOrCreate('heks.view', 'web');

    $this->actingAs($viewer)->get(route('roles.index'))->assertOk()
        ->assertSee('id="role-editor"', false)
        ->assertSee('id="permission-search"', false)
        ->assertSee('audit.actions')
        ->assertSee('heks.view')
        ->assertDontSee('id="create-role"', false)
        ->assertSee(__('ui.permissions.audit_actions'), false);

    $urls = Sidebar::forUser($viewer)->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['items'])->pluck('url');
    expect($urls)->toContain('user-management/roles')->not->toContain('user-management/user');
});

it('denies access management to a user without permissions', function (): void {
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('Unprivileged role', 'web');
    $this->actingAs($viewer)->get(route('roles.index'))->assertForbidden();
    $this->getJson(route('roles.edit', $role))->assertForbidden();
    $this->getJson(route('roles.members', $role))->assertForbidden();
    $this->get(route('users.access', $viewer))->assertForbidden();
    $this->postJson(route('roles.store'), ['name' => 'New role', 'permissions' => []])->assertForbidden();
    $this->putJson(route('roles.update', $role), ['name' => $role->name, 'permissions' => []])->assertForbidden();
    $this->deleteJson(route('roles.destroy', $role))->assertForbidden();
});

it('creates a role with selected permissions and records the change', function (): void {
    $manager = accessManager();
    Permission::findOrCreate('reports.export', 'web');

    $response = $this->actingAs($manager)->postJson(route('roles.store'), [
        'name' => 'Report exporters', 'permissions' => ['reports.export'],
    ])->assertOk()->assertJsonPath('role.users_count', 0)->assertJsonPath('role.permissions.0', 'reports.export');

    $role = Role::findByName('Report exporters');
    expect($role->hasPermissionTo('reports.export'))->toBeTrue();
    $log = UserActivityLog::query()->where('metadata->access_control', true)->firstOrFail();
    expect($log->metadata['before'])->toBeNull()
        ->and($log->metadata['after']['permissions'])->toBe(['reports.export'])
        ->and($response->json('role.revision'))->toHaveLength(64);
});

it('updates permissions atomically and rejects an outdated editor', function (): void {
    $manager = accessManager();
    Permission::findOrCreate('reports.view', 'web');
    Permission::findOrCreate('reports.export', 'web');
    $role = Role::findOrCreate('Reports team', 'web');
    $role->givePermissionTo('reports.view');
    $member = User::factory()->create();
    $member->assignRole($role);
    $snapshot = $this->actingAs($manager)->getJson(route('roles.edit', $role))->assertOk()->json('role');

    $this->putJson(route('roles.update', $role), [
        'name' => 'Export team', 'permissions' => ['reports.export'], 'revision' => $snapshot['revision'],
    ])->assertOk()->assertJsonPath('role.users_count', 1);

    expect($member->fresh()->can('reports.export'))->toBeTrue()
        ->and($member->fresh()->can('reports.view'))->toBeFalse();

    $this->putJson(route('roles.update', $role), [
        'name' => 'Stale name', 'permissions' => [], 'revision' => $snapshot['revision'],
    ])->assertConflict();
    expect($role->fresh()->name)->toBe('Export team')
        ->and($role->fresh()->hasPermissionTo('reports.export'))->toBeTrue();

    $log = UserActivityLog::query()->where('metadata->access_control', true)->firstOrFail();
    expect($log->metadata['before']['permissions'])->toBe(['reports.view'])
        ->and($log->metadata['after']['permissions'])->toBe(['reports.export']);
});

it('requires a complete valid web permission selection', function (array $payload, string $field): void {
    $manager = accessManager();
    Permission::findOrCreate('api-only', 'api');
    Permission::findOrCreate('reports.view', 'web');

    $this->actingAs($manager)->postJson(route('roles.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Role::query()->where('name', 'Invalid role')->exists())->toBeFalse();
})->with([
    'missing name' => [['permissions' => []], 'name'],
    'missing selection' => [['name' => 'Invalid role'], 'permissions'],
    'wrong guard' => [['name' => 'Invalid role', 'permissions' => ['api-only']], 'permissions.0'],
    'unknown permission' => [['name' => 'Invalid role', 'permissions' => ['missing']], 'permissions.0'],
    'duplicate permission' => [['name' => 'Invalid role', 'permissions' => ['reports.view', 'reports.view']], 'permissions.0'],
]);

it('prevents delegated administrators from granting permissions they do not hold', function (): void {
    $manager = User::factory()->create();
    foreach (['roles.create', 'roles.update', 'roles.delete', 'reports.export'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $manager->givePermissionTo(['roles.create', 'roles.update', 'roles.delete']);
    $role = Role::findOrCreate('Privileged team', 'web');
    $role->givePermissionTo('reports.export');

    $this->actingAs($manager)->postJson(route('roles.store'), [
        'name' => 'Escalation', 'permissions' => ['reports.export'],
    ])->assertForbidden();

    $this->putJson(route('roles.update', $role), [
        'name' => $role->name, 'permissions' => [], 'revision' => app(PermissionCatalog::class)->revision($role->load('permissions')),
    ])->assertForbidden();
    $this->deleteJson(route('roles.destroy', $role))->assertForbidden();
    expect($role->fresh()->hasPermissionTo('reports.export'))->toBeTrue();
});

it('allows a delegated creator to create only a role within their grants', function (): void {
    $manager = User::factory()->create();
    foreach (['roles.create', 'reports.view'] as $name) {
        $manager->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }
    $this->actingAs($manager)->postJson(route('roles.store'), [
        'name' => 'Read reports', 'permissions' => ['reports.view'],
    ])->assertOk();
    $this->postJson(route('roles.store'), ['name' => 'Manager', 'permissions' => []])->assertForbidden();
});

it('protects system role names and the administrator role', function (): void {
    $manager = accessManager();
    $role = Role::findOrCreate('Area Manager', 'web');
    $this->actingAs($manager)->putJson(route('roles.update', $role), [
        'name' => 'Renamed system role', 'permissions' => [],
        'revision' => app(PermissionCatalog::class)->revision($role->load('permissions')),
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->deleteJson(route('roles.destroy', $role))->assertForbidden();

    $adminRole = Role::findByName('Database Officer');
    $this->putJson(route('roles.update', $adminRole), [
        'name' => $adminRole->name, 'permissions' => [],
        'revision' => app(PermissionCatalog::class)->revision($adminRole->load('permissions')),
    ])->assertForbidden();
    $this->deleteJson(route('roles.destroy', $adminRole))->assertForbidden();
});

it('requires reassignment before deleting a role with members', function (): void {
    $manager = accessManager();
    $role = Role::findOrCreate('Assigned custom role', 'web');
    $member = User::factory()->create();
    $member->assignRole($role);

    $this->actingAs($manager)->deleteJson(route('roles.destroy', $role))->assertUnprocessable();
    $member->removeRole($role);
    $this->deleteJson(route('roles.destroy', $role))->assertOk();
    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});

it('shows every source of a permission and existing data scope', function (): void {
    $manager = accessManager();
    $permission = Permission::findOrCreate('reports.export', 'web');
    $firstRole = Role::findOrCreate('Reports reviewer', 'web');
    $secondRole = Role::findOrCreate('Reports supervisor', 'web');
    $firstRole->givePermissionTo($permission);
    $secondRole->givePermissionTo($permission);
    $user = User::factory()->create(['region' => 'Test Region', 'allowed_phase_numbers' => [2, 3], 'default_phase_number' => 2]);
    $user->assignRole([$firstRole, $secondRole]);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($manager)->get(route('users.access', $user))->assertOk()
        ->assertSee('Test Region')->assertSee('reports.export')->assertSee('Reports reviewer')->assertSee('Reports supervisor')
        ->assertSee('data-direct="1" data-inherited="1"', false);
    expect($response->viewData('accessGroups')['reports'][0]['roles']->all())->toBe(['Reports reviewer', 'Reports supervisor']);

    $user->revokePermissionTo($permission);
    $this->get(route('users.access', $user))->assertOk()
        ->assertSee('data-direct="0" data-inherited="1"', false);
    expect($user->fresh()->can('reports.export'))->toBeTrue();
});

it('restricts role membership data to user viewers and paginates the results', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::findOrCreate('roles.view', 'web'));
    $role = Role::findOrCreate('Members team', 'web');
    User::factory()->count(26)->create()->each(fn (User $user) => $user->assignRole($role));

    $this->actingAs($viewer)->getJson(route('roles.members', $role))->assertForbidden();
    $viewer->givePermissionTo(Permission::findOrCreate('users.view', 'web'));
    $this->getJson(route('roles.members', $role))->assertOk()->assertJsonCount(25, 'data')
        ->assertJsonPath('total', 26)->assertJsonStructure(['data' => [['name', 'email', 'url']]]);
});

it('keeps unknown permissions visible and excludes other guards', function (): void {
    foreach (['new-module.special', 'damage-assessment.building-deletion.request', 'user-activity-logs.view'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    Permission::findOrCreate('api-only', 'api');
    $groups = app(PermissionCatalog::class)->groups()->keyBy('key');

    expect($groups['other']['permissions']->pluck('name')->all())->toBe(['new-module.special'])
        ->and($groups['damage_assessment']['permissions']->pluck('name')->all())->toContain('damage-assessment.building-deletion.request')
        ->and($groups['system']['permissions']->pluck('name')->all())->toContain('user-activity-logs.view')
        ->and($groups->flatMap(fn (array $group) => $group['permissions'])->pluck('name')->all())->not->toContain('api-only');
});

it('does not expose roles from another guard', function (): void {
    $manager = accessManager();
    $role = Role::findOrCreate('API administrator', 'api');
    $this->actingAs($manager)->get(route('roles.index'))->assertOk()->assertDontSee('API administrator');
    $this->getJson(route('roles.edit', $role))->assertNotFound();
    $this->getJson(route('roles.members', $role))->assertNotFound();
    $this->deleteJson(route('roles.destroy', $role))->assertNotFound();
});

it('copies registered permissions into an independent role', function (): void {
    $manager = accessManager();
    $permission = Permission::findOrCreate('reports.view', 'web');
    $source = Role::findOrCreate('Original team', 'web');
    $source->givePermissionTo($permission);
    $member = User::factory()->create();
    $member->assignRole($source);

    $this->actingAs($manager)->postJson(route('roles.store'), [
        'name' => 'Copied team', 'permissions' => $source->permissions->pluck('name')->all(),
    ])->assertOk()->assertJsonPath('role.users_count', 0);

    $copy = Role::findByName('Copied team');
    $this->putJson(route('roles.update', $copy), [
        'name' => $copy->name, 'permissions' => [],
        'revision' => app(PermissionCatalog::class)->revision($copy->load('permissions')),
    ])->assertOk();

    expect($copy->fresh()->permissions)->toHaveCount(0)
        ->and($source->fresh()->hasPermissionTo('reports.view'))->toBeTrue()
        ->and($member->fresh()->can('reports.view'))->toBeTrue();
});

it('links the users table to the access details page', function (): void {
    $manager = accessManager();
    $member = User::factory()->create();

    $response = $this->actingAs($manager)->getJson(route('users.show'))->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $member->id);

    expect($row['action'])->toContain(route('users.access', $member));
});
