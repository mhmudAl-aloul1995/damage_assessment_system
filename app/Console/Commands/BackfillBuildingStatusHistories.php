<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BackfillBuildingStatusHistories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:backfill-building-status-histories
        {--dry-run : Report what would be inserted without changing data}
        {--rollback : Delete histories inserted by this command}
        {--chunk=500 : Number of status rows to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing building status histories and track inserted rows for rollback.';

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

        if ($rollback) {
            return $this->rollback($chunkSize);
        }

        return $this->backfill($dryRun, $chunkSize);
    }

    private function backfill(bool $dryRun, int $chunkSize): int
    {
        $counts = [
            'missing' => 0,
            'inserted' => 0,
        ];

        $this->missingBuildingStatusesQuery()
            ->orderBy('bs.id')
            ->chunkById($chunkSize, function ($statuses) use (&$counts, $dryRun): void {
                foreach ($statuses as $status) {
                    $counts['missing']++;

                    if ($dryRun) {
                        continue;
                    }

                    DB::transaction(function () use ($status, &$counts): void {
                        if (! $this->isBuildingStatusStillMissing($status)) {
                            return;
                        }

                        $historyId = DB::table('building_status_histories')->insertGetId([
                            'building_id' => $status->building_id,
                            'status_id' => $status->status_id,
                            'user_id' => $status->user_id,
                            'notes' => $status->notes,
                            'type' => $status->type,
                            'created_at' => $status->created_at,
                            'updated_at' => $status->updated_at,
                        ]);

                        DB::table('building_status_history_backfills')->insert([
                            'building_status_id' => $status->id,
                            'building_status_history_id' => $historyId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $counts['inserted']++;
                    });
                }
            }, 'bs.id', 'id');

        $this->components->info($dryRun ? 'Dry run complete.' : 'Backfill complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Missing histories found', $counts['missing']],
                ['Histories inserted', $counts['inserted']],
            ],
        );

        return self::SUCCESS;
    }

    private function rollback(int $chunkSize): int
    {
        $counts = [
            'tracked' => 0,
            'deleted' => 0,
            'already_missing' => 0,
        ];

        DB::table('building_status_history_backfills')
            ->whereNull('rolled_back_at')
            ->select(['id', 'building_status_history_id'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($backfills) use (&$counts): void {
                foreach ($backfills as $backfill) {
                    $counts['tracked']++;

                    DB::transaction(function () use ($backfill, &$counts): void {
                        $deleted = DB::table('building_status_histories')
                            ->where('id', $backfill->building_status_history_id)
                            ->delete();

                        DB::table('building_status_history_backfills')
                            ->where('id', $backfill->id)
                            ->update([
                                'rolled_back_at' => now(),
                                'updated_at' => now(),
                            ]);

                        if ($deleted > 0) {
                            $counts['deleted']++;

                            return;
                        }

                        $counts['already_missing']++;
                    });
                }
            }, 'id');

        $this->components->info('Rollback complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Tracked backfills found', $counts['tracked']],
                ['Histories deleted', $counts['deleted']],
                ['Already missing', $counts['already_missing']],
            ],
        );

        return self::SUCCESS;
    }

    private function missingBuildingStatusesQuery(): Builder
    {
        return DB::table('building_statuses as bs')
            ->select([
                'bs.id',
                'bs.building_id',
                'bs.status_id',
                'bs.user_id',
                'bs.notes',
                'bs.type',
                'bs.created_at',
                'bs.updated_at',
            ])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('building_status_histories as bsh')
                    ->whereColumn('bsh.building_id', 'bs.building_id')
                    ->whereColumn('bsh.status_id', 'bs.status_id')
                    ->whereColumn('bsh.type', 'bs.type')
                    ->where(function ($query): void {
                        $query->whereColumn('bsh.created_at', 'bs.created_at')
                            ->orWhere(function ($query): void {
                                $query->whereNull('bsh.created_at')
                                    ->whereNull('bs.created_at');
                            });
                    });
            });
    }

    private function isBuildingStatusStillMissing(object $status): bool
    {
        return ! DB::table('building_status_histories')
            ->where('building_id', $status->building_id)
            ->where('status_id', $status->status_id)
            ->where('type', $status->type)
            ->where(function ($query) use ($status): void {
                if ($status->created_at === null) {
                    $query->whereNull('created_at');

                    return;
                }

                $query->where('created_at', $status->created_at);
            })
            ->exists();
    }
}
