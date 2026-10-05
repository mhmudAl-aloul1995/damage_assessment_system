<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DedupeStatusHistories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:dedupe-status-histories
        {target=all : all, building, or housing}
        {--dry-run : Report duplicates without deleting data}
        {--rollback : Restore rows deleted by this command}
        {--chunk=500 : Number of duplicate rows to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate building and housing status history rows while keeping the oldest id.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rollback = (bool) $this->option('rollback');
        $chunkSize = max(1, (int) $this->option('chunk'));

        if ($dryRun && $rollback) {
            $this->components->error('Use either --dry-run or --rollback, not both.');

            return self::FAILURE;
        }

        try {
            $targets = $this->resolveTargets((string) $this->argument('target'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($rollback) {
            return $this->rollback($targets, $chunkSize);
        }

        return $this->dedupe($targets, $dryRun, $chunkSize);
    }

    /**
     * @return array<int, array{table: string, entity_column: string, label: string}>
     */
    private function resolveTargets(string $target): array
    {
        $availableTargets = [
            'building' => [
                'table' => 'building_status_histories',
                'entity_column' => 'building_id',
                'label' => 'Building histories',
            ],
            'housing' => [
                'table' => 'housing_status_histories',
                'entity_column' => 'housing_id',
                'label' => 'Housing histories',
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
     * @param  array<int, array{table: string, entity_column: string, label: string}>  $targets
     */
    private function dedupe(array $targets, bool $dryRun, int $chunkSize): int
    {
        $rows = [];

        foreach ($targets as $target) {
            $duplicateGroups = $this->duplicateGroupsQuery($target)->get();
            $duplicateRows = (int) $duplicateGroups->sum(fn ($group): int => (int) $group->duplicate_count - 1);
            $deletedRows = 0;

            if (! $dryRun && $duplicateRows > 0) {
                $this->duplicateRowsQuery($target)
                    ->orderBy('duplicate_rows.id')
                    ->chunkById($chunkSize, function ($duplicates) use (&$deletedRows, $target): void {
                        foreach ($duplicates as $duplicate) {
                            DB::transaction(function () use ($duplicate, &$deletedRows, $target): void {
                                $row = DB::table($target['table'])->where('id', $duplicate->id)->first();

                                if (! $row) {
                                    return;
                                }

                                DB::table('status_history_deduplications')->updateOrInsert(
                                    [
                                        'source_table' => $target['table'],
                                        'history_id' => $row->id,
                                    ],
                                    [
                                        'payload' => json_encode($row, JSON_THROW_ON_ERROR),
                                        'restored_at' => null,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ],
                                );

                                DB::table($target['table'])->where('id', $row->id)->delete();

                                $deletedRows++;
                            });
                        }
                    }, 'duplicate_rows.id', 'id');
            }

            $rows[] = [$target['label'], $duplicateGroups->count(), $duplicateRows, $deletedRows];
        }

        $this->components->info($dryRun ? 'Dry run complete.' : 'Deduplication complete.');
        $this->table(['Target', 'Duplicate groups', 'Duplicate rows', 'Rows deleted'], $rows);

        return self::SUCCESS;
    }

    /**
     * @param  array{table: string, entity_column: string, label: string}  $target
     */
    private function duplicateGroupsQuery(array $target): Builder
    {
        return DB::table($target['table'])
            ->select([
                $target['entity_column'],
                'status_id',
                'type',
                'notes',
            ])
            ->selectRaw('MIN(id) as keep_id')
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy($target['entity_column'], 'status_id', 'type', 'notes')
            ->havingRaw('COUNT(*) > 1');
    }

    /**
     * @param  array{table: string, entity_column: string, label: string}  $target
     */
    private function duplicateRowsQuery(array $target): Builder
    {
        $groups = $this->duplicateGroupsQuery($target);

        return DB::table($target['table'].' as duplicate_rows')
            ->joinSub($groups, 'duplicate_groups', function ($join) use ($target): void {
                $join->on('duplicate_groups.'.$target['entity_column'], '=', 'duplicate_rows.'.$target['entity_column'])
                    ->on('duplicate_groups.status_id', '=', 'duplicate_rows.status_id')
                    ->on('duplicate_groups.type', '=', 'duplicate_rows.type')
                    ->where(function ($join): void {
                        $join->on('duplicate_groups.notes', '=', 'duplicate_rows.notes')
                            ->orWhere(function ($join): void {
                                $join->whereNull('duplicate_groups.notes')
                                    ->whereNull('duplicate_rows.notes');
                            });
                    });
            })
            ->whereColumn('duplicate_rows.id', '!=', 'duplicate_groups.keep_id')
            ->select('duplicate_rows.*');
    }

    /**
     * @param  array<int, array{table: string, entity_column: string, label: string}>  $targets
     */
    private function rollback(array $targets, int $chunkSize): int
    {
        $rows = [];
        $targetTables = collect($targets)->pluck('table')->all();

        foreach ($targets as $target) {
            $counts = [
                'tracked' => 0,
                'restored' => 0,
                'already_present' => 0,
            ];

            DB::table('status_history_deduplications')
                ->where('source_table', $target['table'])
                ->whereNull('restored_at')
                ->select(['id', 'source_table', 'history_id', 'payload'])
                ->chunkById($chunkSize, function ($deduplications) use (&$counts, $targetTables): void {
                    foreach ($deduplications as $deduplication) {
                        $counts['tracked']++;

                        DB::transaction(function () use ($deduplication, &$counts, $targetTables): void {
                            if (! in_array($deduplication->source_table, $targetTables, true)) {
                                return;
                            }

                            if (DB::table($deduplication->source_table)->where('id', $deduplication->history_id)->exists()) {
                                $counts['already_present']++;
                            } else {
                                $payload = json_decode($deduplication->payload, true, 512, JSON_THROW_ON_ERROR);

                                DB::table($deduplication->source_table)->insert($payload);

                                $counts['restored']++;
                            }

                            DB::table('status_history_deduplications')
                                ->where('id', $deduplication->id)
                                ->update([
                                    'restored_at' => now(),
                                    'updated_at' => now(),
                                ]);
                        });
                    }
                }, 'id');

            $rows[] = [$target['label'], $counts['tracked'], $counts['restored'], $counts['already_present']];
        }

        $this->components->info('Rollback complete.');
        $this->table(['Target', 'Tracked deletions', 'Rows restored', 'Already present'], $rows);

        return self::SUCCESS;
    }
}
