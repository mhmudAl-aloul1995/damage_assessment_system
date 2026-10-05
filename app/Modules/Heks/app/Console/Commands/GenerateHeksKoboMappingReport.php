<?php

namespace App\Console\Commands;

use App\Modules\Heks\Services\HeksKoboMappingReportService;
use Illuminate\Console\Command;

class GenerateHeksKoboMappingReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:kobo-mapping-report
        {--main-false= : HEKS main technical Kobo export}
        {--main-labels= : HEKS main labels Kobo export}
        {--followup-false= : HEKS follow-up technical Kobo export}
        {--followup-labels= : HEKS follow-up labels Kobo export}
        {--boq-false= : HEKS BOQ technical Kobo export}
        {--boq-labels= : HEKS BOQ labels Kobo export}
        {--output= : Output directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate HEKS Kobo field mapping and BOQ mapping reports from paired Kobo Excel exports';

    /**
     * Execute the console command.
     */
    public function handle(HeksKoboMappingReportService $reportService): int
    {
        $pairs = $this->pairs();

        if ($pairs === []) {
            $this->components->error('Provide at least one complete False/labels file pair.');

            return self::FAILURE;
        }

        $result = $reportService->generate(
            $pairs,
            (string) ($this->option('output') ?: storage_path('app/heks/reports'))
        );

        $this->components->info("Mapping rows: {$result['rows']}; BOQ rows: {$result['boq_rows']}.");
        $this->line($result['mapping_report']);
        $this->line($result['boq_report']);

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{technical: string, labels: string}>
     */
    private function pairs(): array
    {
        $definitions = [
            'heks_main' => ['main-false', 'main-labels'],
            'heks_followup' => ['followup-false', 'followup-labels'],
            'heks_boq' => ['boq-false', 'boq-labels'],
        ];
        $pairs = [];

        foreach ($definitions as $service => [$technicalOption, $labelsOption]) {
            $technical = (string) $this->option($technicalOption);
            $labels = (string) $this->option($labelsOption);

            if ($technical === '' && $labels === '') {
                continue;
            }

            if (! is_file($technical) || ! is_file($labels)) {
                $this->components->warn("Skipping {$service}: both files must exist.");

                continue;
            }

            $pairs[$service] = [
                'technical' => $technical,
                'labels' => $labels,
            ];
        }

        return $pairs;
    }
}
