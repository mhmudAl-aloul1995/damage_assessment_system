<?php

namespace App\Jobs;

use App\Models\Export;
use App\services\HousingUnitCivilRegistryNameBackfillService;
use App\Support\Exports\ExportDataColumns;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class ExportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const INTERNAL_EXPORT_COLUMNS = [
        'export_row_id',
        'export_building_globalid',
        'export_housing_globalid',
    ];

    private const HEADER_COLUMN_MIN_WIDTH = 10;

    private const HEADER_COLUMN_MAX_WIDTH = 45;

    private const HEADER_COLUMN_PADDING = 4;

    private const PROGRESS_UPDATE_ROW_INTERVAL = 50;

    public int $tries = 3;

    public int $timeout = 0;

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $claimed = Export::query()
            ->whereKey($this->exportId)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'progress' => 0,
                'processed' => 0,
                'total_rows' => null,
                'file_name' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        $export = Export::find($this->exportId);

        if (! $export) {
            return;
        }

        /**f */
        try {
            ini_set('memory_limit', '-1');
            set_time_limit(0);

            Log::info('Export started', ['id' => $export->id]);

            $params = json_decode($export->filters, true) ?: [];

            $buildingColumns = ExportDataColumns::sanitizeRequestedColumns(
                ExportDataColumns::BUILDINGS_TABLE,
                array_values($params['building_columns'] ?? []),
                [ExportDataColumns::BUILDING_UNITS_COUNT_COLUMN],
            );
            $housingColumns = ExportDataColumns::sanitizeRequestedColumns(
                ExportDataColumns::HOUSING_UNITS_TABLE,
                array_values($params['housing_columns'] ?? []),
            );
            ExportDataColumns::appendRequestedAuditNoteColumns($buildingColumns, $housingColumns, $params);
            $filters = $params['filters'] ?? [];
            $importedObjectIds = collect($params['imported_object_ids'] ?? [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $importedObjectIdTarget = ($params['imported_object_id_target'] ?? 'building') === 'housing_unit'
                ? 'housing_unit'
                : 'building';

            $familyMembersFrom = $params['family_members_from'] ?? null;
            $familyMembersTo = $params['family_members_to'] ?? null;
            $buildingEndFrom = $params['building_end_from'] ?? null;
            $buildingEndTo = $params['building_end_to'] ?? null;

            $buildingUnitsCountColumn = ExportDataColumns::BUILDING_UNITS_COUNT_COLUMN;
            $needsHousingUnitsCount = in_array($buildingUnitsCountColumn, $buildingColumns, true);
            $needsHousingJoin = ! empty($housingColumns) || (! empty($importedObjectIds) && $importedObjectIdTarget === 'housing_unit');
            $needsFamily = ! is_null($familyMembersFrom) || ! is_null($familyMembersTo);
            $paginateByHousing = $needsHousingJoin;
            $buildingsSource = ExportDataColumns::BUILDINGS_TABLE;
            $housingUnitsSource = ExportDataColumns::HOUSING_UNITS_TABLE;

            $assessmentLabels = DB::table('assessments')
                ->whereNotNull('name')
                ->select('name', 'label')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [trim($item->name) => trim($item->label ?? '')];
                })
                ->toArray();

            $query = $needsHousingJoin
                ? DB::table("{$housingUnitsSource} as h")->join("{$buildingsSource} as b", 'b.globalid', '=', 'h.parentglobalid')
                : DB::table("{$buildingsSource} as b");

            $buildingEndExpression = $this->editedColumnExpression('building_table', 'b', 'globalid', 'end');

            if ($buildingEndFrom !== null && $buildingEndFrom !== '') {
                $query->whereDate(DB::raw($buildingEndExpression), '>=', $buildingEndFrom);
            }

            if ($buildingEndTo !== null && $buildingEndTo !== '') {
                $query->whereDate(DB::raw($buildingEndExpression), '<=', $buildingEndTo);
            }

            if ($needsFamily) {
                $familySub = DB::table("{$housingUnitsSource} as hf")
                    ->selectRaw($this->familyMembersSelectExpression());

                $query->leftJoinSub($familySub, 'fam', function ($join) {
                    $join->on('b.globalid', '=', 'fam.parentglobalid');
                });

                if (! is_null($familyMembersFrom)) {
                    $query->where('fam.family_members_total', '>=', (int) $familyMembersFrom);
                }

                if (! is_null($familyMembersTo)) {
                    $query->where('fam.family_members_total', '<=', (int) $familyMembersTo);
                }
            }

            $selects = [
                $paginateByHousing
                ? 'h.objectid as export_row_id'
                : 'b.objectid as export_row_id',
                'b.globalid as export_building_globalid',
            ];

            if ($paginateByHousing) {
                $selects[] = 'h.globalid as export_housing_globalid';
            }

            foreach ($buildingColumns as $column) {
                if ($column === $buildingUnitsCountColumn) {
                    $selects[] = "(SELECT COUNT(*) FROM {$housingUnitsSource} hu_count WHERE hu_count.parentglobalid = b.globalid) as `building_housing_units_count`";

                    continue;
                }

                if (ExportDataColumns::isAuditNoteColumn($column)) {
                    $selects[] = $this->auditNoteColumnExpression('building_statuses', 'building_id', 'b.objectid', $column)." as `building_{$column}`";
                } elseif (ExportDataColumns::hasColumn($buildingsSource, $column)) {
                    $selects[] = $this->editedColumnExpression('building_table', 'b', 'globalid', $column)." as `building_{$column}`";
                }
            }

            foreach ($housingColumns as $column) {
                if (ExportDataColumns::isAuditNoteColumn($column)) {
                    $selects[] = $this->auditNoteColumnExpression('housing_statuses', 'housing_id', 'h.objectid', $column)." as `housing_{$column}`";
                } elseif (ExportDataColumns::hasColumn($housingUnitsSource, $column)) {
                    $selects[] = $this->editedColumnExpression('housing_table', 'h', 'globalid', $column)." as `housing_{$column}`";
                }
            }

            if ($needsFamily) {
                $selects[] = 'fam.family_members_total as family_members_total';
            }

            $query->selectRaw(implode(', ', $selects));

            foreach ($filters as $field => $values) {
                $values = array_filter((array) $values, fn ($value) => $value !== null && $value !== '');

                if (empty($values)) {
                    continue;
                }

                if ($field === 'building_states_auditig') {
                    $query->whereExists(function ($sub) use ($values) {
                        $sub->select(DB::raw(1))
                            ->from('building_statuses as bs')
                            ->whereColumn('bs.building_id', 'b.objectid')
                            ->whereIn('bs.status_id', $values);
                    });

                    continue;
                }

                if (ExportDataColumns::hasColumn($buildingsSource, $field)) {
                    $query->whereIn(DB::raw($this->editedColumnExpression('building_table', 'b', 'globalid', $field)), $values);
                } elseif (ExportDataColumns::hasColumn($housingUnitsSource, $field)) {
                    $query->whereIn(DB::raw($this->editedColumnExpression('housing_table', 'h', 'globalid', $field)), $values);
                }
            }

            $this->applyAuditNotesPresenceFilter(
                $query,
                (string) ($params['legal_notes_filter'] ?? ''),
                'Legal Auditor',
                $needsHousingJoin,
            );
            $this->applyAuditNotesPresenceFilter(
                $query,
                (string) ($params['engineering_notes_filter'] ?? ''),
                'QC/QA Engineer',
                $needsHousingJoin,
            );

            if (! empty($importedObjectIds)) {
                $query->whereIn(
                    $importedObjectIdTarget === 'housing_unit' ? 'h.objectid' : 'b.objectid',
                    $importedObjectIds,
                );
            }

            if ($needsHousingJoin && $this->truthy($params['update_housing_names_from_civil_registry'] ?? null)) {
                $backfillCounts = app(HousingUnitCivilRegistryNameBackfillService::class)
                    ->updateFilteredQuery(clone $query, $housingColumns);

                Log::info('Export civil registry housing name backfill finished', [
                    'export_id' => $export->id,
                    ...$backfillCounts,
                ]);
            }

            Log::info('Export query prepared', [
                'export_id' => $export->id,
                'bindings_count' => count($query->getBindings()),
                'paginate_by_housing' => $paginateByHousing,
                'building_columns' => count($buildingColumns),
                'housing_columns' => count($housingColumns),
                'imported_object_id_target' => $importedObjectIdTarget,
                'source' => 'base_tables',
            ]);

            $totalRows = $this->countExportRows($query, $paginateByHousing);

            $export->update([
                'progress' => 1,
                'total_rows' => $totalRows,
            ]);

            $fileName = 'exports/export_'.now()->timestamp.'.xlsx';
            $fullPath = storage_path('app/public/'.$fileName);

            if (! is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0777, true);
            }

            $generator = function () use ($query, $paginateByHousing, $export) {
                $lastId = 0;
                $limit = 1000;
                $batchNumber = 0;

                while (true) {
                    $export->refresh();

                    if ($export->status === 'cancelled') {
                        Log::warning('Export cancelled mid-process');

                        return;
                    }

                    $batchQuery = clone $query;

                    if ($paginateByHousing) {
                        $batchQuery->where('h.objectid', '>', $lastId)
                            ->orderBy('h.objectid');
                    } else {
                        $batchQuery->where('b.objectid', '>', $lastId)
                            ->orderBy('b.objectid');
                    }

                    $batchNumber++;
                    $batchStartedAt = microtime(true);

                    Log::info('Export batch query started', [
                        'export_id' => $export->id,
                        'batch' => $batchNumber,
                        'last_id' => $lastId,
                        'paginate_by_housing' => $paginateByHousing,
                    ]);

                    $rows = $batchQuery->limit($limit)->get();

                    Log::info('Export batch query finished', [
                        'export_id' => $export->id,
                        'batch' => $batchNumber,
                        'rows' => $rows->count(),
                        'execution_ms' => round((microtime(true) - $batchStartedAt) * 1000, 2),
                    ]);

                    if ($rows->isEmpty()) {
                        break;
                    }

                    foreach ($rows as $row) {
                        $rowArray = (array) $row;

                        yield $rowArray;
                        $lastId = max($lastId, (int) $row->export_row_id);
                    }
                }
            };

            Log::info('Export file write starting', [
                'export_id' => $export->id,
                'path' => $fullPath,
            ]);

            $processed = $this->writeExportFile(
                $fullPath,
                $generator(),
                $assessmentLabels,
                $export,
            );

            if ($processed === 0) {
                if (is_file($fullPath)) {
                    unlink($fullPath);
                }

                $export->update([
                    'status' => 'done',
                    'progress' => 100,
                    'processed' => 0,
                    'total_rows' => $totalRows,
                    'file_name' => null,
                ]);

                Log::warning('No data for export', [
                    'id' => $export->id,
                    'user_id' => $export->user_id,
                    'filters' => $filters,
                    'imported_object_ids_count' => count($importedObjectIds),
                    'building_columns_count' => count($buildingColumns),
                    'housing_columns_count' => count($housingColumns),
                    'family_members_from' => $familyMembersFrom,
                    'family_members_to' => $familyMembersTo,
                    'building_end_from' => $buildingEndFrom,
                    'building_end_to' => $buildingEndTo,
                ]);

                return;
            }

            $export->update([
                'status' => 'done',
                'progress' => 100,
                'processed' => $processed,
                'total_rows' => $totalRows,
                'file_name' => $fileName,
            ]);

            Log::info('Export finished', ['id' => $export->id]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
            ]);

            Log::error('Export failed', [
                'export_id' => $this->exportId,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function writeExportFile(string $fullPath, iterable $rows, array $assessmentLabels, Export $export): int
    {
        $writer = new Writer;

        $this->logInfo('Export writer opening', [
            'export_id' => $export->id,
        ]);

        $writer->openToFile($fullPath);

        $this->logInfo('Export writer opened', [
            'export_id' => $export->id,
        ]);

        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontSize(12)
            ->setFontColor('FFFFFF')
            ->setBackgroundColor('1F4E78')
            ->setCellAlignment(CellAlignment::CENTER);
        $dataStyle = (new Style)
            ->setShouldWrapText(false);

        $headers = [];
        $processed = 0;
        $totalRows = (int) ($export->total_rows ?? 0);

        try {
            foreach ($rows as $row) {
                $row = (array) $row;

                if ($headers === []) {
                    $headers = $this->exportHeaders($row, $assessmentLabels);
                    $this->applyHeaderColumnWidths($writer, array_values($headers));
                    $writer->addRow(Row::fromValues(array_values($headers), $headerStyle));
                }

                $writer->addRow(Row::fromValues($this->exportValues($row, array_keys($headers)), $dataStyle));
                $processed++;

                if ($export->exists && ($processed === 1 || $processed % self::PROGRESS_UPDATE_ROW_INTERVAL === 0)) {
                    $export->update([
                        'progress' => $this->exportProgressPercent($processed, $totalRows),
                        'processed' => $processed,
                    ]);
                }
            }
        } finally {
            $writer->close();
        }

        return $processed;
    }

    private function countExportRows(mixed $query, bool $paginateByHousing): int
    {
        $countColumn = $paginateByHousing ? 'h.objectid' : 'b.objectid';

        return (int) (clone $query)
            ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->distinct()
            ->count($countColumn);
    }

    private function exportProgressPercent(int $processed, int $totalRows): int
    {
        if ($totalRows > 0) {
            return min(95, max(1, (int) floor(($processed / $totalRows) * 95)));
        }

        return min(95, max(1, (int) floor($processed / 100)));
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function applyHeaderColumnWidths(Writer $writer, array $headers): void
    {
        foreach ($headers as $index => $header) {
            $writer->getCurrentSheet()->setColumnWidth(
                $this->headerColumnWidth($header),
                $index + 1,
            );
        }
    }

    private function headerColumnWidth(string $header): float
    {
        $length = mb_strlen($header);

        return (float) min(
            self::HEADER_COLUMN_MAX_WIDTH,
            max(self::HEADER_COLUMN_MIN_WIDTH, $length + self::HEADER_COLUMN_PADDING),
        );
    }

    private function editedColumnExpression(string $type, string $tableAlias, string $globalIdColumn, string $field): string
    {
        return 'COALESCE('.
            $this->latestEditValueExpression($type, $tableAlias, $globalIdColumn, $field).
            ", {$tableAlias}.`{$field}`)";
    }

    private function latestEditValueExpression(string $type, string $tableAlias, string $globalIdColumn, string $field): string
    {
        $escapedType = str_replace("'", "''", $type);
        $escapedField = str_replace("'", "''", $field);

        return "(SELECT ea.field_value FROM edit_assessments ea WHERE ea.type = '{$escapedType}' AND ea.global_id = {$tableAlias}.`{$globalIdColumn}` AND ea.field_name = '{$escapedField}' ORDER BY ea.id DESC LIMIT 1)";
    }

    private function familyMembersSelectExpression(): string
    {
        $fields = [
            'mchildren_001',
            'melderly',
            'myoung',
            'fchildren',
            'fyoung_001',
            'felderly',
        ];

        $membersExpression = collect($fields)
            ->map(fn (string $field): string => 'COALESCE(CAST(NULLIF('.$this->editedColumnExpression('housing_table', 'hf', 'globalid', $field).", '') AS UNSIGNED), 0)")
            ->implode(' + ');

        return "
            hf.parentglobalid,
            ({$membersExpression}) as family_members_total
        ";
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $assessmentLabels
     * @return array<string, string>
     */
    protected function exportHeaders(array $row, array $assessmentLabels): array
    {
        $headers = [];

        foreach (array_keys($row) as $header) {
            if (in_array($header, self::INTERNAL_EXPORT_COLUMNS, true)) {
                continue;
            }

            if (str_starts_with($header, 'building_')) {
                $field = str_replace('building_', '', $header);
            } elseif (str_starts_with($header, 'housing_')) {
                $field = str_replace('housing_', '', $header);
            } else {
                $field = $header;
            }

            if ($field === 'housing_units_count') {
                $headers[$header] = 'عدد الوحدات للمبنى';
            } elseif ($field === 'family_members_total') {
                $headers[$header] = 'عدد أفراد الأسرة';
            } elseif (ExportDataColumns::isAuditNoteColumn($field)) {
                $headers[$header] = ExportDataColumns::auditNoteColumnLabel($field) ?? ucwords(str_replace('_', ' ', $field));
            } else {
                $headers[$header] = $assessmentLabels[$field] ?? ucwords(str_replace('_', ' ', $field));
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $headers
     * @return array<int, bool|float|int|string|null>
     */
    protected function exportValues(array $row, array $headers): array
    {
        return collect($headers)
            ->map(fn (string $header): bool|float|int|string|null => $this->exportValue($row[$header] ?? null))
            ->values()
            ->all();
    }

    protected function exportValue(mixed $value): bool|float|int|string|null
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: null;
    }

    private function auditNoteColumnExpression(string $table, string $foreignKey, string $recordIdExpression, string $column): string
    {
        $type = in_array($column, [ExportDataColumns::LEGAL_AUDITOR_COLUMN, ExportDataColumns::LEGAL_NOTES_COLUMN], true)
            ? 'Legal Auditor'
            : 'QC/QA Engineer';

        $valueExpression = in_array($column, [ExportDataColumns::LEGAL_AUDITOR_COLUMN, ExportDataColumns::ENGINEERING_AUDITOR_COLUMN], true)
            ? 'u.name'
            : 's.notes';

        $escapedType = str_replace("'", "''", $type);

        return "(SELECT {$valueExpression} FROM {$table} s LEFT JOIN users u ON u.id = s.user_id WHERE s.{$foreignKey} = {$recordIdExpression} AND s.type = '{$escapedType}' ORDER BY s.updated_at DESC, s.id DESC LIMIT 1)";
    }

    private function applyAuditNotesPresenceFilter($query, string $filter, string $type, bool $includeHousing): void
    {
        if (! in_array($filter, ['with_notes', 'without_notes'], true)) {
            return;
        }

        $method = $filter === 'with_notes' ? 'where' : 'whereNot';

        $query->{$method}(function ($notesQuery) use ($type, $includeHousing): void {
            $notesQuery->whereExists(function ($sub) use ($type): void {
                $sub->select(DB::raw(1))
                    ->from('building_statuses as bs_notes')
                    ->whereColumn('bs_notes.building_id', 'b.objectid')
                    ->where('bs_notes.type', $type)
                    ->whereNotNull('bs_notes.notes')
                    ->where('bs_notes.notes', '<>', '');
            });

            if ($includeHousing) {
                $notesQuery->orWhereExists(function ($sub) use ($type): void {
                    $sub->select(DB::raw(1))
                        ->from('housing_statuses as hs_notes')
                        ->whereColumn('hs_notes.housing_id', 'h.objectid')
                        ->where('hs_notes.type', $type)
                        ->whereNotNull('hs_notes.notes')
                        ->where('hs_notes.notes', '<>', '');
                });
            }
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        if (! Container::getInstance()->bound('log')) {
            return;
        }

        Log::info($message, $context);
    }

    private function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
