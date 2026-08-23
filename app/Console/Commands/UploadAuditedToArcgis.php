<?php

namespace App\Console\Commands;

use App\Services\ArcgisAuditedCacheService;
use App\Services\ArcgisAuditedUploadService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class UploadAuditedToArcgis extends Command
{
    protected $signature = 'arcgis:upload-audited
        {--buildings-limit= : Upload only the first N audited buildings and their housing units.}
        {--only= : Upload only buildings or units.}
        {--changed-since= : Upload only buildings or housing units with editdate or audit edits on or after this date/time.}
        {--only-audit-edits : With --changed-since, ignore editdate and sync only records changed in edit_assessments.}
        {--skip-counts : Start syncing immediately without counting candidates first.}
        {--without-attachments : Upload or update features without copying attachments.}
        {--attachments-only : Copy missing attachments for existing uploaded features only.}
        {--refresh-cache : Refresh audited cache tables before uploading.}';

    protected $description = 'Upload audited building and housing unit cache tables to ArcGIS and copy attachments.';

    public function handle(
        ArcgisAuditedUploadService $arcgisAuditedUploadService,
        ArcgisAuditedCacheService $cacheService,
    ): int {
        $startedAt = now();

        $this->newLine();
        $this->info('========================================');
        $this->info(' Uploading Audited ArcGIS Data');
        $this->info('========================================');
        $this->newLine();

        $this->line('Started at: '.$startedAt->format('Y-m-d H:i:s'));
        $this->line('Source service: '.config('services.arcgis.source_service'));
        $this->line('Target service: '.config('services.arcgis.target_service'));
        $this->newLine();

        try {
            $this->info('Processing...');
            $buildingsLimit = $this->option('buildings-limit');
            $only = $this->onlyOption();

            if ((bool) $this->option('refresh-cache')) {
                $this->info('Refreshing audited cache...');
                $cacheSummary = $cacheService->refresh(is_numeric($buildingsLimit) ? (int) $buildingsLimit : null);
                $this->line('Cached buildings: '.$cacheSummary['buildings_cached']);
                $this->line('Cached housing units: '.$cacheSummary['housing_units_cached']);
                $this->newLine();
            }

            $changedSince = $this->changedSinceOption();
            $summary = $arcgisAuditedUploadService->upload(
                is_numeric($buildingsLimit) ? (int) $buildingsLimit : null,
                (bool) $this->option('without-attachments'),
                (bool) $this->option('attachments-only'),
                $changedSince,
                (bool) $this->option('skip-counts'),
                (bool) $this->option('only-audit-edits'),
                $only,
            );
        } catch (\Throwable $e) {
            $this->error('Upload failed.');
            $this->error($e->getMessage());

            report($e);

            return self::FAILURE;
        }

        $finishedAt = now();
        $duration = $startedAt->diffInSeconds($finishedAt);

        $errors = (int) Arr::get($summary, 'errors', 0);

        $this->newLine();
        $this->info('========================================');
        $this->info(' Summary');
        $this->info('========================================');

        $rows = [];

        foreach ($summary as $key => $value) {
            $rows[] = [$key, is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)];
        }

        $rows[] = ['started_at', $startedAt->format('Y-m-d H:i:s')];
        $rows[] = ['finished_at', $finishedAt->format('Y-m-d H:i:s')];
        $rows[] = ['duration_seconds', (string) $duration];

        $this->table(['Metric', 'Value'], $rows);

        if ($errors > 0) {
            $this->error("Completed with {$errors} errors.");

            return self::FAILURE;
        }

        $this->info('Completed successfully.');

        return self::SUCCESS;
    }

    private function changedSinceOption(): ?CarbonImmutable
    {
        $changedSince = $this->option('changed-since');

        if (! is_string($changedSince) || trim($changedSince) === '') {
            return null;
        }

        return CarbonImmutable::parse($changedSince)->startOfSecond();
    }

    private function onlyOption(): ?string
    {
        $only = $this->option('only');

        if (! is_string($only) || trim($only) === '') {
            return null;
        }

        $only = trim($only);

        if (! in_array($only, ['buildings', 'units'], true)) {
            throw new \InvalidArgumentException('The --only option must be buildings or units.');
        }

        return $only;
    }
}
