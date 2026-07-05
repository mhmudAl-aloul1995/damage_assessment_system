<?php

namespace App\Console\Commands;

use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;
use Illuminate\Console\Command;

class ImportHeksFollowUpBoqs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:import-followup-boqs
        {--code= : Import BOQ files for one beneficiary code}
        {--limit= : Maximum follow-ups to process}
        {--force : Re-import follow-ups that already have BOQ items}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import HEKS follow-up BOQ Excel attachments into database BOQ items';

    /**
     * Execute the console command.
     */
    public function handle(HeksSpreadsheetImportService $importer): int
    {
        $query = HeksFollowUp::query()
            ->where(function ($query): void {
                $query->whereNotNull('boq_url')
                    ->orWhereNotNull('boq_filename');
            })
            ->orderBy('id');

        if (filled($this->option('code'))) {
            $query->where('code', (string) $this->option('code'));
        }

        if (! $this->option('force')) {
            $query->doesntHave('boqItems');
        }

        $limit = filled($this->option('limit')) ? max(1, (int) $this->option('limit')) : null;
        $processed = 0;
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById(25, function ($followUps) use ($importer, $limit, &$processed, &$imported, &$skipped, &$failed): bool {
            foreach ($followUps as $followUp) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $processed++;
                $summary = $importer->importFollowUpBoq($followUp);

                if ($summary === null) {
                    $skipped++;
                    $this->line("#{$followUp->id} {$followUp->code} visit {$followUp->visit_number}: skipped");

                    continue;
                }

                if (($summary['imported'] ?? false) === true && ((int) ($summary['imported_rows'] ?? 0)) > 0) {
                    $imported++;
                    $this->line("#{$followUp->id} {$followUp->code} visit {$followUp->visit_number}: imported {$summary['imported_rows']} rows");

                    continue;
                }

                $failed++;
                $error = (string) ($summary['error'] ?? 'No BOQ rows were imported.');
                $this->warn("#{$followUp->id} {$followUp->code} visit {$followUp->visit_number}: failed - {$error}");
            }

            return true;
        });

        $this->components->info("HEKS follow-up BOQ import finished. Processed: {$processed}, imported: {$imported}, skipped: {$skipped}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
