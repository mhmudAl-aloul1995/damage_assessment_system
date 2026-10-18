<?php

use App\Models\Attendance;
use App\Models\User;
use App\Modules\HR\Exports\AttendanceExport;
use App\Modules\HR\Models\AttendanceProject;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceRecorder;
use App\Modules\HR\Services\AttendanceSetup;
use App\Support\Navigation\Sidebar;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionsSeeder;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(HrPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00:00', 'Asia/Hebron'));
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->setup = app(AttendanceSetup::class);
    $this->schedule = WorkSchedule::factory()->create(['working_days' => [1, 3]]);
    $this->project = AttendanceProject::factory()->create(['created_by' => $this->officer->id, 'name' => 'مشروع الإسكان']);
});

it('offers project preparation to attendance administrators only', function (): void {
    expect(Sidebar::forUser($this->officer)->firstWhere('key', 'hr')['sections']->pluck('url')->all())->toContain('hr/attendance/projects');
    $this->actingAs($this->officer)->get(route('hr.home'))->assertOk()->assertSee(route('hr.attendance.projects'), false);
    $this->get(route('hr.attendance.projects'))->assertOk()->assertSee('مشروع الإسكان')->assertSee('تفعيل الحضور عبر التطبيق');
    $reader = User::factory()->create();
    $reader->givePermissionTo('hr.attendance.view-own');
    expect(Sidebar::forUser($reader)->firstWhere('key', 'hr')['sections']->pluck('url')->all())->not->toContain('hr/attendance/projects');
    $this->actingAs($reader)->get(route('hr.attendance.projects'))->assertForbidden();
    $this->postJson(route('hr.attendance.projects.store'), [])->assertForbidden();
    $this->postJson(route('hr.attendance.projects.assignments.store'), [])->assertForbidden();
});

it('creates audited immutable projects and validates their actual dates and codes', function (): void {
    $data = ['code' => 'PRJ-HOUSING', 'name' => 'إعادة التأهيل', 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-31'];
    $this->actingAs($this->officer)->post(route('hr.attendance.projects.store'), $data)->assertRedirect(route('hr.attendance.projects'));
    $project = AttendanceProject::query()->where('code', $data['code'])->firstOrFail();
    expect($project->author->id)->toBe($this->officer->id);
    expect(fn () => $project->update(['ends_on' => '2027-12-31']))->toThrow(LogicException::class);
    expect(fn () => $project->delete())->toThrow(LogicException::class);
    $this->assertDatabaseHas('hr_audit_logs', ['action' => 'project.created', 'subject_id' => $project->id]);
    $this->postJson(route('hr.attendance.projects.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson(route('hr.attendance.projects.store'), [...$data, 'code' => 'OTHER', 'ends_on' => '2026-09-01'])->assertUnprocessable()->assertJsonValidationErrors('ends_on');
});

it('maps an existing account by email with an internal number and explicit native access', function (): void {
    $staff = User::factory()->create();
    $staff->assignRole(Role::findOrCreate('Field Engineer', 'web'));
    $this->actingAs($this->officer)->post(route('hr.attendance.projects.employees.store'), [
        'user_email' => $staff->email, 'display_name' => 'عامل مشروع', 'starts_on' => '2026-10-01', 'enable_native_attendance' => true,
    ])->assertRedirect(route('hr.attendance.projects'));
    $employee = Employee::query()->where('user_id', $staff->id)->sole();
    expect($employee->employee_number)->toStartWith('PHC-')->and($employee->ends_on)->toBeNull();
    $staff = $staff->fresh();
    expect($staff->hasRole('Field Engineer'))->toBeTrue()
        ->and($staff->getDirectPermissions()->pluck('name')->sort()->values()->all())->toBe(['hr.attendance.capture', 'hr.attendance.view-own', 'hr.devices.register'])
        ->and($staff->can('hr.devices.approve'))->toBeFalse()->and($staff->can('hr.payroll.view'))->toBeFalse();
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseHas('hr_audit_logs', ['action' => 'attendance.native_access_enabled', 'subject_id' => $staff->id]);
    $this->postJson(route('hr.attendance.projects.employees.store'), ['user_email' => $staff->email, 'display_name' => 'Duplicate', 'starts_on' => '2026-10-12'])
        ->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->assertDatabaseCount('hr_employees', 1);
    $login = $this->postJson('/api/v1/hr/auth/login', ['email' => $staff->email, 'password' => '123456', 'device_name' => 'Android'])
        ->assertOk()->assertJsonPath('employee.id', $employee->id);
    $this->app['auth']->forgetGuards();
    $this->withToken($login->json('access_token'))->getJson('/api/v1/hr/me')->assertOk()->assertJsonPath('data.capture_enabled', true);
});

it('keeps optional native activation off and generates distinct internal numbers', function (): void {
    $staff = User::factory()->create();
    $one = $this->setup->employee($this->officer, ['user_id' => $staff->id, 'display_name' => 'One', 'starts_on' => '2026-01-01']);
    $two = $this->setup->employee($this->officer, ['display_name' => 'Two', 'employee_number' => '', 'starts_on' => '2026-01-01']);
    expect($one->employee_number)->not->toBe($two->employee_number)->and($staff->fresh()->getAllPermissions())->toHaveCount(0);
    expect(AuditLog::query()->where('action', 'attendance.native_access_enabled')->count())->toBe(0);
});

it('keeps one employee and account across several project assignments', function (): void {
    $staff = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $staff->id]);
    Attendance::query()->create(['user_id' => $staff->id, 'date' => '2026-10-01', 'status' => true]);
    $otherProject = AttendanceProject::factory()->create(['created_by' => $this->officer->id]);
    $base = ['employee_id' => $employee->id, 'work_schedule_id' => $this->schedule->id, 'enable_native_attendance' => true];
    $this->actingAs($this->officer)->post(route('hr.attendance.projects.assignments.store'), [...$base,
        'project_id' => $this->project->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-16',
    ])->assertRedirect(route('hr.attendance.projects'));
    $this->post(route('hr.attendance.projects.assignments.store'), [...$base,
        'project_id' => $otherProject->id, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-23',
    ])->assertRedirect(route('hr.attendance.projects'));
    $this->assertDatabaseCount('hr_employees', 1);
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('attendances', 1);
    expect($employee->assignments()->pluck('project_id')->all())->toBe([$this->project->id, $otherProject->id])
        ->and($staff->fresh()->is_active)->toBeTrue()->and($employee->fresh()->ends_on)->toBeNull()
        ->and(AuditLog::query()->where('action', 'attendance.native_access_enabled')->count())->toBe(1);
});

it('rejects out of project dates and overlapping cross project shifts without granting access', function (): void {
    $staff = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $staff->id]);
    $short = AttendanceProject::factory()->create(['starts_on' => '2026-10-12', 'ends_on' => '2026-10-16', 'created_by' => $this->officer->id]);
    $data = ['employee_id' => $employee->id, 'project_id' => $short->id, 'work_schedule_id' => $this->schedule->id, 'starts_on' => '2026-10-11', 'ends_on' => '2026-10-16', 'enable_native_attendance' => true];
    $this->actingAs($this->officer)->postJson(route('hr.attendance.projects.assignments.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('project_id');
    $this->postJson(route('hr.attendance.projects.assignments.store'), [...$data, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-17'])->assertUnprocessable()->assertJsonValidationErrors('project_id');
    $this->assertDatabaseCount('hr_shift_assignments', 0);
    expect($staff->fresh()->getAllPermissions())->toHaveCount(0);
    $this->setup->assign($this->officer, [...$data, 'starts_on' => '2026-10-12', 'enable_native_attendance' => false]);
    $this->postJson(route('hr.attendance.projects.assignments.store'), [...$data, 'project_id' => $this->project->id, 'starts_on' => '2026-10-12'])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
    expect($staff->fresh()->getAllPermissions())->toHaveCount(0);
    $this->assertDatabaseCount('hr_shift_assignments', 1);
});

it('allows concurrent projects on distinct workdays without doubling the expected hours', function (): void {
    $employee = Employee::factory()->create();
    $otherProject = AttendanceProject::factory()->create(['created_by' => $this->officer->id]);
    $otherSchedule = WorkSchedule::factory()->create(['working_days' => [2, 4]]);
    $base = ['employee_id' => $employee->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-16'];
    $this->setup->assign($this->officer, [...$base, 'project_id' => $this->project->id, 'work_schedule_id' => $this->schedule->id]);
    $this->setup->assign($this->officer, [...$base, 'project_id' => $otherProject->id, 'work_schedule_id' => $otherSchedule->id]);
    $calculator = app(AttendanceCalculator::class);
    $asOf = CarbonImmutable::parse('2026-10-20 20:00:00', 'Asia/Hebron');
    expect($calculator->day($employee->fresh(), '2026-10-12', $asOf)['expected_minutes'])->toBe(450)
        ->and($calculator->day($employee->fresh(), '2026-10-13', $asOf)['expected_minutes'])->toBe(450)
        ->and($calculator->day($employee->fresh(), '2026-10-16', $asOf)['status'])->toBe('off_day');
    $this->assertDatabaseCount('hr_employees', 1);
});

it('counts absence only on scheduled working days within the project assignment', function (): void {
    $employee = Employee::factory()->create();
    $this->setup->assign($this->officer, ['employee_id' => $employee->id, 'project_id' => $this->project->id, 'work_schedule_id' => $this->schedule->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-16']);
    $asOf = CarbonImmutable::parse('2026-10-20 20:00:00', 'Asia/Hebron');
    $calculator = app(AttendanceCalculator::class);
    expect($calculator->day($employee->fresh(), '2026-10-11', $asOf)['status'])->toBe('unscheduled')
        ->and($calculator->day($employee->fresh(), '2026-10-12', $asOf)['status'])->toBe('absence_candidate')
        ->and($calculator->day($employee->fresh(), '2026-10-13', $asOf)['status'])->toBe('off_day')
        ->and($calculator->day($employee->fresh(), '2026-10-19', $asOf)['status'])->toBe('unscheduled')
        ->and($calculator->day($employee->fresh(), '2026-10-12', $asOf)['worked_minutes'])->toBeNull();
    $recorder = app(AttendanceRecorder::class);
    expect($recorder->captureState($employee->fresh())['options'])->not->toBeEmpty()
        ->and($recorder->captureState($employee->fresh(), CarbonImmutable::parse('2026-10-19 09:00:00', 'Asia/Hebron'))['options'])->toBeEmpty();
});

it('shows projects in scoped attendance and Excel without exposing other employees', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(['hr.attendance.view-own', 'hr.attendance.export']);
    $employee = Employee::factory()->create(['user_id' => $staff->id]);
    $this->setup->assign($this->officer, ['employee_id' => $employee->id, 'project_id' => $this->project->id, 'work_schedule_id' => $this->schedule->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-16']);
    $hidden = Employee::factory()->create(['display_name' => 'Hidden Employee']);
    $secret = AttendanceProject::factory()->create(['name' => 'Hidden Project', 'created_by' => $this->officer->id]);
    $this->setup->assign($this->officer, ['employee_id' => $hidden->id, 'project_id' => $secret->id, 'work_schedule_id' => $this->schedule->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-16']);
    $this->actingAs($staff)->get(route('hr.attendance.index', ['date' => '2026-10-12']))->assertOk()->assertSee('مشروع الإسكان')->assertDontSee('Hidden Project')->assertDontSee('Hidden Employee');
    Excel::fake();
    $this->get(route('hr.attendance.export', ['date' => '2026-10-12']))->assertOk();
    Excel::assertDownloaded('hr-attendance.xlsx', fn (AttendanceExport $export): bool => $export->collection()->count() === 1 && $export->collection()->first()[16] === 'مشروع الإسكان');
});

it('requires an active mapped account for explicit native activation and rolls back failed preparation', function (): void {
    $inactive = User::factory()->create(['is_active' => false]);
    $data = ['user_email' => $inactive->email, 'display_name' => 'Inactive employee', 'starts_on' => '2026-10-01', 'enable_native_attendance' => true];
    $this->actingAs($this->officer)->postJson(route('hr.attendance.projects.employees.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->assertDatabaseCount('hr_employees', 0);
    $this->assertDatabaseMissing('hr_audit_logs', ['action' => 'employee.mapped']);
    $this->postJson(route('hr.attendance.projects.employees.store'), ['display_name' => 'Unmapped', 'starts_on' => '2026-10-01', 'enable_native_attendance' => true])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->postJson(route('hr.attendance.projects.employees.store'), [...$data, 'user_id' => $this->officer->id])->assertUnprocessable()->assertJsonValidationErrors('user_email');
    $this->postJson(route('hr.attendance.projects.employees.store'), [...$data, 'user_email' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('user_email');
});
