<?php

namespace App\Jobs;

use App\services\ArcgisCsoWebhookChangeSyncService;

class SyncCsoArcgisWebhook
{
    public function __construct(public array $payload = []) {}

    /**
     * @return array<string, int>
     */
    public function handle(): array
    {
        return app(ArcgisCsoWebhookChangeSyncService::class)->sync($this->payload);
    }
}
