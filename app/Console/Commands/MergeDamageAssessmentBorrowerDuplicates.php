<?php

namespace App\Console\Commands;

use App\Modules\DamageAssessmentBorrowers\Services\BorrowerDuplicateMergeService;
use Illuminate\Console\Command;

class MergeDamageAssessmentBorrowerDuplicates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'borrowers:merge-duplicates {--dry-run : Report duplicate groups without merging them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge duplicate borrower rows by identity number while preserving related data';

    public function __construct(private readonly BorrowerDuplicateMergeService $mergeService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $duplicateCount = $this->mergeService->duplicateBorrowerIdNumbers()->count();

        if ((bool) $this->option('dry-run')) {
            $this->info("Duplicate borrower identity groups: {$duplicateCount}");

            return self::SUCCESS;
        }

        $summary = $this->mergeService->merge();

        $this->table(['Indicator', 'Count'], [
            ['Duplicate groups', $summary['groups']],
            ['Removed rows', $summary['removed']],
            ['Merged related rows', $summary['merged_children']],
        ]);

        $this->info('Borrower duplicate merge completed successfully.');

        return self::SUCCESS;
    }
}
