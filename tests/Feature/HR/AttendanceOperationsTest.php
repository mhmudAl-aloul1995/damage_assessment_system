<?php

use App\Models\Attendance;
use App\Models\User;
use App\Modules\HR\Jobs\FinishAttendanceExport;
use App\Modules\HR\Jobs\NotifyAttendanceException;
use App\Modules\HR\Jobs\PrepareAttendanceExport;
use App\Modules\HR\Jobs\WriteAttendanceExportPart;
use App\Modules\HR\Models\AttendanceAlert;
use App\Modules\HR\Models\AttendanceException;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\ReportExport;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Notifications\AttendanceExportReady;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\AttendanceExportQueue;
use App\Modules\HR\Services\AttendanceReconciliation;
use App\Modules\HR\Services\AttendanceReport;
use App\Modules\HR\Services\AttendanceSetup;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(HrPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 18:00:00', 'Asia/Hebron'));
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->employee = Employee::factory()->create();
    Storage::fake('local');
    Queue::fake();
    $this->exports = app(AttendanceExportQueue::class);
});

it('queues a scoped immutable request once and prepares bounded chained workbook jobs', function (): void {
    Employee::factory()->count(500)->create();
    $id = (string) Str::uuid();
    $this->actingAs($this->officer)->post(route('hr.attendance.reports.queue'), ['date' => '2026-10-12', 'idempotency_key' => $id])->assertRedirect();
    $request = ReportExport::query()->findOrFail($id);
    expect(count($request->employee_ids))->toBe(501)->and($request->state)->toBe('queued');
    Queue::assertPushed(PrepareAttendanceExport::class, 1);
    expect($this->exports->request($this->officer, ['date' => '2026-10-12'], $id)->id)->toBe($id);
    Queue::assertPushed(PrepareAttendanceExport::class, 1);
    Bus::fake();
    (new PrepareAttendanceExport($id))->handle($this->exports);
    Bus::assertChained([
        new WriteAttendanceExportPart($id, 0), new WriteAttendanceExportPart($id, 1), new WriteAttendanceExportPart($id, 2),
        new WriteAttendanceExportPart($id, 3), new WriteAttendanceExportPart($id, 4), new WriteAttendanceExportPart($id, 5),
        new FinishAttendanceExport($id),
    ]);
});

it('writes a real private XLSX archive with source manifest and sends readiness only once', function (): void {
    Notification::fake();
    $this->employee->update(['display_name' => '=HYPERLINK("https://example.test","name")']);
    $request = $this->exports->request($this->officer, ['date' => '2026-10-12'], (string) Str::uuid());
    $this->exports->part($request->id, 0);
    $parts = $request->fresh()->parts;
    $this->exports->part($request->id, 0);
    expect($request->fresh()->parts)->toBe($parts);
    $this->exports->finish($request->id);
    $this->exports->finish($request->id);
    $request = $request->fresh();
    expect($request->state)->toBe('ready');
    Storage::disk('local')->assertExists($request->artifact);
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($request->artifact)))->toBeTrue();
    $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($manifest['employee_count'])->toBe(1)->and($manifest['parts'][0]['rows'])->toBe(1);
    expect($zip->getFromName('part-1.xlsx'))->not->toBeFalse();
    $zip->close();
    $workbook = PhpOffice\PhpSpreadsheet\IOFactory::load(Storage::disk('local')->path($parts[0]['file']));
    expect($workbook->getActiveSheet()->getCell('B2')->getDataType())->toBe(PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
        ->and($workbook->getActiveSheet()->getCell('L2')->getValue())->toBeNull();
    $workbook->disconnectWorksheets();
    Notification::assertSentToTimes($this->officer, AttendanceExportReady::class, 1);
    $this->actingAs($this->officer)->get(route('hr.attendance.reports.download', $request))->assertOk()->assertDownload();
    expect(AuditLog::query()->where('action', 'attendance.export_ready')->count())->toBe(1);
});

it('rechecks ownership permission and expiry instead of treating a private path as authorization', function (): void {
    $request = $this->exports->request($this->officer, ['date' => '2026-10-12'], (string) Str::uuid());
    $this->exports->part($request->id, 0);
    $this->exports->finish($request->id);
    $other = User::factory()->create();
    $other->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($other)->get(route('hr.attendance.reports.download', $request))->assertForbidden();
    $this->actingAs($this->officer);
    $this->officer->update(['is_active' => false]);
    $this->getJson(route('hr.attendance.reports.download', $request))->assertForbidden();
    $this->officer->update(['is_active' => true]);
    $this->actingAs($this->officer);
    $this->travelTo($request->expires_at->addSecond());
    $this->getJson(route('hr.attendance.reports.download', $request))->assertForbidden();
});

it('refuses generation if the supervisor loses an exported employee scope', function (): void {
    $manager = User::factory()->create();
    $manager->givePermissionTo(['hr.attendance.view-team', 'hr.attendance.export']);
    $this->employee->update(['supervisor_user_id' => $manager->id]);
    $request = $this->exports->request($manager, ['date' => '2026-10-12'], (string) Str::uuid());
    $this->employee->update(['supervisor_user_id' => null]);
    expect(fn () => $this->exports->part($request->id, 0))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->exports->failed($request->id);
    expect($request->fresh()->state)->toBe('failed');
});

it('preserves export scope and source ledger identity across instance and bulk operations', function (): void {
    $request = $this->exports->request($this->officer, ['date' => '2026-10-12'], (string) Str::uuid());
    expect(fn () => $request->update(['filters' => ['month' => '2026-09']]))->toThrow(LogicException::class);
    expect(fn () => ReportExport::query()->whereKey($request->id)->update(['employee_ids' => []]))->toThrow(LogicException::class);
    expect(fn () => ReportExport::query()->truncate())->toThrow(LogicException::class);
    expect(fn () => AuditLog::query()->truncate())->toThrow(LogicException::class);
    expect(fn () => $request->delete())->toThrow(LogicException::class);
    $this->actingAs($this->officer)->get(route('hr.attendance.audit'))->assertOk()->assertSee($request->id);
});

it('rejects an incomplete or modified archive and purges only expired derived files', function (): void {
    $request = $this->exports->request($this->officer, ['date' => '2026-10-12'], (string) Str::uuid());
    expect(fn () => $this->exports->finish($request->id))->toThrow(RuntimeException::class);
    $this->exports->part($request->id, 0);
    $this->exports->finish($request->id);
    $request = $request->fresh();
    Storage::disk('local')->put($request->artifact, 'modified archive');
    $this->actingAs($this->officer)->getJson(route('hr.attendance.reports.download', $request))->assertStatus(409);
    Storage::disk('local')->put('other-module/preserved.txt', 'Keep this file');
    $this->travelTo($request->expires_at->addSecond());
    $this->artisan('hr:prune-attendance-exports')->assertSuccessful();
    Storage::disk('local')->assertMissing($request->artifact);
    Storage::disk('local')->assertExists('other-module/preserved.txt');
    expect($request->fresh()->state)->toBe('expired');
    $this->assertDatabaseCount('hr_attendance_report_exports', 1);
});

it('keeps legacy hours unknown in scoped administrative metrics', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo('hr.attendance.view-own');
    $this->employee->update(['user_id' => $staff->id]);
    Attendance::query()->create(['user_id' => $staff->id, 'date' => '2026-10-12', 'status' => true]);
    Employee::factory()->create();
    $metrics = app(AttendanceReport::class)->metrics($staff, ['date' => '2026-10-12']);
    expect($metrics['days'])->toBe(1)->and($metrics['unknown_days'])->toBe(1)->and($metrics['known_days'])->toBe(0)->and($metrics['worked_minutes'])->toBe(0);
    $this->actingAs($staff)->get(route('hr.attendance.reports', ['date' => '2026-10-12']))->assertOk()->assertSee('أيام بساعات غير معلومة');
    $this->postJson(route('hr.attendance.reports.queue'), ['date' => '2026-10-12', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
});

it('defaults report cards to the selected monthly period and reports missing workflow storage clearly', function (): void {
    $response = $this->actingAs($this->officer)->get(route('hr.attendance.reports'))->assertOk();
    expect($response->viewData('filters')['month'])->toBe('2026-10')->and($response->viewData('metrics')['days'])->toBe(31);
    Illuminate\Support\Facades\Schema::rename('hr_attendance_alerts', 'hr_attendance_alerts_pending');
    try {
        $this->getJson(route('hr.attendance.index'))->assertServiceUnavailable()->assertSee('HR');
    } finally {
        Illuminate\Support\Facades\Schema::rename('hr_attendance_alerts_pending', 'hr_attendance_alerts');
    }
});

it('records sampled API denials without retaining secrets and scopes security reports by permission', function (): void {
    $this->postJson('https://localhost/api/v1/hr/auth/login', ['email' => 'unknown@example.test', 'password' => 'not-for-audit-log', 'device_name' => 'Android'])->assertUnprocessable();
    $audit = AuditLog::query()->where('action', 'attendance.native_request_denied')->firstOrFail();
    expect($audit->metadata)->toBe(['route' => 'api.v1.hr.login', 'status' => 422, 'sample_window_seconds' => 60]);
    expect(json_encode($audit->getAttributes()))->not->toContain('not-for-audit-log')->not->toContain('unknown@example.test');
    $this->actingAs($this->officer)->get(route('hr.attendance.security-report', ['date' => '2026-10-12']))->assertOk()->assertSee('api.v1.hr.login');
    $reader = User::factory()->create();
    $reader->givePermissionTo('hr.attendance.view');
    $this->actingAs($reader)->get(route('hr.attendance.security-report'))->assertForbidden();
});

it('notifies current authorized employees and supervisors once for an open reconciled exception', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo('hr.attendance.view-own');
    $manager = User::factory()->create();
    $manager->givePermissionTo('hr.attendance.view-team');
    $this->employee->update(['user_id' => $staff->id, 'supervisor_user_id' => $manager->id]);
    $schedule = WorkSchedule::factory()->create();
    app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $schedule->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    app(AttendanceReconciliation::class)->day($this->employee, '2026-10-12');
    Queue::assertPushed(NotifyAttendanceException::class, 1);
    $exception = AttendanceException::query()->firstOrFail();
    (new NotifyAttendanceException($exception->id))->handle(app(EmployeePolicy::class));
    (new NotifyAttendanceException($exception->id))->handle(app(EmployeePolicy::class));
    expect(AttendanceAlert::query()->count())->toBe(2)->and($staff->notifications()->count())->toBe(1)->and($manager->notifications()->count())->toBe(1);
    expect($staff->notifications()->first()->data['action_url'])->toContain('employee_id='.$this->employee->id);
    expect(fn () => AttendanceAlert::query()->first()->delete())->toThrow(LogicException::class);
});

it('does not send an exception alert after resolution or a scope change', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo('hr.attendance.view-own');
    $this->employee->update(['user_id' => $staff->id]);
    $schedule = WorkSchedule::factory()->create();
    app(AttendanceSetup::class)->assign($this->officer, ['employee_id' => $this->employee->id, 'work_schedule_id' => $schedule->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    app(AttendanceReconciliation::class)->day($this->employee, '2026-10-12');
    $exception = AttendanceException::query()->firstOrFail();
    Attendance::query()->create(['user_id' => $staff->id, 'date' => '2026-10-12', 'status' => true]);
    app(AttendanceReconciliation::class)->day($this->employee, '2026-10-12');
    (new NotifyAttendanceException($exception->id))->handle(app(EmployeePolicy::class));
    expect($staff->notifications()->count())->toBe(0)->and(AttendanceAlert::query()->count())->toBe(0);
});
