<?php

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    collect([
        'Field Engineer',
        'Team Leader',
        'QC/QA Engineer',
        'Area Manager',
        'Team Leader -INF',
        'Legal Auditor',
        'Auditing Supervisor',
    ])->each(fn (string $role) => Role::findOrCreate($role, 'web'));
});

it('returns the selected day attendance summary for the active filters', function (): void {
    $viewer = User::factory()->create();
    $role = Role::findByName('Field Engineer', 'web');

    $presentUser = User::factory()->create([
        'contract_type' => 'phc',
        'region' => 'south',
    ]);
    $absentUser = User::factory()->create([
        'contract_type' => 'phc',
        'region' => 'south',
    ]);
    $unsetUser = User::factory()->create([
        'contract_type' => 'phc',
        'region' => 'south',
    ]);
    $outsideRegionUser = User::factory()->create([
        'contract_type' => 'phc',
        'region' => 'north',
    ]);

    collect([$presentUser, $absentUser, $unsetUser, $outsideRegionUser])
        ->each(fn (User $user) => $user->assignRole($role));

    Attendance::query()->create([
        'user_id' => $presentUser->id,
        'date' => '2026-06-15',
        'status' => 1,
    ]);
    Attendance::query()->create([
        'user_id' => $absentUser->id,
        'date' => '2026-06-15',
        'status' => 0,
    ]);
    Attendance::query()->create([
        'user_id' => $outsideRegionUser->id,
        'date' => '2026-06-15',
        'status' => 1,
    ]);

    $this->actingAs($viewer)
        ->postJson(route('attendance.summary'), [
            'date' => '2026-06-15',
            'contract_type' => 'phc',
            'region' => 'south',
        ])
        ->assertOk()
        ->assertJson([
            'total' => 3,
            'present' => 1,
            'absent' => 1,
            'unset' => 1,
        ]);
});

it('applies selected day attendance only to filtered users', function (): void {
    $viewer = User::factory()->create();
    $role = Role::findByName('Field Engineer', 'web');

    $filteredUser = User::factory()->create([
        'contract_type' => 'phc',
        'region' => 'south',
    ]);
    $outsideRegionUser = User::factory()->create([
        'contract_type' => 'phc',
        'region' => 'north',
    ]);

    collect([$filteredUser, $outsideRegionUser])
        ->each(fn (User $user) => $user->assignRole($role));

    $this->actingAs($viewer)
        ->postJson(route('attendance.set-day-present'), [
            'date' => '2026-06-15',
            'contract_type' => 'phc',
            'region' => 'south',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Attendance::query()
        ->where('user_id', $filteredUser->id)
        ->whereDate('date', '2026-06-15')
        ->where('status', 1)
        ->exists())->toBeTrue();

    expect(Attendance::query()
        ->where('user_id', $outsideRegionUser->id)
        ->whereDate('date', '2026-06-15')
        ->exists())->toBeFalse();
});
