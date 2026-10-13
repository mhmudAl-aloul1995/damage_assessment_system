<?php

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceRecorder;
use App\Modules\HR\Services\AttendanceSetup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    config(['hr.attendance_enabled' => true, 'hr.network_mode' => 'simulation']);
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->employee = Employee::factory()->create();
    $this->schedule = WorkSchedule::factory()->create(['flexible' => true, 'latest_start_at' => '10:00:00']);
    app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $this->schedule->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
});

it('moves the checkout deadline according to the flexible arrival while preserving required hours', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00:00', 'Asia/Hebron'));
    app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid());
    $this->travelTo(CarbonImmutable::parse('2026-10-12 16:30:00', 'Asia/Hebron'));
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['status'])->toBe('working');
    $this->travelTo(CarbonImmutable::parse('2026-10-12 17:00:00', 'Asia/Hebron'));
    app(AttendanceRecorder::class)->simulate($this->employee, 'check_out', (string) Str::uuid());
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12'))->toMatchArray(['expected_minutes' => 450, 'worked_minutes' => 450, 'late_minutes' => 0, 'early_minutes' => 0]);
});

it('measures late arrival after the flexible band and early departure before the shifted deadline', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:15:00', 'Asia/Hebron'));
    app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid());
    $this->travelTo(CarbonImmutable::parse('2026-10-12 18:00:00', 'Asia/Hebron'));
    app(AttendanceRecorder::class)->simulate($this->employee, 'check_out', (string) Str::uuid());
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12'))->toMatchArray(['late_minutes' => 10, 'early_minutes' => 15, 'worked_minutes' => 435]);
});

it('waits until the latest permitted start plus required duration to flag absence', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 17:00:00', 'Asia/Hebron'));
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['status'])->toBe('pending');
    $this->travelTo(CarbonImmutable::parse('2026-10-12 18:00:00', 'Asia/Hebron'));
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['status'])->toBe('absence_candidate');
});

it('rejects another assignment overlapping the possible flexible checkout window', function (): void {
    $other = WorkSchedule::factory()->create(['starts_at' => '17:00:00', 'ends_at' => '19:00:00', 'break_minutes' => 0]);
    expect(fn () => app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $other->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12']))->toThrow(ValidationException::class);
});

it('validates the flexible arrival band in the schedule form', function (): void {
    $data = ['name' => 'Flexible shift', 'timezone' => 'Asia/Hebron', 'working_days' => [1], 'starts_at' => '08:00', 'ends_at' => '16:00', 'break_minutes' => 30, 'grace_minutes' => 5, 'flexible' => true];
    $this->actingAs($this->officer)->postJson(route('hr.attendance.schedules.store'), $data)->assertUnprocessable();
    $this->postJson(route('hr.attendance.schedules.store'), [...$data, 'latest_start_at' => '17:00'])->assertUnprocessable();
    $this->post(route('hr.attendance.schedules.store'), [...$data, 'latest_start_at' => '10:00'])->assertRedirect();
});
