<?php

namespace App\Console\Commands;

use App\Services\AuditEditCacheRefresher;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class DeleteNullPendingAuditEdits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit-edits:delete-null-pending
        {target=all : all, building, or housing}
        {--dry-run : Report matching rows without deleting}
        {--chunk=500 : Number of rows to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete null audit edit rows for pending buildings and housing units, with rollback tracking.';

    /**
     * Execute the console command.
     */
    public function handle(AuditEditCacheRefresher $cacheRefresher): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        try {
            $targets = $this->resolveTargets((string) $this->argument('target'));
            $this->ensureRequiredTablesExist($targets);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = [];
        $matchingTotal = 0;

        foreach ($targets as $target) {
            $count = $this->matchingRowsQuery($target)->count();
            $matchingTotal += $count;
            $counts[] = [$target['label'], $count];
        }

        if ($dryRun || $matchingTotal === 0) {
            $this->components->info($dryRun ? 'Dry run complete.' : 'No matching edit rows found.');
            $this->table(['Target', 'Matching null pending edits'], $counts);

            return self::SUCCESS;
        }

        $batchId = DB::table('audit_edit_deletion_batches')->insertGetId([
            'target' => (string) $this->argument('target'),
            'deleted_count' => 0,
            'criteria' => json_encode([
                'field_value' => null,
                'status_column' => 'audit_status',
                'status_value' => 'Pending',
                'building_view' => 'warda_buildings',
                'housing_view' => 'warda_units',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deletedCount = 0;
        $deletedByTarget = [];

        foreach ($targets as $target) {
            $deletedByTarget[$target['label']] = 0;

            $this->matchingRowsQuery($target)
                ->orderBy('ea.id')
                ->chunkById($chunkSize, function ($edits) use (&$deletedCount, &$deletedByTarget, $target, $batchId, $cacheRefresher): void {
                    foreach ($edits as $edit) {
                        DB::transaction(function () use ($edit, &$deletedCount, &$deletedByTarget, $target, $batchId, $cacheRefresher): void {
                            $row = DB::table('edit_assessments')->where('id', $edit->id)->lockForUpdate()->first();

                            if (! $row || $row->field_value !== null) {
                                return;
                            }

                            DB::table('audit_edit_deletion_items')->insert([
                                'batch_id' => $batchId,
                                'edit_assessment_id' => $row->id,
                                'global_id' => $row->global_id,
                                'type' => $row->type,
                                'field_name' => $row->field_name,
                                'field_value' => $row->field_value,
                                'user_id' => $row->user_id,
                                'edit_created_at' => $row->created_at,
                                'edit_updated_at' => $row->updated_at,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            DB::table('edit_assessments')->where('id', $row->id)->delete();

                            $cacheRefresher->refresh($row->type, $row->global_id, $row->field_name);

                            $deletedCount++;
                            $deletedByTarget[$target['label']]++;
                        });
                    }
                }, 'ea.id', 'id');
        }

        DB::table('audit_edit_deletion_batches')
            ->where('id', $batchId)
            ->update([
                'deleted_count' => $deletedCount,
                'updated_at' => now(),
            ]);

        $this->components->info('Deletion complete. Use the batch id below if you need to restore.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Batch ID', $batchId],
                ['Rows deleted', $deletedCount],
            ],
        );
        $this->table(
            ['Target', 'Rows deleted'],
            collect($deletedByTarget)->map(fn (int $count, string $label): array => [$label, $count])->values()->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{type: string, view: string, label: string}>
     */
    private function resolveTargets(string $target): array
    {
        $availableTargets = [
            'building' => [
                'type' => 'building_table',
                'view' => 'warda_buildings',
                'label' => 'Buildings',
            ],
            'housing' => [
                'type' => 'housing_table',
                'view' => 'warda_units',
                'label' => 'Housing units',
            ],
        ];

        if ($target === 'all') {
            return array_values($availableTargets);
        }

        if (! array_key_exists($target, $availableTargets)) {
            throw new InvalidArgumentException('Target must be all, building, or housing.');
        }

        return [$availableTargets[$target]];
    }

    /**
     * @param  array<int, array{type: string, view: string, label: string}>  $targets
     */
    private function ensureRequiredTablesExist(array $targets): void
    {
        foreach (['edit_assessments', 'audit_edit_deletion_batches', 'audit_edit_deletion_items'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new InvalidArgumentException("Required table {$table} does not exist. Run migrations first.");
            }
        }

        foreach ($targets as $target) {
            if (! Schema::hasTable($target['view'])) {
                throw new InvalidArgumentException("Required view {$target['view']} does not exist.");
            }
        }
    }

    /**
     * @param  array{type: string, view: string, label: string}  $target
     */
    private function matchingRowsQuery(array $target): Builder
    {
        return DB::table('edit_assessments as ea')
            ->join($target['view'].' as pending_records', 'ea.global_id', '=', 'pending_records.globalid')
            ->where('ea.type', $target['type'])
            ->whereNull('ea.field_value')
            ->where('pending_records.audit_status', 'Pending')
            ->select('ea.*');
    }
}
