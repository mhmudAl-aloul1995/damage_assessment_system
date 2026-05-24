<?php

namespace App\Console\Commands;

use App\Modules\DamageAssessmentBorrowers\Services\BorrowerSpreadsheetImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportDamageAssessmentBorrowers extends Command
{
    protected $signature = 'borrowers:import
        {file : Absolute path to the beneficiary XLSX workbook or normalized JSON file}
        {--dry-run : Analyze the workbook without saving records}
        {--include-duplicate-identities : Include submissions whose borrower identity number occurs more than once in the workbook}';

    protected $description = 'Import damage assessment borrower survey records from Excel or normalized JSON';

    public function __construct(private readonly BorrowerSpreadsheetImportService $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $path = (string) $this->argument('file');
            $summary = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx'
                ? $this->importer->importWorkbook(
                    $path,
                    (bool) $this->option('dry-run'),
                    (bool) $this->option('include-duplicate-identities')
                )
                : $this->importer->import(
                    $path,
                    (bool) $this->option('dry-run'),
                    (bool) $this->option('include-duplicate-identities')
                );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Indicator', 'Count'], [
            ['Source records', $summary['total']],
            ['Ready records', $summary['ready']],
            ['Skipped records', $summary['skipped']],
            ['Created records', $summary['created']],
            ['Updated records', $summary['updated']],
            ['Duplicate form numbers', $summary['duplicate_form_numbers']],
            ['Critical risk', $summary['risk_levels']['critical']],
            ['High risk', $summary['risk_levels']['high']],
            ['Medium risk', $summary['risk_levels']['medium']],
            ['Low risk', $summary['risk_levels']['low']],
        ]);

        if ($summary['issues'] !== []) {
            $this->warn('Skipped rows requiring review:');
            $this->table(
                ['Excel row', 'Reason'],
                collect($summary['issues'])
                    ->map(fn (array $issue): array => [$issue['row'], implode(' ', $issue['reasons'])])
                    ->all()
            );
        }

        $this->info($this->option('dry-run')
            ? 'Dry run completed. No records were saved.'
            : 'Borrower import completed successfully.');

        return self::SUCCESS;
    }
}
