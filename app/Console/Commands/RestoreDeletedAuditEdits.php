<?php

namespace App\Console\Commands;

use App\Services\AuditEditCacheRefresher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestoreDeletedAuditEdits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit-edits:restore-deleted
        {batch : Batch id returned by audit-edits:delete-null-pending}
        {--dry-run : Report rows that would be restored without changing data}
        {--chunk=500 : Number of rows to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore audit edit rows deleted by audit-edits:delete-null-pending.';

    /**
     * Execute the console command.
     */
    public function handle(AuditEditCacheRefresher $cacheRefresher): int
    {
        foreach (['edit_assessments', 'audit_edit_deletion_batches', 'audit_edit_deletion_items'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->components->error("Required table {$table} does not exist. Run migrations first.");

                return self::FAILURE;
            }
        }

        $batchId = (int) $this->argument('batch');
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $batch = DB::table('audit_edit_deletion_batches')->where('id', $batchId)->first();

        if (! $batch) {
            $this->components->error("Batch {$batchId} was not found.");

            return self::FAILURE;
        }

        $query = DB::table('audit_edit_deletion_items')
            ->where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->orderBy('id');

        $trackedCount = $query->count();

        if ($dryRun) {
            $this->components->info('Dry run complete.');
            $this->table(['Metric', 'Count'], [
                ['Rows available to restore', $trackedCount],
            ]);

            return self::SUCCESS;
        }

        $counts = [
            'tracked' => 0,
            'restored' => 0,
            'already_present' => 0,
        ];

        $query->chunkById($chunkSize, function ($items) use (&$counts, $cacheRefresher): void {
            foreach ($items as $item) {
                $counts['tracked']++;

                DB::transaction(function () use ($item, &$counts, $cacheRefresher): void {
                    if (DB::table('edit_assessments')->where('id', $item->edit_assessment_id)->exists()) {
                        $counts['already_present']++;
                    } else {
                        DB::table('edit_assessments')->insert([
                            'id' => $item->edit_assessment_id,
                            'global_id' => $item->global_id,
                            'type' => $item->type,
                            'field_name' => $item->field_name,
                            'field_value' => $item->field_value,
                            'user_id' => $item->user_id,
                            'created_at' => $item->edit_created_at,
                            'updated_at' => $item->edit_updated_at,
                        ]);

                        $counts['restored']++;
                    }

                    DB::table('audit_edit_deletion_items')
                        ->where('id', $item->id)
                        ->update([
                            'restored_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $cacheRefresher->refresh($item->type, $item->global_id, $item->field_name);
                });
            }
        }, 'id');

        if ($trackedCount === $counts['tracked']) {
            DB::table('audit_edit_deletion_batches')
                ->where('id', $batchId)
                ->update([
                    'restored_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $this->components->info('Restore complete.');
        $this->table(['Metric', 'Count'], [
            ['Tracked rows', $counts['tracked']],
            ['Rows restored', $counts['restored']],
            ['Already present', $counts['already_present']],
        ]);

        return self::SUCCESS;
    }
}
