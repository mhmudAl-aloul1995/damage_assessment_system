<?php

use App\Models\User;
use App\Models\UserActivityLog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('records authenticated page visits', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();

    $this->assertDatabaseHas('user_activity_logs', [
        'user_id' => $user->id,
        'action_type' => 'page_visit',
        'method' => 'GET',
        'url' => '/profile',
        'route_name' => 'profile.edit',
        'status_code' => 200,
    ]);
});

it('does not record activity log datatable requests', function (): void {
    $user = User::factory()->create();

    Permission::findOrCreate('user-activity-logs.view', 'web');
    $user->givePermissionTo('user-activity-logs.view');

    $this->actingAs($user)
        ->get(route('user-activity-logs.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
        ->assertOk();

    expect(UserActivityLog::query()->count())->toBe(0);
});

it('does not record technical pwa asset requests', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/background-sync.js')
        ->assertOk();

    expect(UserActivityLog::query()->count())->toBe(0);
});
