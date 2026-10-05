<?php

namespace App\Console\Commands;

use App\Models\HousingUnit;
use App\Support\ArabicNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshMissingCitizenNameReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'missing-citizen-names:refresh {--chunk=5000 : Records processed per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the cached report of housing unit owner names missing from active citizens';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = max(100, (int) $this->option('chunk'));
        $citizenNamesTable = 'active_citizen_normalized_names_staging';
        $reportStagingTable = 'missing_citizen_name_reports_staging';

        $this->prepareCitizenNamesTable($citizenNamesTable);
        $this->prepareReportStagingTable($reportStagingTable);

        $this->cacheActiveCitizenNames($citizenNamesTable, $chunkSize);

        [$processed, $missing] = $this->cacheMissingHousingUnitOwnerNames(
            $citizenNamesTable,
            $reportStagingTable,
            $chunkSize
        );

        DB::transaction(function () use ($reportStagingTable): void {
            DB::table('missing_citizen_name_reports')->delete();

            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("
                    INSERT INTO missing_citizen_name_reports
                        (housing_unit_id, owner_name, normalized_owner_name, created_at, updated_at)
                    SELECT housing_unit_id, owner_name, normalized_owner_name, created_at, updated_at
                    FROM {$reportStagingTable}
                ");
            } else {
                DB::table('missing_citizen_name_reports')->insert(
                    DB::table($reportStagingTable)
                        ->select(['housing_unit_id', 'owner_name', 'normalized_owner_name', 'created_at', 'updated_at'])
                        ->get()
                        ->map(fn ($row): array => (array) $row)
                        ->all()
                );
            }
        });

        Schema::drop($reportStagingTable);
        Schema::drop($citizenNamesTable);

        $this->info("Name report refreshed. Processed {$processed} housing units and cached {$missing} missing owner names.");

        return self::SUCCESS;
    }

    private function cacheActiveCitizenNames(string $citizenNamesTable, int $chunkSize): void
    {
        $processed = 0;
        $cached = 0;

        DB::table($this->citizensTable())
            ->select(['id', 'full_name'])
            ->where('status', 'A')
            ->whereNotNull('full_name')
            ->where('full_name', '<>', '')
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $citizens) use ($citizenNamesTable, &$processed, &$cached): void {
                $processed += $citizens->count();

                $rows = $citizens
                    ->map(fn ($citizen): string => ArabicNameNormalizer::normalize((string) $citizen->full_name))
                    ->filter()
                    ->unique()
                    ->map(fn (string $normalizedName): array => [
                        'normalized_name' => $normalizedName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->values();

                if ($rows->isEmpty()) {
                    return;
                }

                DB::table($citizenNamesTable)->insertOrIgnore($rows->all());
                $cached += $rows->count();

                $this->line("Processed {$processed} active citizens; cached up to {$cached} normalized names.");
            }, 'id');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function cacheMissingHousingUnitOwnerNames(string $citizenNamesTable, string $reportStagingTable, int $chunkSize): array
    {
        $processed = 0;
        $missing = 0;

        HousingUnit::query()
            ->select(['id', 'unit_owner'])
            ->whereNotNull('unit_owner')
            ->where('unit_owner', '<>', '')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($housingUnits) use ($citizenNamesTable, $reportStagingTable, &$processed, &$missing): void {
                $processed += $housingUnits->count();

                $normalizedNamesByUnitId = $housingUnits
                    ->mapWithKeys(fn (HousingUnit $housingUnit): array => [
                        $housingUnit->id => ArabicNameNormalizer::normalize((string) $housingUnit->unit_owner),
                    ])
                    ->filter();

                if ($normalizedNamesByUnitId->isEmpty()) {
                    return;
                }

                $existingNames = DB::table($citizenNamesTable)
                    ->whereIn('normalized_name', $normalizedNamesByUnitId->unique()->values())
                    ->pluck('normalized_name')
                    ->mapWithKeys(fn ($name): array => [(string) $name => true]);

                $rows = $housingUnits
                    ->filter(function (HousingUnit $housingUnit) use ($existingNames, $normalizedNamesByUnitId): bool {
                        $normalizedName = $normalizedNamesByUnitId[$housingUnit->id] ?? '';

                        return $normalizedName !== '' && ! isset($existingNames[$normalizedName]);
                    })
                    ->map(fn (HousingUnit $housingUnit): array => [
                        'housing_unit_id' => $housingUnit->id,
                        'owner_name' => $housingUnit->unit_owner,
                        'normalized_owner_name' => $normalizedNamesByUnitId[$housingUnit->id],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->values();

                if ($rows->isEmpty()) {
                    return;
                }

                DB::table($reportStagingTable)->insert($rows->all());
                $missing += $rows->count();

                $this->line("Processed {$processed} housing units; cached {$missing} missing names.");
            });

        return [$processed, $missing];
    }

    private function prepareCitizenNamesTable(string $citizenNamesTable): void
    {
        Schema::dropIfExists($citizenNamesTable);

        Schema::create($citizenNamesTable, function (Blueprint $table): void {
            $table->id();
            $table->string('normalized_name');
            $table->timestamps();

            $table->unique('normalized_name', 'active_citizen_names_normalized_unique');
        });
    }

    private function prepareReportStagingTable(string $reportStagingTable): void
    {
        Schema::dropIfExists($reportStagingTable);

        Schema::create($reportStagingTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('housing_unit_id');
            $table->string('owner_name')->nullable();
            $table->string('normalized_owner_name')->nullable();
            $table->timestamps();
        });
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }
}
