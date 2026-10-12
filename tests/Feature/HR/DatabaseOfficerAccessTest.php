<?php

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use App\Support\Navigation\Sidebar;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
});

it('shows attendance and all employee rows to database officers without HR permission records', function (): void {
    Employee::factory()->create(['display_name' => 'First Employee']);
    Employee::factory()->create(['display_name' => 'Second Employee']);
    expect(Permission::query()->where('name', 'like', 'hr.%')->count())->toBe(0)
        ->and($this->officer->getAllPermissions())->toHaveCount(0)
        ->and(Sidebar::forUser($this->officer)->pluck('key'))->toContain('hr');

    $this->actingAs($this->officer)->get(route('hr.attendance.index', ['month' => '2026-10']))
        ->assertOk()->assertSee('First Employee')->assertSee('Second Employee')
        ->assertSee(route('hr.attendance.setup'), false)->assertSee('تصدير Excel');
    $this->get(route('hr.attendance.setup'))->assertOk();
    Excel::fake();
    $this->get(route('hr.attendance.export', ['month' => '2026-10']))->assertOk();
    Excel::assertDownloaded('hr-attendance.xlsx');
    $this->assertDatabaseCount('model_has_permissions', 0);
    $this->assertDatabaseCount('role_has_permissions', 0);
});

it('allows database officers to set up employee identities schedules and assignments', function (): void {
    $this->actingAs($this->officer)->post(route('hr.attendance.employees.store'), [
        'employee_number' => 'ROLE-HR-001', 'display_name' => 'موظف التجربة', 'starts_on' => '2026-10-01',
    ])->assertRedirect(route('hr.attendance.setup'));
    $this->post(route('hr.attendance.schedules.store'), [
        'name' => 'وردية صباحية', 'timezone' => 'Asia/Hebron', 'working_days' => [1, 2, 3, 4, 7],
        'starts_at' => '08:00', 'ends_at' => '16:00', 'grace_minutes' => 5, 'break_minutes' => 30,
    ])->assertRedirect(route('hr.attendance.setup'));
    $this->post(route('hr.attendance.assignments.store'), [
        'employee_id' => Employee::query()->firstOrFail()->id,
        'work_schedule_id' => WorkSchedule::query()->firstOrFail()->id,
        'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31',
    ])->assertRedirect(route('hr.attendance.setup'));
    $this->assertDatabaseCount('hr_shift_assignments', 1);
    $this->assertDatabaseCount('hr_audit_logs', 3);
    $this->assertDatabaseCount('model_has_permissions', 0);
});

it('limits the role allowance to the current attendance abilities', function (): void {
    foreach (['hr.attendance.view', 'hr.attendance.view-own', 'hr.attendance.setup', 'hr.attendance.export'] as $ability) {
        expect($this->officer->can($ability))->toBeTrue();
    }
    foreach (['hr.payroll.view', 'hr.devices.approve', 'hr.attendance.approve', 'users.view'] as $ability) {
        expect($this->officer->can($ability))->toBeFalse();
    }
});

it('removes role based attendance access when the role is removed', function (): void {
    $this->actingAs($this->officer)->get(route('hr.attendance.index'))->assertOk();
    $this->officer->removeRole('Database Officer');
    $user = $this->officer->fresh();
    expect(Sidebar::forUser($user)->pluck('key'))->not->toContain('hr');
    $this->actingAs($user)->get(route('hr.attendance.index'))->assertForbidden();
    $this->get(route('hr.attendance.setup'))->assertForbidden();
    $this->get(route('hr.attendance.export'))->assertForbidden();
    $this->postJson(route('hr.attendance.employees.store'), [])->assertForbidden();
});
