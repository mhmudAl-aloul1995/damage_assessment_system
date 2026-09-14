<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Artisan;

class SyncCsoArcgisWebhook
{
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
