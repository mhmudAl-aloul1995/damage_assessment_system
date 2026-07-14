<?php

namespace App\Jobs;

use App\Models\Export;
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
            $filters = $params['filters'] ?? [];
            $importedObjectIds = collect($params['imported_object_ids'] ?? [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $familyMembersFrom = $params['family_members_from'] ?? null;
            $familyMembersTo = $params['family_members_to'] ?? null;
            $buildingEndFrom = $params['building_end_from'] ?? null;
            $buildingEndTo = $params['building_end_to'] ?? null;

            $buildingUnitsCountColumn = ExportDataColumns::BUILDING_UNITS_COUNT_COLUMN;
            $needsHousingUnitsCount = in_array($buildingUnitsCountColumn, $buildingColumns, true);
            $needsHousingJoin = ! empty($housingColumns);
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

            if ($buildingEndFrom !== null && $buildingEndFrom !== '') {
                $query->whereDate('b.end', '>=', $buildingEndFrom);
            }

            if ($buildingEndTo !== null && $buildingEndTo !== '') {
                $query->whereDate('b.end', '<=', $buildingEndTo);
            }

            if ($needsFamily) {
                $familySub = DB::table("{$housingUnitsSource} as hf")
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
                    $selects[] = "(SELECT COUNT(*) FROM {$housingUnitsSource} hu_count WHERE hu_count.parentglobalid = b.globalid) as `building_housing_units_count`";

                    continue;
                }

                if (ExportDataColumns::hasColumn($buildingsSource, $column)) {
                    $selects[] = "b.`{$column}` as `building_{$column}`";
                }
            }

            foreach ($housingColumns as $column) {
                if (ExportDataColumns::hasColumn($housingUnitsSource, $column)) {
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

                if (ExportDataColumns::hasColumn($buildingsSource, $field)) {
                    $query->whereIn("b.$field", $values);
                } elseif (ExportDataColumns::hasColumn($housingUnitsSource, $field)) {
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

            Log::info('Export query prepared', [
                'export_id' => $export->id,
                'bindings_count' => count($query->getBindings()),
                'paginate_by_housing' => $paginateByHousing,
                'building_columns' => count($buildingColumns),
                'housing_columns' => count($housingColumns),
                'source' => 'base_tables',
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
}
