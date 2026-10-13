<?php

use App\Models\Attendance;
use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Holiday;
use App\Modules\HR\Models\ScheduleException;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceRecorder;
use App\Modules\HR\Services\AttendanceSetup;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(HrPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 18:00:00', 'Asia/Hebron'));
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->employee = Employee::factory()->create();
    $this->schedule = WorkSchedule::factory()->create();
    app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $this->schedule->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
});

it('records scoped holidays and excludes them from potential absence', function (): void {
    $this->actingAs($this->officer)->post(route('hr.attendance.holidays.store'), ['name' => 'عطلة معتمدة', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12', 'work_schedule_id' => $this->schedule->id])->assertRedirect();
    $this->get(route('hr.attendance.calendar'))->assertOk()->assertSee('عطلة معتمدة');
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12'))->toMatchArray(['status' => 'holiday', 'expected_minutes' => 0]);
    $other = Employee::factory()->create();
    $otherSchedule = WorkSchedule::factory()->create();
    app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $other->id, 'work_schedule_id' => $otherSchedule->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    expect(app(AttendanceCalculator::class)->day($other->fresh(), '2026-10-12')['status'])->toBe('absence_candidate');
});

it('supports global holidays without changing legacy imports', function (): void {
    $user = User::factory()->create();
    $this->employee->update(['user_id' => $user->id]);
    Attendance::query()->create(['user_id' => $user->id, 'date' => '2026-10-12', 'status' => false]);
    Holiday::factory()->create();
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['status'])->toBe('holiday');
    $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'status' => false]);
});

it('applies approved leave and keeps earlier exception versions', function (): void {
    $data = ['employee_id' => $this->employee->id, 'work_date' => '2026-10-12', 'kind' => 'leave', 'reason' => 'Approved leave reference'];
    $this->actingAs($this->officer)->post(route('hr.attendance.schedule-exceptions.store'), $data)->assertRedirect();
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12'))->toMatchArray(['status' => 'leave', 'expected_minutes' => 0]);
    $this->post(route('hr.attendance.schedule-exceptions.store'), array_replace($data, ['kind' => 'excused_absence', 'reason' => 'New HR approved exception']))->assertRedirect();
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['status'])->toBe('excused_absence');
    $this->assertDatabaseCount('hr_schedule_exceptions', 2);
    expect(fn () => ScheduleException::query()->first()->update(['kind' => 'off_day']))->toThrow(LogicException::class);
});

it('supports temporary shorter schedules and scheduled work on a weekend', function (): void {
    $short = WorkSchedule::factory()->create(['starts_at' => '09:00:00', 'ends_at' => '15:00:00']);
    ScheduleException::factory()->create(['employee_id' => $this->employee->id, 'work_date' => '2026-10-09', 'kind' => 'work_schedule', 'work_schedule_id' => $short->id]);
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-09'))->toMatchArray(['status' => 'absence_candidate', 'expected_minutes' => 330, 'schedule' => $short->name]);
});

it('prevents ordinary check-in simulation on a holiday or approved leave', function (string $type): void {
    config(['hr.attendance_enabled' => true, 'hr.network_mode' => 'simulation']);
    if ($type === 'holiday') {
        Holiday::factory()->create();
    } else {
        ScheduleException::factory()->create(['employee_id' => $this->employee->id]);
    }
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00', 'Asia/Hebron'));
    expect(fn () => app(AttendanceRecorder::class)->simulate($this->employee, 'check_in', (string) Str::uuid()))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('hr_attendance_events', 0);
})->with(['holiday', 'leave']);

it('validates dates and refuses an employee granting their own exception', function (): void {
    $this->actingAs($this->officer)->postJson(route('hr.attendance.holidays.store'), ['name' => 'Wrong date range', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-11'])->assertUnprocessable();
    $this->employee->update(['user_id' => $this->officer->id]);
    $this->postJson(route('hr.attendance.schedule-exceptions.store'), ['employee_id' => $this->employee->id, 'work_date' => '2026-10-12', 'kind' => 'leave', 'reason' => 'Self exception attempt'])->assertForbidden();
    $this->assertDatabaseCount('hr_schedule_exceptions', 0);
});

it('blocks ordinary attendance readers from calendar administration', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('hr.attendance.view');
    $this->actingAs($user)->get(route('hr.attendance.calendar'))->assertForbidden();
    $this->postJson(route('hr.attendance.holidays.store'), [])->assertForbidden();
    $this->postJson(route('hr.attendance.schedule-exceptions.store'), [])->assertForbidden();
});
