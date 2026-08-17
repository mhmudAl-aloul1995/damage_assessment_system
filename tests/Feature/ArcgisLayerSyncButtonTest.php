<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('shows the ArcGIS force sync button to database officers', function (): void {
    $user = User::factory()->create();
    $databaseOfficer = Role::findOrCreate('Database Officer', 'web');

    $user->assignRole($databaseOfficer);

    $this->actingAs($user)
        ->get(route('system.logs'))
        ->assertOk()
        ->assertSee('data-arcgis-force-sync', false)
        ->assertSee(route('system.logs.sync-arcgis-layers'), false);
});

it('hides the ArcGIS force sync button from users without the database officer role', function (): void {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('system-logs.view', 'web');

    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->get(route('system.logs'))
        ->assertOk()
        ->assertDontSee(route('system.logs.sync-arcgis-layers'), false)
        ->assertDontSee(__('ui.arcgis_sync.button_force'), false);
});

it('blocks ArcGIS force sync for users without the database officer role', function (): void {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('system-logs.view', 'web');

    $user->givePermissionTo($permission);

    Artisan::shouldReceive('call')->never();

    $this->actingAs($user)
        ->postJson(route('system.logs.sync-arcgis-layers'))
        ->assertForbidden();
});

it('runs sync arcgis layers with force for database officers', function (): void {
    $user = User::factory()->create();
    $databaseOfficer = Role::findOrCreate('Database Officer', 'web');

    $user->assignRole($databaseOfficer);

    Artisan::shouldReceive('call')
        ->once()
        ->with('sync:arcgis-layers', ['--force' => true])
        ->andReturn(0);

    Artisan::shouldReceive('output')
        ->once()
        ->andReturn('Sync finished.');

    $this->actingAs($user)
        ->postJson(route('system.logs.sync-arcgis-layers'))
        ->assertOk()
        ->assertJson([
            'message' => __('ui.arcgis_sync.completed'),
        ]);
});
