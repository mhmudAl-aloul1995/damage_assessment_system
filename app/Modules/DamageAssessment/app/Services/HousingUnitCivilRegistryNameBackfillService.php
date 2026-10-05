<?php

declare(strict_types=1);

namespace App\services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HousingUnitCivilRegistryNameBackfillService
{
    public function __construct(private readonly ArcgisService $arcgisService) {}

    /**
     * @param  array<int, string>  $housingColumns
     * @return array<string, int>
     */
    public function updateFilteredQuery(Builder $query, array $housingColumns, int $chunkSize = 200): array
    {
        return $this->processFilteredQuery($query, $housingColumns, $chunkSize, false);
    }

    /**
     * @param  array<int, string>  $housingColumns
     * @return array<string, int>
     */
    public function previewFilteredQuery(Builder $query, array $housingColumns, int $chunkSize = 200): array
    {
        return $this->processFilteredQuery($query, $housingColumns, $chunkSize, true);
    }

    /**
     * @param  array<int, string>  $housingColumns
     * @return array<string, int>
     */
    private function processFilteredQuery(Builder $query, array $housingColumns, int $chunkSize, bool $dryRun): array
    {
        $pairs = $this->selectedIdentityNamePairs($housingColumns);
        $counts = $this->emptyCounts();

        if ($pairs === []) {
            return $counts;
        }

        $lastId = 0;

        while (true) {
            $batchQuery = clone $query;
            $rows = $batchQuery
                ->select($this->selectColumns($pairs))
                ->where('h.id', '>', $lastId)
                ->orderBy('h.id')
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $counts['housing_units_scanned']++;
                $lastId = max($lastId, (int) $row->id);
                $updates = $this->updatesForRow($row, $pairs, $counts);

                if ($updates === []) {
                    $counts['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $counts['would_update']++;

                    continue;
                }

                DB::table('housing_units')
                    ->where('id', $row->id)
                    ->update($updates);
                $counts['updated_database']++;

                try {
                    $arcgisResult = $this->arcgisService->updateHousingUnitFields($row->objectid ?? null, $updates);
                } catch (Throwable) {
                    $arcgisResult = ['status' => 'failed'];
                }

                match ((string) ($arcgisResult['status'] ?? 'failed')) {
                    'synced' => $counts['synced_arcgis']++,
                    'skipped' => $counts['skipped_arcgis']++,
                    default => $counts['failed_arcgis']++,
                };
            }
        }

        return $counts;
    }

    /**
     * @param  array<int, string>  $housingColumns
     * @return list<array{identity_field: string, name_field: string}>
     */
    private function selectedIdentityNamePairs(array $housingColumns): array
    {
        $selected = array_flip($housingColumns);

        return collect($this->identityNamePairs())
            ->filter(fn (array $pair): bool => isset($selected[$pair['identity_field']]) || isset($selected[$pair['name_field']]))
            ->values()
            ->all();
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

    /**
     * @param  list<array{identity_field: string, name_field: string}>  $pairs
     * @return array<int, string>
     */
    private function selectColumns(array $pairs): array
    {
        $columns = ['h.id', 'h.objectid'];

        foreach ($pairs as $pair) {
            $columns[] = 'h.'.$pair['identity_field'];
            $columns[] = 'h.'.$pair['name_field'];
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param  list<array{identity_field: string, name_field: string}>  $pairs
     * @param  array<string, int>  $counts
     * @return array<string, string>
     */
    private function updatesForRow(object $row, array $pairs, array &$counts): array
    {
        $updates = [];

        foreach ($pairs as $pair) {
            $idNumber = $this->cleanIdentityNumber((string) ($row->{$pair['identity_field']} ?? ''));

            if (! $this->isUsableIdentityNumber($idNumber)) {
                continue;
            }

            $counts['identity_numbers_checked']++;
            $registryName = $this->civilRegistryFullName($idNumber);

            if ($registryName === '') {
                continue;
            }

            $counts['registry_names_found']++;
            $currentName = trim((string) ($row->{$pair['name_field']} ?? ''));

            if (! $this->shouldUpdateName($currentName, $registryName)) {
                continue;
            }

            $updates[$pair['name_field']] = $registryName;
        }

        return $updates;
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

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
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
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }
}
