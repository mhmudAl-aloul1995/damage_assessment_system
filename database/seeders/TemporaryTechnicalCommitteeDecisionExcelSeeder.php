<?php

namespace Database\Seeders;

use App\services\TemporaryTechnicalCommitteeDecisionImportService;
use Illuminate\Database\Seeder;

class TemporaryTechnicalCommitteeDecisionExcelSeeder extends Seeder
{
    /**
     * @var list<array{path: string, municipality: string, member_id_numbers: list<string>}>
     */
    private const FILES = [
        [
            'path' => 'C:/Users/M2/Downloads/قرارات اللجنة الفنية كامل/قرارات اللجنة الفنية كامل/قرار اللجان الفنية غزة.xlsx',
            'municipality' => 'غزة',
            'member_id_numbers' => [
                '934863572',
                '900277229',
                '801933490',
                '800282667',
            ],
        ],
        [
            'path' => 'C:/Users/M2/Downloads/قرارات اللجنة الفنية كامل/قرارات اللجنة الفنية كامل/تقارير  لجنة-خانيونس.xlsx',
            'municipality' => 'خانيونس',
            'member_id_numbers' => [
                '801933490',
                '800282667',
                '800846958',
                '804475044',
            ],
        ],
        [
            'path' => 'C:/Users/M2/Downloads/قرارات اللجنة الفنية كامل/قرارات اللجنة الفنية كامل/لجنة فنية النصيرات.xlsx',
            'municipality' => 'النصيرات',
            'member_id_numbers' => [
                '801933490',
                '800282667',
                '800846958',
                '804475044',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(TemporaryTechnicalCommitteeDecisionImportService $importer): void
    {
        $summary = $importer->importFiles(self::FILES);

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
}
