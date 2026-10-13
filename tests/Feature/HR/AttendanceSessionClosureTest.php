<?php

use App\Models\User;
use App\Modules\HR\Models\AttendanceEvent;
use App\Modules\HR\Models\AttendanceSessionClosure;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceCorrection;
use App\Modules\HR\Services\AttendanceRecorder;
use App\Modules\HR\Services\AttendanceSetup;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(HrPermissionsSeeder::class);
    Queue::fake();
    config(['hr.attendance_enabled' => true, 'hr.network_mode' => 'simulation']);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00', 'Asia/Hebron'));
    $this->staff = User::factory()->create();
    $this->staff->givePermissionTo(['hr.attendance.view-own', 'hr.attendance.correct-own']);
    $this->reviewer = User::factory()->create();
    $this->reviewer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->employee = Employee::factory()->create(['user_id' => $this->staff->id]);
    $schedule = WorkSchedule::factory()->create();
    app(AttendanceSetup::class)->assign($this->reviewer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $schedule->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $this->in = app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid());
    $this->original = $this->in->fresh()->getAttributes();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 08:00:00', 'Asia/Hebron'));
    $this->data = ['work_date' => '2026-10-12', 'requested_status' => 'present', 'reason' => 'Missing checkout verified by HR',
        'basis_fingerprint' => app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['source_fingerprint'], 'idempotency_key' => (string) Str::uuid()];
});

it('settles a missing checkout after independent approval and permits a later check in without editing raw evidence', function (): void {
    expect(fn () => app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid()))->toThrow(ValidationException::class);
    $this->data += ['check_in' => '2026-10-12T08:00', 'check_out' => '2026-10-12T16:00'];
    $request = app(AttendanceCorrection::class)->submit($this->staff, $this->employee, $this->data);
    app(AttendanceCorrection::class)->decide($this->reviewer, $request, ['decision' => 'approved', 'note' => 'HR reviewed the actual checkout evidence']);
    expect($this->in->fresh()->getAttributes())->toBe($this->original);
    $event = app(AttendanceRecorder::class)->simulate($this->employee->fresh(), 'check_in', (string) Str::uuid());
    expect($event->work_date->toDateString())->toBe('2026-10-13');
    $this->assertDatabaseCount('hr_attendance_events', 2);
    $this->assertDatabaseCount('hr_attendance_session_closures', 1);
    expect(AttendanceEvent::query()->where('action', 'check_out')->count())->toBe(0);
});

it('supports an explicit administrative closure with unknown hours and retains immutable closure history', function (): void {
    $this->data['close_open_session'] = true;
    $request = app(AttendanceCorrection::class)->submit($this->staff, $this->employee, $this->data);
    app(AttendanceCorrection::class)->decide($this->reviewer, $request, ['decision' => 'approved', 'note' => 'Session closed administratively; time unknown']);
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['worked_minutes'])->toBeNull();
    expect(fn () => app(AttendanceRecorder::class)->simulate($this->employee, 'check_out', (string) Str::uuid()))->toThrow(ValidationException::class);
    app(AttendanceRecorder::class)->simulate($this->employee->fresh(), 'check_in', (string) Str::uuid());
    $closure = AttendanceSessionClosure::query()->firstOrFail();
    expect(fn () => $closure->delete())->toThrow(LogicException::class);
    expect($closure->getRawOriginal('note'))->not->toContain('Session closed administratively');
});

it('does not close a raw session for a pending or rejected proposal', function (): void {
    $this->data['close_open_session'] = true;
    $request = app(AttendanceCorrection::class)->submit($this->staff, $this->employee, $this->data);
    expect(fn () => app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid()))->toThrow(ValidationException::class);
    app(AttendanceCorrection::class)->decide($this->reviewer, $request, ['decision' => 'rejected', 'note' => 'Insufficient evidence for administrative closure']);
    expect(fn () => app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid()))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('hr_attendance_session_closures', 0);
});

it('binds the closure choice to request idempotency and presents it to the independent reviewer', function (): void {
    $this->data['close_open_session'] = true;
    $request = app(AttendanceCorrection::class)->submit($this->staff, $this->employee, $this->data);
    $this->data['close_open_session'] = false;
    expect(fn () => app(AttendanceCorrection::class)->submit($this->staff, $this->employee, $this->data))->toThrow(ValidationException::class);
    $this->actingAs($this->reviewer)->get(route('hr.attendance.corrections'))->assertOk()->assertSee('تسوية الجلسة المفتوحة إدارياً');
    expect($request->fresh()->close_open_session)->toBeTrue();
});
