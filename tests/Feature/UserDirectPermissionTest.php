<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows a user with a direct permission to view user management', function (): void {
    $user = User::factory()->create();

    Permission::findOrCreate('users.view', 'web');
    $user->givePermissionTo('users.view');

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee(__('ui.users.title'), false);
});

it('blocks user management when the user has no matching role or permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertForbidden();
});

it('stores direct permissions for a managed user', function (): void {
    Mail::fake();

    $manager = User::factory()->create();
    $managedRole = Role::findOrCreate('Managed Role', 'web');

    Permission::findOrCreate('users.create', 'web');
    Permission::findOrCreate('reports.export', 'web');
    $manager->givePermissionTo('users.create');

    $this->actingAs($manager)
        ->postJson(route('users.store'), [
            'name' => 'Managed User',
            'email' => 'managed@example.com',
            'phone' => '0599000000',
            'roles' => [$managedRole->name],
            'permissions' => ['reports.export'],
        ])
        ->assertOk()
        ->assertJson([
            'message' => __('ui.users.saved'),
        ]);

    $managedUser = User::query()->where('email', 'managed@example.com')->firstOrFail();

    expect($managedUser->hasRole($managedRole))->toBeTrue()
        ->and($managedUser->hasDirectPermission('reports.export'))->toBeTrue();
});
