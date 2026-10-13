<?php

use App\Models\Attendance;
use App\Models\User;
use App\Modules\HR\Jobs\ReconcileAttendance;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\DailySummary;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\AttendanceReconciliation;
use App\Modules\HR\Services\AttendanceSetup;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(HrPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 18:00:00', 'Asia/Hebron'));
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->employee = Employee::factory()->create();
    $schedule = WorkSchedule::factory()->create();
    app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $schedule->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
});

it('materializes absence exceptions once and resolves them after a new source arrives', function (): void {
    $service = app(AttendanceReconciliation::class);
    $first = $service->day($this->employee, '2026-10-12', $this->officer->id);
    expect($first->status)->toBe('absence_candidate');
    $service->day($this->employee, '2026-10-12', $this->officer->id);
    $this->assertDatabaseCount('hr_attendance_daily_summaries', 1);
    $this->assertDatabaseCount('hr_attendance_exceptions', 1);
    expect(AuditLog::query()->where('action', 'attendance.reconciled')->count())->toBe(1);
    $user = User::factory()->create();
    $this->employee->update(['user_id' => $user->id]);
    Attendance::query()->create(['user_id' => $user->id, 'date' => '2026-10-12', 'status' => true, 'notes' => 'Existing import']);
    $service->day($this->employee, '2026-10-12', $this->officer->id);
    $this->assertDatabaseHas('hr_attendance_exceptions', ['status' => 'resolved']);
    expect(DailySummary::query()->first()->worked_minutes)->toBeNull();
    $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'status' => true, 'notes' => 'Existing import']);
});

it('counts all matching employees rather than only the displayed page', function (): void {
    Employee::factory()->count(30)->create();
    $response = $this->actingAs($this->officer)->get(route('hr.attendance.index', ['date' => '2026-10-12']))->assertOk();
    expect($response->viewData('totals')->sum())->toBe(31)
        ->and($response->viewData('employees')->count())->toBe(25);
});

it('queues reconciliation and limits the allowed period', function (): void {
    Queue::fake();
    $this->actingAs($this->officer)->post(route('hr.attendance.reconcile'), ['from' => '2026-10-12', 'to' => '2026-10-12', 'employee_id' => $this->employee->id])->assertRedirect();
    Queue::assertPushed(ReconcileAttendance::class, fn ($job): bool => $job->actorId === $this->officer->id && $job->employeeId === $this->employee->id);
    $this->postJson(route('hr.attendance.reconcile'), ['from' => '2026-09-01', 'to' => '2026-10-12'])->assertUnprocessable();
    $this->postJson(route('hr.attendance.reconcile'), ['from' => '2026-10-12', 'to' => '2026-10-13'])->assertUnprocessable();
});

it('rechecks actor access before a queued reconciliation runs', function (): void {
    $job = new ReconcileAttendance($this->officer->id, '2026-10-12', '2026-10-12');
    $this->officer->removeRole('Database Officer');
    expect(fn () => $job->handle(app(AttendanceReconciliation::class), app(EmployeePolicy::class)))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    $this->assertDatabaseCount('hr_attendance_daily_summaries', 0);
});

it('runs a bounded reconciliation through the administration command', function (): void {
    $this->artisan('hr:reconcile-attendance', ['--from' => '2026-10-12', '--to' => '2026-10-12', '--employee' => $this->employee->id, '--no-interaction' => true])->assertSuccessful();
    $this->assertDatabaseCount('hr_attendance_daily_summaries', 1);
    $this->artisan('hr:reconcile-attendance', ['--from' => '2026-09-01', '--to' => '2026-10-12', '--no-interaction' => true])->assertFailed();
});

it('scopes persisted exceptions and audits to authorized users', function (): void {
    app(AttendanceReconciliation::class)->day($this->employee, '2026-10-12');
    $this->actingAs($this->officer)->get(route('hr.attendance.exceptions'))->assertOk()->assertSee($this->employee->display_name);
    $this->get(route('hr.attendance.audit'))->assertOk()->assertSee(__('hr::attendance.actions.attendance.reconciled'));
    $reader = User::factory()->create();
    $reader->givePermissionTo('hr.attendance.view-own');
    $this->actingAs($reader)->get(route('hr.attendance.exceptions'))->assertOk()->assertDontSee($this->employee->display_name);
    $this->get(route('hr.attendance.audit'))->assertForbidden();
    $this->postJson(route('hr.attendance.reconcile'), ['from' => '2026-10-12', 'to' => '2026-10-12'])->assertForbidden();
});

it('returns an actionable deployment response if workflow migrations are absent', function (): void {
    Schema::rename('hr_holidays', 'hr_holidays_unavailable');
    try {
        $this->actingAs($this->officer)->getJson(route('hr.attendance.index'))->assertServiceUnavailable();
    } finally {
        Schema::rename('hr_holidays_unavailable', 'hr_holidays');
    }
});
