<?php

use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use App\Support\ArtisanCommandCatalog;
use App\Support\Navigation\Sidebar;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('grants only attendance viewing while retaining existing roles and grants', function (): void {
    $user = User::factory()->create();
    $role = Role::findOrCreate('Database Officer', 'web');
    $user->assignRole($role);
    $user->givePermissionTo(Permission::findOrCreate('users.view', 'web'));

    $this->artisan('hr:grant-attendance-view', ['email' => $user->email, '--no-interaction' => true])->assertSuccessful();

    $user = $user->fresh();
    expect($user->can('hr.attendance.view'))->toBeTrue()
        ->and($user->can('users.view'))->toBeTrue()
        ->and($user->hasRole('Database Officer'))->toBeTrue()
        ->and($user->can('hr.attendance.setup'))->toBeFalse()
        ->and($user->can('hr.attendance.export'))->toBeFalse()
        ->and($role->fresh()->permissions)->toHaveCount(0)
        ->and(Sidebar::forUser($user)->pluck('key'))->toContain('hr');
    $this->actingAs($user)->get(route('hr.attendance.index'))->assertOk();
    $this->get(route('hr.attendance.setup'))->assertForbidden();
    $this->assertDatabaseHas('hr_audit_logs', ['action' => 'attendance.view_permission_granted', 'subject_id' => $user->id]);
});

it('can be rerun without duplicate grants or audit records', function (): void {
    $user = User::factory()->create();
    $this->artisan('hr:grant-attendance-view', ['email' => $user->email, '--no-interaction' => true])->assertSuccessful();
    $this->artisan('hr:grant-attendance-view', ['email' => $user->email, '--no-interaction' => true])->assertSuccessful();
    $this->assertDatabaseCount('model_has_permissions', 1);
    expect(AuditLog::query()->where('action', 'attendance.view_permission_granted')->count())->toBe(1);
});

it('rejects invalid missing and inactive accounts without writing permissions', function (string $account): void {
    if ($account === 'inactive') {
        $email = User::factory()->create(['is_active' => false])->email;
    } else {
        $email = $account;
    }
    $this->artisan('hr:grant-attendance-view', ['email' => $email, '--no-interaction' => true])->assertFailed();
    $this->assertDatabaseCount('model_has_permissions', 0);
    expect(Permission::query()->where('name', 'hr.attendance.view')->exists())->toBeFalse();
})->with(['not-an-email', 'missing@example.test', 'inactive']);

it('requires the HR migration before granting access', function (): void {
    $user = User::factory()->create();
    Schema::rename('hr_audit_logs', 'hr_audit_logs_unavailable');
    try {
        $this->artisan('hr:grant-attendance-view', ['email' => $user->email, '--no-interaction' => true])
            ->expectsOutputToContain('جداول HR غير جاهزة')->assertFailed();
    } finally {
        Schema::rename('hr_audit_logs_unavailable', 'hr_audit_logs');
    }
    $this->assertDatabaseCount('model_has_permissions', 0);
});

it('offers the command through the existing protected administration screen', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));
    expect(app(ArtisanCommandCatalog::class)->find('hr:grant-attendance-view')['can_run'])->toBeTrue();
    $this->actingAs($user)->get(route('admin.artisan-commands.index'))->assertOk()->assertSee('hr:grant-attendance-view');
    Process::fake(['*' => Process::result()]);
    $this->postJson(route('admin.artisan-commands.run'), [
        'command' => 'hr:grant-attendance-view', 'arguments' => ['email' => 'employee@example.test'],
    ])->assertAccepted();
});

it('blocks ordinary accounts from running the command through the web', function (): void {
    Process::fake();
    $this->actingAs(User::factory()->create())->postJson(route('admin.artisan-commands.run'), [
        'command' => 'hr:grant-attendance-view', 'arguments' => ['email' => 'employee@example.test'],
    ])->assertForbidden();
    Process::assertNothingRan();
});
