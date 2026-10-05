<?php

namespace App\Console\Commands;

use App\Models\KoboRestSubmission;
use App\Modules\Heks\Services\HeksKoboSubmissionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class BackfillHeksKoboSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:kobo-backfill
        {service : HEKS service name, for example heks-main, heks-followups, heks-boq, or heks-followup-boq}
        {asset : KoboToolbox asset UID}
        {--limit= : Maximum submissions to import}
        {--since= : Import submissions submitted on or after this date}
        {--dry-run : Fetch and count submissions without saving them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill old HEKS KoboToolbox submissions into the local HEKS module';

    /**
     * Execute the console command.
     */
    public function handle(HeksKoboSubmissionSyncService $syncService): int
    {
        $service = (string) $this->argument('service');
        $asset = (string) $this->argument('asset');
        $token = (string) config('services.kobotoolbox.token', '');

        if (! Str::startsWith($service, 'heks-')) {
            $this->components->error('The service argument must start with heks-.');

            return self::FAILURE;
        }

        if ($token === '') {
            $this->components->error('KOBOTOOLBOX_TOKEN is not configured.');

            return self::FAILURE;
        }

        $limit = $this->integerOption('limit');
        $url = "https://kf.kobotoolbox.org/api/v2/assets/{$asset}/data/?format=json";
        $imported = 0;
        $synced = 0;
        $skipped = 0;
        $failed = 0;

        while ($url !== null) {
            $response = Http::timeout((int) config('services.kobotoolbox.timeout', 60))
                ->withHeader('Authorization', "Token {$token}")
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                $this->components->error("Kobo request failed with status {$response->status()}.");

                if ($response->status() === 404) {
                    $this->components->warn('Check that the asset UID belongs to the same Kobo account as KOBOTOOLBOX_TOKEN, or share the project with that account.');
                }

                return self::FAILURE;
            }

            $body = $response->json();
            $results = Arr::get($body, 'results', []);

            if (! is_array($results)) {
                $this->components->error('Kobo response did not include a results array.');

                return self::FAILURE;
            }

            foreach ($results as $payload) {
                if (! is_array($payload)) {
                    continue;
                }

                if (! $this->passesSinceFilter($payload)) {
                    continue;
                }

                if ($this->option('dry-run')) {
                    $imported++;
                } else {
                    [$status, $submission] = $this->storeAndSync($payload, $service, $syncService);
                    $imported++;

                    if ($status === 'synced') {
                        $synced++;
                    } elseif ($status === 'failed') {
                        $failed++;
                    } else {
                        $skipped++;
                    }

                    $this->line("#{$submission->id} {$submission->submission_uuid} {$status}");
                }

                if ($limit !== null && $imported >= $limit) {
                    $this->components->info($this->summary($imported, $synced, $skipped, $failed));

                    return self::SUCCESS;
                }
            }

            $next = Arr::get($body, 'next');
            $url = is_string($next) && $next !== '' ? $next : null;
        }

        $this->components->info($this->summary($imported, $synced, $skipped, $failed));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: KoboRestSubmission}
     */
    private function storeAndSync(array $payload, string $service, HeksKoboSubmissionSyncService $syncService): array
    {
        $submissionUuid = Arr::get($payload, '_uuid') ?? Arr::get($payload, 'meta/instanceID');

        $submission = filled($submissionUuid)
            ? KoboRestSubmission::query()->updateOrCreate(
                ['submission_uuid' => $submissionUuid],
                [
                    'service_name' => $service,
                    'payload' => $payload,
                    'received_at' => now(),
                ]
            )
            : KoboRestSubmission::query()->create([
                'service_name' => $service,
                'payload' => $payload,
                'received_at' => now(),
            ]);

        try {
            $sync = $syncService->sync($submission);
            $status = $sync['status'] ?? 'skipped';

            $submission->forceFill([
                'sync_status' => $status,
                'sync_error' => $sync['error'] ?? null,
                'synced_at' => $status === 'synced' ? now() : null,
            ])->save();
        } catch (Throwable $exception) {
            $status = 'failed';

            $submission->forceFill([
                'sync_status' => 'failed',
                'sync_error' => $exception->getMessage(),
                'synced_at' => null,
            ])->save();
        }

        return [$status, $submission];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function passesSinceFilter(array $payload): bool
    {
        $since = $this->option('since');

        if (! filled($since)) {
            return true;
        }

        $submittedAt = Arr::get($payload, '_submission_time') ?? Arr::get($payload, 'end') ?? Arr::get($payload, 'today');

        if (! filled($submittedAt)) {
            return true;
        }

        return strtotime((string) $submittedAt) >= strtotime((string) $since);
    }

    private function integerOption(string $key): ?int
    {
        $value = $this->option($key);

        if (! filled($value)) {
            return null;
        }

        return max(1, (int) $value);
    }

    private function summary(int $imported, int $synced, int $skipped, int $failed): string
    {
        if ($this->option('dry-run')) {
            return "Kobo dry run finished. Matched submissions: {$imported}.";
        }

        return "HEKS Kobo backfill finished. Imported: {$imported}, synced: {$synced}, skipped: {$skipped}, failed: {$failed}.";
    }
}
