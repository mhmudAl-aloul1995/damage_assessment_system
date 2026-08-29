<?php

namespace App\Console\Commands;

use App\Exports\NumericallyEquivalentAuditEditsExport;
use App\Services\ArcgisAuditedCacheService;
use App\Services\ArcgisAuditedUploadService;
use App\Services\AuditEditCacheRefresher;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class DeleteNumericallyEquivalentAuditEdits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit-edits:delete-numeric-equivalent
        {target=all : all, building, or housing}
        {--dry-run : Report matching rows without deleting}
        {--export= : Export matching rows to an XLSX file. Leave empty to use the default storage path}
        {--base-url= : Base URL used for Audit URL values in the export}
        {--sync-target : Upload audited cache to the ArcGIS target after deleting}
        {--without-attachments : When syncing target, upload/update features without copying attachments}
        {--chunk=500 : Number of rows to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete audit edits that differ as text but equal the source value as numbers, only when no later edit exists.';

    /**
     * Execute the console command.
     */
    public function handle(
        AuditEditCacheRefresher $cacheRefresher,
        ArcgisAuditedCacheService $cacheService,
        ArcgisAuditedUploadService $uploadService,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        try {
            $targets = $this->resolveTargets((string) $this->argument('target'));
            $this->ensureRequiredTablesExist($targets);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $matchingRows = $this->matchingRows($targets, $chunkSize);
        $counts = $matchingRows
            ->groupBy('target')
            ->map(fn ($rows, string $target): array => [$target, $rows->count()])
            ->values()
            ->all();

        foreach ($targets as $target) {
            if (! $matchingRows->contains('target', $target['label'])) {
                $counts[] = [$target['label'], 0];
            }
        }

        if ($this->hasExportOption()) {
            $path = $this->exportPath();
            Excel::store(new NumericallyEquivalentAuditEditsExport($matchingRows), $path, 'local');
            $this->components->info('Export created: '.Storage::disk('local')->path($path));
        }

        if ($dryRun || $matchingRows->isEmpty()) {
            $this->components->info($dryRun ? 'Dry run complete.' : 'No matching numeric-equivalent edit rows found.');
            $this->table(['Target', 'Matching rows'], $counts);

            return self::SUCCESS;
        }

        $batchId = DB::table('audit_edit_deletion_batches')->insertGetId([
            'target' => (string) $this->argument('target'),
            'deleted_count' => 0,
            'criteria' => json_encode([
                'field_value' => 'text-different-numeric-equivalent',
                'has_later_edit_for_same_field' => false,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deletedCount = 0;
        $deletedByTarget = [];

        foreach ($matchingRows as $matchingRow) {
            $deletedByTarget[$matchingRow['target']] ??= 0;

            DB::transaction(function () use ($matchingRow, &$deletedCount, &$deletedByTarget, $batchId, $cacheRefresher): void {
                $row = DB::table('edit_assessments')
                    ->where('id', $matchingRow['edit_assessment_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $row || $this->hasLaterEdit($row)) {
                    return;
                }

                DB::table('audit_edit_deletion_items')->insert([
                    'batch_id' => $batchId,
                    'edit_assessment_id' => $row->id,
                    'global_id' => $row->global_id,
                    'type' => $row->type,
                    'field_name' => $row->field_name,
                    'field_value' => $row->field_value,
                    'user_id' => $row->user_id,
                    'edit_created_at' => $row->created_at,
                    'edit_updated_at' => $row->updated_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('edit_assessments')->where('id', $row->id)->delete();

                $cacheRefresher->refresh($row->type, $row->global_id, $row->field_name);

                $deletedCount++;
                $deletedByTarget[$matchingRow['target']]++;
            });
        }

        DB::table('audit_edit_deletion_batches')
            ->where('id', $batchId)
            ->update([
                'deleted_count' => $deletedCount,
                'updated_at' => now(),
            ]);

        $this->components->info('Deletion complete. Use the batch id below if you need to restore.');
        $this->table(['Metric', 'Value'], [
            ['Batch ID', $batchId],
            ['Rows deleted', $deletedCount],
        ]);
        $this->table(['Target', 'Rows deleted'], collect($deletedByTarget)->map(fn (int $count, string $label): array => [$label, $count])->values()->all());

        if ((bool) $this->option('sync-target')) {
            $this->syncTarget($targets, $cacheService, $uploadService);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{type: string, source: string, label: string}>  $targets
     */
    private function matchingRows(array $targets, int $chunkSize): \Illuminate\Support\Collection
    {
        $rows = collect();

        foreach ($targets as $target) {
            $this->candidateRowsQuery($target)
                ->orderBy('ea.id')
                ->chunkById($chunkSize, function ($candidateRows) use (&$rows, $target): void {
                    foreach ($candidateRows as $candidateRow) {
                        $sourceValue = $this->sourceValue($candidateRow);

                        if (! $this->isTextDifferentButNumericEquivalent($candidateRow->edit_field_value, $sourceValue)) {
                            continue;
                        }

                        $rows->push($this->formatExportRow($candidateRow, $sourceValue, $target['label']));
                    }
                }, 'ea.id', 'id');
        }

        return $rows;
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
        foreach (['edit_assessments', 'audit_edit_deletion_batches', 'audit_edit_deletion_items'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new InvalidArgumentException("Required table {$table} does not exist. Run migrations first.");
            }
        }

        foreach ($targets as $target) {
            if (! Schema::hasTable($target['source'])) {
                throw new InvalidArgumentException("Required table {$target['source']} does not exist.");
            }
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
            ->whereNull('next_edit.id')
            ->whereIn('ea.field_name', $this->comparableColumns($target['source']))
            ->select([
                'source_records.*',
                'ea.id as id',
                'ea.id as edit_assessment_id',
                'ea.type',
                'ea.global_id',
                'ea.field_name',
                'ea.field_value as edit_field_value',
                'ea.user_id',
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
            'has_later_edit_for_same_field' => 'No',
            'next_edit_id' => null,
            'next_value' => null,
            'next_edit_created_at' => null,
        ];
    }

    private function hasLaterEdit(object $row): bool
    {
        return DB::table('edit_assessments')
            ->where('global_id', $row->global_id)
            ->where('type', $row->type)
            ->where('field_name', $row->field_name)
            ->where('id', '>', $row->id)
            ->exists();
    }

    private function hasExportOption(): bool
    {
        return array_key_exists('export', $this->options())
            && $this->option('export') !== null;
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
            return 'audit-edit-reports/deleted-numeric-equivalent-audit-edits-'.now()->format('Y-m-d-His').'.xlsx';
        }

        return $path;
    }

    /**
     * @param  array<int, array{type: string, source: string, label: string}>  $targets
     */
    private function syncTarget(array $targets, ArcgisAuditedCacheService $cacheService, ArcgisAuditedUploadService $uploadService): void
    {
        $only = count($targets) === 1
            ? ($targets[0]['type'] === 'building_table' ? 'buildings' : 'units')
            : null;

        $this->components->info('Refreshing audited cache before syncing target...');
        $cacheService->refresh();

        $this->components->info('Syncing audited cache to ArcGIS target...');
        $summary = $uploadService->upload(
            withoutAttachments: (bool) $this->option('without-attachments'),
            only: $only,
        );

        $this->table(
            ['ArcGIS Metric', 'Value'],
            collect($summary)->map(fn (mixed $value, string $key): array => [
                $key,
                is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            ])->values()->all(),
        );
    }
}
