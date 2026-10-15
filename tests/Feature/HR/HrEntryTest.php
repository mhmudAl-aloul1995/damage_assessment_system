<?php

use App\Models\User;
use App\Modules\HR\Exports\AttendanceExport;
use App\Modules\HR\Models\Employee;
use App\Support\Navigation\Sidebar;
use Database\Seeders\HrPermissionsSeeder;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

it('opens the HR home from the workspace picker for database officers without seeded permissions', function (): void {
    $officer = User::factory()->create();
    $officer->assignRole(Role::findOrCreate('Database Officer', 'web'));

    $module = Sidebar::forUser($officer)->firstWhere('key', 'hr');
    expect($module['url'])->toBe('hr');
    $this->actingAs($officer)->get(route('hr.home'))->assertOk()
        ->assertSee('أهلاً بك في الموارد البشرية')
        ->assertSee(route('hr.attendance.index'), false)
        ->assertSee(route('hr.attendance.setup'), false);
    $this->get(route('hr.attendance.index'))->assertOk()->assertSee('اسم الموظف أو رقمه');
});

it('protects the home and omits setup links for attendance readers', function (): void {
    $this->getJson(route('hr.home'))->assertUnauthorized();
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('hr.home'))->assertForbidden();
    $this->seed(HrPermissionsSeeder::class);
    $user->givePermissionTo('hr.attendance.view-own');
    $this->get(route('hr.home'))->assertOk()->assertDontSee(route('hr.attendance.setup'), false);
});

it('searches attendance by name or employee number and exports the same filtered rows', function (string $search): void {
    $officer = User::factory()->create();
    $officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    Employee::factory()->create(['display_name' => 'موظف البحث', 'employee_number' => 'PHC-025']);
    Employee::factory()->create(['display_name' => 'موظف آخر', 'employee_number' => 'PHC-999']);
    $filters = ['date' => '2026-10-16', 'search' => $search];

    $this->actingAs($officer)->get(route('hr.attendance.index', $filters))->assertOk()
        ->assertSee('موظف البحث')->assertDontSee('موظف آخر')
        ->assertViewHas('totals', fn ($totals): bool => $totals->sum() === 1);
    Excel::fake();
    $this->get(route('hr.attendance.export', $filters))->assertOk();
    Excel::assertDownloaded('hr-attendance.xlsx', fn (AttendanceExport $export): bool => $export->collection()->count() === 1 && $export->collection()->first()[1] === 'موظف البحث');
})->with(['موظف البحث', 'PHC-025', '0']);

it('keeps search within the employees visible to the current user', function (): void {
    $this->seed(HrPermissionsSeeder::class);
    $reader = User::factory()->create();
    $reader->givePermissionTo('hr.attendance.view-own');
    Employee::factory()->create(['user_id' => $reader->id, 'display_name' => 'Visible', 'employee_number' => 'OWN-1']);
    Employee::factory()->create(['display_name' => 'Private employee', 'employee_number' => 'SECRET-2']);

    $this->actingAs($reader)->get(route('hr.attendance.index', ['search' => 'SECRET-2']))
        ->assertOk()->assertDontSee('Private employee')->assertViewHas('rows', fn ($rows): bool => $rows->isEmpty());
    $this->get(route('hr.attendance.index', ['search' => '%']))->assertOk()
        ->assertSee('Visible')->assertDontSee('Private employee');
    $this->getJson(route('hr.attendance.index', ['search' => ['invalid']]))
        ->assertUnprocessable()->assertJsonValidationErrors('search');
});
