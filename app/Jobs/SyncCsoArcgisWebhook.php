<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class SyncCsoArcgisWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public array $payload = []) {}

    public function handle(): void
    {
        foreach (['cso_surveys', 'cso_survey_organizations', 'cso_survey_units'] as $table) {
            Artisan::call('sync:arcgis-layers', [
                'table' => $table,
                '--force' => true,
            ]);
        }
    }
}
