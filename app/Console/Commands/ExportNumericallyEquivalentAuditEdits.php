<?php

namespace App\Console\Commands;

use App\Exports\NumericallyEquivalentAuditEditsExport;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExportNumericallyEquivalentAuditEdits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit-edits:export-numeric-equivalent
        {target=all : all, building, or housing}
        {--export= : Export path. Leave empty to use the default storage path}
        {--base-url= : Base URL used for Audit URL values in the export}
        {--chunk=500 : Number of rows to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export audit edits where the edit value and source value differ as text but are equal as numbers.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        try {
            $targets = $this->resolveTargets((string) $this->argument('target'));
            $this->ensureRequiredTablesExist($targets);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = collect();
        $counts = [];

        foreach ($targets as $target) {
            $targetCount = 0;

            $this->candidateRowsQuery($target)
                ->orderBy('ea.id')
                ->chunkById($chunkSize, function ($candidateRows) use (&$rows, &$targetCount, $target): void {
                    foreach ($candidateRows as $candidateRow) {
                        $sourceValue = $this->sourceValue($candidateRow);
                        $editValue = $candidateRow->edit_field_value;

                        if (! $this->isTextDifferentButNumericEquivalent($editValue, $sourceValue)) {
                            continue;
                        }

                        $rows->push($this->formatExportRow($candidateRow, $sourceValue, $target['label']));
                        $targetCount++;
                    }
                }, 'ea.id', 'id');

            $counts[] = [$target['label'], $targetCount];
        }

        $path = $this->exportPath();
        Excel::store(new NumericallyEquivalentAuditEditsExport($rows), $path, 'local');

        $this->components->info('Export created: '.Storage::disk('local')->path($path));
        $this->table(['Target', 'Rows exported'], $counts);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{type: string, source: string, label: string}>
     */
    private function resolveTargets(string $target): array
    {
        $availableTargets = [
            'building' => [
                'type' => 'building_table',
                'source' => 'buildings',
                'label' => 'Buildings',
            ],
            'housing' => [
                'type' => 'housing_table',
                'source' => 'housing_units',
                'label' => 'Housing units',
            ],
        ];

        if ($target === 'all') {
            return array_values($availableTargets);
        }

        if (! array_key_exists($target, $availableTargets)) {
            throw new InvalidArgumentException('Target must be all, building, or housing.');
        }

        return [$availableTargets[$target]];
    }

    /**
     * @param  array<int, array{type: string, source: string, label: string}>  $targets
     */
    private function ensureRequiredTablesExist(array $targets): void
    {
        if (! Schema::hasTable('edit_assessments')) {
            throw new InvalidArgumentException('Required table edit_assessments does not exist.');
        }

        foreach ($targets as $target) {
            if (! $this->relationExists($target['source'])) {
                throw new InvalidArgumentException("Required table {$target['source']} does not exist.");
            }
        }
    }

    private function relationExists(string $name): bool
    {
        if (Schema::hasTable($name)) {
            return true;
        }

        try {
            DB::table($name)->limit(1)->exists();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array{type: string, source: string, label: string}  $target
     */
    private function candidateRowsQuery(array $target): Builder
    {
        return DB::table('edit_assessments as ea')
            ->join($target['source'].' as source_records', 'ea.global_id', '=', 'source_records.globalid')
            ->leftJoin('edit_assessments as next_edit', function ($join): void {
                $join->on('next_edit.id', '=', DB::raw('(
                    select min(next_lookup.id)
                    from edit_assessments as next_lookup
                    where next_lookup.global_id = ea.global_id
                        and next_lookup.type = ea.type
                        and next_lookup.field_name = ea.field_name
                        and next_lookup.id > ea.id
                )'));
            })
            ->where('ea.type', $target['type'])
            ->whereNotNull('ea.field_value')
            ->whereIn('ea.field_name', $this->comparableColumns($target['source']))
            ->select([
                'source_records.*',
                'ea.id as id',
                'ea.id as edit_assessment_id',
                'ea.type',
                'ea.global_id',
                'ea.field_name',
                'ea.field_value as edit_field_value',
                'ea.created_at as edit_created_at',
                'ea.updated_at as edit_updated_at',
                'next_edit.id as next_edit_id',
                'next_edit.field_value as next_field_value',
                'next_edit.created_at as next_created_at',
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function comparableColumns(string $sourceTable): array
    {
        return collect(Schema::getColumnListing($sourceTable))
            ->reject(fn (string $column): bool => in_array($column, [
                'id',
                'objectid',
                'globalid',
                'parentglobalid',
                'created_at',
                'updated_at',
                'deleted_at',
            ], true))
            ->values()
            ->all();
    }

    private function sourceValue(object $row): mixed
    {
        $fieldName = (string) $row->field_name;

        return property_exists($row, $fieldName) ? $row->{$fieldName} : null;
    }

    private function isTextDifferentButNumericEquivalent(mixed $editValue, mixed $sourceValue): bool
    {
        if ($editValue === null || $sourceValue === null) {
            return false;
        }

        $editText = trim((string) $editValue);
        $sourceText = trim((string) $sourceValue);

        if ($editText === '' || $sourceText === '' || $editText === $sourceText) {
            return false;
        }

        $normalizedEditValue = $this->normalizeDecimalString($editText);
        $normalizedSourceValue = $this->normalizeDecimalString($sourceText);

        return $normalizedEditValue !== null
            && $normalizedSourceValue !== null
            && $normalizedEditValue === $normalizedSourceValue;
    }

    private function normalizeDecimalString(string $value): ?string
    {
        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $value)) {
            return null;
        }

        $isNegative = str_starts_with($value, '-');
        $unsignedValue = ltrim($value, '+-');
        [$integer, $fraction] = array_pad(explode('.', $unsignedValue, 2), 2, '');

        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim($fraction, '0');

        $normalized = $fraction === '' ? $integer : "{$integer}.{$fraction}";

        if ($normalized === '0') {
            return '0';
        }

        return $isNegative ? "-{$normalized}" : $normalized;
    }

    private function formatExportRow(object $row, mixed $sourceValue, string $targetLabel): array
    {
        return [
            'target' => $targetLabel,
            'edit_assessment_id' => $row->edit_assessment_id,
            'type' => $row->type,
            'global_id' => $row->global_id,
            'objectid' => $row->objectid,
            'parentglobalid' => property_exists($row, 'parentglobalid') ? $row->parentglobalid : null,
            'audit_url' => $this->auditUrl($row),
            'field_name' => $row->field_name,
            'edit_value' => $row->edit_field_value,
            'source_value' => $sourceValue,
            'normalized_edit_value' => $this->normalizeDecimalString(trim((string) $row->edit_field_value)),
            'normalized_source_value' => $this->normalizeDecimalString(trim((string) $sourceValue)),
            'edit_created_at' => $row->edit_created_at,
            'edit_updated_at' => $row->edit_updated_at,
            'has_later_edit_for_same_field' => $row->next_edit_id === null ? 'No' : 'Yes',
            'next_edit_id' => $row->next_edit_id,
            'next_value' => $row->next_field_value,
            'next_edit_created_at' => $row->next_created_at,
        ];
    }

    private function auditUrl(object $row): string
    {
        $baseUrl = $this->baseUrl();
        $parentGlobalId = property_exists($row, 'parentglobalid') ? $row->parentglobalid : null;

        if ($row->type === 'housing_table' && filled($parentGlobalId)) {
            return $baseUrl.'/damage-assessment/showAssessmentAudit/'.rawurlencode((string) $parentGlobalId).'/'.rawurlencode((string) $row->global_id);
        }

        return $baseUrl.'/damage-assessment/showAssessmentAudit/'.rawurlencode((string) $row->global_id);
    }

    private function baseUrl(): string
    {
        $baseUrl = trim((string) ($this->option('base-url') ?: config('app.url')));

        return rtrim($baseUrl, '/');
    }

    private function exportPath(): string
    {
        $path = trim((string) $this->option('export'));

        if ($path === '') {
            return 'audit-edit-reports/numeric-equivalent-audit-edits-'.now()->format('Y-m-d-His').'.xlsx';
        }

        return $path;
    }
}
