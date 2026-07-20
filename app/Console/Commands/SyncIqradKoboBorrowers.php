<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncIqradKoboBorrowers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kobo:sync-iqrad-borrowers
        {--asset= : KoboToolbox asset UID. Defaults to KOBO_IQRAD_ASSET_UID}
        {--all : Re-sync every stored iqrad submission after fetching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch iqrad Kobo submissions and sync them into borrower records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $assetUid = (string) ($this->option('asset') ?: config('services.kobotoolbox.iqrad_asset_uid'));

        if ($assetUid === '') {
            $this->components->error('Kobo iqrad asset UID is missing. Set KOBO_IQRAD_ASSET_UID or pass --asset.');

            return self::FAILURE;
        }

        $fetchExitCode = Artisan::call('kobo:fetch-asset-submissions', [
            'asset_uid' => $assetUid,
            '--service' => 'iqrad',
        ]);
        $this->output->write(Artisan::output());

        if ($fetchExitCode !== self::SUCCESS) {
            return $fetchExitCode;
        }

        $syncArguments = [
            '--service' => 'iqrad',
        ];

        if ((bool) $this->option('all')) {
            $syncArguments['--all'] = true;
        }

        $syncExitCode = Artisan::call('kobo:sync-rest-submissions', $syncArguments);
        $this->output->write(Artisan::output());

        return $syncExitCode;
    }
}
