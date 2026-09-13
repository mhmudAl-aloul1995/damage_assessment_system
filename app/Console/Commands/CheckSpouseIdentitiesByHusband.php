<?php

namespace App\Console\Commands;

use App\Models\HousingUnit;
use App\Support\ArabicNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckSpouseIdentitiesByHusband extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spouse-identities:check-by-husband
        {--apply : Update housing unit spouse identity fields in the local database}
        {--unit=* : Housing unit objectid values to check}
        {--include-unmarried : Check units even when marital_status is not married}
        {--chunk=500 : Housing units processed per batch}
        {--limit=20 : Maximum correction preview rows to print}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check housing unit spouse identities using citizens.husband_id by owner identity number.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $includeUnmarried = (bool) $this->option('include-unmarried');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $previewLimit = max(0, (int) $this->option('limit'));
        $unitObjectIds = $this->unitObjectIds();

        $counts = [
            'housing_units_scanned' => 0,
            'registry_husbands_found' => 0,
            'spouse_slots_checked' => 0,
            'matched_existing' => 0,
            'would_update' => 0,
            'updated_database' => 0,
            'ambiguous' => 0,
            'missing_registry_match' => 0,
            'no_registry_spouses' => 0,
            'no_empty_slot' => 0,
        ];
        $previewRows = collect();

        $query = HousingUnit::query()
            ->select([
                'id',
                'objectid',
                'unit_owner',
                'id_number1',
                'marital_status',
                'spouse1',
                'spouse1_id',
                'spouse2',
                'spouse2_id',
                'spouse3',
                'spouse3_id',
                'spouse4',
                'spouse4_id',
            ])
            ->whereNotNull('id_number1')
            ->where('id_number1', '<>', '')
            ->when($unitObjectIds !== [], fn ($query) => $query->whereIn('objectid', $unitObjectIds))
            ->when(! $includeUnmarried, function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->whereRaw('LOWER(TRIM(marital_status)) = ?', ['married'])
                        ->orWhere('marital_status', 'like', '%متزوج%');
                });
            })
            ->orderBy('id');

        $query->chunkById($chunkSize, function ($housingUnits) use (&$counts, &$previewRows, $apply, $previewLimit): void {
            $counts['housing_units_scanned'] += $housingUnits->count();

            $registrySpousesByHusbandId = $this->citizenSpousesByHusbandId(
                $housingUnits
                    ->pluck('id_number1')
                    ->map(fn ($idNumber): string => trim((string) $idNumber))
                    ->filter()
                    ->unique()
                    ->values()
            );

            $counts['registry_husbands_found'] += $registrySpousesByHusbandId->count();

            foreach ($housingUnits as $housingUnit) {
                $result = $this->checkHousingUnit($housingUnit, $registrySpousesByHusbandId, $apply);

                foreach ($result['counts'] as $key => $value) {
                    $counts[$key] += $value;
                }

                if ($previewLimit > 0 && $previewRows->count() < $previewLimit) {
                    $previewRows = $previewRows
                        ->merge($result['preview_rows'])
                        ->take($previewLimit)
                        ->values();
                }
            }
        });

        $this->components->info($apply ? 'Spouse identity check applied.' : 'Dry run complete. Re-run with --apply to update the local database.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Housing units scanned', $counts['housing_units_scanned']],
                ['Husbands with registry spouses', $counts['registry_husbands_found']],
                ['Spouse slots checked', $counts['spouse_slots_checked']],
                ['Already matched', $counts['matched_existing']],
                [$apply ? 'Rows updated' : 'Would update', $apply ? $counts['updated_database'] : $counts['would_update']],
                ['Ambiguous name matches', $counts['ambiguous']],
                ['Existing spouse not found under husband', $counts['missing_registry_match']],
                ['Units without registry spouses', $counts['no_registry_spouses']],
                ['Registry spouses without empty slot', $counts['no_empty_slot']],
            ],
        );

        if ($previewRows->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Unit', 'Field', 'Current ID', 'Suggested ID', 'Suggested Name', 'Reason'],
                $previewRows->map(fn (array $row): array => [
                    $row['objectid'],
                    $row['identity_field'],
                    $row['current_id'],
                    $row['suggested_id'],
                    $row['suggested_name'],
                    $row['reason'],
                ])->all(),
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function unitObjectIds(): array
    {
        return collect($this->option('unit'))
            ->flatMap(fn ($value): array => preg_split('/[\s,]+/', (string) $value) ?: [])
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => ctype_digit($value))
            ->map(fn (string $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, string>  $husbandIdNumbers
     * @return Collection<string, Collection<int, object>>
     */
    private function citizenSpousesByHusbandId(Collection $husbandIdNumbers): Collection
    {
        if ($husbandIdNumbers->isEmpty()) {
            return collect();
        }

        return DB::table($this->citizensTable())
            ->select(['id_card_no', 'full_name', 'husband_id'])
            ->where('status', 'A')
            ->whereIn('husband_id', $husbandIdNumbers)
            ->orderBy('id_card_no')
            ->get()
            ->groupBy(fn ($record): string => trim((string) $record->husband_id))
            ->map(fn (Collection $records): Collection => $records
                ->map(function ($record): object {
                    return (object) [
                        'id_card_no' => trim((string) $record->id_card_no),
                        'full_name' => trim((string) $record->full_name),
                    ];
                })
                ->filter(fn (object $record): bool => $record->id_card_no !== '')
                ->unique('id_card_no')
                ->values()
            );
    }

    /**
     * @param  Collection<string, Collection<int, object>>  $registrySpousesByHusbandId
     * @return array{counts: array<string, int>, preview_rows: Collection<int, array<string, mixed>>}
     */
    private function checkHousingUnit(HousingUnit $housingUnit, Collection $registrySpousesByHusbandId, bool $apply): array
    {
        $counts = [
            'spouse_slots_checked' => 0,
            'matched_existing' => 0,
            'would_update' => 0,
            'updated_database' => 0,
            'ambiguous' => 0,
            'missing_registry_match' => 0,
            'no_registry_spouses' => 0,
            'no_empty_slot' => 0,
        ];
        $previewRows = collect();
        $husbandIdNumber = trim((string) $housingUnit->id_number1);
        $registrySpouses = $registrySpousesByHusbandId->get($husbandIdNumber, collect());

        if ($registrySpouses->isEmpty()) {
            $counts['no_registry_spouses']++;

            return ['counts' => $counts, 'preview_rows' => $previewRows];
        }

        $updates = [];
        $matchedRegistryIds = [];
        $emptySlots = [];

        foreach (range(1, 4) as $index) {
            $nameField = 'spouse'.$index;
            $identityField = 'spouse'.$index.'_id';
            $currentName = trim((string) $housingUnit->{$nameField});
            $currentId = trim((string) $housingUnit->{$identityField});

            if ($currentName === '' && $currentId === '') {
                $emptySlots[] = $index;

                continue;
            }

            $counts['spouse_slots_checked']++;
            $registryMatchById = $registrySpouses->first(fn (object $record): bool => $record->id_card_no === $currentId);

            if ($registryMatchById !== null) {
                $matchedRegistryIds[] = $registryMatchById->id_card_no;
                $counts['matched_existing']++;

                if ($currentName === '' && $registryMatchById->full_name !== '') {
                    $updates[$nameField] = $registryMatchById->full_name;
                }

                continue;
            }

            $nameMatches = $registrySpouses
                ->filter(fn (object $record): bool => $this->namesMatch($currentName, $record->full_name))
                ->values();

            if ($nameMatches->count() === 1) {
                $registryMatch = $nameMatches->first();
                $matchedRegistryIds[] = $registryMatch->id_card_no;
                $updates[$identityField] = $registryMatch->id_card_no;

                if ($currentName === '' && $registryMatch->full_name !== '') {
                    $updates[$nameField] = $registryMatch->full_name;
                }

                $counts['would_update']++;
                $previewRows->push($this->previewRow($housingUnit, $identityField, $currentId, $registryMatch, 'name_match'));

                continue;
            }

            if ($nameMatches->count() > 1) {
                $counts['ambiguous']++;

                continue;
            }

            $counts['missing_registry_match']++;
        }

        $remainingRegistrySpouses = $registrySpouses
            ->reject(fn (object $record): bool => in_array($record->id_card_no, $matchedRegistryIds, true))
            ->values();

        if ($counts['missing_registry_match'] > 0 || $counts['ambiguous'] > 0) {
            $counts['no_empty_slot'] += $remainingRegistrySpouses->count();
            $remainingRegistrySpouses = collect();
        }

        foreach ($remainingRegistrySpouses as $registrySpouse) {
            $slot = array_shift($emptySlots);

            if ($slot === null) {
                $counts['no_empty_slot']++;

                continue;
            }

            $nameField = 'spouse'.$slot;
            $identityField = 'spouse'.$slot.'_id';
            $updates[$nameField] = $registrySpouse->full_name;
            $updates[$identityField] = $registrySpouse->id_card_no;
            $counts['would_update']++;
            $previewRows->push($this->previewRow($housingUnit, $identityField, '', $registrySpouse, 'empty_slot'));
        }

        if ($apply && $updates !== []) {
            $updatedIdentities = collect(array_keys($updates))
                ->filter(fn (string $field): bool => str_ends_with($field, '_id'))
                ->count();

            DB::transaction(function () use ($housingUnit, $updates): void {
                $housingUnit->forceFill($updates)->save();
            });

            $counts['updated_database'] += $updatedIdentities;
        }

        return ['counts' => $counts, 'preview_rows' => $previewRows];
    }

    /**
     * @return array<string, mixed>
     */
    private function previewRow(HousingUnit $housingUnit, string $identityField, string $currentId, object $registrySpouse, string $reason): array
    {
        return [
            'objectid' => $housingUnit->objectid,
            'identity_field' => $identityField,
            'current_id' => $currentId !== '' ? $currentId : '-',
            'suggested_id' => $registrySpouse->id_card_no,
            'suggested_name' => $registrySpouse->full_name !== '' ? $registrySpouse->full_name : '-',
            'reason' => $reason,
        ];
    }

    private function namesMatch(string $housingSpouseName, string $registrySpouseName): bool
    {
        $normalizedHousingName = ArabicNameNormalizer::normalize($housingSpouseName);
        $normalizedRegistryName = ArabicNameNormalizer::normalize($registrySpouseName);

        if ($normalizedHousingName === '' || $normalizedRegistryName === '') {
            return false;
        }

        if ($normalizedHousingName === $normalizedRegistryName) {
            return true;
        }

        $housingNameParts = $this->normalizedNameParts($housingSpouseName);
        $registryNameParts = $this->normalizedNameParts($registrySpouseName);

        if ($housingNameParts->count() < 2 || $registryNameParts->count() < 2) {
            return false;
        }

        $shorterNameParts = $housingNameParts->count() <= $registryNameParts->count()
            ? $housingNameParts
            : $registryNameParts;
        $longerNameParts = $housingNameParts->count() <= $registryNameParts->count()
            ? $registryNameParts
            : $housingNameParts;

        return $shorterNameParts
            ->diff($longerNameParts)
            ->isEmpty();
    }

    private function normalizedNameParts(string $name): Collection
    {
        return collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->map(fn (string $part): string => ArabicNameNormalizer::normalize($part))
            ->filter()
            ->values();
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }
}
