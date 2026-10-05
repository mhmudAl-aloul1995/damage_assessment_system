<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncHeksKoboSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:kobo-sync
        {--service= : Sync only one HEKS service alias, for example heks-main}
        {--all : Re-sync every stored submission for the selected HEKS service(s)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch HEKS Kobo submissions and sync them into the HEKS module';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configuredServices = config('heks_kobo.services', []);
        $onlyService = trim((string) $this->option('service'));
        $syncedServices = 0;

        foreach ($configuredServices as $serviceConfig) {
            if (! is_array($serviceConfig)) {
                continue;
            }

            $service = (string) ($serviceConfig['aliases'][0] ?? '');
            $assetUid = (string) ($serviceConfig['asset_uid'] ?? '');

            if ($service === '') {
                continue;
            }

            if ($onlyService !== '' && $onlyService !== $service) {
                continue;
            }

            if ($assetUid === '') {
                $this->components->warn("Skipping {$service}: Kobo asset UID is not configured.");

                continue;
            }

            $fetchExitCode = Artisan::call('kobo:fetch-asset-submissions', [
                'asset_uid' => $assetUid,
                '--service' => $service,
            ]);
            $this->output->write(Artisan::output());

            if ($fetchExitCode !== self::SUCCESS) {
                return $fetchExitCode;
            }

            $syncArguments = [
                '--service' => $service,
            ];

            if ((bool) $this->option('all')) {
                $syncArguments['--all'] = true;
            }

            $syncExitCode = Artisan::call('kobo:sync-rest-submissions', $syncArguments);
            $this->output->write(Artisan::output());

            if ($syncExitCode !== self::SUCCESS) {
                return $syncExitCode;
            }

            $syncedServices++;
        }

        if ($syncedServices === 0) {
            $this->components->warn('No configured HEKS Kobo assets were available to sync.');
        }

        return self::SUCCESS;
    }
}
