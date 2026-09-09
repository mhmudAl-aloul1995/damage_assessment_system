<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('inactive users cannot authenticate', function (): void {
    $user = User::factory()->create([
        'is_active' => false,
    ]);

    $response = $this
        ->from('/login')
        ->post('/login', [
            'email' => $user->email,
            'password' => '123456',
        ]);

    $this->assertGuest();
    $response->assertRedirect('/login');
});

test('database officers can bulk activate and deactivate selected users', function (): void {
    $databaseOfficer = User::factory()->create();
    $databaseOfficer->assignRole(Role::findOrCreate('Database Officer', 'web'));

    $activeUser = User::factory()->create([
        'is_active' => true,
    ]);
    $inactiveUser = User::factory()->create([
        'is_active' => false,
        'deactivated_at' => now(),
        'deactivated_by' => $databaseOfficer->id,
    ]);

    DB::table('sessions')->insert([
        'id' => 'active-user-session',
        'user_id' => $activeUser->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'test',
        'last_activity' => now()->timestamp,
    ]);

    $this
        ->actingAs($databaseOfficer)
        ->post(route('users.bulk-status'), [
            'user_ids' => [$activeUser->id, $inactiveUser->id, $databaseOfficer->id],
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('count', 2);

    $this->assertDatabaseHas('users', [
        'id' => $activeUser->id,
        'is_active' => false,
        'deactivated_by' => $databaseOfficer->id,
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $inactiveUser->id,
        'is_active' => false,
        'deactivated_by' => $databaseOfficer->id,
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $databaseOfficer->id,
        'is_active' => true,
    ]);
    $this->assertDatabaseMissing('sessions', [
        'id' => 'active-user-session',
    ]);

    $this
        ->actingAs($databaseOfficer)
        ->post(route('users.bulk-status'), [
            'user_ids' => [$activeUser->id, $inactiveUser->id],
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('count', 2);

    $this->assertDatabaseHas('users', [
        'id' => $activeUser->id,
        'is_active' => true,
        'deactivated_at' => null,
        'deactivated_by' => null,
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $inactiveUser->id,
        'is_active' => true,
        'deactivated_at' => null,
        'deactivated_by' => null,
    ]);
});

test('database officers can update all users matching the current search', function (): void {
    $databaseOfficer = User::factory()->create([
        'name' => 'Current Database Officer',
    ]);
    $databaseOfficer->assignRole(Role::findOrCreate('Database Officer', 'web'));

    $matchingUser = User::factory()->create([
        'name' => 'North Field Engineer',
        'is_active' => true,
    ]);
    $otherUser = User::factory()->create([
        'name' => 'South Field Engineer',
        'is_active' => true,
    ]);

    $this
        ->actingAs($databaseOfficer)
        ->post(route('users.bulk-status'), [
            'all' => true,
            'search' => 'North',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('count', 1);

    $this->assertDatabaseHas('users', [
        'id' => $matchingUser->id,
        'is_active' => false,
        'deactivated_by' => $databaseOfficer->id,
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $otherUser->id,
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $databaseOfficer->id,
        'is_active' => true,
    ]);
});
