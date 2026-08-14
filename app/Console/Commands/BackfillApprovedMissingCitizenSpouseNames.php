<?php

namespace App\Console\Commands;

use App\Models\HousingUnit;
use App\Models\MissingCitizenIdentityApproval;
use App\Models\MissingCitizenIdentityReport;
use App\services\ArcgisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillApprovedMissingCitizenSpouseNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'missing-citizen-identities:backfill-approved-spouse-names
        {--dry-run : Show eligible approved spouse rows without changing local database or ArcGIS}
        {--chunk=200 : Approved reports processed per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill approved spouse names into housing units and ArcGIS from missing identity approvals.';

    /**
     * Execute the console command.
     */
    public function handle(ArcgisService $arcgisService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $counts = [
            'eligible' => 0,
            'updated_database' => 0,
            'synced_arcgis' => 0,
            'failed_arcgis' => 0,
            'skipped' => 0,
        ];

        MissingCitizenIdentityReport::query()
            ->where('identity_subject', 'spouse')
            ->whereNotNull('approved_at')
            ->whereNotNull('matched_citizen_full_name')
            ->whereIn('identity_name_field', $this->spouseNameFields())
            ->whereIn('identity_number_field', $this->spouseIdentityFields())
            ->orderBy('id')
            ->chunkById($chunkSize, function ($reports) use (&$counts, $dryRun, $arcgisService): void {
                foreach ($reports as $report) {
                    $counts['eligible']++;
                    $result = $this->backfillReport($report, $dryRun, $arcgisService);
                    $counts[$result['status']]++;

                    if ($result['database_updated']) {
                        $counts['updated_database']++;
                    }
                }
            });

        $this->components->info($dryRun ? 'Dry run complete.' : 'Backfill complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Eligible approved spouse reports', $counts['eligible']],
                ['Housing units updated', $counts['updated_database']],
                ['ArcGIS synced', $counts['synced_arcgis']],
                ['ArcGIS failed', $counts['failed_arcgis']],
                ['Skipped', $counts['skipped']],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * @return array{status: string, database_updated: bool}
     */
    private function backfillReport(MissingCitizenIdentityReport $report, bool $dryRun, ArcgisService $arcgisService): array
    {
        $identityNameField = (string) $report->identity_name_field;
        $identityNumberField = (string) $report->identity_number_field;
        $fullName = trim((string) $report->matched_citizen_full_name);
        $idNumber = trim((string) $report->matched_citizen_id_card_no);

        if ($fullName === '' || $fullName === '-' || $idNumber === '') {
            return ['status' => 'skipped', 'database_updated' => false];
        }

        $housingUnit = HousingUnit::query()->find($report->housing_unit_id);

        if (! $housingUnit instanceof HousingUnit) {
            return ['status' => 'skipped', 'database_updated' => false];
        }

        $updates = [
            $identityNameField => $fullName,
            $identityNumberField => $idNumber,
        ];

        if ($dryRun) {
            return ['status' => 'skipped', 'database_updated' => false];
        }

        DB::transaction(function () use ($housingUnit, $updates): void {
            $housingUnit->forceFill($updates)->save();
        });

        $arcgisResult = $arcgisService->updateHousingUnitFields($housingUnit->objectid, $updates);
        $this->recordArcgisResult($report, $arcgisResult);
        $arcgisStatus = (string) ($arcgisResult['status'] ?? 'failed');

        return [
            'status' => match ($arcgisStatus) {
                'synced' => 'synced_arcgis',
                'skipped' => 'skipped',
                default => 'failed_arcgis',
            },
            'database_updated' => true,
        ];
    }

    /**
     * @param  array{status?: string, message?: string|null, response?: mixed}  $arcgisResult
     */
    private function recordArcgisResult(MissingCitizenIdentityReport $report, array $arcgisResult): void
    {
        $report->forceFill([
            'arcgis_sync_status' => $arcgisResult['status'] ?? 'failed',
            'arcgis_sync_message' => $arcgisResult['message'] ?? null,
        ])->save();

        MissingCitizenIdentityApproval::query()
            ->where('missing_citizen_identity_report_id', $report->id)
            ->latest('id')
            ->first()
            ?->forceFill([
                'arcgis_sync_status' => $arcgisResult['status'] ?? 'failed',
                'arcgis_sync_message' => $arcgisResult['message'] ?? null,
                'arcgis_sync_response' => $arcgisResult['response'] ?? null,
            ])
            ->save();
    }

    /**
     * @return list<string>
     */
    private function spouseNameFields(): array
    {
        return ['spouse1', 'spouse2', 'spouse3', 'spouse4'];
    }

    /**
     * @return list<string>
     */
    private function spouseIdentityFields(): array
    {
        return ['spouse1_id', 'spouse2_id', 'spouse3_id', 'spouse4_id'];
    }
}
