<?php

namespace App\Console\Commands;

use App\services\HousingUnitCivilRegistryNameBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillHousingUnitNamesFromCivilRegistry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'housing-units:backfill-names-from-civil-registry
        {--dry-run : Show housing unit names that would change without updating the database or ArcGIS}
        {--chunk=200 : Housing units processed per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill housing unit owner and spouse names from civil registry tables by identity number.';

    /**
     * Execute the console command.
     */
    public function handle(HousingUnitCivilRegistryNameBackfillService $backfillService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $query = DB::table('housing_units as h')
            ->where(function ($query): void {
                foreach ($this->identityFields() as $field) {
                    $query->orWhereNotNull('h.'.$field);
                }
            });

        $counts = $dryRun
            ? $backfillService->previewFilteredQuery($query, $this->identityAndNameFields(), $chunkSize)
            : $backfillService->updateFilteredQuery($query, $this->identityAndNameFields(), $chunkSize);

        $this->components->info($dryRun ? 'Dry run complete.' : 'Housing unit name backfill complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Housing units scanned', $counts['housing_units_scanned']],
                ['Identity numbers checked', $counts['identity_numbers_checked']],
                ['Registry names found', $counts['registry_names_found']],
                ['Would update', $counts['would_update']],
                ['Housing units updated', $counts['updated_database']],
                ['ArcGIS synced', $counts['synced_arcgis']],
                ['ArcGIS skipped', $counts['skipped_arcgis']],
                ['ArcGIS failed', $counts['failed_arcgis']],
                ['Skipped', $counts['skipped']],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function identityAndNameFields(): array
    {
        return [
            'id_number1',
            'unit_owner',
            'spouse1_id',
            'spouse1',
            'spouse2_id',
            'spouse2',
            'spouse3_id',
            'spouse3',
            'spouse4_id',
            'spouse4',
        ];
    }

    /**
     * @return list<string>
     */
    private function identityFields(): array
    {
        return ['id_number1', 'spouse1_id', 'spouse2_id', 'spouse3_id', 'spouse4_id'];
    }
}
