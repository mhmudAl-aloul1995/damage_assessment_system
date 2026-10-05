<?php

namespace App\Console\Commands;

use App\services\ArcgisAuditedCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshAuditedArcgisCache extends Command
{
    protected $signature = 'arcgis:refresh-audited-cache
        {--buildings-limit= : Refresh only the first N buildings and their housing units.}';

    protected $description = 'Refresh audited ArcGIS cache tables from local buildings, housing units, and edit assessments.';

    public function handle(ArcgisAuditedCacheService $cacheService): int
    {
        $startedAt = now();
        $buildingsLimit = $this->option('buildings-limit');

        $this->info('Refreshing audited ArcGIS cache...');

        $summary = $cacheService->refresh(
            is_numeric($buildingsLimit) ? (int) $buildingsLimit : null
        );

        Cache::forever('damage_dashboard.stats_version', now()->timestamp);

        $this->table(['Metric', 'Value'], [
            ['buildings_cached', (string) $summary['buildings_cached']],
            ['housing_units_cached', (string) $summary['housing_units_cached']],
            ['duration_seconds', (string) $startedAt->diffInSeconds(now())],
        ]);

        $this->info('Audited ArcGIS cache refreshed successfully.');

        return self::SUCCESS;
    }
}
