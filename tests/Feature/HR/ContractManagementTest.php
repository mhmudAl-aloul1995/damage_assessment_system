<?php

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\AttendanceInstaller;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($this->officer);
});

it('retires contract endpoints even for officers holding legacy permissions', function (string $method, string $path): void {
    foreach (['view', 'manage', 'approve', 'configure', 'export', 'sensitive'] as $action) {
        $permission = Permission::findOrCreate('hr.contracts.'.$action, 'web');
        $this->officer->givePermissionTo($permission);
    }
    $this->{$method}($path, [])->assertNotFound();
})->with([
    ['getJson', '/hr/contracts'], ['postJson', '/hr/contracts'],
    ['getJson', '/hr/contracts/export'], ['getJson', '/hr/contracts/types'],
    ['postJson', '/hr/contracts/types'], ['putJson', '/hr/contracts/types/1'],
    ['getJson', '/hr/contracts/1'], ['postJson', '/hr/contracts/1/revisions'],
    ['postJson', '/hr/contract-revisions/1/decision'],
]);

it('removes contract navigation jobs and automatic officer allowances', function (): void {
    $this->get(route('hr.attendance.index'))->assertOk()
        ->assertDontSee('/hr/contracts', false)->assertDontSee('العقود');
    foreach (['view', 'manage', 'configure', 'export'] as $action) {
        expect($this->officer->can('hr.contracts.'.$action))->toBeFalse();
    }
    expect(array_keys(Artisan::all()))->not->toContain('hr:notify-contract-expiry');
    foreach (app(Schedule::class)->events() as $event) {
        expect($event->command ?? '')->not->toContain('hr:notify-contract-expiry');
    }
    expect(app('router')->has('hr.contracts.index'))->toBeFalse()
        ->and(class_exists(App\Modules\HR\Services\ContractManagement::class))->toBeFalse()
        ->and(class_exists(App\Modules\HR\Jobs\NotifyContractExpiry::class))->toBeFalse();
});

it('preserves archived contract records and grants when reinstalling attendance', function (): void {
    $employee = Employee::factory()->create();
    $contract = new class extends Model {};
    $contract->setTable('hr_contracts');
    $contract->forceFill(['employee_id' => $employee->id, 'number' => 'EXISTING-ARCHIVE', 'created_by' => $this->officer->id])->save();
    $original = $contract->fresh()->getAttributes();
    $permission = Permission::findOrCreate('hr.contracts.view', 'web');
    $this->officer->givePermissionTo($permission);

    app(AttendanceInstaller::class)->install(true);

    expect($contract->fresh()->getAttributes())->toBe($original)
        ->and($this->officer->fresh()->hasDirectPermission($permission))->toBeTrue();
    $this->assertDatabaseCount('hr_contract_types', 0);
    $this->get(route('hr.attendance.index'))->assertOk()->assertDontSee('/hr/contracts', false);
});
