<?php

namespace App\Modules\DamageAssessmentBorrowers\Services;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BorrowerDuplicateMergeService
{
    /**
     * @return array{groups: int, removed: int, merged_children: int}
     */
    public function merge(): array
    {
        $summary = [
            'groups' => 0,
            'removed' => 0,
            'merged_children' => 0,
        ];

        DB::transaction(function () use (&$summary): void {
            foreach ($this->duplicateBorrowerIdNumbers() as $borrowerIdNumber) {
                $borrowers = DamageAssessmentBorrower::query()
                    ->where('borrower_id_number', $borrowerIdNumber)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->get();

                if ($borrowers->count() < 2) {
                    continue;
                }

                $summary['groups']++;
                $summary['removed'] += $borrowers->count() - 1;
                $summary['merged_children'] += $this->mergeBorrowerGroup($borrowers);
            }
        });

        return $summary;
    }

    /**
     * @return Collection<int, string>
     */
    public function duplicateBorrowerIdNumbers(): Collection
    {
        return DamageAssessmentBorrower::query()
            ->select('borrower_id_number')
            ->whereNotNull('borrower_id_number')
            ->where('borrower_id_number', '!=', '')
            ->groupBy('borrower_id_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('borrower_id_number');
    }

    /**
     * @param  Collection<int, DamageAssessmentBorrower>  $borrowers
     */
    private function mergeBorrowerGroup(Collection $borrowers): int
    {
        /** @var DamageAssessmentBorrower $keeper */
        $keeper = $borrowers->first();
        $duplicates = $borrowers->slice(1)->values();
        $mergedChildren = 0;

        $keeper->forceFill($this->mergedBorrowerAttributes($keeper, $duplicates))->save();

        foreach ($this->childMergeDefinitions() as $definition) {
            $mergedChildren += $this->mergeChildren(
                $definition['table'],
                $definition['unique_column'],
                $definition['fillable_columns'],
                $keeper->id,
                $duplicates->pluck('id')->all(),
            );
        }

        if (Schema::hasTable('kobo_rest_submissions') && Schema::hasColumn('kobo_rest_submissions', 'damage_assessment_borrower_id')) {
            DB::table('kobo_rest_submissions')
                ->whereIn('damage_assessment_borrower_id', $duplicates->pluck('id')->all())
                ->update(['damage_assessment_borrower_id' => $keeper->id]);
        }

        $keeper->forceFill([
            'attachments_count' => Schema::hasTable('damage_assessment_borrower_attachments')
                ? DB::table('damage_assessment_borrower_attachments')->where('damage_assessment_borrower_id', $keeper->id)->count()
                : $keeper->attachments_count,
        ])->save();

        DamageAssessmentBorrower::query()
            ->whereIn('id', $duplicates->pluck('id')->all())
            ->delete();

        return $mergedChildren;
    }

    /**
     * @param  Collection<int, DamageAssessmentBorrower>  $duplicates
     * @return array<string, mixed>
     */
    private function mergedBorrowerAttributes(DamageAssessmentBorrower $keeper, Collection $duplicates): array
    {
        $attributes = $keeper->getAttributes();
        $rows = collect([$keeper])->merge($duplicates);
        $fillable = array_diff($keeper->getFillable(), ['borrower_id_number']);

        foreach ($fillable as $field) {
            $current = $attributes[$field] ?? null;

            if ($field === 'notes') {
                $attributes[$field] = $this->mergedNotes($rows->pluck($field)->all());

                continue;
            }

            if ($this->isJsonMergeField($field)) {
                $attributes[$field] = $this->mergedJsonValues($rows->pluck($field)->all());

                continue;
            }

            if (in_array($field, ['risk_level', 'risk_score', 'risk_reasons'], true)) {
                continue;
            }

            foreach ($duplicates as $duplicate) {
                $candidate = $duplicate->getAttribute($field);

                if ($this->isEmptyValue($current) && ! $this->isEmptyValue($candidate)) {
                    $attributes[$field] = $candidate;
                    $current = $candidate;
                }
            }
        }

        $highestRiskBorrower = $rows
            ->sortByDesc(fn (DamageAssessmentBorrower $borrower): int => (int) $borrower->risk_score)
            ->first();

        if ($highestRiskBorrower instanceof DamageAssessmentBorrower) {
            $attributes['risk_level'] = $highestRiskBorrower->risk_level;
            $attributes['risk_score'] = $highestRiskBorrower->risk_score;
            $attributes['risk_reasons'] = $highestRiskBorrower->risk_reasons;
        }

        $attributes['created_at'] = $rows->pluck('created_at')->filter()->min();
        $attributes['updated_at'] = now();

        return $attributes;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function mergedNotes(array $values): ?string
    {
        $notes = collect($values)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        return $notes->isEmpty() ? null : $notes->implode("\n\n");
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int|string, mixed>|null
     */
    private function mergedJsonValues(array $values): ?array
    {
        $merged = [];
        $seen = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            foreach ($value as $key => $item) {
                $identity = is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE) : (string) $item;

                if ($identity === false || isset($seen[$identity])) {
                    continue;
                }

                $seen[$identity] = true;
                $merged[is_int($key) ? count($merged) : $key] = $item;
            }
        }

        return $merged === [] ? null : $merged;
    }

    private function isJsonMergeField(string $field): bool
    {
        return in_array($field, [
            'vulnerability_types',
            'deceased_guarantors',
            'guarantors_employment_statuses',
            'affected_guarantors',
            'resident_households',
            'risk_reasons',
        ], true);
    }

    /**
     * @param  array<int, int>  $duplicateBorrowerIds
     * @param  array<int, string>  $fillableColumns
     */
    private function mergeChildren(string $table, string $uniqueColumn, array $fillableColumns, int $keeperId, array $duplicateBorrowerIds): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'damage_assessment_borrower_id')) {
            return 0;
        }

        $merged = 0;
        $children = DB::table($table)
            ->whereIn('damage_assessment_borrower_id', $duplicateBorrowerIds)
            ->orderBy('id')
            ->get();

        foreach ($children as $child) {
            $uniqueValue = $child->{$uniqueColumn} ?? null;
            $existing = null;

            if (! $this->isEmptyValue($uniqueValue)) {
                $existing = DB::table($table)
                    ->where('damage_assessment_borrower_id', $keeperId)
                    ->where($uniqueColumn, $uniqueValue)
                    ->first();
            }

            if ($existing === null) {
                DB::table($table)
                    ->where('id', $child->id)
                    ->update(['damage_assessment_borrower_id' => $keeperId]);
                $merged++;

                continue;
            }

            $updates = [];

            foreach ($fillableColumns as $column) {
                if (! property_exists($existing, $column) || ! property_exists($child, $column)) {
                    continue;
                }

                if ($this->isEmptyValue($existing->{$column}) && ! $this->isEmptyValue($child->{$column})) {
                    $updates[$column] = $child->{$column};
                }
            }

            if ($updates !== []) {
                $updates['updated_at'] = now();

                DB::table($table)
                    ->where('id', $existing->id)
                    ->update($updates);
            }

            DB::table($table)->where('id', $child->id)->delete();
            $merged++;
        }

        return $merged;
    }

    /**
     * @return array<int, array{table: string, unique_column: string, fillable_columns: array<int, string>}>
     */
    private function childMergeDefinitions(): array
    {
        return [
            [
                'table' => 'damage_assessment_borrower_boq_items',
                'unique_column' => 'source_key',
                'fillable_columns' => [
                    'catalog_item_id',
                    'source_column',
                    'item_code',
                    'description',
                    'unit',
                    'unit_price',
                    'exchange_rate',
                    'unit_price_ils',
                    'quantity',
                    'total_price',
                    'total_price_ils',
                    'sort_order',
                ],
            ],
            [
                'table' => 'damage_assessment_borrower_attachments',
                'unique_column' => 'source_index',
                'fillable_columns' => [
                    'filename',
                    'url',
                    'source_index',
                ],
            ],
            [
                'table' => 'damage_assessment_borrower_resident_households',
                'unique_column' => 'source_index',
                'fillable_columns' => [
                    'head_name',
                    'id_number',
                    'members_count',
                    'phone',
                    'employment_status',
                    'source_index',
                ],
            ],
        ];
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        if (is_numeric($value)) {
            return (float) $value === 0.0;
        }

        return false;
    }
}
