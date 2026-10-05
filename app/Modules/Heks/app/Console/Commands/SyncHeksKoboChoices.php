<?php

namespace App\Console\Commands;

use App\Modules\Heks\Services\HeksKoboChoiceSyncService;
use App\Modules\Heks\Services\HeksKoboServiceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class SyncHeksKoboChoices extends Command
{
    protected $signature = 'heks:kobo-choices:sync
        {service : HEKS service name, for example heks-main}
        {asset? : KoboToolbox asset UID. If omitted, the configured asset for the service is used}
        {--from-api : Read the deployed Kobo form definition from Kobo API}
        {--xlsform= : Reserved for XLSForm file input}
        {--dry-run : Report what would be synced without saving}
        {--force : Continue even when no choices are found}';

    protected $description = 'Sync HEKS Kobo select_one and select_multiple choices into heks_kobo_choices';

    public function handle(HeksKoboServiceRegistry $services, HeksKoboChoiceSyncService $choiceSyncService): int
    {
        $service = (string) $this->argument('service');
        $canonicalService = $services->canonical($service);

        if ($canonicalService === null) {
            $this->components->error('Unsupported HEKS Kobo service.');

            return self::FAILURE;
        }

        if ($this->option('xlsform')) {
            $this->components->error('XLSForm input is not enabled in this build. Use --from-api or omit it.');

            return self::FAILURE;
        }

        $asset = (string) ($this->argument('asset') ?: config("heks_kobo.services.{$canonicalService}.asset_uid", ''));
        $token = (string) config('services.kobotoolbox.token', '');
        $mappingService = $this->mappingServiceName($service, $canonicalService);

        if ($asset === '' || $token === '') {
            $this->components->error('Kobo asset UID and KOBOTOOLBOX_TOKEN are required.');

            return self::FAILURE;
        }

        $response = Http::timeout((int) config('services.kobotoolbox.timeout', 60))
            ->withHeader('Authorization', "Token {$token}")
            ->acceptJson()
            ->get("https://kf.kobotoolbox.org/api/v2/assets/{$asset}/?format=json");

        if (! $response->successful()) {
            $this->components->error("Kobo form request failed with status {$response->status()}.");

            return self::FAILURE;
        }

        $body = $response->json();
        $survey = Arr::get($body, 'content.survey', []);
        $choices = Arr::get($body, 'content.choices', []);

        if (! is_array($survey) || ! is_array($choices) || ($choices === [] && ! $this->option('force'))) {
            $this->components->error('Kobo response did not include usable survey/choices rows.');

            return self::FAILURE;
        }

        $stats = $choiceSyncService->sync(
            $mappingService,
            $survey,
            $choices,
            is_string($body['version_id'] ?? null) ? $body['version_id'] : null,
            (bool) $this->option('dry-run')
        );

        $this->components->info("HEKS Kobo choices synced. Select one: {$stats['select_one']}, select multiple: {$stats['select_multiple']}, choices: {$stats['choices']}, inactive: {$stats['inactive']}.");

        return self::SUCCESS;
    }

    private function mappingServiceName(string $service, string $canonicalService): string
    {
        if (str_contains($service, '-')) {
            return $service;
        }

        $aliases = (array) config("heks_kobo.services.{$canonicalService}.aliases", []);

        return is_string($aliases[0] ?? null) ? $aliases[0] : $canonicalService;
    }
}
