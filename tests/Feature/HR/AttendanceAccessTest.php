<?php

use App\Models\User;
use App\Modules\HR\Exports\AttendanceExport;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceGatewayService;
use App\Modules\HR\Services\AttendanceReport;
use App\Modules\HR\Services\NetworkEligibilityService;
use App\Support\Navigation\Sidebar;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

beforeEach(function (): void {
    $this->seed(HrPermissionsSeeder::class);
    $this->viewer = User::factory()->create();
});

it('requires attendance grants for accounts without the database officer role', function (): void {
    $this->actingAs($this->viewer)->get(route('hr.attendance.index'))->assertForbidden();
    $this->get(route('hr.attendance.setup'))->assertForbidden();
    $this->postJson(route('hr.attendance.employees.store'), [])->assertForbidden();
    $this->get(route('hr.attendance.export'))->assertForbidden();
});

it('scopes employee history and export to the authenticated mapped account', function (): void {
    $this->viewer->givePermissionTo(['hr.attendance.view-own', 'hr.attendance.export']);
    Employee::factory()->create(['user_id' => $this->viewer->id, 'display_name' => 'Visible Employee']);
    $hidden = Employee::factory()->create(['display_name' => 'Hidden Employee']);
    $this->actingAs($this->viewer)->get(route('hr.attendance.index', ['month' => '2026-10']))
        ->assertOk()->assertSee('Visible Employee')->assertDontSee('Hidden Employee');
    $this->get(route('hr.attendance.index', ['employee_id' => $hidden->id]))
        ->assertOk()->assertDontSee('Hidden Employee')->assertDontSee('Visible Employee');
    Excel::fake();
    $this->get(route('hr.attendance.export', ['month' => '2026-10']))->assertOk();
    Excel::assertDownloaded('hr-attendance.xlsx', function (AttendanceExport $export): bool {
        return $export->collection()->count() === 31
            && $export->collection()->every(fn (array $row): bool => $row[1] === 'Visible Employee');
    });
});

it('does not grant exports implicitly with global viewing', function (): void {
    $this->viewer->givePermissionTo('hr.attendance.view');
    $this->actingAs($this->viewer)->get(route('hr.attendance.index'))->assertOk();
    $this->get(route('hr.attendance.export'))->assertForbidden();
});

it('validates mutually exclusive reporting dates', function (): void {
    $this->viewer->givePermissionTo('hr.attendance.view');
    $this->actingAs($this->viewer)->getJson(route('hr.attendance.index', ['month' => '2026-13']))->assertUnprocessable();
    $this->getJson(route('hr.attendance.index', ['month' => '2026-10', 'date' => '2026-10-12']))->assertUnprocessable();
});

it('creates audited employee identity without creating an account or changing permissions', function (): void {
    $this->viewer->givePermissionTo('hr.attendance.setup');
    $this->actingAs($this->viewer)->post(route('hr.attendance.employees.store'), [
        'employee_number' => 'HR-001', 'display_name' => 'موظف جديد', 'starts_on' => '2026-10-01',
    ])->assertRedirect(route('hr.attendance.setup'));
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('hr_employees', ['employee_number' => 'HR-001', 'user_id' => null]);
    expect(AuditLog::query()->where('action', 'employee.mapped')->first()->actor_id)->toBe($this->viewer->id);
    $this->get(route('hr.attendance.setup'))->assertOk()->assertSee('HR-001');
});

it('prevents duplicate account mappings', function (): void {
    $this->viewer->givePermissionTo('hr.attendance.setup');
    Employee::factory()->create(['user_id' => $this->viewer->id]);
    $this->actingAs($this->viewer)->postJson(route('hr.attendance.employees.store'), [
        'employee_number' => 'HR-002', 'display_name' => 'Duplicate', 'starts_on' => '2026-10-01',
        'user_id' => $this->viewer->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('user_id');
});

it('validates schedules and creates effective dated assignments through the UI endpoints', function (): void {
    $this->viewer->givePermissionTo('hr.attendance.setup');
    $schedule = ['name' => 'Morning v1', 'timezone' => 'Asia/Hebron', 'working_days' => [1, 2, 3, 4, 7],
        'starts_at' => '08:00', 'ends_at' => '16:00', 'grace_minutes' => 5, 'break_minutes' => 30];
    $this->actingAs($this->viewer)->post(route('hr.attendance.schedules.store'), $schedule)->assertRedirect();
    $this->postJson(route('hr.attendance.schedules.store'), array_replace($schedule, ['ends_at' => '08:00']))->assertUnprocessable();
    $this->postJson(route('hr.attendance.schedules.store'), array_replace($schedule, ['break_minutes' => 480]))->assertUnprocessable();
    $this->postJson(route('hr.attendance.schedules.store'), array_replace($schedule, ['working_days' => [1, 1]]))->assertUnprocessable();
    $employee = Employee::factory()->create();
    $assignment = ['employee_id' => $employee->id, 'work_schedule_id' => WorkSchedule::query()->first()->id,
        'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31'];
    $this->post(route('hr.attendance.assignments.store'), $assignment)->assertRedirect();
    $this->postJson(route('hr.attendance.assignments.store'), $assignment)->assertUnprocessable();
    $this->assertDatabaseCount('hr_shift_assignments', 1);
});

it('never enables production capture through flags or claimed network headers', function (): void {
    config(['hr.web_require_https' => false, 'hr.attendance_enabled' => true, 'hr.network_mode' => 'simulation', 'hr.gateway_url' => 'https://gateway.example.test']);
    app()->instance('env', 'production');
    try {
        expect(fn () => app(AttendanceGatewayService::class)->assertSimulationAllowed())
            ->toThrow(ServiceUnavailableHttpException::class);
        expect(app(NetworkEligibilityService::class)->verified())->toBeFalse();
    } finally {
        app()->instance('env', 'testing');
    }
});

it('rejects insecure HR requests when HTTPS enforcement is enabled even with forged proxy headers', function (): void {
    config(['hr.web_require_https' => true]);
    $middleware = app(App\Modules\HR\Http\Middleware\RequireHrHttps::class);
    $request = Request::create('http://example.test/hr/attendance');
    $request->headers->set('X-Forwarded-Proto', 'https');
    app()->instance('env', 'production');
    try {
        expect(fn () => $middleware->handle($request, fn () => response('private')))
            ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    } finally {
        app()->instance('env', 'testing');
    }
});

it('allows the configured HR web transport in production', function (bool $requireHttps, string $scheme): void {
    config(['hr.web_require_https' => $requireHttps]);
    $middleware = app(App\Modules\HR\Http\Middleware\RequireHrHttps::class);
    $request = Request::create($scheme.'://example.test/hr/attendance');
    app()->instance('env', 'production');
    try {
        $response = $middleware->handle($request, fn () => response('Continue to authentication'));
        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('Continue to authentication');
    } finally {
        app()->instance('env', 'testing');
    }
})->with([
    'HTTP permitted' => [false, 'http'],
    'HTTPS permitted without enforcement' => [false, 'https'],
    'HTTPS permitted with enforcement' => [true, 'https'],
]);

it('still requires authentication when HR web HTTP is allowed', function (): void {
    config(['hr.web_require_https' => false]);
    $this->getJson(route('hr.attendance.index'))->assertUnauthorized();
    $this->getJson(route('hr.attendance.setup'))->assertUnauthorized();
    $this->postJson(route('hr.attendance.employees.store'), [])->assertUnauthorized();
    $this->getJson(route('hr.attendance.export'))->assertUnauthorized();
});

it('keeps the simulator disabled by default', function (): void {
    config(['hr.attendance_enabled' => false, 'hr.network_mode' => 'disabled']);
    expect(fn () => app(AttendanceGatewayService::class)->assertSimulationAllowed())
        ->toThrow(ServiceUnavailableHttpException::class);
});

it('writes employee-controlled strings as literal Excel cells', function (): void {
    $sheet = (new Spreadsheet)->getActiveSheet();
    $export = new AttendanceExport(collect());
    $export->bindValue($sheet->getCell('A1'), '=HYPERLINK("https://example.test","click")');
    $export->bindValue($sheet->getCell('A2'), '0000123');
    expect($sheet->getCell('A1')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getCell('A2')->getValue())->toBe('0000123');
});

it('downloads a real Excel workbook for authorized attendance readers', function (): void {
    $this->viewer->givePermissionTo(['hr.attendance.view', 'hr.attendance.export']);
    Employee::factory()->create();
    $this->actingAs($this->viewer)->get(route('hr.attendance.export', ['date' => '2026-10-12']))
        ->assertOk()->assertDownload('hr-attendance.xlsx');
});

it('exposes no ordinary check-in or check-out endpoint', function (): void {
    $this->actingAs($this->viewer)->postJson('/api/v1/hr/attendance/check-in', [])->assertStatus(426);
    $this->postJson('/api/v1/hr/attendance/check-out', [])->assertStatus(426);
});

it('seeds HR grants idempotently without assigning them to existing roles', function (): void {
    $role = Role::findOrCreate('Database Officer', 'web');
    $this->seed(HrPermissionsSeeder::class);
    expect(Spatie\Permission\Models\Permission::query()->where('name', 'like', 'hr.%')->count())->toBe(16)
        ->and($role->fresh()->permissions)->toHaveCount(0);
});

it('adds navigation only for attendance viewers and preserves existing modules', function (): void {
    expect(Sidebar::forUser($this->viewer)->pluck('key'))->not->toContain('hr');
    $this->viewer->givePermissionTo('hr.attendance.view-own');
    expect(Sidebar::forUser($this->viewer)->pluck('key'))->toContain('hr');
    expect(app('router')->has('attendance.summary'))->toBeTrue()
        ->and(app('router')->has('damageAssessment.index'))->toBeTrue();
});

it('loads report relationships in a bounded number of queries', function (): void {
    $this->viewer->givePermissionTo('hr.attendance.view');
    Employee::factory()->count(10)->create();
    $connection = Employee::query()->getConnection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();
    $report = app(AttendanceReport::class);
    $rows = $report->rows($report->query($this->viewer, ['month' => '2026-10'])->get(), ['month' => '2026-10']);
    $queries = count($connection->getQueryLog());
    $connection->disableQueryLog();
    expect($rows)->toHaveCount(310)->and($queries)->toBeLessThan(10);
});
