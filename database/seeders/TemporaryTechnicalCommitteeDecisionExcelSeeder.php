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

        $this->command?->info(sprintf(
            'Temporary technical committee seed completed: %d rows, %d completed, %d skipped.',
            $summary['rows'],
            $summary['decisions_completed'],
            $summary['skipped_rows'],
        ));

        if ($summary['missing_users'] !== []) {
            $this->command?->warn('Missing committee users by id_no: '.implode(', ', $summary['missing_users']));
        }

        if ($summary['issues'] !== []) {
            $this->command?->warn(sprintf('Temporary technical committee seed reported %d row issues.', count($summary['issues'])));
        }
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
