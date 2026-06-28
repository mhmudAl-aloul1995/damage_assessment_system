<?php

namespace App\Console\Commands;

use App\Models\KoboRestSubmission;
use App\Modules\DamageAssessmentBorrowers\Services\KoboBorrowerSubmissionSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncKoboRestSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kobo:sync-rest-submissions {--all : Re-sync every stored Kobo submission}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync stored Kobo REST submissions into borrower records';

    /**
     * Execute the console command.
     */
    public function handle(KoboBorrowerSubmissionSyncService $syncService): int
    {
        $query = KoboRestSubmission::query()
            ->orderBy('id');

        if (! $this->option('all')) {
            $query->whereIn('sync_status', ['pending', 'skipped', 'failed']);
        }

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById(100, function ($submissions) use ($syncService, &$synced, &$skipped, &$failed): void {
            foreach ($submissions as $submission) {
                try {
                    $sync = $syncService->sync($submission);

                    $submission->forceFill([
                        'damage_assessment_borrower_id' => $sync['borrower']?->id,
                        'sync_status' => $sync['status'],
                        'sync_error' => $sync['error'],
                        'synced_at' => $sync['status'] === 'synced' ? now() : null,
                    ])->save();

                    if ($sync['status'] === 'synced') {
                        $synced++;
                    } else {
                        $skipped++;
                    }
                } catch (Throwable $exception) {
                    $submission->forceFill([
                        'sync_status' => 'failed',
                        'sync_error' => $exception->getMessage(),
                        'synced_at' => null,
                    ])->save();

                    $failed++;
                }
            }
        });

        $this->components->info("Kobo submissions sync finished. Synced: {$synced}, skipped: {$skipped}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
