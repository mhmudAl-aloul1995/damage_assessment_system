<?php

namespace App\Console\Commands;

use App\Exports\AuditEditDeletionPreviewExport;
use App\Services\AuditEditCacheRefresher;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

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
        {--export= : Export matching rows to an XLSX file. Leave empty to use the default storage pathh}
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

        if ($this->hasExportOption()) {
            $exportPath = $this->exportMatchingRows($targets);
            $this->components->info("Export created: {$exportPath}");
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
            if (! $this->relationExists($target['view'])) {
                throw new InvalidArgumentException("Required view {$target['view']} does not exist.");
            }
        }
    }

    private function relationExists(string $name): bool
    {
        if (Schema::hasTable($name)) {
            return true;
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return DB::table('information_schema.tables')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', $name)
                ->whereIn('table_type', ['BASE TABLE', 'VIEW'])
                ->exists();
        }

        try {
            DB::table($name)->limit(1)->exists();

            return true;
        } catch (Throwable) {
            return false;
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

    /**
     * @param  array<int, array{type: string, view: string, label: string}>  $targets
     */
    private function exportMatchingRows(array $targets): string
    {
        $rows = collect();

        foreach ($targets as $target) {
            $this->matchingRowsForExportQuery($target)
                ->orderBy('ea.id')
                ->chunkById(500, function ($matchingRows) use (&$rows, $target): void {
                    $rows = $rows->merge(
                        $matchingRows->map(fn (object $row): array => $this->formatExportRow($row, $target['label'])),
                    );
                }, 'ea.id', 'id');
        }

        $path = $this->exportPath();
        Excel::store(new AuditEditDeletionPreviewExport($rows), $path, 'local');

        return Storage::disk('local')->path($path);
    }

    /**
     * @param  array{type: string, view: string, label: string}  $target
     */
    private function matchingRowsForExportQuery(array $target): Builder
    {
        $sourceTable = $target['type'] === 'building_table' ? 'buildings' : 'housing_units';
        $parentGlobalIdSelect = $target['type'] === 'building_table'
            ? DB::raw('NULL as parentglobalid')
            : 'source_records.parentglobalid';

        return DB::table('edit_assessments as ea')
            ->join($target['view'].' as pending_records', 'ea.global_id', '=', 'pending_records.globalid')
            ->leftJoin($sourceTable.' as source_records', 'ea.global_id', '=', 'source_records.globalid')
            ->leftJoin('edit_assessments as previous_edit', function ($join): void {
                $join->on('previous_edit.id', '=', DB::raw('(
                    select max(previous_lookup.id)
                    from edit_assessments as previous_lookup
                    where previous_lookup.global_id = ea.global_id
                        and previous_lookup.type = ea.type
                        and previous_lookup.field_name = ea.field_name
                        and previous_lookup.id < ea.id
                )'));
            })
            ->leftJoin('edit_assessments as next_edit', function ($join): void {
                $join->on('next_edit.id', '=', DB::raw('(
                    select min(next_lookup.id)
                    from edit_assessments as next_lookup
                    where next_lookup.global_id = ea.global_id
                        and next_lookup.type = ea.type
                        and next_lookup.field_name = ea.field_name
                        and next_lookup.id > ea.id
                )'));
            })
            ->where('ea.type', $target['type'])
            ->whereNull('ea.field_value')
            ->where('pending_records.audit_status', 'Pending')
            ->select([
                'ea.id',
                'ea.type',
                'ea.global_id',
                'ea.field_name',
                'ea.field_value',
                'ea.created_at',
                'ea.updated_at',
                'pending_records.audit_status',
                'previous_edit.id as previous_edit_id',
                'previous_edit.field_value as previous_field_value',
                'previous_edit.created_at as previous_created_at',
                'next_edit.id as next_edit_id',
                'next_edit.field_value as next_field_value',
                'next_edit.created_at as next_created_at',
                'source_records.objectid',
                $parentGlobalIdSelect,
            ]);
    }

    private function formatExportRow(object $row, string $targetLabel): array
    {
        return [
            'target' => $targetLabel,
            'edit_assessment_id' => $row->id,
            'type' => $row->type,
            'global_id' => $row->global_id,
            'objectid' => $row->objectid,
            'parentglobalid' => $row->parentglobalid,
            'field_name' => $row->field_name,
            'deleted_field_value' => $row->field_value,
            'deleted_edit_created_at' => $row->created_at,
            'deleted_edit_updated_at' => $row->updated_at,
            'previous_edit_id' => $row->previous_edit_id,
            'previous_value' => $row->previous_field_value,
            'previous_edit_created_at' => $row->previous_created_at,
            'has_later_edit_for_same_field' => $row->next_edit_id === null ? 'No' : 'Yes',
            'next_edit_id' => $row->next_edit_id,
            'next_value' => $row->next_field_value,
            'next_edit_created_at' => $row->next_created_at,
            'current_audit_status' => $row->audit_status,
        ];
    }

    private function hasExportOption(): bool
    {
        return array_key_exists('export', $this->options())
            && $this->option('export') !== null;
    }

    private function exportPath(): string
    {
        $path = trim((string) $this->option('export'));

        if ($path === '') {
            return 'audit-edit-deletions/null-pending-audit-edits-'.now()->format('Y-m-d-His').'.xlsx';
        }

        return $path;
    }
}
