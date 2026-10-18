<?php

use App\Models\User;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\DepartmentScope;
use App\Modules\HR\Models\DepartmentScopeEnding;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeAssignment;
use App\Modules\HR\Models\EmployeeAssignmentEnding;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\AttendanceSetup;
use App\Modules\HR\Services\EmployeeOrganizationScope;
use App\Support\Navigation\Sidebar;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function hrArchivedEmployeeRecord(string $table, array $attributes): Model
{
    $record = new class extends Model {};
    $record->setTable($table);
    $record->forceFill($attributes)->save();

    return $record;
}

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
    $this->seed(HrPermissionsSeeder::class);
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($this->officer);
});

it('retires employee administration and organization endpoints with legacy grants', function (string $method, string $path): void {
    foreach (['hr.employees.view', 'hr.employees.manage', 'hr.employees.sensitive', 'hr.organization.manage', 'hr.documents.view', 'hr.documents.upload', 'hr.documents.withdraw'] as $name) {
        $this->officer->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }
    $this->{$method}($path, [])->assertNotFound();
})->with([
    ['getJson', '/hr/employees'], ['getJson', '/hr/employees/create'],
    ['postJson', '/hr/employees'], ['getJson', '/hr/employees/1'],
    ['putJson', '/hr/employees/1'], ['postJson', '/hr/employees/1/assignments'],
    ['postJson', '/hr/employee-assignments/1/end'], ['postJson', '/hr/employees/1/notes'],
    ['postJson', '/hr/employees/1/archive'], ['postJson', '/hr/employees/1/documents'],
    ['getJson', '/hr/employee-documents/1/download'], ['postJson', '/hr/employee-documents/1/withdraw'],
    ['getJson', '/hr/organization-chart'], ['getJson', '/hr/organization/departments'],
    ['postJson', '/hr/organization/departments'], ['putJson', '/hr/organization/departments/1'],
    ['postJson', '/hr/department-scopes'], ['postJson', '/hr/department-scopes/1/end'],
]);

it('keeps the module focused on attendance without recreating retired grants or policies', function (): void {
    foreach (['hr.employees.view', 'hr.employees.manage', 'hr.organization.manage'] as $ability) {
        expect($this->officer->can($ability))->toBeFalse();
    }
    $this->get(route('hr.attendance.index'))->assertOk()
        ->assertDontSee('/hr/employees', false)->assertDontSee('/hr/organization', false)
        ->assertDontSee('الموظفون والتاريخ الوظيفي')->assertDontSee('الهيكل التنظيمي');
    $urls = collect(Sidebar::forUser($this->officer)->firstWhere('key', 'hr')['sections'])->pluck('url');
    expect($urls)->toContain('hr/attendance', 'hr/attendance/projects')
        ->not->toContain('hr/employees', 'hr/organization/departments', 'hr/contracts');
    expect(Permission::query()->where('name', 'like', 'hr.%')->count())->toBe(16)
        ->and(app('router')->has('hr.employees.index'))->toBeFalse()
        ->and(method_exists(EmployeePolicy::class, 'viewProfile'))->toBeFalse()
        ->and(class_exists(App\Modules\HR\Services\EmployeeManagement::class))->toBeFalse();
});

it('preserves old employee profiles encrypted documents histories and shared accounts during attendance setup', function (): void {
    Storage::fake('local');
    $account = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $account->id]);
    $profile = hrArchivedEmployeeRecord('hr_employee_profiles', [
        'employee_id' => $employee->id, 'name_en' => 'Archived name', 'birth_date' => Crypt::encryptString('1990-01-01'),
    ]);
    $history = hrArchivedEmployeeRecord('hr_employment_histories', [
        'employee_id' => $employee->id, 'event_type' => 'note', 'occurred_on' => '2026-01-01',
        'note' => Crypt::encryptString('Private existing note'), 'created_by' => $this->officer->id,
    ]);
    $path = 'hr/documents/preserved.enc';
    $contents = Crypt::encryptString('Existing private document');
    Storage::disk('local')->put($path, $contents);
    $document = hrArchivedEmployeeRecord('hr_employee_documents', [
        'employee_id' => $employee->id, 'category' => 'legacy', 'title' => Crypt::encryptString('Archived document'),
        'original_name' => Crypt::encryptString('original.pdf'), 'path' => $path, 'mime' => 'application/pdf',
        'size' => strlen($contents), 'checksum' => hash('sha256', $contents), 'created_by' => $this->officer->id,
    ]);
    $records = collect([$profile, $history, $document, $employee, $account]);
    $original = $records->map(fn (Model $record): array => $record->fresh()->getAttributes())->all();
    app(AttendanceSetup::class)->employee($this->officer, ['display_name' => 'New attendance identity', 'starts_on' => '2026-10-18']);
    $this->get(route('hr.attendance.index'))->assertOk();
    $this->getJson('/hr/employee-documents/'.$document->id.'/download')->assertNotFound();
    $this->postJson('/hr/employees/'.$employee->id.'/archive', ['archive' => true])->assertNotFound();
    expect($records->map(fn (Model $record): array => $record->fresh()->getAttributes())->all())->toBe($original)
        ->and(Storage::disk('local')->get($path))->toBe($contents);
});

it('retains dated primary reporting authority and never falls back to a superseded supervisor', function (): void {
    $legacy = User::factory()->create();
    $current = User::factory()->create();
    foreach ([$legacy, $current] as $actor) {
        $actor->givePermissionTo('hr.attendance.view-team');
    }
    $employee = Employee::factory()->create(['supervisor_user_id' => $legacy->id, 'starts_on' => '2026-01-01']);
    $manager = Employee::factory()->create(['user_id' => $current->id]);
    $department = Department::factory()->create();
    $title = hrArchivedEmployeeRecord('hr_job_titles', ['code' => 'EXISTING', 'name' => 'Existing title']);
    $assignment = EmployeeAssignment::query()->create([
        'employee_id' => $employee->id, 'department_id' => $department->id, 'job_title_id' => $title->id,
        'manager_employee_id' => $manager->id, 'primary' => true, 'starts_on' => '2026-10-01',
        'snapshot' => [], 'created_by' => $this->officer->id,
    ]);
    $policy = app(EmployeePolicy::class);
    expect($policy->view($legacy, $employee))->toBeFalse()
        ->and($policy->view($current, $employee))->toBeTrue()
        ->and(app(EmployeeOrganizationScope::class)->supervisorId($employee))->toBe($current->id);
    EmployeeAssignmentEnding::query()->create(['assignment_id' => $assignment->id, 'ends_on' => '2026-10-18', 'reason' => 'Existing dated ending', 'created_by' => $this->officer->id]);
    $this->travel(1)->days();
    expect($policy->view($legacy, $employee))->toBeFalse()->and($policy->view($current, $employee))->toBeFalse()
        ->and(app(EmployeeOrganizationScope::class)->supervisorId($employee))->toBeNull();
});

it('retains dated department attendance scopes descendants and immutable endings', function (): void {
    $parent = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $parent->id]);
    $actor = User::factory()->create();
    $actor->givePermissionTo('hr.attendance.view-team');
    $employee = Employee::factory()->create(['starts_on' => '2026-01-01']);
    $title = hrArchivedEmployeeRecord('hr_job_titles', ['code' => 'LEGACY', 'name' => 'Existing title']);
    EmployeeAssignment::query()->create([
        'employee_id' => $employee->id, 'department_id' => $child->id, 'job_title_id' => $title->id,
        'primary' => true, 'starts_on' => '2026-01-01', 'snapshot' => [], 'created_by' => $this->officer->id,
    ]);
    $scope = DepartmentScope::query()->create([
        'user_id' => $actor->id, 'department_id' => $parent->id, 'include_descendants' => true,
        'starts_on' => '2026-10-18', 'ends_on' => '2026-10-31', 'created_by' => $this->officer->id,
    ]);
    $policy = app(EmployeePolicy::class);
    expect($policy->view($actor, $employee))->toBeTrue();
    DepartmentScopeEnding::query()->create(['scope_id' => $scope->id, 'ends_on' => '2026-10-18', 'reason' => 'Existing scope ending', 'created_by' => $this->officer->id]);
    $this->travel(1)->days();
    expect($policy->view($actor, $employee))->toBeFalse()
        ->and($scope->fresh()->ends_on->toDateString())->toBe('2026-10-31');
});
