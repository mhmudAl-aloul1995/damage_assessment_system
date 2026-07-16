<?php

namespace App\Console\Commands;

use App\Models\KoboRestSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FetchKoboAssetSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kobo:fetch-asset-submissions
        {asset_uid : KoboToolbox asset UID}
        {--service=iqrad : Local service name to store submissions under}
        {--base-url=https://kf.kobotoolbox.org : KoboToolbox KPI server base URL}
        {--token= : KoboToolbox API token. Defaults to KOBOTOOLBOX_TOKEN}
        {--page-size=1000 : Number of submissions to request per page}
        {--dry-run : Fetch and count without writing records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch KoboToolbox asset submissions into kobo_rest_submissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $assetUid = (string) $this->argument('asset_uid');
        $service = (string) $this->option('service');
        $baseUrl = rtrim((string) $this->option('base-url'), '/');
        $token = (string) ($this->option('token') ?: config('services.kobotoolbox.token'));
        $pageSize = max(1, (int) $this->option('page-size'));

        if ($token === '') {
            $this->components->error('KoboToolbox token is missing. Set KOBOTOOLBOX_TOKEN or pass --token.');

            return self::FAILURE;
        }

        $url = "{$baseUrl}/api/v2/assets/{$assetUid}/data/";
        $fetched = 0;
        $stored = 0;

        while ($url !== '') {
            $response = Http::withToken($token, 'Token')
                ->acceptJson()
                ->timeout((int) config('services.kobotoolbox.timeout', 60))
                ->get($url, [
                    'format' => 'json',
                    'limit' => $pageSize,
                ]);

            if (! $response->successful()) {
                $this->components->error("Kobo API request failed ({$response->status()}): {$response->body()}");

                return self::FAILURE;
            }

            $payload = $response->json();
            $results = $this->submissionResults($payload);
            $fetched += count($results);

            if (! (bool) $this->option('dry-run')) {
                foreach ($results as $submission) {
                    $submissionUuid = $this->submissionUuid($submission);

                    KoboRestSubmission::query()->updateOrCreate(
                        [
                            'service_name' => $service,
                            'submission_uuid' => $submissionUuid,
                        ],
                        [
                            'payload' => $submission,
                            'received_at' => now(),
                            'sync_status' => 'pending',
                            'sync_error' => null,
                        ],
                    );

                    $stored++;
                }
            }

            $next = Arr::get($payload, 'next');
            $url = is_string($next) && $next !== '' ? $next : '';
        }

        $this->table(['Indicator', 'Count'], [
            ['Fetched submissions', $fetched],
            [(bool) $this->option('dry-run') ? 'Would store' : 'Stored submissions', (bool) $this->option('dry-run') ? $fetched : $stored],
        ]);

        $this->components->info('Kobo asset fetch completed.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function submissionResults(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $results = Arr::get($payload, 'results');

        if (is_array($results)) {
            return array_values(array_filter($results, 'is_array'));
        }

        return array_is_list($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    private function submissionUuid(array $submission): string
    {
        $uuid = Arr::get($submission, '_uuid')
            ?? Arr::get($submission, 'meta/instanceID')
            ?? Arr::get($submission, '_id');

        return (string) ($uuid ?: Str::uuid());
    }
}
