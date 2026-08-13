<?php

namespace App\Console\Commands;

use App\Models\HousingUnit;
use App\Support\ArabicNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RefreshMissingCitizenIdentityReport extends Command
{
    private const SUBJECT_OWNER = 'owner';

    private const SUBJECT_SPOUSE = 'spouse';

    private const ISSUE_MISSING_CIVIL_REGISTRY_IDENTITY = 'missing_civil_registry_identity';

    private const ISSUE_OWNER_WITHOUT_IDENTITY = 'owner_without_identity';

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
            ->select([
                'id',
                'unit_owner',
                'id_number1',
                'q_9_3_1_first_name',
                'q_9_3_2_second_name__father',
                'q_9_3_3_third_name__grandfather',
                'q_9_3_4_last_name',
                'spouse1',
                'spouse1_id',
                'spouse2',
                'spouse2_id',
                'spouse3',
                'spouse3_id',
                'spouse4',
                'spouse4_id',
            ])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($housingUnits) use ($stagingTable, &$processed, &$missing): void {
                $processed += $housingUnits->count();

                $identityRows = $housingUnits
                    ->flatMap(fn (HousingUnit $housingUnit): Collection => $this->identityRows($housingUnit))
                    ->values();

                $identityRows = $identityRows
                    ->merge($this->husbandRegistryFallbackIdentityRows($housingUnits, $identityRows))
                    ->values();

                $identityNumbers = $identityRows
                    ->pluck('id_number')
                    ->filter()
                    ->unique()
                    ->values();

                $activeCitizenIds = $identityNumbers->isEmpty()
                    ? collect()
                    : DB::table($this->citizensTable())
                        ->where('status', 'A')
                        ->whereIn('id_card_no', $identityNumbers)
                        ->pluck('id_card_no')
                        ->mapWithKeys(fn ($id): array => [(string) $id => true]);

                $sgazaIds = $identityNumbers->isEmpty()
                    ? collect()
                    : $this->sgazaIdsByNumbers($identityNumbers)
                        ->mapWithKeys(fn ($id): array => [(string) $id => true]);

                $husbandRegistryIds = $identityNumbers->isEmpty()
                    ? collect()
                    : $this->husbandRegistryIdsByNumbers($identityNumbers)
                        ->mapWithKeys(fn ($id): array => [(string) $id => true]);

                $existingCivilRegistryIds = $activeCitizenIds->union($sgazaIds);

                $missingIdentityRows = $identityRows
                    ->filter(function (array $identityRow) use ($existingCivilRegistryIds, $husbandRegistryIds): bool {
                        $idNumber = $identityRow['id_number'];

                        if ($idNumber === '') {
                            return filled($identityRow['owner_name']);
                        }

                        if (isset($existingCivilRegistryIds[$idNumber])) {
                            return false;
                        }

                        return ! (
                            $identityRow['identity_subject'] === self::SUBJECT_SPOUSE
                            && isset($husbandRegistryIds[$idNumber])
                        );
                    })
                    ->values();

                if ($missingIdentityRows->isEmpty()) {
                    return;
                }

                $nameMatchesByIdentityKey = $this->nameMatchesByIdentityKey($missingIdentityRows);

                $rows = $missingIdentityRows
                    ->map(function (array $identityRow) use ($nameMatchesByIdentityKey): array {
                        $ownerName = $identityRow['owner_name'];
                        $nameMatch = $nameMatchesByIdentityKey[$identityRow['identity_key']] ?? [
                            'normalized_owner_name' => ArabicNameNormalizer::normalize($ownerName),
                            'name_match_status' => filled($ownerName) ? 'not_found' : 'no_owner_name',
                            'matched_citizen_id' => null,
                            'matched_citizen_id_card_no' => null,
                            'matched_citizen_full_name' => null,
                            'matched_citizens_count' => 0,
                        ];

                        return [
                            'housing_unit_id' => $identityRow['housing_unit_id'],
                            'identity_subject' => $identityRow['identity_subject'],
                            'identity_index' => $identityRow['identity_index'],
                            'identity_name_field' => $identityRow['identity_name_field'],
                            'identity_number_field' => $identityRow['identity_number_field'],
                            'owner_name' => $ownerName,
                            'normalized_owner_name' => $nameMatch['normalized_owner_name'],
                            'id_number' => $identityRow['id_number'],
                            'issue_type' => $this->issueType($identityRow),
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
                            identity_subject,
                            identity_index,
                            identity_name_field,
                            identity_number_field,
                            owner_name,
                            normalized_owner_name,
                            id_number,
                            issue_type,
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
                        identity_subject,
                        identity_index,
                        identity_name_field,
                        identity_number_field,
                        owner_name,
                        normalized_owner_name,
                        id_number,
                        issue_type,
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
                            'identity_subject',
                            'identity_index',
                            'identity_name_field',
                            'identity_number_field',
                            'owner_name',
                            'normalized_owner_name',
                            'id_number',
                            'issue_type',
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
            $table->string('identity_subject', 20)->default(self::SUBJECT_OWNER);
            $table->unsignedTinyInteger('identity_index')->nullable();
            $table->string('identity_name_field', 50)->default('unit_owner');
            $table->string('identity_number_field', 50)->default('id_number1');
            $table->string('owner_name')->nullable();
            $table->string('normalized_owner_name')->nullable();
            $table->string('id_number', 255);
            $table->string('issue_type', 40)->default(self::ISSUE_MISSING_CIVIL_REGISTRY_IDENTITY);
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
     * @param  Collection<int, array<string, mixed>>  $identityRows
     * @return array<int, array<string, mixed>>
     */
    private function nameMatchesByIdentityKey(Collection $identityRows): array
    {
        $normalizedNamesByIdentityKey = $identityRows
            ->mapWithKeys(fn (array $identityRow): array => [
                $identityRow['identity_key'] => ArabicNameNormalizer::normalize((string) $identityRow['owner_name']),
            ])
            ->filter();

        if ($normalizedNamesByIdentityKey->isEmpty()) {
            return [];
        }

        $citizensByNormalizedName = DB::table($this->citizensTable())
            ->select(['id', 'id_card_no', 'full_name', 'full_name_normalized'])
            ->where('status', 'A')
            ->whereIn('full_name_normalized', $normalizedNamesByIdentityKey->unique()->values())
            ->get()
            ->groupBy(fn ($citizen): string => (string) $citizen->full_name_normalized);

        $sgazaByNormalizedName = $this->sgazaMatchesByNormalizedNames($normalizedNamesByIdentityKey->unique()->values());

        return $identityRows
            ->mapWithKeys(function (array $identityRow) use ($normalizedNamesByIdentityKey, $citizensByNormalizedName, $sgazaByNormalizedName): array {
                $identityKey = (string) $identityRow['identity_key'];
                $normalizedOwnerName = (string) ($normalizedNamesByIdentityKey[$identityKey] ?? '');
                $citizens = $citizensByNormalizedName->get($normalizedOwnerName, collect());
                $sgazaRecords = $sgazaByNormalizedName->get($normalizedOwnerName, collect());
                $husbandRegistryRecords = $this->husbandRegistryMatches($identityRow, $normalizedOwnerName);
                $matches = $husbandRegistryRecords
                    ->merge($sgazaRecords)
                    ->merge($citizens)
                    ->unique(fn ($match): string => (string) $match->id_card_no)
                    ->values();
                $matchedCitizen = $matches->count() === 1 ? $matches->first() : null;

                return [
                    $identityKey => [
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

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function identityRows(HousingUnit $housingUnit): Collection
    {
        return collect([
            [
                'identity_subject' => self::SUBJECT_OWNER,
                'identity_index' => null,
                'identity_name_field' => 'unit_owner',
                'identity_number_field' => 'id_number1',
                'owner_name' => $this->ownerName($housingUnit),
                'id_number' => trim((string) $housingUnit->id_number1),
                'husband_id_card_no' => null,
            ],
            ...collect(range(1, 4))->map(fn (int $index): array => [
                'identity_subject' => self::SUBJECT_SPOUSE,
                'identity_index' => $index,
                'identity_name_field' => 'spouse'.$index,
                'identity_number_field' => 'spouse'.$index.'_id',
                'owner_name' => trim((string) $housingUnit->{'spouse'.$index}),
                'id_number' => trim((string) $housingUnit->{'spouse'.$index.'_id'}),
                'husband_id_card_no' => trim((string) $housingUnit->id_number1),
            ])->all(),
        ])
            ->filter(fn (array $identityRow): bool => filled($identityRow['owner_name']) || filled($identityRow['id_number']))
            ->map(function (array $identityRow) use ($housingUnit): array {
                return [
                    ...$identityRow,
                    'housing_unit_id' => $housingUnit->id,
                    'identity_key' => $housingUnit->id.':'.$identityRow['identity_number_field'],
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, HousingUnit>  $housingUnits
     * @param  Collection<int, array<string, mixed>>  $identityRows
     * @return Collection<int, array<string, mixed>>
     */
    private function husbandRegistryFallbackIdentityRows(Collection $housingUnits, Collection $identityRows): Collection
    {
        $husbandIdCardNumbers = $housingUnits
            ->pluck('id_number1')
            ->map(fn ($idNumber): string => trim((string) $idNumber))
            ->filter()
            ->unique()
            ->values();

        if ($husbandIdCardNumbers->isEmpty()) {
            return collect();
        }

        try {
            $registryRecordsByHusbandId = DB::table($this->husbandRegistryTable())
                ->select(['id_card_no', 'full_name', 'full_name_normalized', 'husband_id_card_no'])
                ->where('status', 'A')
                ->whereIn(DB::raw('TRIM(husband_id_card_no)'), $husbandIdCardNumbers)
                ->orderBy('id_card_no')
                ->get()
                ->groupBy(fn ($record): string => trim((string) $record->husband_id_card_no));
        } catch (Throwable) {
            return collect();
        }

        $spouseRowsByUnitId = $identityRows
            ->filter(fn (array $identityRow): bool => $identityRow['identity_subject'] === self::SUBJECT_SPOUSE)
            ->groupBy('housing_unit_id');

        return $housingUnits
            ->flatMap(function (HousingUnit $housingUnit) use ($registryRecordsByHusbandId, $spouseRowsByUnitId): Collection {
                $husbandIdCardNo = trim((string) $housingUnit->id_number1);
                $registryRecords = $registryRecordsByHusbandId->get($husbandIdCardNo, collect());

                if ($registryRecords->isEmpty()) {
                    return collect();
                }

                $existingSpouseRows = $spouseRowsByUnitId->get($housingUnit->id, collect());
                $existingIdNumbers = $existingSpouseRows
                    ->pluck('id_number')
                    ->map(fn ($idNumber): string => trim((string) $idNumber))
                    ->filter()
                    ->flip();
                $existingNames = $existingSpouseRows
                    ->pluck('owner_name')
                    ->map(fn ($name): string => ArabicNameNormalizer::normalize((string) $name))
                    ->filter()
                    ->flip();
                $availableSlots = collect(range(1, 4))
                    ->filter(fn (int $index): bool => trim((string) $housingUnit->{'spouse'.$index}) === ''
                        && trim((string) $housingUnit->{'spouse'.$index.'_id'}) === '')
                    ->values();

                if ($availableSlots->isEmpty()) {
                    return collect();
                }

                return $registryRecords
                    ->filter(function ($record) use ($existingIdNumbers, $existingNames): bool {
                        $idNumber = trim((string) $record->id_card_no);
                        $normalizedName = ArabicNameNormalizer::normalize((string) $record->full_name);

                        return $idNumber !== ''
                            && ! isset($existingIdNumbers[$idNumber])
                            && ! isset($existingNames[$normalizedName]);
                    })
                    ->take($availableSlots->count())
                    ->values()
                    ->map(function ($record, int $recordIndex) use ($availableSlots, $housingUnit, $husbandIdCardNo): array {
                        $slot = (int) $availableSlots[$recordIndex];

                        return [
                            'identity_subject' => self::SUBJECT_SPOUSE,
                            'identity_index' => $slot,
                            'identity_name_field' => 'spouse'.$slot,
                            'identity_number_field' => 'spouse'.$slot.'_id',
                            'owner_name' => trim((string) $record->full_name),
                            'id_number' => '',
                            'husband_id_card_no' => $husbandIdCardNo,
                            'housing_unit_id' => $housingUnit->id,
                            'identity_key' => $housingUnit->id.':spouse'.$slot.'_id',
                        ];
                    });
            })
            ->values();
    }

    private function ownerName(HousingUnit $housingUnit): string
    {
        $structuredName = trim(implode(' ', array_filter([
            trim((string) $housingUnit->q_9_3_1_first_name),
            trim((string) $housingUnit->q_9_3_2_second_name__father),
            trim((string) $housingUnit->q_9_3_3_third_name__grandfather),
            trim((string) $housingUnit->q_9_3_4_last_name),
        ])));

        return $structuredName !== ''
            ? $structuredName
            : trim((string) $housingUnit->unit_owner);
    }

    /**
     * @param  array<string, mixed>  $identityRow
     */
    private function issueType(array $identityRow): string
    {
        return $identityRow['id_number'] === ''
            ? self::ISSUE_OWNER_WITHOUT_IDENTITY
            : self::ISSUE_MISSING_CIVIL_REGISTRY_IDENTITY;
    }

    private function sgazaIdsByNumbers(Collection $identityNumbers): Collection
    {
        if (! Schema::hasTable('sgaza') || ! Schema::hasColumn('sgaza', 'id_number')) {
            return collect();
        }

        $identityNumbers = $identityNumbers
            ->map(fn ($identityNumber): string => trim((string) $identityNumber))
            ->filter()
            ->values();

        if ($identityNumbers->isEmpty()) {
            return collect();
        }

        return DB::table('sgaza')
            ->selectRaw('TRIM(id_number) as id_number')
            ->whereIn(DB::raw('TRIM(id_number)'), $identityNumbers)
            ->pluck('id_number');
    }

    private function husbandRegistryIdsByNumbers(Collection $identityNumbers): Collection
    {
        $identityNumbers = $identityNumbers
            ->map(fn ($identityNumber): string => trim((string) $identityNumber))
            ->filter()
            ->values();

        if ($identityNumbers->isEmpty()) {
            return collect();
        }

        try {
            return DB::table($this->husbandRegistryTable())
                ->selectRaw('TRIM(id_card_no) as id_card_no')
                ->where('status', 'A')
                ->whereIn(DB::raw('TRIM(id_card_no)'), $identityNumbers)
                ->pluck('id_card_no');
        } catch (Throwable) {
            return collect();
        }
    }

    /**
     * @param  array<string, mixed>  $identityRow
     */
    private function husbandRegistryMatches(array $identityRow, string $normalizedOwnerName): Collection
    {
        if ($identityRow['identity_subject'] !== self::SUBJECT_SPOUSE || $normalizedOwnerName === '') {
            return collect();
        }

        $husbandIdCardNo = trim((string) ($identityRow['husband_id_card_no'] ?? ''));

        if ($husbandIdCardNo === '') {
            return collect();
        }

        try {
            return DB::table($this->husbandRegistryTable())
                ->select([
                    DB::raw('0 as id'),
                    'id_card_no',
                    'full_name',
                    'full_name_normalized',
                ])
                ->where('status', 'A')
                ->whereRaw('TRIM(husband_id_card_no) = ?', [$husbandIdCardNo])
                ->where('full_name_normalized', $normalizedOwnerName)
                ->get();
        } catch (Throwable) {
            return collect();
        }
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

    private function husbandRegistryTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens_to_set_husband_id';
        }

        return 'phc_dashboard.citizens_to_set_husband_id';
    }
}
