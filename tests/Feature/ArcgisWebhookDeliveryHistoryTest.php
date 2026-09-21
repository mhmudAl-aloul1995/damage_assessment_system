<?php

use App\Models\ArcgisWebhookDelivery;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('shows arcgis webhook history to database officers', function (): void {
    $user = User::factory()->create();
    $databaseOfficer = Role::findOrCreate('Database Officer', 'web');

    $user->assignRole($databaseOfficer);

    ArcgisWebhookDelivery::query()->create([
        'source' => 'arcgis_cso',
        'webhook_name' => 'CSOs Damage Assessment',
        'event_names' => ['FeaturesCreated'],
        'status' => 'success',
        'http_status' => 200,
        'signature_present' => true,
        'summary' => [
            'upserted' => 2,
            'deleted' => 0,
            'skipped' => 1,
        ],
        'started_at' => now(),
        'finished_at' => now(),
        'duration_ms' => 150,
    ]);

    $this->actingAs($user)
        ->get(route('admin.arcgis-webhook-deliveries.index'))
        ->assertOk()
        ->assertSee('ArcGIS Webhook History')
        ->assertSee(route('admin.arcgis-webhook-deliveries.data'), false);

    $this->actingAs($user)
        ->getJson(route('admin.arcgis-webhook-deliveries.data'))
        ->assertOk()
        ->assertSee('CSOs Damage Assessment')
        ->assertSee('FeaturesCreated')
        ->assertSee('upserted: 2');
});

it('blocks arcgis webhook history from ordinary authenticated users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.arcgis-webhook-deliveries.index'))
        ->assertForbidden();
});

it('allows users with system logs permission to view arcgis webhook history', function (): void {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('system-logs.view', 'web');

    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->get(route('admin.arcgis-webhook-deliveries.index'))
        ->assertOk();
});
