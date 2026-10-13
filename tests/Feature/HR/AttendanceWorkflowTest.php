<?php

use App\Models\Attendance;
use App\Models\User;
use App\Modules\HR\Models\AttendanceAdjustment;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Notifications\AttendanceDecisionNotification;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceCorrection;
use App\Modules\HR\Services\AttendanceSetup;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(HrPermissionsSeeder::class);
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-12 18:00:00', 'Asia/Hebron'));
    $this->requester = User::factory()->create();
    $this->requester->givePermissionTo(['hr.attendance.view-own', 'hr.attendance.correct-own']);
    $this->reviewer = User::factory()->create();
    $this->reviewer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->employee = Employee::factory()->create(['user_id' => $this->requester->id]);
    $this->schedule = WorkSchedule::factory()->create();
    app(AttendanceSetup::class)->assign($this->reviewer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $this->schedule->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
});

function hrCorrectionPayload(Employee $employee, array $changes = []): array
{
    $row = app(AttendanceCalculator::class)->day($employee->fresh(), '2026-10-12');

    return array_replace([
        'work_date' => '2026-10-12', 'requested_status' => 'present',
        'check_in' => '2026-10-12T08:00', 'check_out' => '2026-10-12T16:00',
        'reason' => 'Missing device record explained', 'basis_fingerprint' => $row['source_fingerprint'],
        'idempotency_key' => (string) Str::uuid(),
    ], $changes);
}

it('approves a correction without rewriting the legacy attendance record', function (): void {
    $legacy = Attendance::query()->create(['user_id' => $this->requester->id, 'date' => '2026-10-12', 'status' => false, 'notes' => 'Original import']);
    $original = $legacy->fresh()->getAttributes();
    $payload = hrCorrectionPayload($this->employee);
    $this->actingAs($this->requester)->get(route('hr.attendance.corrections.create', ['employee' => $this->employee, 'date' => '2026-10-12']))->assertOk();
    $this->post(route('hr.attendance.corrections.store', $this->employee), $payload)->assertRedirect();
    $pending = app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12');
    expect($pending['status'])->toBe('legacy_absent');
    $adjustment = AttendanceAdjustment::query()->firstOrFail();
    $this->actingAs($this->reviewer)->post(route('hr.attendance.corrections.decide', $adjustment), ['decision' => 'approved', 'note' => 'Reviewed against office records'])->assertRedirect();
    $row = app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12');
    expect($row)->toMatchArray(['status' => 'present', 'source' => 'approved_correction', 'worked_minutes' => 450, 'conflict' => false]);
    expect($legacy->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('hr_attendance_events', 0);
    $this->assertDatabaseHas('hr_attendance_daily_summaries', ['employee_id' => $this->employee->id, 'status' => 'present']);
    Notification::assertSentTo($this->requester, AttendanceDecisionNotification::class);
});

it('prevents both the requester and target employee from approving their own correction', function (): void {
    $adjustment = app(AttendanceCorrection::class)->submit($this->requester, $this->employee, hrCorrectionPayload($this->employee));
    $this->requester->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($this->requester)->postJson(route('hr.attendance.corrections.decide', $adjustment), ['decision' => 'approved', 'note' => 'Self approval attempt'])->assertForbidden();
    $this->assertDatabaseCount('hr_adjustment_decisions', 0);
});

it('preserves rejected proposals and leaves the original calculation unchanged', function (): void {
    $adjustment = app(AttendanceCorrection::class)->submit($this->requester, $this->employee, hrCorrectionPayload($this->employee));
    $this->actingAs($this->reviewer)->post(route('hr.attendance.corrections.decide', $adjustment), ['decision' => 'rejected', 'note' => 'Insufficient supporting evidence'])->assertRedirect();
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12')['status'])->toBe('absence_candidate');
    $this->assertDatabaseCount('hr_attendance_adjustments', 1);
    $this->assertDatabaseHas('hr_adjustment_decisions', ['decision' => 'rejected']);
});

it('rejects an approval when an import changes after submission', function (): void {
    $legacy = Attendance::query()->create(['user_id' => $this->requester->id, 'date' => '2026-10-12', 'status' => false]);
    $adjustment = app(AttendanceCorrection::class)->submit($this->requester, $this->employee, hrCorrectionPayload($this->employee));
    $legacy->update(['status' => true]);
    $this->actingAs($this->reviewer)->postJson(route('hr.attendance.corrections.decide', $adjustment), ['decision' => 'approved', 'note' => 'Attempt after import change'])->assertUnprocessable();
    $this->assertDatabaseCount('hr_adjustment_decisions', 0);
});

it('flags source changes after approval without discarding the approved correction', function (): void {
    $legacy = Attendance::query()->create(['user_id' => $this->requester->id, 'date' => '2026-10-12', 'status' => false]);
    $adjustment = app(AttendanceCorrection::class)->submit($this->requester, $this->employee, hrCorrectionPayload($this->employee));
    app(AttendanceCorrection::class)->decide($this->reviewer, $adjustment, ['decision' => 'approved', 'note' => 'Confirmed by supervisor']);
    $legacy->update(['status' => true]);
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12'))
        ->toMatchArray(['status' => 'conflict', 'post_approval_change' => true, 'worked_minutes' => 450, 'source' => 'approved_correction']);
});

it('accepts unknown hours without inventing timestamps for approved presence', function (): void {
    $adjustment = app(AttendanceCorrection::class)->submit($this->requester, $this->employee, hrCorrectionPayload($this->employee, ['check_in' => null, 'check_out' => null]));
    app(AttendanceCorrection::class)->decide($this->reviewer, $adjustment, ['decision' => 'approved', 'note' => 'Presence known but times unavailable']);
    expect(app(AttendanceCalculator::class)->day($this->employee->fresh(), '2026-10-12'))
        ->toMatchArray(['status' => 'approved_present', 'worked_minutes' => null, 'check_in' => null, 'check_out' => null]);
});

it('rejects future dates wrong work dates reversed times and nonpresence timestamps', function (array $changes): void {
    $this->actingAs($this->requester)->postJson(route('hr.attendance.corrections.store', $this->employee), hrCorrectionPayload($this->employee, $changes))->assertUnprocessable();
    $this->assertDatabaseCount('hr_attendance_adjustments', 0);
})->with([
    [['work_date' => '2026-10-13']],
    [['check_in' => '2026-10-11T08:00']],
    [['check_out' => '2026-10-12T07:00']],
    [['requested_status' => 'absent']],
    [['check_out' => '2026-10-13T19:00']],
]);

it('handles repeat submissions and decisions without duplicate records', function (): void {
    $payload = hrCorrectionPayload($this->employee);
    $service = app(AttendanceCorrection::class);
    $adjustment = $service->submit($this->requester, $this->employee, $payload);
    expect($service->submit($this->requester, $this->employee, $payload)->id)->toBe($adjustment->id);
    $this->actingAs($this->requester)->postJson(route('hr.attendance.corrections.store', $this->employee), array_replace($payload, ['check_in' => '2026-10-12T08:05']))->assertUnprocessable();
    $decision = $service->decide($this->reviewer, $adjustment, ['decision' => 'approved', 'note' => 'All records checked']);
    expect($service->decide($this->reviewer, $adjustment, ['decision' => 'approved', 'note' => 'All records checked'])->id)->toBe($decision->id);
    $this->assertDatabaseCount('hr_attendance_adjustments', 1);
    $this->assertDatabaseCount('hr_adjustment_decisions', 1);
});

it('keeps adjustment proposals and decisions append only', function (): void {
    $adjustment = app(AttendanceCorrection::class)->submit($this->requester, $this->employee, hrCorrectionPayload($this->employee));
    $decision = app(AttendanceCorrection::class)->decide($this->reviewer, $adjustment, ['decision' => 'rejected', 'note' => 'History should remain']);
    expect(fn () => $adjustment->update(['reason' => 'Changed history']))->toThrow(LogicException::class);
    expect(fn () => $decision->delete())->toThrow(LogicException::class);
});

it('scopes correction visibility and review to assigned supervisor employees', function (): void {
    $manager = User::factory()->create();
    $manager->givePermissionTo(['hr.attendance.view-team', 'hr.attendance.approve']);
    $this->employee->update(['supervisor_user_id' => $manager->id]);
    $adjustment = app(AttendanceCorrection::class)->submit($this->requester, $this->employee, hrCorrectionPayload($this->employee));
    $outsider = Employee::factory()->create(['display_name' => 'Hidden Team Employee']);
    $this->actingAs($manager)->get(route('hr.attendance.index'))->assertOk()->assertDontSee('Hidden Team Employee');
    $this->get(route('hr.attendance.corrections'))->assertOk()->assertSee($this->employee->display_name);
    $this->post(route('hr.attendance.corrections.decide', $adjustment), ['decision' => 'approved', 'note' => 'Team attendance verified'])->assertRedirect();
    $this->get(route('hr.attendance.corrections.create', ['employee' => $outsider, 'date' => '2026-10-12']))->assertForbidden();
});

it('blocks unauthorized correction access and exposes review history to its owner', function (): void {
    $adjustment = app(AttendanceCorrection::class)->submit($this->requester, $this->employee, hrCorrectionPayload($this->employee));
    $other = User::factory()->create();
    $other->givePermissionTo(['hr.attendance.view-own', 'hr.attendance.correct-own', 'hr.attendance.approve']);
    $this->actingAs($other)->get(route('hr.attendance.corrections'))->assertOk()->assertDontSee('Missing device record explained');
    $this->postJson(route('hr.attendance.corrections.decide', $adjustment), ['decision' => 'approved', 'note' => 'Wrong employee reviewer'])->assertForbidden();
    $this->get(route('hr.attendance.corrections.create', ['employee' => $this->employee, 'date' => '2026-10-12']))->assertForbidden();
});
