<?php

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Holiday;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceCorrection;
use App\Modules\HR\Services\AttendanceRecorder;
use App\Modules\HR\Services\AttendanceSetup;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(HrPermissionsSeeder::class);
    Notification::fake();
    config(['hr.attendance_enabled' => true, 'hr.network_mode' => 'simulation']);
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->employee = Employee::factory()->create();
    $this->morning = WorkSchedule::factory()->create(['starts_at' => '08:00:00', 'ends_at' => '12:00:00', 'break_minutes' => 0]);
    $this->afternoon = WorkSchedule::factory()->create(['starts_at' => '14:00:00', 'ends_at' => '18:00:00', 'break_minutes' => 0]);
    $this->first = app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $this->morning->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $this->second = app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $this->afternoon->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
});

function hrRecordShift(object $test, Employee $employee, int $assignmentId, string $action, string $time): void
{
    $test->travelTo(CarbonImmutable::parse('2026-10-12 '.$time, 'Asia/Hebron'));
    app(AttendanceRecorder::class)->simulate($employee, $action, (string) Str::uuid(), $assignmentId);
}

it('records two shift pairs without counting the gap as working time', function (): void {
    hrRecordShift($this, $this->employee, $this->first->id, 'check_in', '08:00:00');
    hrRecordShift($this, $this->employee, $this->first->id, 'check_out', '12:00:00');
    hrRecordShift($this, $this->employee, $this->second->id, 'check_in', '14:00:00');
    hrRecordShift($this, $this->employee, $this->second->id, 'check_out', '18:00:00');
    $row = app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12');
    expect($row)->toMatchArray(['status' => 'present', 'expected_minutes' => 480, 'worked_minutes' => 480, 'late_minutes' => 0, 'early_minutes' => 0]);
    expect($row['sessions'])->toHaveCount(2);
    $this->assertDatabaseCount('hr_attendance_events', 4);
});

it('flags a missing second shift only after its deadline', function (): void {
    hrRecordShift($this, $this->employee, $this->first->id, 'check_in', '08:00:00');
    hrRecordShift($this, $this->employee, $this->first->id, 'check_out', '12:00:00');
    $this->travelTo(CarbonImmutable::parse('2026-10-12 13:00:00', 'Asia/Hebron'));
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['status'])->toBe('pending');
    $this->travelTo(CarbonImmutable::parse('2026-10-12 19:00:00', 'Asia/Hebron'));
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12'))->toMatchArray(['status' => 'missing_session', 'worked_minutes' => 240]);
});

it('rejects wrong shift checkout and overlapping schedule assignments', function (): void {
    hrRecordShift($this, $this->employee, $this->first->id, 'check_in', '08:00:00');
    $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00:00', 'Asia/Hebron'));
    expect(fn () => app(AttendanceRecorder::class)->simulate($this->employee, 'check_out', (string) Str::uuid(), $this->second->id))->toThrow(ValidationException::class);
    $overlap = WorkSchedule::factory()->create(['starts_at' => '11:00:00', 'ends_at' => '15:00:00']);
    expect(fn () => app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $overlap->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12']))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('hr_shift_assignments', 2);
});

it('detects an overlap with the previous day overnight shift', function (): void {
    $employee = Employee::factory()->create();
    $night = WorkSchedule::factory()->create(['starts_at' => '22:00:00', 'ends_at' => '06:00:00', 'working_days' => [1]]);
    $early = WorkSchedule::factory()->create(['starts_at' => '05:00:00', 'ends_at' => '09:00:00', 'working_days' => [2]]);
    app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $employee->id, 'work_schedule_id' => $night->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12']);
    expect(fn () => app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $employee->id, 'work_schedule_id' => $early->id, 'starts_on' => '2026-10-13', 'ends_on' => '2026-10-13']))->toThrow(ValidationException::class);
});

it('applies a holiday to only its matching shift', function (): void {
    Holiday::factory()->create(['work_schedule_id' => $this->morning->id]);
    hrRecordShift($this, $this->employee, $this->second->id, 'check_in', '14:00:00');
    hrRecordShift($this, $this->employee, $this->second->id, 'check_out', '18:00:00');
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12'))->toMatchArray(['status' => 'present', 'expected_minutes' => 240, 'worked_minutes' => 240]);
});

it('approves separate corrected intervals and rejects overlapping corrected times', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 19:00:00', 'Asia/Hebron'));
    $requester = User::factory()->create();
    $requester->givePermissionTo(['hr.attendance.view-own', 'hr.attendance.correct-own']);
    $this->employee->update(['user_id' => $requester->id]);
    $payload = [
        'work_date' => '2026-10-12', 'requested_status' => 'present', 'reason' => 'Both shifts checked against records',
        'basis_fingerprint' => app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['source_fingerprint'],
        'idempotency_key' => (string) Str::uuid(),
        'sessions' => [
            ['assignment_id' => $this->first->id, 'check_in' => '2026-10-12T08:00', 'check_out' => '2026-10-12T12:00'],
            ['assignment_id' => $this->second->id, 'check_in' => '2026-10-12T14:00', 'check_out' => '2026-10-12T18:00'],
        ],
    ];
    $bad = $payload;
    $bad['sessions'][0]['check_out'] = '2026-10-12T15:00';
    $this->actingAs($requester)->postJson(route('hr.attendance.corrections.store', $this->employee), $bad)->assertUnprocessable();
    $service = app(AttendanceCorrection::class);
    $adjustment = $service->submit($requester, $this->employee, $payload);
    $service->decide($this->officer, $adjustment, ['decision' => 'approved', 'note' => 'Both sessions are confirmed']);
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12'))->toMatchArray(['worked_minutes' => 480, 'status' => 'present']);
    $this->assertDatabaseCount('hr_attendance_events', 0);
});

it('renders shift interval fields for multi shift corrections', function (): void {
    $this->actingAs($this->officer)->get(route('hr.attendance.corrections.create', ['employee' => $this->employee, 'date' => '2026-10-12']))
        ->assertOk()->assertSee('sessions[0][assignment_id]', false)->assertSee('sessions[1][assignment_id]', false);
});
