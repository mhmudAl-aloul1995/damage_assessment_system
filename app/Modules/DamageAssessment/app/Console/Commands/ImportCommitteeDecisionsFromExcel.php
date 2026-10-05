<?php

namespace App\Console\Commands;

use App\services\CommitteeDecisionExcelImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportCommitteeDecisionsFromExcel extends Command
{
    protected $signature = 'committee-decisions:import-excel
        {file : Absolute path to the temporary committee decisions XLSX file on the server}
        {--dry-run : Read and validate the workbook without saving records}';

    protected $description = 'Import temporary committee decision Excel rows into committee decisions, members, and signature slots';

    public function __construct(private readonly CommitteeDecisionExcelImportService $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $summary = $this->importer->import(
                (string) $this->argument('file'),
                (bool) $this->option('dry-run'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Indicator', 'Count'], [
            ['Rows read', $summary['rows']],
            ['Decisions created', $summary['decisions_created']],
            ['Decisions updated', $summary['decisions_updated']],
            ['Committee members created', $summary['members_created']],
            ['Committee members updated', $summary['members_updated']],
            ['Signature slots created', $summary['signatures_created']],
            ['Signature slots updated', $summary['signatures_updated']],
            ['Skipped rows', $summary['skipped_rows']],
            ['Missing users', count($summary['missing_users'])],
        ]);

        if ($summary['issues'] !== []) {
            $this->warn('Rows requiring review:');
            $this->table(
                ['Excel row', 'Reason'],
                collect($summary['issues'])
                    ->map(fn (array $issue): array => [$issue['row'], $issue['reason']])
                    ->all(),
            );
        }

        if ($summary['missing_users'] !== []) {
            $this->warn('Committee members not found in users:');
            $this->table(
                ['Name'],
                collect($summary['missing_users'])
                    ->unique()
                    ->sort()
                    ->map(fn (string $name): array => [$name])
                    ->values()
                    ->all(),
            );
        }

        $this->info($this->option('dry-run')
            ? 'Dry run completed. No records were saved.'
            : 'Committee decision import completed successfully.');

        return self::SUCCESS;
    }
}
