<?php

use App\Models\User;
use App\Modules\HR\Exports\ContractExport;
use App\Modules\HR\Jobs\NotifyContractExpiry;
use App\Modules\HR\Models\ContractAlert;
use App\Modules\HR\Models\ContractRevision;
use App\Modules\HR\Models\ContractType;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmploymentContract;
use App\Modules\HR\Services\ContractManagement;
use App\Modules\HR\Services\ContractReporting;
use Database\Seeders\HrContractTypesSeeder;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 13)->setTime(12, 0));
    $this->seed(HrPermissionsSeeder::class);
    Queue::fake();
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->reviewer = User::factory()->create();
    $this->reviewer->givePermissionTo(['hr.contracts.view', 'hr.contracts.approve', 'hr.contracts.sensitive']);
    $this->employee = Employee::factory()->create(['starts_on' => '2026-01-01']);
    $this->type = ContractType::factory()->create(['name' => 'PHC Fixed Term']);
    $this->data = ['employee_id' => $this->employee->id, 'number' => 'PHC-C-001', 'primary' => true, 'request_key' => (string) Str::uuid(), 'contract_type_id' => $this->type->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'reason' => 'Initial documented agreement'];
    $this->management = app(ContractManagement::class);
    $this->report = app(ContractReporting::class);
    $this->approve = function (?array $data = null): ContractRevision {
        $revision = $this->management->create($this->officer, $this->employee, $data ?? $this->data);
        $this->management->decide($this->reviewer, $revision, ['decision' => 'approved', 'reason' => 'Verified independently']);

        return $revision->fresh();
    };
});

it('renders contract pages for database officers without exposing sensitive terms or granting approval', function (): void {
    $this->actingAs($this->officer)->get(route('hr.contracts.index'))->assertOk();
    $this->get(route('hr.contracts.types'))->assertOk();
    $this->post(route('hr.contracts.store'), $this->data)->assertRedirect();
    $revision = ContractRevision::query()->firstOrFail();
    $this->get(route('hr.contracts.show', $revision->contract))->assertOk()->assertSee('PHC-C-001');
    expect($this->officer->can('hr.contracts.approve'))->toBeFalse()->and($this->officer->can('hr.contracts.sensitive'))->toBeFalse();
    $this->assertDatabaseCount('model_has_roles', 1);
});

it('creates idempotent immutable proposals without activating contracts or modifying existing user employment fields', function (): void {
    $account = User::factory()->create(['contract_type' => 'Legacy', 'contract_start_date' => '2026-01-01']);
    $this->employee->update(['user_id' => $account->id]);
    $before = $account->fresh()->getAttributes();
    $revision = $this->management->create($this->officer, $this->employee, $this->data);
    expect($this->management->create($this->officer, $this->employee, $this->data)->id)->toBe($revision->id)
        ->and($this->report->row($revision->contract)['status'])->toBe('pending')->and($account->fresh()->getAttributes())->toBe($before);
    expect(fn () => $this->management->create($this->officer, $this->employee, [...$this->data, 'reason' => 'Different request']))->toThrow(ValidationException::class);
    expect(fn () => $revision->update(['ends_on' => '2027-12-31']))->toThrow(LogicException::class);
    expect(fn () => $revision->contract->delete())->toThrow(LogicException::class);
});

it('requires independent reviewers with scope and private contract authority', function (): void {
    $revision = $this->management->create($this->officer, $this->employee, $this->data);
    $this->officer->givePermissionTo(['hr.contracts.approve', 'hr.contracts.sensitive']);
    $this->actingAs($this->officer)->postJson(route('hr.contracts.decide', $revision), ['decision' => 'approved', 'reason' => 'Self approval attempt'])->assertForbidden();
    $this->employee->update(['user_id' => $this->reviewer->id]);
    $this->actingAs($this->reviewer)->postJson(route('hr.contracts.decide', $revision), ['decision' => 'approved', 'reason' => 'Own employee approval'])->assertForbidden();
    $this->employee->update(['user_id' => null]);
    $this->reviewer->revokePermissionTo('hr.contracts.sensitive');
    $this->postJson(route('hr.contracts.decide', $revision), ['decision' => 'approved', 'reason' => 'Incomplete authority'])->assertForbidden();
    $this->assertDatabaseCount('hr_contract_approvals', 0);
});

it('encrypts salary and benefits and keeps public readers and exports free of financial data', function (): void {
    $this->actingAs($this->officer)->postJson(route('hr.contracts.store'), [...$this->data, 'financial_terms' => ['salary' => '2450.50', 'currency' => 'ILS', 'currency_scale' => 2]])->assertUnprocessable();
    $this->officer->givePermissionTo('hr.contracts.sensitive');
    $revision = ($this->approve)([...$this->data, 'financial_terms' => ['salary' => '2450.5', 'currency' => 'ILS', 'currency_scale' => 2, 'basis' => 'monthly', 'benefits' => 'Private health benefit']]);
    expect($revision->financial_terms['salary'])->toBe('2450.50')->and($revision->getRawOriginal('financial_terms'))->not->toContain('Private health benefit');
    $this->officer->revokePermissionTo('hr.contracts.sensitive');
    $this->get(route('hr.contracts.show', $revision->contract))->assertOk()->assertDontSee('2450.50')->assertDontSee('Private health benefit');
    Excel::fake();
    $this->get(route('hr.contracts.export'))->assertOk();
    Excel::assertDownloaded('hr-contracts.xlsx', fn (ContractExport $export): bool => $export->headings() === ['رقم الموظف', 'الاسم', 'رقم العقد', 'نوع العقد', 'الحالة', 'من', 'إلى', 'مقترح معلق'] && ! str_contains(json_encode($export->collection()), '2450.50'));
});

it('validates monetary precision without float arithmetic or a guessed currency scale', function (): void {
    $this->officer->givePermissionTo('hr.contracts.sensitive');
    expect(fn () => $this->management->create($this->officer, $this->employee, [...$this->data, 'financial_terms' => ['salary' => '10.125', 'currency' => 'ILS', 'currency_scale' => 2]]))->toThrow(ValidationException::class);
    $revision = $this->management->create($this->officer, $this->employee, [...$this->data, 'financial_terms' => ['salary' => '0010.125', 'currency' => 'KWD', 'currency_scale' => 3]]);
    expect($revision->financial_terms['salary'])->toBe('10.125');
});

it('preserves approved terms until future amendments take effect and extends expiry without a false version deadline', function (): void {
    $initial = ($this->approve)();
    $amendment = $this->management->propose($this->officer, $initial->contract, [...$this->data, 'request_key' => (string) Str::uuid(), 'kind' => 'amendment', 'effective_on' => '2026-11-01', 'ends_on' => '2027-06-30', 'reason' => 'Future end date extension']);
    $this->management->decide($this->reviewer, $amendment, ['decision' => 'approved', 'reason' => 'Extension independently verified']);
    $contract = $initial->contract->fresh();
    expect($this->report->row($contract)['revision']->id)->toBe($initial->id)->and($this->report->row($contract)['ends_on'])->toBe('2027-06-30');
    $this->travelTo(now()->setDate(2026, 11, 2));
    expect($this->report->row($contract->fresh())['revision']->id)->toBe($amendment->id)
        ->and($initial->fresh()->ends_on->toDateString())->toBe('2026-12-31');
});

it('preserves gaps in renewals and rejects overlapping renewed periods', function (): void {
    $initial = ($this->approve)();
    expect(fn () => $this->management->propose($this->officer, $initial->contract, [...$this->data, 'request_key' => (string) Str::uuid(), 'kind' => 'renewal', 'starts_on' => '2026-12-31', 'ends_on' => '2027-12-31']))->toThrow(ValidationException::class);
    $renewal = $this->management->propose($this->officer, $initial->contract, [...$this->data, 'request_key' => (string) Str::uuid(), 'kind' => 'renewal', 'starts_on' => '2027-02-01', 'ends_on' => '2027-12-31']);
    $this->management->decide($this->reviewer, $renewal, ['decision' => 'approved', 'reason' => 'Renewal independently verified']);
    $periods = $this->report->periods($initial->contract->fresh());
    expect($periods)->toHaveCount(2)->and($periods[0]['end'])->toBe('2026-12-31')->and($periods[1]['start'])->toBe('2027-02-01');
    expect($this->report->row($initial->contract->fresh(), '2027-01-15')['status'])->toBe('scheduled');
});

it('terminates contract coverage without deleting employees or deactivating their existing accounts', function (): void {
    $account = User::factory()->create();
    $this->employee->update(['user_id' => $account->id]);
    $initial = ($this->approve)();
    $ending = $this->management->propose($this->officer, $initial->contract, [...$this->data, 'request_key' => (string) Str::uuid(), 'kind' => 'termination', 'ends_on' => '2026-10-20']);
    $this->management->decide($this->reviewer, $ending, ['decision' => 'approved', 'reason' => 'Contract ending verified']);
    expect($this->report->row($initial->contract->fresh(), '2026-10-20')['status'])->toBe('active')
        ->and($this->report->row($initial->contract->fresh(), '2026-10-21')['status'])->toBe('terminated')
        ->and($account->fresh()->is_active)->toBeTrue()->and($this->employee->fresh()->ends_on)->toBeNull();
});

it('rejects primary contract overlap at approval but permits additional project contracts', function (): void {
    ($this->approve)();
    $overlap = $this->management->create($this->officer, $this->employee, [...$this->data, 'number' => 'CON-OVERLAP', 'request_key' => (string) Str::uuid()]);
    expect(fn () => $this->management->decide($this->reviewer, $overlap, ['decision' => 'approved', 'reason' => 'Overlapping proposal']))->toThrow(ValidationException::class);
    $project = $this->management->create($this->officer, $this->employee, [...$this->data, 'number' => 'CON-PROJECT', 'primary' => false, 'request_key' => (string) Str::uuid()]);
    $this->management->decide($this->reviewer, $project, ['decision' => 'approved', 'reason' => 'Additional project engagement']);
    $this->assertDatabaseCount('hr_contract_approvals', 2);
});

it('keeps rejection final and accepts a new proposal under the same contract identity', function (): void {
    $initial = $this->management->create($this->officer, $this->employee, $this->data);
    $decision = $this->management->decide($this->reviewer, $initial, ['decision' => 'rejected', 'reason' => 'Document requires correction']);
    expect($this->management->decide($this->reviewer, $initial, ['decision' => 'rejected', 'reason' => 'Document requires correction'])->id)->toBe($decision->id);
    expect(fn () => $this->management->decide($this->reviewer, $initial, ['decision' => 'approved', 'reason' => 'Changing final outcome']))->toThrow(ValidationException::class);
    $replacement = $this->management->propose($this->officer, $initial->contract, [...$this->data, 'kind' => 'initial', 'request_key' => (string) Str::uuid(), 'reason' => 'Corrected initial proposal']);
    $this->management->decide($this->reviewer, $replacement, ['decision' => 'approved', 'reason' => 'Correction independently accepted']);
    $this->assertDatabaseCount('hr_contracts', 1);
    $this->assertDatabaseCount('hr_contract_revisions', 2);
});

it('limits contract reading to own or authorized team employees including confidential own terms', function (): void {
    $own = User::factory()->create();
    $own->givePermissionTo('hr.contracts.view-own');
    $this->employee->update(['user_id' => $own->id]);
    $this->officer->givePermissionTo('hr.contracts.sensitive');
    $initial = ($this->approve)([...$this->data, 'financial_terms' => ['salary' => '7654.32', 'currency' => 'USD', 'currency_scale' => 2]]);
    $otherEmployee = Employee::factory()->create(['starts_on' => '2026-01-01']);
    $hidden = $this->management->create($this->officer, $otherEmployee, [...$this->data, 'number' => 'HIDDEN-CONTRACT', 'request_key' => (string) Str::uuid()]);
    $this->actingAs($own)->get(route('hr.contracts.index'))->assertOk()->assertDontSee('HIDDEN-CONTRACT');
    $this->get(route('hr.contracts.show', $initial->contract))->assertOk()->assertSee('7654.32');
    $this->get(route('hr.contracts.show', $hidden->contract))->assertForbidden();
    $this->postJson(route('hr.contracts.propose', $initial->contract), [])->assertForbidden();
});

it('configures arbitrary contract types and reminder thresholds while preserving original type snapshots', function (): void {
    $initial = ($this->approve)();
    $this->actingAs($this->officer)->put(route('hr.contracts.types.update', $this->type), ['code' => $this->type->code, 'name' => 'Renamed type', 'active' => true, 'requires_end' => true, 'reminder_days' => '30,7,0'])->assertRedirect();
    expect($this->type->fresh()->reminder_days)->toBe([30, 7, 0])->and($initial->fresh()->type_snapshot['name'])->toBe('PHC Fixed Term');
    $this->post(route('hr.contracts.types.store'), ['code' => 'CUSTOM', 'name' => 'New funding agreement', 'active' => true, 'requires_end' => false, 'reminder_days' => ''])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('hr_contract_types', ['code' => 'CUSTOM']);
    expect(fn () => $this->type->delete())->toThrow(LogicException::class);
});

it('sends private database expiry reminders once per threshold only to currently authorized HR staff', function (): void {
    $this->type->update(['reminder_days' => [30, 7]]);
    $initial = ($this->approve)([...$this->data, 'ends_on' => '2026-10-20']);
    $unprivileged = User::factory()->create();
    $job = new NotifyContractExpiry($initial->contract_id);
    $job->handle($this->report);
    $job->handle($this->report);
    expect(ContractAlert::query()->count())->toBe(2)->and($this->officer->notifications()->count())->toBe(1)->and($this->reviewer->notifications()->count())->toBe(1)
        ->and($unprivileged->notifications()->count())->toBe(0);
    $notification = $this->officer->notifications()->first()->data;
    expect($notification['action_url'])->toBe(route('hr.contracts.show', $initial->contract_id))->and($notification['message'])->toContain('2026-10-20');
});

it('uses no guessed reminder policy and does not notify after a contract is ended', function (): void {
    $initial = ($this->approve)([...$this->data, 'ends_on' => '2026-10-20']);
    (new NotifyContractExpiry($initial->contract_id))->handle($this->report);
    $this->assertDatabaseCount('hr_contract_alerts', 0);
    $this->type->update(['reminder_days' => [30, 7]]);
    $this->travelTo(now()->setDate(2026, 10, 22));
    (new NotifyContractExpiry($initial->contract_id))->handle($this->report);
    $this->assertDatabaseCount('hr_contract_alerts', 0);
});

it('imports configurable type labels idempotently without migrating legacy account contracts', function (): void {
    $account = User::factory()->create(['contract_type' => 'Local partner']);
    $this->seed(HrContractTypesSeeder::class);
    $this->seed(HrContractTypesSeeder::class);
    expect(ContractType::query()->where('name', 'Local partner')->count())->toBe(1)->and($account->fresh()->contract_type)->toBe('Local partner');
    $this->assertDatabaseCount('hr_contracts', 0);
});

it('resolves employee numbers through contract scope and rejects duplicate contract numbers', function (): void {
    $this->actingAs($this->officer)->post(route('hr.contracts.store'), [...$this->data, 'employee_id' => null, 'employee_number' => $this->employee->employee_number])->assertRedirect()->assertSessionHasNoErrors();
    $this->postJson(route('hr.contracts.store'), [...$this->data, 'request_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('number');
    $this->assertDatabaseCount('hr_contracts', 1);
});

it('verifies private attached document integrity again before approval', function (): void {
    Illuminate\Support\Facades\Storage::fake('local');
    $this->officer->givePermissionTo(['hr.documents.upload', 'hr.documents.view', 'hr.documents.sensitive']);
    $this->reviewer->givePermissionTo(['hr.employees.view', 'hr.documents.view', 'hr.documents.sensitive']);
    $document = app(App\Modules\HR\Services\EmployeeDocuments::class)->upload($this->officer, $this->employee, ['title' => 'Signed agreement', 'category' => 'employment'], Illuminate\Http\UploadedFile::fake()->createWithContent('agreement.pdf', "%PDF-1.4\nOriginal terms\n%%EOF"));
    $revision = $this->management->create($this->officer, $this->employee, [...$this->data, 'document_id' => $document->id]);
    Illuminate\Support\Facades\Storage::disk('local')->put($document->path, Illuminate\Support\Facades\Crypt::encryptString('Changed contents'));
    expect(fn () => $this->management->decide($this->reviewer, $revision, ['decision' => 'approved', 'reason' => 'Document integrity checked']))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('hr_contract_approvals', 0);
});

it('bounds contract dashboards and reports and produces a real scoped workbook', function (): void {
    $initial = ($this->approve)();
    $this->actingAs($this->officer)->get(route('hr.contracts.export'))->assertOk()->assertDownload('hr-contracts.xlsx');
    $this->get(route('hr.contracts.index', ['status' => 'expired']))->assertOk()->assertDontSee('PHC-C-001');
    $this->get(route('hr.contracts.index', ['expires_before' => '2026-11-01']))->assertOk()->assertDontSee('PHC-C-001');
    $this->get(route('hr.contracts.index', ['date' => '2027-01-01']))->assertOk()->assertSee('منتهي المدة')->assertSee('PHC-C-001');
    $query = EmploymentContract::query()->getConnection();
    $query->enableQueryLog();
    $query->flushQueryLog();
    $page = $this->report->page($this->officer);
    $count = count($query->getQueryLog());
    $query->disableQueryLog();
    expect($page['rows']->total())->toBe(1)->and($count)->toBeLessThan(12);
});

it('queues expiry checks through the scheduled command without sending prematurely',function (): void {
    ($this->approve)();
    $this->artisan('hr:notify-contract-expiry')->assertSuccessful();
    Queue::assertPushed(NotifyContractExpiry::class,1);
    $this->assertDatabaseCount('hr_contract_alerts',0);
});
