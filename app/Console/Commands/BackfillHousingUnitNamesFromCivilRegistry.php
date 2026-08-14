<?php

namespace App\Console\Commands;

use App\Models\HousingUnit;
use App\services\ArcgisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

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
    public function handle(ArcgisService $arcgisService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $counts = [
            'housing_units_scanned' => 0,
            'identity_numbers_checked' => 0,
            'registry_names_found' => 0,
            'would_update' => 0,
            'updated_database' => 0,
            'synced_arcgis' => 0,
            'failed_arcgis' => 0,
            'skipped_arcgis' => 0,
            'skipped' => 0,
        ];

        $query = HousingUnit::query()
            ->where(function ($query): void {
                foreach ($this->identityNamePairs() as $pair) {
                    $query->orWhereNotNull($pair['identity_field']);
                }
            })
            ->orderBy('id');

        $total = (clone $query)->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($chunkSize, function ($housingUnits) use (&$counts, $dryRun, $arcgisService, $bar): void {
            foreach ($housingUnits as $housingUnit) {
                $counts['housing_units_scanned']++;
                $updates = $this->updatesForHousingUnit($housingUnit, $counts);

                if ($updates === []) {
                    $counts['skipped']++;
                    $bar->advance();

                    continue;
                }

                if ($dryRun) {
                    $counts['would_update']++;
                    $bar->advance();

                    continue;
                }

                $housingUnit->forceFill($updates)->save();
                $counts['updated_database']++;

                try {
                    $arcgisResult = $arcgisService->updateHousingUnitFields($housingUnit->objectid, $updates);
                } catch (Throwable) {
                    $arcgisResult = ['status' => 'failed'];
                }

                match ((string) ($arcgisResult['status'] ?? 'failed')) {
                    'synced' => $counts['synced_arcgis']++,
                    'skipped' => $counts['skipped_arcgis']++,
                    default => $counts['failed_arcgis']++,
                };

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

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
     * @param  array<string, int>  $counts
     * @return array<string, string>
     */
    private function updatesForHousingUnit(HousingUnit $housingUnit, array &$counts): array
    {
        $updates = [];

        foreach ($this->identityNamePairs() as $pair) {
            $idNumber = $this->cleanIdentityNumber((string) $housingUnit->{$pair['identity_field']});

            if (! $this->isUsableIdentityNumber($idNumber)) {
                continue;
            }

            $counts['identity_numbers_checked']++;
            $registryName = $this->civilRegistryFullName($idNumber);

            if ($registryName === '') {
                continue;
            }

            $counts['registry_names_found']++;
            $currentName = trim((string) $housingUnit->{$pair['name_field']});

            if (! $this->shouldUpdateName($currentName, $registryName)) {
                continue;
            }

            $updates[$pair['name_field']] = $registryName;
        }

        return $updates;
    }

    /**
     * @return list<array{identity_field: string, name_field: string}>
     */
    private function identityNamePairs(): array
    {
        return [
            ['identity_field' => 'id_number1', 'name_field' => 'unit_owner'],
            ['identity_field' => 'spouse1_id', 'name_field' => 'spouse1'],
            ['identity_field' => 'spouse2_id', 'name_field' => 'spouse2'],
            ['identity_field' => 'spouse3_id', 'name_field' => 'spouse3'],
            ['identity_field' => 'spouse4_id', 'name_field' => 'spouse4'],
        ];
    }

    private function civilRegistryFullName(string $idNumber): string
    {
        $citizenName = $this->citizenFullName($idNumber);

        if ($citizenName !== '') {
            return $citizenName;
        }

        return $this->sgazaFullName($idNumber);
    }

    private function citizenFullName(string $idNumber): string
    {
        $table = $this->citizensTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id_card_no') || ! Schema::hasColumn($table, 'full_name')) {
            return '';
        }

        $query = DB::table($table)
            ->where('id_card_no', $idNumber);

        if (Schema::hasColumn($table, 'status')) {
            $query->where('status', 'A');
        }

        return trim((string) $query->value('full_name'));
    }

    private function sgazaFullName(string $idNumber): string
    {
        if (! Schema::hasTable('sgaza') || ! Schema::hasColumn('sgaza', 'id_number')) {
            return '';
        }

        $select = ['id_number'];

        foreach (['full_name', 'first_name', 'father_name', 'grandfather_name', 'grand_name', 'family_name'] as $column) {
            if (Schema::hasColumn('sgaza', $column)) {
                $select[] = $column;
            }
        }

        $record = DB::table('sgaza')
            ->select($select)
            ->where('id_number', $idNumber)
            ->first();

        if (! $record) {
            return '';
        }

        $fullName = trim((string) ($record->full_name ?? ''));

        if ($fullName !== '') {
            return $fullName;
        }

        return collect([
            $record->first_name ?? null,
            $record->father_name ?? null,
            $record->grandfather_name ?? $record->grand_name ?? null,
            $record->family_name ?? null,
        ])
            ->map(fn (mixed $part): string => trim((string) $part))
            ->filter()
            ->implode(' ');
    }

    private function shouldUpdateName(string $currentName, string $registryName): bool
    {
        if ($registryName === '' || $registryName === '-') {
            return false;
        }

        if ($currentName === '' || $currentName === '-') {
            return true;
        }

        if ($this->normalizeName($currentName) === $this->normalizeName($registryName)) {
            return false;
        }

        return $this->wordCount($registryName) >= $this->wordCount($currentName);
    }

    private function isUsableIdentityNumber(string $idNumber): bool
    {
        return $idNumber !== ''
            && ctype_digit($idNumber)
            && strlen($idNumber) >= 5
            && count(array_unique(str_split($idNumber))) > 1;
    }

    private function cleanIdentityNumber(string $idNumber): string
    {
        return preg_replace('/\D+/', '', trim($idNumber)) ?? '';
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', '', trim($name)) ?? '';
    }

    private function wordCount(string $value): int
    {
        return collect(preg_split('/\s+/u', trim($value)) ?: [])
            ->filter()
            ->count();
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }
}
