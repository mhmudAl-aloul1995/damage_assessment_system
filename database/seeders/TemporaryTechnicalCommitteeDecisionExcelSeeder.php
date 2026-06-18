<?php

namespace Database\Seeders;

use App\services\TemporaryTechnicalCommitteeDecisionImportService;
use Illuminate\Database\Seeder;

class TemporaryTechnicalCommitteeDecisionExcelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(TemporaryTechnicalCommitteeDecisionImportService $importer): void
    {
        $summary = $importer->importRecords($this->records());
        $signatureSummary = $importer->syncExistingCommitteeReviewDecisionSignatures();
        $archiveSummary = $importer->archiveSeedRecords($this->records());

        $this->command?->info(sprintf(
            'Temporary technical committee seed completed: %d rows, %d completed, %d skipped.',
            $summary['rows'],
            $summary['decisions_completed'],
            $summary['skipped_rows'],
        ));
        $this->command?->info(sprintf(
            'Committee review decisions synced: %d signed, %d completed, %d skipped without recognized municipality, %d skipped without decision type.',
            $signatureSummary['decisions_synced'],
            $signatureSummary['decisions_completed'],
            $signatureSummary['skipped_without_municipality'],
            $signatureSummary['skipped_without_decision_type'],
        ));
        $this->command?->info(sprintf(
            'Temporary technical committee exceptional archive completed: %d rows, %d archived, %d skipped.',
            $archiveSummary['rows'],
            $archiveSummary['archived'],
            $archiveSummary['skipped_rows'],
        ));

        $missingUsers = array_values(array_unique([
            ...$summary['missing_users'],
            ...$signatureSummary['missing_users'],
        ]));

        if ($missingUsers !== []) {
            $this->command?->warn('Missing committee users by id_no: '.implode(', ', $missingUsers));
        }

        if ($summary['issues'] !== []) {
            $this->command?->warn(sprintf('Temporary technical committee seed reported %d row issues.', count($summary['issues'])));
            $this->printSkipReasonSummary($summary['skip_reasons']);
            $this->printIssueSamples($summary['issues']);
        }

        if ($archiveSummary['issues'] !== []) {
            $this->command?->warn(sprintf('Temporary technical committee exceptional archive reported %d row issues.', count($archiveSummary['issues'])));
            $this->printSkipReasonSummary($archiveSummary['skip_reasons']);
            $this->printIssueSamples($archiveSummary['issues']);
        }
    }

    /**
     * @param  array<string, int>  $skipReasons
     */
    private function printSkipReasonSummary(array $skipReasons): void
    {
        if ($skipReasons === []) {
            return;
        }

        $this->command?->warn('Skipped rows by reason:');

        foreach ($skipReasons as $reason => $count) {
            $this->command?->line(sprintf(
                '  - %s: %d',
                $this->reasonLabel($reason),
                $count,
            ));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function printIssueSamples(array $issues): void
    {
        $this->command?->warn('First skipped row samples:');

        foreach (array_slice($issues, 0, 15) as $issue) {
            $this->command?->line(sprintf(
                '  - sheet="%s", row=%s, type=%s, objectid=%s, status=%s, reason=%s',
                $issue['sheet'] ?? '-',
                $issue['row'] ?? '-',
                $issue['record_type'] ?? '-',
                $issue['objectid'] ?? '-',
                $issue['current_status'] ?? '-',
                $this->reasonLabel((string) ($issue['reason_key'] ?? 'unknown')),
            ));
        }
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'missing_committee_users' => 'committee users not found by id_no',
            'missing_archive_user' => 'no user available for archive ownership',
            'record_not_found' => 'record not found in database',
            'not_committee_review' => 'record exists but damage status is not committee review',
            default => $reason,
        };
    }

    /**
     * @return list<array{
     *     record_type: string,
     *     municipality: string,
     *     sheet: string,
     *     row: int,
     *     objectid: string|int,
     *     globalid: string|null,
     *     decision_type: string,
     *     decision_text: string,
     *     action_text: string|null,
     *     member_id_numbers: list<string>
     * }>
     */
    private function records(): array
    {
        return require database_path('seeders/data/temporary_technical_committee_decisions.php');
    }
}
