<?php

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Holiday;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceSetup;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'Asia/Hebron'));
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->employee = Employee::factory()->create();
    $this->schedule = WorkSchedule::factory()->create();
    $this->assignment = app(AttendanceSetup::class)->assign($this->officer, [
        'employee_id' => $this->employee->id, 'work_schedule_id' => $this->schedule->id,
        'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
    ]);
});

it('records an assignment end without editing the original and permits a future replacement', function (): void {
    $original = $this->assignment->fresh()->getAttributes();
    $this->actingAs($this->officer)->post(route('hr.attendance.assignments.retire', $this->assignment), ['ends_on' => '2026-10-13', 'reason' => 'Move to new working schedule'])->assertRedirect();
    $assignment = $this->assignment->fresh();
    expect($assignment->getAttributes())->toBe($original)
        ->and($assignment->covers('2026-10-13'))->toBeTrue()
        ->and($assignment->covers('2026-10-14'))->toBeFalse();
    $next = WorkSchedule::factory()->create(['starts_at' => '09:00:00', 'ends_at' => '17:00:00']);
    app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $next->id, 'starts_on' => '2026-10-14', 'ends_on' => '2026-12-31']);
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-14')['schedule'])->toBe($next->name);
    $this->assertDatabaseCount('hr_shift_assignments', 2);
    $this->assertDatabaseCount('hr_assignment_retirements', 1);
});

it('rejects retrospective assignment ending and handles repeated retirement safely', function (): void {
    $setup = app(AttendanceSetup::class);
    expect(fn () => $setup->retire($this->officer, $this->assignment, ['ends_on' => '2026-10-11', 'reason' => 'Retrospective shift edit']))->toThrow(ValidationException::class);
    $data = ['ends_on' => '2026-10-13', 'reason' => 'Approved future schedule change'];
    $first = $setup->retire($this->officer, $this->assignment, $data);
    expect($setup->retire($this->officer, $this->assignment, $data)->id)->toBe($first->id);
    expect(fn () => $first->delete())->toThrow(LogicException::class);
});

it('cancels a holiday by appending history and preserves its original fields', function (): void {
    $holiday = Holiday::factory()->create(['starts_on' => '2026-10-12', 'ends_on' => '2026-10-14']);
    $original = $holiday->fresh()->getAttributes();
    $this->actingAs($this->officer)->post(route('hr.attendance.holidays.cancel', $holiday), ['cancelled_from' => '2026-10-13', 'reason' => 'Official holiday calendar amended'])->assertRedirect();
    expect($holiday->fresh()->getAttributes())->toBe($original);
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['status'])->toBe('holiday')
        ->and(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-13')['status'])->toBe('pending');
    $this->assertDatabaseCount('hr_holidays', 1);
    $this->assertDatabaseCount('hr_holiday_cancellations', 1);
});

it('applies a temporary schedule period with its configured weekends', function (): void {
    $short = WorkSchedule::factory()->create(['starts_at' => '09:00:00', 'ends_at' => '14:00:00']);
    $this->actingAs($this->officer)->post(route('hr.attendance.schedule-exceptions.store'), [
        'employee_id' => $this->employee->id, 'work_date' => '2026-10-12', 'until_date' => '2026-10-18',
        'kind' => 'work_schedule', 'work_schedule_id' => $short->id, 'reason' => 'Approved temporary monthly policy',
    ])->assertRedirect();
    $this->assertDatabaseCount('hr_schedule_exceptions', 7);
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-13')['expected_minutes'])->toBe(270)
        ->and(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-16')['status'])->toBe('off_day');
});

it('protects cancellation and retirement endpoints from ordinary accounts', function (): void {
    $holiday = Holiday::factory()->create();
    $this->actingAs(User::factory()->create())->postJson(route('hr.attendance.holidays.cancel', $holiday), ['cancelled_from' => '2026-10-12', 'reason' => 'Unauthorized attempt'])->assertForbidden();
    $this->postJson(route('hr.attendance.assignments.retire', $this->assignment), ['ends_on' => '2026-10-13', 'reason' => 'Unauthorized attempt'])->assertForbidden();
});
