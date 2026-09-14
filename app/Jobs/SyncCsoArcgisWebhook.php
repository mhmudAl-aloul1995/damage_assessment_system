<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class SyncCsoArcgisWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public array $payload = []) {}

    public function uniqueId(): string
    {
        return 'arcgis-cso-webhook-sync';
    }

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
