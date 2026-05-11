<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('groups permissions on the role management page', function (): void {
    $user = User::factory()->create();

    Permission::findOrCreate('roles.view', 'web');
    Permission::findOrCreate('users.view', 'web');
    Permission::findOrCreate('reports.export', 'web');
    Permission::findOrCreate('audit.assign', 'web');

    Role::findOrCreate('Sample Role', 'web');
    $user->givePermissionTo('roles.view');

    $this->actingAs($user)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertSee(__('ui.permission_groups.user_management'), false)
        ->assertSee(__('ui.permission_groups.reports'), false)
        ->assertSee(__('ui.permission_groups.audit'), false)
        ->assertSee('users.view', false)
        ->assertSee('reports.export', false)
        ->assertSee('audit.assign', false);
});
