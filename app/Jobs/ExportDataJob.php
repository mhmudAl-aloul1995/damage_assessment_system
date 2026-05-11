<?php

namespace App\Jobs;

use App\Models\Export;
use App\Support\Exports\ExportDataColumns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public int $tries = 3;

    public int $timeout = 0;

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $export = Export::find($this->exportId);

        if (! $export || in_array($export->status, ['cancelled', 'done', 'failed'], true)) {
            return;
        }
        /**f */
        try {
            ini_set('memory_limit', '-1');
            set_time_limit(0);

            \Log::info('Export started', ['id' => $export->id]);

            $export->update([
                'status' => 'processing',
                'progress' => 0,
                'processed' => 0,
                'file_name' => null,
            ]);

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
            $filters = $params['filters'] ?? [];
            $importedObjectIds = collect($params['imported_object_ids'] ?? [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $familyMembersFrom = $params['family_members_from'] ?? null;
            $familyMembersTo = $params['family_members_to'] ?? null;

            $buildingUnitsCountColumn = ExportDataColumns::BUILDING_UNITS_COUNT_COLUMN;
            $needsHousingUnitsCount = in_array($buildingUnitsCountColumn, $buildingColumns, true);
            $needsHousingJoin = ! empty($housingColumns);
            $needsFamily = ! is_null($familyMembersFrom) || ! is_null($familyMembersTo);
            $paginateByHousing = $needsHousingJoin;

            $assessmentLabels = DB::table('assessments')
                ->whereNotNull('name')
                ->select('name', 'label')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [trim($item->name) => trim($item->label ?? '')];
                })
                ->toArray();

            $query = DB::table('v_buildings_audited as b');

            if ($needsHousingJoin) {
                $query->leftJoin('v_housing_units_audited as h', 'b.globalid', '=', 'h.parentglobalid');
            }

            if ($needsHousingUnitsCount) {
                $housingUnitsCountSub = DB::table('v_housing_units_audited as hu_count')
                    ->selectRaw('hu_count.parentglobalid, COUNT(*) as housing_units_count')
                    ->groupBy('hu_count.parentglobalid');

                $query->leftJoinSub($housingUnitsCountSub, 'housing_counts', function ($join) {
                    $join->on('b.globalid', '=', 'housing_counts.parentglobalid');
                });
            }

            if ($needsFamily) {
                $familySub = DB::table('v_housing_units_audited as hf')
                    ->selectRaw("
                        hf.parentglobalid,
                        (
                            COALESCE(CAST(NULLIF(hf.mchildren_001, '') AS UNSIGNED), 0) +
                            COALESCE(CAST(NULLIF(hf.melderly, '') AS UNSIGNED), 0) +
                            COALESCE(CAST(NULLIF(hf.myoung, '') AS UNSIGNED), 0) +
                            COALESCE(CAST(NULLIF(hf.fchildren, '') AS UNSIGNED), 0) +
                            COALESCE(CAST(NULLIF(hf.fyoung_001, '') AS UNSIGNED), 0) +
                            COALESCE(CAST(NULLIF(hf.felderly, '') AS UNSIGNED), 0)
                        ) as family_members_total
                    ");

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
                    $selects[] = 'COALESCE(housing_counts.housing_units_count, 0) as `building_housing_units_count`';

                    continue;
                }

                if (Schema::hasColumn('v_buildings_audited', $column)) {
                    $selects[] = "b.`{$column}` as `building_{$column}`";
                }
            }

            foreach ($housingColumns as $column) {
                if (Schema::hasColumn('v_housing_units_audited', $column)) {
                    $selects[] = "h.`{$column}` as `housing_{$column}`";
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

                if (Schema::hasColumn('v_buildings_audited', $field)) {
                    $query->whereIn("b.$field", $values);
                } elseif (Schema::hasColumn('v_housing_units_audited', $field)) {
                    $query->whereIn("h.$field", $values);
                }
            }

            if (! empty($importedObjectIds)) {
                $query->where(function ($nested) use ($importedObjectIds, $needsHousingJoin) {
                    $nested->whereIn('b.objectid', $importedObjectIds);

                    if ($needsHousingJoin) {
                        $nested->orWhereIn('h.objectid', $importedObjectIds);
                    }
                });
            }

            \Log::info('Export Query', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            $export->update([
                'progress' => 1,
            ]);

            $fileName = 'exports/export_'.now()->timestamp.'.xlsx';
            $fullPath = storage_path('app/public/'.$fileName);

            if (! is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0777, true);
            }

            $generator = function () use ($query, $paginateByHousing, $export) {
                $lastId = 0;
                $limit = 500;
                $batchNumber = 0;

                while (true) {
                    $export->refresh();

                    if ($export->status === 'cancelled') {
                        \Log::warning('Export cancelled mid-process');

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

                    \Log::info('Export batch query started', [
                        'export_id' => $export->id,
                        'batch' => $batchNumber,
                        'last_id' => $lastId,
                        'paginate_by_housing' => $paginateByHousing,
                    ]);

                    $rows = $batchQuery->limit($limit)->get();

                    \Log::info('Export batch query finished', [
                        'export_id' => $export->id,
                        'batch' => $batchNumber,
                        'rows' => $rows->count(),
                        'execution_ms' => round((microtime(true) - $batchStartedAt) * 1000, 2),
                    ]);

                    if ($rows->isEmpty()) {
                        break;
                    }

                    $buildingGlobalIds = [];
                    $housingGlobalIds = [];

                    foreach ($rows as $row) {
                        if (! empty($row->export_building_globalid)) {
                            $buildingGlobalIds[] = $row->export_building_globalid;
                        }

                        if ($paginateByHousing && ! empty($row->export_housing_globalid)) {
                            $housingGlobalIds[] = $row->export_housing_globalid;
                        }
                    }

                    $editsStartedAt = microtime(true);

                    $buildingEdits = $this->loadLatestEdits(
                        array_values(array_unique($buildingGlobalIds)),
                        'building_table'
                    );

                    $housingEdits = $paginateByHousing
                        ? $this->loadLatestEdits(
                            array_values(array_unique($housingGlobalIds)),
                            'housing_table'
                        )
                        : [];

                    \Log::info('Export batch edits loaded', [
                        'export_id' => $export->id,
                        'batch' => $batchNumber,
                        'building_global_ids' => count(array_unique($buildingGlobalIds)),
                        'housing_global_ids' => count(array_unique($housingGlobalIds)),
                        'execution_ms' => round((microtime(true) - $editsStartedAt) * 1000, 2),
                    ]);

                    foreach ($rows as $row) {
                        $rowArray = (array) $row;

                        $buildingGlobalId = $rowArray['export_building_globalid'] ?? null;
                        $housingGlobalId = $rowArray['export_housing_globalid'] ?? null;

                        if ($buildingGlobalId && isset($buildingEdits[$buildingGlobalId])) {
                            foreach ($buildingEdits[$buildingGlobalId] as $fieldName => $fieldValue) {
                                $key = 'building_'.$fieldName;
                                if (array_key_exists($key, $rowArray)) {
                                    $rowArray[$key] = $fieldValue;
                                }
                            }
                        }

                        if ($paginateByHousing && $housingGlobalId && isset($housingEdits[$housingGlobalId])) {
                            foreach ($housingEdits[$housingGlobalId] as $fieldName => $fieldValue) {
                                $key = 'housing_'.$fieldName;
                                if (array_key_exists($key, $rowArray)) {
                                    $rowArray[$key] = $fieldValue;
                                }
                            }
                        }

                        yield $rowArray;
                        $lastId = max($lastId, (int) $row->export_row_id);
                    }
                }
            };

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
                    'file_name' => null,
                ]);

                \Log::warning('No data for export', ['id' => $export->id]);

                return;
            }

            $export->update([
                'status' => 'done',
                'progress' => 100,
                'processed' => $processed,
                'file_name' => $fileName,
            ]);

            \Log::info('Export finished', ['id' => $export->id]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
            ]);

            \Log::error('Export failed', [
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
        $writer->openToFile($fullPath);

        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontSize(12)
            ->setFontColor('FFFFFF')
            ->setBackgroundColor('1F4E78')
            ->setCellAlignment(CellAlignment::CENTER)
            ->setShouldWrapText();

        $headers = [];
        $processed = 0;

        try {
            foreach ($rows as $row) {
                $row = (array) $row;

                if ($headers === []) {
                    $headers = $this->exportHeaders($row, $assessmentLabels);
                    $writer->addRow(Row::fromValues(array_values($headers), $headerStyle));
                }

                $writer->addRow(Row::fromValues($this->exportValues($row, array_keys($headers))));
                $processed++;

                if ($processed % 200 === 0) {
                    $export->update([
                        'progress' => min(95, max(1, (int) floor($processed / 100))),
                        'processed' => $processed,
                    ]);
                }
            }
        } finally {
            $writer->close();
        }

        return $processed;
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

    protected function loadLatestEdits(array $globalIds, string $type): array
    {
        if (empty($globalIds)) {
            return [];
        }

        $latestIds = DB::table('edit_assessments')
            ->selectRaw('global_id, field_name, MAX(id) as max_id')
            ->where('type', $type)
            ->whereIn('global_id', $globalIds)
            ->groupBy('global_id', 'field_name');

        $rows = DB::table('edit_assessments as ea1')
            ->joinSub($latestIds, 'ea2', function ($join) {
                $join->on('ea1.id', '=', 'ea2.max_id');
            })
            ->select('ea1.global_id', 'ea1.field_name', 'ea1.field_value')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[$row->global_id][$row->field_name] = $row->field_value;
        }

        return $result;
    }
}
