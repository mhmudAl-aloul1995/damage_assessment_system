<?php

namespace App\Console\Commands;

use App\Services\ArcgisAuditedUploadService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class UploadAuditedToArcgis extends Command
{
    protected $signature = 'arcgis:upload-audited
        {--buildings-limit= : Upload only the first N audited buildings and their housing units.}
        {--changed-since= : Upload only buildings or housing units with editdate or audit edits on or after this date/time.}
        {--only-audit-edits : With --changed-since, ignore editdate and sync only records changed in edit_assessments.}
        {--skip-counts : Start syncing immediately without counting candidates first.}
        {--without-attachments : Upload or update features without copying attachments.}
        {--attachments-only : Copy missing attachments for existing uploaded features only.}';

    protected $description = 'Upload audited building and housing unit views to ArcGIS and copy attachments.';

    public function handle(ArcgisAuditedUploadService $arcgisAuditedUploadService): int
    {
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
            $changedSince = $this->changedSinceOption();
            $summary = $arcgisAuditedUploadService->upload(
                is_numeric($buildingsLimit) ? (int) $buildingsLimit : null,
                (bool) $this->option('without-attachments'),
                (bool) $this->option('attachments-only'),
                $changedSince,
                (bool) $this->option('skip-counts'),
                (bool) $this->option('only-audit-edits'),
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
}
