<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillHousingStatusHistoryTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:backfill-housing-status-history-types
        {--dry-run : Report what would be updated without changing data}
        {--chunk=500 : Number of history rows to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill empty housing status history types from current statuses, then user roles.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $counts = [
            'empty' => 0,
            'from_housing_statuses' => 0,
            'from_user_roles' => 0,
            'unresolved' => 0,
        ];

        DB::table('housing_status_histories')
            ->where(function ($query): void {
                $query->whereNull('type')
                    ->orWhere('type', '');
            })
            ->select(['id', 'housing_id', 'status_id', 'user_id'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($histories) use (&$counts, $dryRun): void {
                foreach ($histories as $history) {
                    $counts['empty']++;

                    $type = $this->typeFromHousingStatuses($history);
                    $source = 'from_housing_statuses';

                    if ($type === null) {
                        $type = $this->typeFromUserRole($history->user_id);
                        $source = 'from_user_roles';
                    }

                    if ($type === null) {
                        $counts['unresolved']++;

                        continue;
                    }

                    $counts[$source]++;

                    if (! $dryRun) {
                        DB::table('housing_status_histories')
                            ->where('id', $history->id)
                            ->update([
                                'type' => $type,
                                'updated_at' => now(),
                            ]);
                    }
                }
            }, 'id');

        $this->components->info(($dryRun ? 'Dry run complete.' : 'Backfill complete.'));
        $this->table(
            ['Metric', 'Count'],
            [
                ['Empty histories found', $counts['empty']],
                ['Resolved from housing_statuses', $counts['from_housing_statuses']],
                ['Resolved from user roles', $counts['from_user_roles']],
                ['Still unresolved', $counts['unresolved']],
            ],
        );

        return self::SUCCESS;
    }

    private function typeFromHousingStatuses(object $history): ?string
    {
        $types = DB::table('housing_statuses')
            ->where('housing_id', $history->housing_id)
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->when($history->status_id, function ($query) use ($history): void {
                $query->orderByRaw('CASE WHEN status_id = ? THEN 0 ELSE 1 END', [$history->status_id]);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->pluck('type')
            ->filter(fn ($type): bool => trim((string) $type) !== '')
            ->unique()
            ->values();

        if ($types->count() === 1) {
            return (string) $types->first();
        }

        $matchingType = DB::table('housing_statuses')
            ->where('housing_id', $history->housing_id)
            ->where('status_id', $history->status_id)
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('type');

        return is_string($matchingType) && trim($matchingType) !== ''
            ? $matchingType
            : null;
    }

    private function typeFromUserRole(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        $user = User::query()->with('roles')->find($userId);

        if (! $user) {
            return null;
        }

        if ($user->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor'])) {
            return 'QC/QA Engineer';
        }

        if ($user->hasRole('Legal Auditor')) {
            return 'Legal Auditor';
        }

        if ($user->hasRole('Database Officer')) {
            return 'Database Officer';
        }

        if ($user->hasRole('undp-Project Manager')) {
            return 'undp-Project Manager';
        }

        return null;
    }
}
