<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\HR\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function hrArchivedContractRecord(string $table, array $attributes): Model
{
    $record = new class extends Model
    {
        public $timestamps = false;
    };
    $record->setTable($table);
    $record->forceFill($attributes)->save();

    return $record;
}

function hrArchivedContractType(array $attributes = []): Model
{
    return hrArchivedContractRecord('hr_contract_types', array_replace([
        'code' => 'ARCHIVE-'.Str::random(12), 'name' => 'Archived contract type',
        'reminder_days' => json_encode([30, 7]),
    ], $attributes));
}

function hrArchivedContract(): Model
{
    return hrArchivedContractRecord('hr_contracts', [
        'employee_id' => Employee::factory()->create()->id,
        'number' => 'ARCHIVE-'.Str::random(12), 'created_by' => User::factory()->create()->id,
    ]);
}

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
    $type = hrArchivedContractType();
    $contract = hrArchivedContract();
    $reviewer = User::factory()->create();
    $revision = hrArchivedContractRecord('hr_contract_revisions', [
        'contract_id' => $contract->id, 'contract_type_id' => $type->id,
        'request_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'existing revision'),
        'kind' => 'initial', 'effective_on' => '2026-01-01', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        'type_snapshot' => json_encode(['code' => $type->code]), 'reason' => 'Existing contract agreement', 'created_by' => $contract->created_by,
    ]);
    $decision = hrArchivedContractRecord('hr_contract_approvals', [
        'revision_id' => $revision->id, 'reviewed_by' => $reviewer->id, 'decision' => 'approved', 'reason' => 'Existing approval',
    ]);
    $alert = hrArchivedContractRecord('hr_contract_alerts', [
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
    $type = hrArchivedContractType(['code' => 'EXISTING']);
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
    expect(hrArchivedContract()->exists)->toBeTrue();
});

it('reports incompatible existing columns without replacing the existing table', function (): void {
    $type = hrArchivedContractType(['code' => 'LEGACY', 'name' => 'Preserved legacy type']);
    Schema::table('hr_contract_types', function (Blueprint $table): void {
        $table->dropColumn('requires_end');
    });

    expect(fn () => hrContractMigration()->up())->toThrow(RuntimeException::class, 'requires_end');
    expect(Schema::hasColumn('hr_contract_types', 'requires_end'))->toBeFalse();
    $this->assertDatabaseHas('hr_contract_types', ['id' => $type->id, 'code' => 'LEGACY', 'name' => 'Preserved legacy type']);
});
