<?php

use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeDocument;
use App\Modules\HR\Models\JobTitle;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\EmployeeManagement;
use App\Modules\HR\Services\EmployeeOrganizationScope;
use App\Modules\HR\Services\OrganizationCatalog;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 13)->setTime(12, 0));
    $this->seed(HrPermissionsSeeder::class);
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->department = Department::factory()->create(['name' => 'Operations']);
    $this->title = JobTitle::factory()->create(['name' => 'Coordinator']);
    $this->employee = Employee::factory()->create(['starts_on' => '2026-01-01']);
    $this->assignmentData = ['department_id' => $this->department->id, 'job_title_id' => $this->title->id,
        'primary' => true, 'starts_on' => '2026-01-01'];
});

it('renders employee and organization pages for database officers without granting private capabilities', function (): void {
    $this->actingAs($this->officer)->get(route('hr.employees.index'))->assertOk()->assertSee($this->employee->display_name);
    $this->get(route('hr.employees.create'))->assertOk();
    $this->get(route('hr.employees.show', $this->employee))->assertOk();
    foreach (array_keys(OrganizationCatalog::MODELS) as $kind) {
        $this->get(route('hr.organization.index', $kind))->assertOk();
    }
    foreach (['hr.employees.sensitive', 'hr.documents.view', 'hr.documents.upload', 'hr.payroll.view', 'hr.devices.approve'] as $ability) {
        expect($this->officer->can($ability))->toBeFalse();
    }
    $this->assertDatabaseCount('model_has_permissions', 0);
});

it('reuses existing accounts and preserves account data when creating employee identities', function (): void {
    $account = User::factory()->create(['email' => 'employee@example.test']);
    $original = $account->fresh()->getAttributes();
    $this->actingAs($this->officer)->post(route('hr.employees.store'), [
        'user_id' => $account->id, 'employee_number' => 'PHC-NEW', 'display_name' => 'New employee', 'starts_on' => '2026-10-01',
    ])->assertRedirect();
    $this->assertDatabaseCount('users', 2);
    expect($account->fresh()->getAttributes())->toBe($original);
    $created = Employee::query()->where('employee_number', 'PHC-NEW')->firstOrFail();
    expect($created->user_id)->toBe($account->id)->and($created->employmentHistory()->first()->event_type)->toBe('identity_created');
    $this->postJson(route('hr.employees.store'), ['user_id' => $account->id, 'employee_number' => 'DUPLICATE', 'display_name' => 'Duplicate', 'starts_on' => '2026-10-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('user_id');
});

it('updates work profiles separately from encrypted personal data and logs no private values', function (): void {
    $this->actingAs($this->officer)->put(route('hr.employees.update', $this->employee), ['display_name' => 'Updated', 'work_email' => 'work@example.test'])->assertRedirect();
    expect($this->employee->fresh()->profile->work_email)->toBe('work@example.test');
    $this->putJson(route('hr.employees.update', $this->employee), ['display_name' => 'Private', 'birth_date' => '1990-02-03'])
        ->assertUnprocessable()->assertJsonValidationErrors('birth_date');
    $this->officer->givePermissionTo('hr.employees.sensitive');
    $this->put(route('hr.employees.update', $this->employee), ['display_name' => 'Updated', 'birth_date' => '1990-02-03', 'emergency_contact' => ['name' => 'Private Relative', 'phone' => '0599123456']])->assertRedirect();
    $profile = $this->employee->fresh()->profile;
    expect($profile->birth_date)->toBe('1990-02-03')->and($profile->getRawOriginal('birth_date'))->not->toContain('1990-02-03')
        ->and($profile->getRawOriginal('emergency_contact'))->not->toContain('Private Relative');
    $history = $this->employee->employmentHistory()->latest('id')->firstOrFail();
    expect(json_decode($history->note, true)['before']['work_email'] ?? null)->toBeNull()
        ->and($history->getRawOriginal('note'))->not->toContain('Private Relative');
    expect(json_encode(AuditLog::query()->get()->toArray()))->not->toContain('Private Relative')->not->toContain('0599123456');
    $other = User::factory()->create();
    $other->givePermissionTo('hr.employees.view');
    $this->actingAs($other)->get(route('hr.employees.show', $this->employee))->assertOk()->assertDontSee('1990-02-03')->assertDontSee('Private Relative');
});

it('allows own profile reading while preventing access to another employee and administrative writes', function (): void {
    $own = User::factory()->create();
    $own->givePermissionTo('hr.employees.view-own');
    $this->employee->update(['user_id' => $own->id]);
    $hidden = Employee::factory()->create(['display_name' => 'Hidden Staff']);
    $this->actingAs($own)->get(route('hr.employees.index'))->assertOk()->assertDontSee('Hidden Staff');
    $this->get(route('hr.employees.show', $this->employee))->assertOk();
    $this->get(route('hr.employees.show', $hidden))->assertForbidden();
    $this->putJson(route('hr.employees.update', $this->employee), ['display_name' => 'Unauthorized'])->assertForbidden();
    $this->get(route('hr.organization.index', 'departments'))->assertForbidden();
});

it('makes effective primary assignments visible with immutable snapshots and rejects overlap', function (): void {
    $this->actingAs($this->officer)->post(route('hr.employees.assignments.store', $this->employee), $this->assignmentData)->assertRedirect();
    $assignment = $this->employee->fresh()->currentOrganizationAssignment;
    expect($assignment->department_id)->toBe($this->department->id)->and($assignment->snapshot['department'])->toBe('Operations');
    $this->postJson(route('hr.employees.assignments.store', $this->employee), $this->assignmentData)->assertUnprocessable()->assertJsonValidationErrors('starts_on');
    app(OrganizationCatalog::class)->save($this->officer, 'departments', ['name' => 'Renamed', 'code' => $this->department->code, 'active' => true], $this->department->id);
    expect($assignment->fresh()->snapshot['department'])->toBe('Operations');
    $this->get(route('hr.employees.index'))->assertOk()->assertSee('Renamed');
    $this->get(route('hr.employees.show', $this->employee))->assertOk()->assertSee('Operations');
    expect(fn () => $assignment->update(['primary' => false]))->toThrow(LogicException::class);
});

it('retires an assignment by appending history and activates the next assignment on its start date', function (): void {
    $service = app(EmployeeManagement::class);
    $old = $service->assign($this->officer, $this->employee, $this->assignmentData);
    $service->endAssignment($this->officer, $old, ['ends_on' => '2026-10-13', 'reason' => 'Department transfer']);
    $nextDepartment = Department::factory()->create();
    $next = $service->assign($this->officer, $this->employee, [...$this->assignmentData, 'department_id' => $nextDepartment->id, 'starts_on' => '2026-10-14']);
    expect($old->fresh()->ends_on)->toBeNull()->and($old->fresh()->effectiveEndsOn())->toBe('2026-10-13')
        ->and($this->employee->fresh()->currentOrganizationAssignment->id)->toBe($old->id);
    $this->travel(1)->days();
    expect($this->employee->fresh()->currentOrganizationAssignment->id)->toBe($next->id);
    $this->assertDatabaseCount('hr_employee_assignment_endings', 1);
});

it('detects future reporting cycles and blocks changing ones own authority', function (): void {
    $second = Employee::factory()->create(['starts_on' => '2026-01-01']);
    app(EmployeeManagement::class)->assign($this->officer, $second, [...$this->assignmentData, 'starts_on' => '2026-11-01', 'manager_employee_id' => $this->employee->id]);
    $this->actingAs($this->officer)->postJson(route('hr.employees.assignments.store', $this->employee), [...$this->assignmentData, 'manager_employee_id' => $second->id])
        ->assertUnprocessable()->assertJsonValidationErrors('manager_employee_id');
    $self = Employee::factory()->create(['user_id' => $this->officer->id, 'starts_on' => '2026-01-01']);
    $this->postJson(route('hr.employees.assignments.store', $self), $this->assignmentData)->assertForbidden();
});

it('keeps secondary project assignments from granting team authority', function (): void {
    $managerUser = User::factory()->create();
    $managerUser->givePermissionTo(['hr.employees.view-team', 'hr.attendance.view-team']);
    $manager = Employee::factory()->create(['user_id' => $managerUser->id, 'starts_on' => '2026-01-01']);
    app(EmployeeManagement::class)->assign($this->officer, $this->employee, [...$this->assignmentData, 'primary' => false, 'manager_employee_id' => $manager->id]);
    expect(app(EmployeePolicy::class)->viewProfile($managerUser, $this->employee))->toBeFalse()
        ->and(app(EmployeePolicy::class)->view($managerUser, $this->employee))->toBeFalse();
});

it('revokes legacy supervisor access when the new primary manager takes over', function (): void {
    $legacy = User::factory()->create();
    $current = User::factory()->create();
    foreach ([$legacy, $current] as $user) {
        $user->givePermissionTo(['hr.employees.view-team', 'hr.attendance.view-team']);
    }
    $this->employee->update(['supervisor_user_id' => $legacy->id]);
    $manager = Employee::factory()->create(['user_id' => $current->id, 'starts_on' => '2026-01-01']);
    $policy = app(EmployeePolicy::class);
    expect($policy->viewProfile($legacy, $this->employee))->toBeTrue();
    app(EmployeeManagement::class)->assign($this->officer, $this->employee, [...$this->assignmentData, 'manager_employee_id' => $manager->id]);
    expect($policy->viewProfile($legacy, $this->employee))->toBeFalse()->and($policy->view($legacy, $this->employee))->toBeFalse()
        ->and($policy->viewProfile($current, $this->employee))->toBeTrue()
        ->and(app(EmployeeOrganizationScope::class)->supervisorId($this->employee))->toBe($current->id);
});

it('limits department scopes to their dates and configured descendants', function (): void {
    $child = Department::factory()->create(['parent_id' => $this->department->id]);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['hr.employees.view-team', 'hr.attendance.view-team']);
    app(EmployeeManagement::class)->assign($this->officer, $this->employee, [...$this->assignmentData, 'department_id' => $child->id]);
    $catalog = app(OrganizationCatalog::class);
    $scope = $catalog->scope($this->officer, ['user_id' => $viewer->id, 'department_id' => $this->department->id, 'include_descendants' => false, 'starts_on' => '2026-10-13', 'ends_on' => '2026-10-31']);
    expect(app(EmployeePolicy::class)->viewProfile($viewer, $this->employee))->toBeFalse();
    $descendant = $catalog->scope($this->officer, ['user_id' => $viewer->id, 'department_id' => $this->department->id, 'include_descendants' => true, 'starts_on' => '2026-10-13', 'ends_on' => '2026-10-31']);
    expect(app(EmployeePolicy::class)->viewProfile($viewer, $this->employee))->toBeTrue();
    $catalog->endScope($this->officer, $descendant, ['ends_on' => '2026-10-13', 'reason' => 'Scope ends']);
    $this->travel(1)->days();
    expect(app(EmployeePolicy::class)->viewProfile($viewer, $this->employee))->toBeFalse()
        ->and($descendant->fresh()->ends_on->toDateString())->toBe('2026-10-31');
});

it('rejects department cycles and preserves archived catalogs and assignments', function (): void {
    $child = Department::factory()->create(['parent_id' => $this->department->id]);
    $this->actingAs($this->officer)->putJson(route('hr.organization.update', ['kind' => 'departments', 'record' => $this->department->id]), ['code' => $this->department->code, 'name' => 'Cycle', 'active' => true, 'parent_id' => $child->id])
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    app(OrganizationCatalog::class)->save($this->officer, 'departments', ['active' => false], $this->department->id);
    expect(fn () => $this->department->delete())->toThrow(LogicException::class);
    $this->postJson(route('hr.employees.assignments.store', $this->employee), $this->assignmentData)->assertNotFound();
    $this->assertDatabaseCount('hr_departments', 2);
});

it('encrypts private documents and validates scoped downloads independently of uploading', function (): void {
    Storage::fake('local');
    $this->officer->givePermissionTo('hr.documents.upload');
    $pdf = "%PDF-1.4\nprivate-document-content\n%%EOF";
    $this->actingAs($this->officer)->post(route('hr.employees.documents.store', $this->employee), ['title' => 'Private employment file', 'category' => 'employment', 'file' => UploadedFile::fake()->createWithContent('employee.pdf', $pdf)])->assertRedirect();
    $document = EmployeeDocument::query()->firstOrFail();
    expect(Storage::disk('local')->get($document->path))->not->toContain('private-document-content')
        ->and($document->getRawOriginal('title'))->not->toContain('Private employment file');
    $this->get(route('hr.employees.documents.download', $document))->assertForbidden();
    $this->officer->givePermissionTo(['hr.documents.view', 'hr.documents.sensitive']);
    $this->get(route('hr.employees.documents.download', $document))->assertOk()->assertDownload('employee.pdf')->assertStreamedContent($pdf);
    Storage::disk('local')->put($document->path, Crypt::encryptString('changed'));
    $this->get(route('hr.employees.documents.download', $document))->assertStatus(409);
});

it('versions and withdraws documents without deleting old encrypted files', function (): void {
    Storage::fake('local');
    $this->officer->givePermissionTo(['hr.documents.upload', 'hr.documents.view', 'hr.documents.sensitive', 'hr.documents.withdraw']);
    $data = ['title' => 'Document', 'category' => 'qualification'];
    $this->actingAs($this->officer)->post(route('hr.employees.documents.store', $this->employee), [...$data, 'file' => UploadedFile::fake()->createWithContent('first.pdf', "%PDF-1.4\nfirst\n%%EOF")])->assertRedirect();
    $first = EmployeeDocument::query()->firstOrFail();
    $this->post(route('hr.employees.documents.store', $this->employee), [...$data, 'previous_document_id' => $first->id, 'file' => UploadedFile::fake()->createWithContent('second.pdf', "%PDF-1.4\nsecond\n%%EOF")])->assertRedirect();
    $second = $first->fresh()->replacement;
    $this->post(route('hr.employees.documents.withdraw', $second), ['reason' => 'Superseded by official copy'])->assertRedirect();
    expect($second->fresh()->withdrawal)->not->toBeNull();
    Storage::disk('local')->assertExists([$first->path, $second->path]);
    expect(fn () => $first->delete())->toThrow(LogicException::class);
    $this->get(route('hr.employees.show', $this->employee))->assertOk()->assertSee('first.pdf')->assertSee('second.pdf');
});

it('rejects executable document extensions and documents belonging to another employee', function (): void {
    Storage::fake('local');
    $this->officer->givePermissionTo('hr.documents.upload');
    $this->actingAs($this->officer)->postJson(route('hr.employees.documents.store', $this->employee), ['title' => 'Bad', 'category' => 'other', 'file' => UploadedFile::fake()->createWithContent('payload.php', "%PDF-1.4\n%%EOF")])->assertUnprocessable();
    $other = Employee::factory()->create();
    $this->post(route('hr.employees.documents.store', $other), ['title' => 'Other', 'category' => 'other', 'file' => UploadedFile::fake()->createWithContent('valid.pdf', "%PDF-1.4\n%%EOF")])->assertRedirect();
    $first = EmployeeDocument::query()->firstOrFail();
    $this->postJson(route('hr.employees.documents.store', $this->employee), ['title' => 'Wrong version', 'category' => 'other', 'previous_document_id' => $first->id, 'file' => UploadedFile::fake()->createWithContent('valid.pdf', "%PDF-1.4\n%%EOF")])->assertUnprocessable();
    $this->assertDatabaseCount('hr_employee_documents', 1);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});

it('archives ended staff without deleting their accounts or employment history', function (): void {
    $account = User::factory()->create();
    $this->employee->update(['user_id' => $account->id, 'ends_on' => '2026-10-12']);
    $this->actingAs($this->officer)->post(route('hr.employees.archive', $this->employee), ['archive' => true])->assertRedirect();
    $this->get(route('hr.employees.index'))->assertOk()->assertDontSee($this->employee->employee_number);
    $this->get(route('hr.employees.index', ['archive' => 'archived']))->assertOk()->assertSee($this->employee->employee_number);
    expect($account->fresh()->is_active)->toBeTrue()->and($this->employee->fresh()->employmentHistory)->toHaveCount(1);
    $this->post(route('hr.employees.archive', $this->employee), ['archive' => false])->assertRedirect();
    expect($this->employee->fresh()->profile->archived_at)->toBeNull();
});

it('stores encrypted immutable employment notes and requires private data access', function (): void {
    $this->actingAs($this->officer)->postJson(route('hr.employees.notes.store', $this->employee), ['occurred_on' => '2026-10-13', 'note' => 'Confidential employment note'])->assertForbidden();
    $this->officer->givePermissionTo('hr.employees.sensitive');
    $this->post(route('hr.employees.notes.store', $this->employee), ['occurred_on' => '2026-10-13', 'note' => 'Confidential employment note'])->assertRedirect();
    $history = $this->employee->employmentHistory()->firstOrFail();
    expect($history->note)->toBe('Confidential employment note')->and($history->getRawOriginal('note'))->not->toContain('Confidential employment note');
    expect(fn () => $history->update(['note' => 'Rewrite']))->toThrow(LogicException::class);
});

it('denies employee administration to inactive accounts and accounts without grants', function (): void {
    $unprivileged = User::factory()->create();
    $this->actingAs($unprivileged)->get(route('hr.employees.index'))->assertForbidden();
    $this->get(route('hr.employees.show', $this->employee))->assertForbidden();
    $this->postJson(route('hr.employees.store'), [])->assertForbidden();
    $this->officer->update(['is_active' => false]);
    $this->actingAs($this->officer)->get(route('hr.employees.index'))->assertRedirect()->assertSessionHasErrors('email');
    expect(fn () => app(EmployeeManagement::class)->create($this->officer, ['employee_number' => 'BLOCKED', 'display_name' => 'Blocked', 'starts_on' => '2026-01-01']))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('renders an interactive current organization chart with scoped employees and department hierarchy', function (): void {
    $child = Department::factory()->create(['parent_id' => $this->department->id, 'name' => 'Child Team']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('hr.employees.view-team');
    $manager = Employee::factory()->create(['user_id' => $viewer->id, 'starts_on' => '2026-01-01']);
    app(EmployeeManagement::class)->assign($this->officer, $this->employee, [...$this->assignmentData, 'department_id' => $child->id, 'manager_employee_id' => $manager->id]);
    $hidden = Employee::factory()->create(['display_name' => 'Outside Chart Scope']);
    $this->actingAs($viewer)->get(route('hr.organization.chart'))->assertOk()->assertSee('<details open>', false)
        ->assertSee('Child Team')->assertSee($this->employee->display_name)->assertDontSee($hidden->display_name);
    $this->get(route('hr.organization.chart', ['q' => 'No match']))->assertOk()->assertDontSee($this->employee->employee_number);
    $this->actingAs($this->officer)->get(route('hr.organization.chart'))->assertOk()->assertSee($hidden->display_name);
});
