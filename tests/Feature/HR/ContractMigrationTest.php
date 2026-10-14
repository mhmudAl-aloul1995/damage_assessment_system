<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\HR\Models\ContractAlert;
use App\Modules\HR\Models\ContractDecision;
use App\Modules\HR\Models\ContractRevision;
use App\Modules\HR\Models\ContractType;
use App\Modules\HR\Models\EmploymentContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function hrContractMigration(): Migration
{
    return require base_path('app/Modules/HR/database/migrations/2026_10_13_152415_create_hr_contract_management_tables.php');
}

it('creates the contract schema on a fresh database', function (): void {
    $migration = hrContractMigration();
    $migration->down();
    $migration->up();

    expect(Schema::hasColumns('hr_contract_types', ['code', 'requires_end', 'reminder_days']))->toBeTrue()
        ->and(Schema::hasColumns('hr_contracts', ['employee_id', 'number', 'created_by']))->toBeTrue()
        ->and(Schema::hasColumns('hr_contract_revisions', ['request_key', 'effective_on', 'previous_approved_id']))->toBeTrue()
        ->and(Schema::hasColumns('hr_contract_approvals', ['revision_id', 'decision', 'reviewed_by']))->toBeTrue()
        ->and(Schema::hasColumns('hr_contract_alerts', ['recipient_id', 'threshold_days', 'expires_on']))->toBeTrue();
});

it('registers a previously unrecorded migration without changing existing contract records', function (): void {
    $type = ContractType::factory()->create();
    $contract = EmploymentContract::factory()->create();
    $reviewer = User::factory()->create();
    $revision = ContractRevision::query()->create([
        'contract_id' => $contract->id, 'contract_type_id' => $type->id,
        'request_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'existing revision'),
        'kind' => 'initial', 'effective_on' => '2026-01-01', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        'type_snapshot' => ['code' => $type->code], 'reason' => 'Existing contract agreement', 'created_by' => $contract->created_by,
    ]);
    $decision = ContractDecision::query()->create([
        'revision_id' => $revision->id, 'reviewed_by' => $reviewer->id, 'decision' => 'approved', 'reason' => 'Existing approval',
    ]);
    $alert = ContractAlert::query()->create([
        'revision_id' => $revision->id, 'recipient_id' => $reviewer->id, 'threshold_days' => 30,
        'expires_on' => '2026-12-31', 'created_at' => now(),
    ]);
    $records = collect([$type, $contract, $revision, $decision, $alert]);
    $original = $records->map(fn (Model $record): array => $record->fresh()->getAttributes())->all();
    $migrationName = '2026_10_13_152415_create_hr_contract_management_tables';
    $repository = app('migration.repository');
    $repository->delete((object) ['migration' => $migrationName]);

    $this->artisan('migrate', [
        '--path' => 'app/Modules/HR/database/migrations/'.$migrationName.'.php',
        '--no-interaction' => true, '--force' => true,
    ])->assertExitCode(0);

    expect($repository->getRan())->toContain($migrationName)
        ->and($records->map(fn (Model $record): array => $record->fresh()->getAttributes())->all())->toBe($original);
    foreach (['hr_contract_types', 'hr_contracts', 'hr_contract_revisions', 'hr_contract_approvals', 'hr_contract_alerts'] as $table) {
        $this->assertDatabaseCount($table, 1);
    }
});

it('resumes a partial installation and preserves the existing contract types', function (): void {
    $type = ContractType::factory()->create(['code' => 'EXISTING', 'reminder_days' => [30, 7]]);
    $original = $type->fresh()->getAttributes();
    foreach (['hr_contract_alerts', 'hr_contract_approvals', 'hr_contract_revisions', 'hr_contracts'] as $table) {
        Schema::drop($table);
    }

    $migration = hrContractMigration();
    $migration->up();
    $migration->up();

    expect($type->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('hr_contract_types', 1);
    foreach (['hr_contracts', 'hr_contract_revisions', 'hr_contract_approvals', 'hr_contract_alerts'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(EmploymentContract::factory()->create()->exists)->toBeTrue();
});

it('reports incompatible existing columns without replacing the existing table', function (): void {
    $type = ContractType::factory()->create(['code' => 'LEGACY', 'name' => 'Preserved legacy type']);
    Schema::table('hr_contract_types', function (Blueprint $table): void {
        $table->dropColumn('requires_end');
    });

    expect(fn () => hrContractMigration()->up())->toThrow(RuntimeException::class, 'requires_end');
    expect(Schema::hasColumn('hr_contract_types', 'requires_end'))->toBeFalse();
    $this->assertDatabaseHas('hr_contract_types', ['id' => $type->id, 'code' => 'LEGACY', 'name' => 'Preserved legacy type']);
});
