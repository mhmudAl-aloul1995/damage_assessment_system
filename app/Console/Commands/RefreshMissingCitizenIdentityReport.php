<?php

namespace App\Console\Commands;

use App\Models\HousingUnit;
use App\Support\ArabicNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshMissingCitizenIdentityReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'missing-citizen-identities:refresh {--chunk=5000 : Housing units processed per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the cached report of housing unit identity numbers missing from active citizens';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = max(100, (int) $this->option('chunk'));
        $stagingTable = 'missing_citizen_identity_reports_staging';

        $this->prepareStagingTable($stagingTable);

        $processed = 0;
        $missing = 0;

        HousingUnit::query()
            ->select(['id', 'unit_owner', 'id_number1'])
            ->whereNotNull('id_number1')
            ->where('id_number1', '<>', '')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($housingUnits) use ($stagingTable, &$processed, &$missing): void {
                $processed += $housingUnits->count();

                $identityNumbers = $housingUnits
                    ->pluck('id_number1')
                    ->filter()
                    ->map(fn ($value): string => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->values();

                if ($identityNumbers->isEmpty()) {
                    return;
                }

                $activeCitizenIds = DB::table($this->citizensTable())
                    ->where('status', 'A')
                    ->whereIn('id_card_no', $identityNumbers)
                    ->pluck('id_card_no')
                    ->mapWithKeys(fn ($id): array => [(string) $id => true]);

                $sgazaIds = $this->sgazaIdsByNumbers($identityNumbers)
                    ->mapWithKeys(fn ($id): array => [(string) $id => true]);

                $existingCivilRegistryIds = $activeCitizenIds->union($sgazaIds);

                $missingHousingUnits = $housingUnits
                    ->filter(fn (HousingUnit $housingUnit): bool => ! isset($existingCivilRegistryIds[trim((string) $housingUnit->id_number1)]))
                    ->values();

                if ($missingHousingUnits->isEmpty()) {
                    return;
                }

                $nameMatchesByUnitId = $this->nameMatchesByUnitId($missingHousingUnits);

                $rows = $missingHousingUnits
                    ->map(function (HousingUnit $housingUnit) use ($nameMatchesByUnitId): array {
                        $nameMatch = $nameMatchesByUnitId[$housingUnit->id] ?? [
                            'normalized_owner_name' => ArabicNameNormalizer::normalize((string) $housingUnit->unit_owner),
                            'name_match_status' => filled($housingUnit->unit_owner) ? 'not_found' : 'no_owner_name',
                            'matched_citizen_id' => null,
                            'matched_citizen_id_card_no' => null,
                            'matched_citizen_full_name' => null,
                            'matched_citizens_count' => 0,
                        ];

                        return [
                            'housing_unit_id' => $housingUnit->id,
                            'owner_name' => $housingUnit->unit_owner,
                            'normalized_owner_name' => $nameMatch['normalized_owner_name'],
                            'id_number' => trim((string) $housingUnit->id_number1),
                            'name_match_status' => $nameMatch['name_match_status'],
                            'matched_citizen_id' => $nameMatch['matched_citizen_id'],
                            'matched_citizen_id_card_no' => $nameMatch['matched_citizen_id_card_no'],
                            'matched_citizen_full_name' => $nameMatch['matched_citizen_full_name'],
                            'matched_citizens_count' => $nameMatch['matched_citizens_count'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })
                    ->values();

                if ($rows->isEmpty()) {
                    return;
                }

                DB::table($stagingTable)->insert($rows->all());
                $missing += $rows->count();

                $this->line("Processed {$processed} housing units; cached {$missing} missing IDs.");
            });

        DB::transaction(function () use ($stagingTable): void {
            DB::table('missing_citizen_identity_reports')->delete();

            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("
                    INSERT INTO missing_citizen_identity_reports
                        (
                            housing_unit_id,
                            owner_name,
                            normalized_owner_name,
                            id_number,
                            name_match_status,
                            matched_citizen_id,
                            matched_citizen_id_card_no,
                            matched_citizen_full_name,
                            matched_citizens_count,
                            created_at,
                            updated_at
                        )
                    SELECT
                        housing_unit_id,
                        owner_name,
                        normalized_owner_name,
                        id_number,
                        name_match_status,
                        matched_citizen_id,
                        matched_citizen_id_card_no,
                        matched_citizen_full_name,
                        matched_citizens_count,
                        created_at,
                        updated_at
                    FROM {$stagingTable}
                ");
            } else {
                DB::table('missing_citizen_identity_reports')->insert(
                    DB::table($stagingTable)
                        ->select([
                            'housing_unit_id',
                            'owner_name',
                            'normalized_owner_name',
                            'id_number',
                            'name_match_status',
                            'matched_citizen_id',
                            'matched_citizen_id_card_no',
                            'matched_citizen_full_name',
                            'matched_citizens_count',
                            'created_at',
                            'updated_at',
                        ])
                        ->get()
                        ->map(fn ($row): array => (array) $row)
                        ->all()
                );
            }
        });

        Schema::drop($stagingTable);

        $this->info("Report refreshed. Processed {$processed} housing units and cached {$missing} missing IDs.");

        return self::SUCCESS;
    }

    private function prepareStagingTable(string $stagingTable): void
    {
        Schema::dropIfExists($stagingTable);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("CREATE TABLE {$stagingTable} LIKE missing_citizen_identity_reports");

            return;
        }

        Schema::create($stagingTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('housing_unit_id');
            $table->string('owner_name')->nullable();
            $table->string('normalized_owner_name')->nullable();
            $table->string('id_number', 255);
            $table->string('name_match_status', 30)->default('not_checked');
            $table->unsignedBigInteger('matched_citizen_id')->nullable();
            $table->string('matched_citizen_id_card_no')->nullable();
            $table->string('matched_citizen_full_name')->nullable();
            $table->unsignedInteger('matched_citizens_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('arcgis_sync_status', 30)->nullable();
            $table->text('arcgis_sync_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param  Collection<int, HousingUnit>  $housingUnits
     * @return array<int, array<string, mixed>>
     */
    private function nameMatchesByUnitId(Collection $housingUnits): array
    {
        $normalizedNamesByUnitId = $housingUnits
            ->mapWithKeys(fn (HousingUnit $housingUnit): array => [
                $housingUnit->id => ArabicNameNormalizer::normalize((string) $housingUnit->unit_owner),
            ])
            ->filter();

        if ($normalizedNamesByUnitId->isEmpty()) {
            return [];
        }

        $citizensByNormalizedName = DB::table($this->citizensTable())
            ->select(['id', 'id_card_no', 'full_name', 'full_name_normalized'])
            ->where('status', 'A')
            ->whereIn('full_name_normalized', $normalizedNamesByUnitId->unique()->values())
            ->get()
            ->groupBy(fn ($citizen): string => (string) $citizen->full_name_normalized);

        $sgazaByNormalizedName = $this->sgazaMatchesByNormalizedNames($normalizedNamesByUnitId->unique()->values());

        return $housingUnits
            ->mapWithKeys(function (HousingUnit $housingUnit) use ($normalizedNamesByUnitId, $citizensByNormalizedName, $sgazaByNormalizedName): array {
                $normalizedOwnerName = (string) ($normalizedNamesByUnitId[$housingUnit->id] ?? '');
                $citizens = $citizensByNormalizedName->get($normalizedOwnerName, collect());
                $sgazaRecords = $sgazaByNormalizedName->get($normalizedOwnerName, collect());
                $matches = $citizens->merge($sgazaRecords);
                $matchedCitizen = $matches->count() === 1 ? $matches->first() : null;

                return [
                    $housingUnit->id => [
                        'normalized_owner_name' => $normalizedOwnerName,
                        'name_match_status' => match (true) {
                            $normalizedOwnerName === '' => 'no_owner_name',
                            $matches->count() === 1 => 'matched',
                            $matches->count() > 1 => 'ambiguous',
                            default => 'not_found',
                        },
                        'matched_citizen_id' => $matchedCitizen?->id,
                        'matched_citizen_id_card_no' => $matchedCitizen?->id_card_no,
                        'matched_citizen_full_name' => $matchedCitizen?->full_name,
                        'matched_citizens_count' => $matches->count(),
                    ],
                ];
            })
            ->all();
    }

    private function sgazaIdsByNumbers(Collection $identityNumbers): Collection
    {
        if (! Schema::hasTable('sgaza') || ! Schema::hasColumn('sgaza', 'id_number')) {
            return collect();
        }

        return DB::table('sgaza')
            ->whereIn('id_number', $identityNumbers)
            ->pluck('id_number');
    }

    private function sgazaMatchesByNormalizedNames(Collection $normalizedNames): Collection
    {
        if (
            ! Schema::hasTable('sgaza')
            || ! Schema::hasColumn('sgaza', 'full_name_normalized')
            || ! Schema::hasColumn('sgaza', 'full_name')
        ) {
            return collect();
        }

        return DB::table('sgaza')
            ->select([
                DB::raw('0 as id'),
                'id_number as id_card_no',
                'full_name',
                'full_name_normalized',
            ])
            ->whereIn('full_name_normalized', $normalizedNames)
            ->get()
            ->groupBy(fn ($record): string => (string) $record->full_name_normalized);
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }
}
