<?php

use App\Imports\AttendanceSheetImport;
use App\Models\Attendance;
use App\Models\AttendanceImportLog;
use App\Models\User;
use App\Modules\HR\Models\AttendanceEvent;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceRecorder;
use App\Modules\HR\Services\AttendanceSetup;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['hr.attendance_enabled' => true, 'hr.network_mode' => 'simulation']);
    $this->seed(HrPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->actor->givePermissionTo('hr.attendance.setup');
    $this->employee = Employee::factory()->create(['user_id' => $this->actor->id]);
    $this->schedule = WorkSchedule::factory()->create();
    app(AttendanceSetup::class)->assign($this->actor, [
        'employee_id' => $this->employee->id, 'work_schedule_id' => $this->schedule->id,
        'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
    ]);
});

function hrRow(Employee $employee, string $date = '2026-10-12'): array
{
    return app(AttendanceCalculator::class)->day($employee->load(['assignments.schedule', 'events', 'legacyAttendances']), $date);
}

it('records server time once and calculates working duration late and early departure', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:20:00', 'Asia/Hebron'));
    $key = (string) Str::uuid();
    $recorder = app(AttendanceRecorder::class);
    $first = $recorder->simulate($this->employee, 'check_in', $key);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:22:00', 'Asia/Hebron'));
    expect($recorder->simulate($this->employee, 'check_in', $key)->id)->toBe($first->id);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 15:30:00', 'Asia/Hebron'));
    $recorder->simulate($this->employee, 'check_out', (string) Str::uuid());

    expect($first->occurred_at)->toBe('2026-10-12 05:20:00')
        ->and(hrRow($this->employee))->toMatchArray([
            'status' => 'present', 'worked_minutes' => 400, 'late_minutes' => 15,
            'early_minutes' => 30, 'expected_minutes' => 450, 'overtime_minutes' => 0,
        ]);
    $this->assertDatabaseCount('hr_attendance_events', 2);
    $this->assertDatabaseCount('attendances', 0);
    expect(AuditLog::query()->where('action', 'attendance.simulated')->count())->toBe(2);
});

it('rejects duplicate check-in and checkout without check-in', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00', 'Asia/Hebron'));
    $recorder = app(AttendanceRecorder::class);
    expect(fn () => $recorder->simulate($this->employee, 'check_out', (string) Str::uuid()))->toThrow(ValidationException::class);
    $recorder->simulate($this->employee, 'check_in', (string) Str::uuid());
    expect(fn () => $recorder->simulate($this->employee, 'check_in', (string) Str::uuid()))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('hr_attendance_events', 1);
});

it('binds idempotency keys to action and employee', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00', 'Asia/Hebron'));
    $key = (string) Str::uuid();
    $recorder = app(AttendanceRecorder::class);
    $recorder->simulate($this->employee, 'check_in', $key);
    expect(fn () => $recorder->simulate($this->employee, 'check_out', $key))->toThrow(ValidationException::class);
    $other = Employee::factory()->create();
    expect(fn () => $recorder->simulate($other, 'check_in', $key))->toThrow(ValidationException::class);
});

it('keeps overnight checkout on the starting work date', function (): void {
    $employee = Employee::factory()->create();
    $night = WorkSchedule::factory()->create(['starts_at' => '22:00:00', 'ends_at' => '06:00:00']);
    app(AttendanceSetup::class)->assign($this->actor, [
        'employee_id' => $employee->id, 'work_schedule_id' => $night->id,
        'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12',
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 22:00:00', 'Asia/Hebron'));
    app(AttendanceRecorder::class)->simulate($employee, 'check_in', (string) Str::uuid());
    $this->travelTo(CarbonImmutable::parse('2026-10-13 06:00:00', 'Asia/Hebron'));
    $event = app(AttendanceRecorder::class)->simulate($employee, 'check_out', (string) Str::uuid());
    expect($event->work_date->toDateString())->toBe('2026-10-12')
        ->and(hrRow($employee))->toMatchArray(['worked_minutes' => 450, 'early_minutes' => 0, 'late_minutes' => 0]);
});

it('does not mark unfinished future or nonworking shifts absent', function (string $at, string $date, string $status): void {
    $this->travelTo(CarbonImmutable::parse($at, 'Asia/Hebron'));
    expect(hrRow($this->employee, $date)['status'])->toBe($status);
})->with([
    ['2026-10-12 09:00:00', '2026-10-12', 'pending'],
    ['2026-10-12 17:00:00', '2026-10-13', 'pending'],
    ['2026-10-12 17:00:00', '2026-10-09', 'off_day'],
    ['2026-10-12 17:00:00', '2026-10-12', 'absence_candidate'],
    ['2026-10-12 17:00:00', '2025-12-31', 'outside_employment'],
]);

it('distinguishes missing checkout and refuses stale sessions', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00', 'Asia/Hebron'));
    app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid());
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:00:00', 'Asia/Hebron'));
    expect(hrRow($this->employee))->toMatchArray(['status' => 'missing_checkout', 'worked_minutes' => null]);
    expect(fn () => app(AttendanceRecorder::class)->simulate($this->employee, 'check_out', (string) Str::uuid()))
        ->toThrow(ValidationException::class);
});

it('preserves legacy status without fabricating time and flags conflicting events', function (): void {
    $legacy = Attendance::query()->create(['user_id' => $this->actor->id, 'date' => '2026-10-12', 'status' => 0, 'notes' => 'Imported original']);
    $before = $legacy->fresh()->getAttributes();
    expect(hrRow($this->employee))->toMatchArray(['status' => 'legacy_absent', 'worked_minutes' => null, 'source' => 'legacy']);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00', 'Asia/Hebron'));
    app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid());
    expect(hrRow($this->employee))->toMatchArray(['status' => 'conflict', 'conflict' => true]);
    expect($legacy->fresh()->getAttributes())->toBe($before);
});

it('continues to read existing Excel imports after explicit employee mapping', function (): void {
    $this->actor->update(['id_no' => '123456789']);
    $log = AttendanceImportLog::query()->create(['file_hash' => md5('hr-test'), 'file_name' => 'attendance.xlsx', 'status' => 'processing']);
    $rows = collect([collect(), collect(), collect([9 => 12]), collect(), collect([
        2 => 'Example Employee', 3 => 'موظف', 4 => 'Field Engineer', 5 => '123456789',
        6 => 'PHC', 7 => '', 9 => 1,
    ])]);
    (new AttendanceSheetImport($log, 'Oct 2026', $this->actor->id, 'south'))->collection($rows);
    expect(hrRow($this->employee))->toMatchArray(['status' => 'legacy_present', 'worked_minutes' => null]);
    $this->assertDatabaseCount('hr_attendance_events', 0);
    $this->assertDatabaseCount('hr_employees', 1);
    expect($log->fresh()->imported_records)->toBe(1);
});

it('rejects overlapping assignments without partial audit writes', function (): void {
    $count = AuditLog::query()->count();
    expect(fn () => app(AttendanceSetup::class)->assign($this->actor, [
        'employee_id' => $this->employee->id, 'work_schedule_id' => $this->schedule->id,
        'starts_on' => '2026-12-31', 'ends_on' => '2027-01-02',
    ]))->toThrow(ValidationException::class);
    expect(AuditLog::query()->count())->toBe($count);
});

it('rejects inactive accounts and unscheduled check-in', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00', 'Asia/Hebron'));
    $this->actor->update(['is_active' => false]);
    expect(fn () => app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(fn () => app(AttendanceRecorder::class)->simulate(Employee::factory()->create(), 'check_in', (string) Str::uuid()))->toThrow(ValidationException::class);
});

it('protects event and schedule history from instance and bulk modification', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00', 'Asia/Hebron'));
    $event = app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid());
    expect(fn () => $event->update(['source' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => $event->delete())->toThrow(LogicException::class);
    expect(fn () => AttendanceEvent::query()->whereKey($event->id)->update(['source' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => AttendanceEvent::query()->delete())->toThrow(LogicException::class);
    expect(fn () => $this->schedule->update(['starts_at' => '10:00:00']))->toThrow(LogicException::class);
});

it('calculates actual elapsed minutes through a daylight saving transition', function (): void {
    $schedule = WorkSchedule::factory()->make(['timezone' => 'Europe/Berlin', 'starts_at' => '22:00:00', 'ends_at' => '06:00:00']);
    [$start, $end] = app(AttendanceCalculator::class)->window($schedule, '2026-10-24');
    expect((int) $start->diffInMinutes($end))->toBe(540);
});
