<?php

namespace App\Jobs;

use App\Models\KoboRestSubmission;
use App\Modules\Heks\Services\HeksKoboSubmissionSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncHeksKoboSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $submissionId) {}

    /**
     * Execute the job.
     */
    public function handle(HeksKoboSubmissionSyncService $syncService): void
    {
        $submission = KoboRestSubmission::query()->findOrFail($this->submissionId);

        $submission->forceFill([
            'sync_status' => 'processing',
            'sync_error' => null,
        ])->save();

        $sync = $syncService->sync($submission);

        $submission->forceFill([
            'sync_status' => $sync['status'] ?? 'skipped',
            'sync_error' => $sync['error'] ?? null,
            'synced_at' => ($sync['status'] ?? null) === 'synced' ? now() : null,
        ])->save();
    }
}
