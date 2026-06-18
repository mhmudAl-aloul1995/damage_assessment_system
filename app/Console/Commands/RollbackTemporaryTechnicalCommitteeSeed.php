<?php

namespace App\Console\Commands;

use App\services\TemporaryTechnicalCommitteeRollbackService;
use Illuminate\Console\Command;

class RollbackTemporaryTechnicalCommitteeSeed extends Command
{
    protected $signature = 'committee:rollback-temporary-technical-seed
        {--force : Apply the rollback. Without this option the command only reports what would happen}
        {--delete-decisions : Delete referenced committee decisions instead of resetting them to pending signatures}';

    protected $description = 'Rollback the temporary technical committee seed changes using archive snapshots when available';

    public function __construct(private readonly TemporaryTechnicalCommitteeRollbackService $rollbackService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = ! (bool) $this->option('force');
        $deleteDecisions = (bool) $this->option('delete-decisions');

        $summary = $this->rollbackService->rollback($dryRun, $deleteDecisions);

        $this->table(['Indicator', 'Count'], [
            ['Mode', $dryRun ? 'dry-run' : 'applied'],
            ['Temporary archive rows scanned', $summary['archives_scanned']],
            ['Buildings restored from snapshot', $summary['buildings_restored_from_snapshot']],
            ['Buildings restored to committee_review fallback', $summary['buildings_restored_to_committee_review']],
            ['Housing units restored from snapshot', $summary['housing_units_restored_from_snapshot']],
            ['Housing units restored to committee_review2 fallback', $summary['housing_units_restored_to_committee_review']],
            ['Parent buildings restored from snapshot', $summary['parent_buildings_restored_from_snapshot']],
            ['Parent buildings restored to COMPLETED fallback', $summary['parent_buildings_restored_to_completed']],
            ['Committee decisions reset', $summary['decisions_reset']],
            ['Committee decisions deleted', $summary['decisions_deleted']],
            ['Committee signatures deleted', $summary['signatures_deleted']],
            ['Skipped rows', $summary['skipped_rows']],
        ]);

        if ($summary['skip_reasons'] !== []) {
            $this->warn('Skipped rows by reason:');
            $this->table(
                ['Reason', 'Count'],
                collect($summary['skip_reasons'])
                    ->map(fn (int $count, string $reason): array => [$reason, $count])
                    ->values()
                    ->all(),
            );
        }

        if ($summary['issues'] !== []) {
            $this->warn('First skipped row samples:');
            $this->table(
                ['Archive ID', 'Building ObjectID', 'Housing Unit ObjectID', 'Reason'],
                collect($summary['issues'])
                    ->take(20)
                    ->map(fn (array $issue): array => [
                        $issue['archive_id'] ?? '',
                        $issue['building_objectid'] ?? '',
                        $issue['housing_unit_objectid'] ?? '',
                        $issue['reason_key'],
                    ])
                    ->all(),
            );
        }

        $this->info($dryRun
            ? 'Dry run completed. No database records were changed. Re-run with --force to apply.'
            : 'Temporary technical committee rollback completed.');

        return self::SUCCESS;
    }
}
