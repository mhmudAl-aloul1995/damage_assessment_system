<?php

namespace App\Console\Commands;

use App\services\CommitteeDecisionWorkflowExcelImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportWorkflowCommitteeDecisionsFromExcel extends Command
{
    protected $signature = 'committee-decisions:import-workflow-excel
        {file* : Absolute path(s) to committee decision workflow XLSX file(s) on the server}
        {--units-only : Import housing unit sheets only}
        {--ignore-higher-committee : Ignore all higher committee columns and use the initial committee decision columns}
        {--dry-run : Parse and validate the workbook without saving database changes}';

    protected $description = 'Import committee decision workflow Excel rows into completed committee decisions';

    public function __construct(private readonly CommitteeDecisionWorkflowExcelImportService $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $summary = $this->emptySummary();

        foreach ((array) $this->argument('file') as $path) {
            try {
                $fileSummary = $this->importer->import((string) $path, [
                    'units_only' => (bool) $this->option('units-only'),
                    'ignore_higher_committee' => (bool) $this->option('ignore-higher-committee'),
                    'dry_run' => (bool) $this->option('dry-run'),
                    'recognize_arabic_yes' => true,
                ]);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $summary = $this->mergeSummary($summary, $fileSummary, (string) $path);
        }

        $this->table(['Indicator', 'Count'], [
            ['Files', count($summary['files'])],
            ['Rows read', $summary['rows']],
            ['Decisions completed', $summary['decisions_completed']],
            ['Statuses forced to committee review', $summary['statuses_forced_to_committee_review']],
            ['Resurvey completed statuses fixed', $summary['resurvey_completed_statuses_fixed']],
            ['Skipped rows', $summary['skipped_rows']],
            ['Missing users', count($summary['missing_users'])],
            ['Parse issues', count($summary['parse_issues'])],
        ]);

        if ($summary['sheets'] !== []) {
            $this->info('Sheets read:');
            $this->table(
                ['Sheet', 'Rows'],
                collect($summary['sheets'])->map(fn (int $count, string $sheet): array => [$sheet, $count])->values()->all(),
            );
        }

        if ($summary['skip_reasons'] !== []) {
            $this->warn('Skipped rows by reason:');
            $this->table(
                ['Reason', 'Count'],
                collect($summary['skip_reasons'])->map(fn (int $count, string $reason): array => [$reason, $count])->values()->all(),
            );
        }

        if ($summary['issues'] !== []) {
            $this->warn('First rows requiring review:');
            $this->table(
                ['File', 'Sheet', 'Row', 'ObjectID', 'Reason'],
                collect($summary['issues'])
                    ->take(20)
                    ->map(fn (array $issue): array => [
                        $issue['file'] ?? '-',
                        $issue['sheet'] ?? '-',
                        $issue['row'] ?? '-',
                        $issue['objectid'] ?? '-',
                        $issue['reason'] ?? ($issue['reason_key'] ?? '-'),
                    ])
                    ->all(),
            );
        }

        if ($summary['parse_issues'] !== []) {
            $this->warn('Parse issues:');
            $this->table(
                ['File', 'Sheet', 'Row', 'Reason'],
                collect($summary['parse_issues'])
                    ->take(20)
                    ->map(fn (array $issue): array => [
                        $issue['file'] ?? '-',
                        $issue['sheet'] ?? '-',
                        $issue['row'] ?? '-',
                        $issue['reason'] ?? '-',
                    ])
                    ->all(),
            );
        }

        $this->info($this->option('dry-run')
            ? 'Dry run completed. No records were saved.'
            : 'Workflow committee decision import completed successfully.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'files' => [],
            'sheets' => [],
            'parse_issues' => [],
            'rows' => 0,
            'decisions_completed' => 0,
            'skipped_rows' => 0,
            'statuses_forced_to_committee_review' => 0,
            'resurvey_completed_statuses_fixed' => 0,
            'skip_reasons' => [],
            'missing_users' => [],
            'issues' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $fileSummary
     * @return array<string, mixed>
     */
    private function mergeSummary(array $summary, array $fileSummary, string $filename): array
    {
        $summary['files'][] = $filename;

        foreach (['rows', 'decisions_completed', 'skipped_rows', 'statuses_forced_to_committee_review', 'resurvey_completed_statuses_fixed'] as $key) {
            $summary[$key] = ($summary[$key] ?? 0) + ($fileSummary[$key] ?? 0);
        }

        foreach (($fileSummary['skip_reasons'] ?? []) as $reason => $count) {
            $summary['skip_reasons'][$reason] = ($summary['skip_reasons'][$reason] ?? 0) + $count;
        }

        foreach (($fileSummary['sheets'] ?? []) as $sheet => $count) {
            $summary['sheets'][$filename.' / '.$sheet] = $count;
        }

        $summary['missing_users'] = array_values(array_unique([
            ...($summary['missing_users'] ?? []),
            ...($fileSummary['missing_users'] ?? []),
        ]));

        foreach (['issues', 'parse_issues'] as $key) {
            foreach (($fileSummary[$key] ?? []) as $issue) {
                $summary[$key][] = ['file' => $filename, ...$issue];
            }
        }

        return $summary;
    }
}
