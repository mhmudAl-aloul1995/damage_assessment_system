<?php

namespace App\Console\Commands;

use App\Models\KoboRestSubmission;
use App\Modules\DamageAssessmentBorrowers\Services\KoboBorrowerSubmissionSyncService;
use App\Modules\Heks\Services\HeksKoboSubmissionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class SyncKoboRestSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kobo:sync-rest-submissions
        {--all : Re-sync every stored Kobo submission}
        {--service= : Only sync submissions for the given Kobo service name}
        {--borrower-name-field= : Kobo payload key to use as the borrower name}
        {--field-map= : JSON object mapping borrower fields to Kobo payload keys}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync stored Kobo REST submissions into borrower records';

    /**
     * Execute the console command.
     */
    public function handle(KoboBorrowerSubmissionSyncService $syncService, HeksKoboSubmissionSyncService $heksSyncService): int
    {
        $borrowerNameField = $this->option('borrower-name-field')
            ?: config('services.kobotoolbox.borrower_name_field');
        $fieldMap = $this->fieldMapOption();

        $query = KoboRestSubmission::query()
            ->orderBy('id');

        if (filled($this->option('service'))) {
            $query->where('service_name', (string) $this->option('service'));
        }

        if (! $this->option('all')) {
            $query->whereIn('sync_status', ['pending', 'queued', 'skipped', 'failed']);
        }

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById(100, function ($submissions) use ($syncService, $heksSyncService, $borrowerNameField, $fieldMap, &$synced, &$skipped, &$failed): void {
            foreach ($submissions as $submission) {
                try {
                    $isHeksSubmission = Str::startsWith($submission->service_name, 'heks-');
                    $sync = $isHeksSubmission
                        ? $heksSyncService->sync($submission)
                        : $syncService->sync($submission, $borrowerNameField, $fieldMap);

                    if ($sync === null) {
                        $skipped++;

                        continue;
                    }

                    $submission->forceFill([
                        'damage_assessment_borrower_id' => $isHeksSubmission
                            ? $submission->damage_assessment_borrower_id
                            : $sync['borrower']?->id,
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

    /**
     * @return array<string, mixed>
     */
    private function fieldMapOption(): array
    {
        $fieldMap = config('services.kobotoolbox.borrower_field_map', []);
        $option = $this->option('field-map');

        if (filled($option)) {
            $decoded = json_decode((string) $option, true);
            $fieldMap = is_array($decoded) ? $decoded : [];
        }

        return is_array($fieldMap) ? $fieldMap : [];
    }
}
