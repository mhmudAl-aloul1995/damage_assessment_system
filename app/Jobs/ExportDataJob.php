<?php

namespace App\Jobs;

use App\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 0;

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $export = Export::find($this->exportId);

        if (! $export || $export->status === 'cancelled') {
            return;
        }

        try {
            ini_set('memory_limit', '1024M');
            set_time_limit(0);

            \Log::info('Export started', ['id' => $export->id]);

            $export->update([
                'status' => 'processing',
                'progress' => 0,
                'processed' => 0,
                'file_name' => null,
            ]);

            $params = json_decode($export->filters, true) ?: [];

            $buildingColumns = array_values($params['building_columns'] ?? []);
            $housingColumns = array_values($params['housing_columns'] ?? []);
            $filters = $params['filters'] ?? [];

            $familyMembersFrom = $params['family_members_from'] ?? null;
            $familyMembersTo = $params['family_members_to'] ?? null;
            $buildingUnitsCountColumn = 'housing_units_count';
            $needsHousingUnitsCount = in_array($buildingUnitsCountColumn, $buildingColumns, true);

            $query = DB::table('buildings as b');

            if (! empty($housingColumns)) {
                $query->leftJoin('housing_units as h', 'b.globalid', '=', 'h.parentglobalid');
            }

            $needsFamily = ! is_null($familyMembersFrom) || ! is_null($familyMembersTo);

            if ($needsHousingUnitsCount) {
                $housingUnitsCountSub = DB::table('housing_units as hu_count')
                    ->selectRaw('hu_count.parentglobalid, COUNT(*) as housing_units_count')
                    ->groupBy('hu_count.parentglobalid');

                $query->leftJoinSub($housingUnitsCountSub, 'housing_counts', function ($join) {
                    $join->on('b.globalid', '=', 'housing_counts.parentglobalid');
                });
            }

            if ($needsFamily) {
                $familySub = DB::table('housing_units as hf')
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

            $paginateByHousing = ! empty($housingColumns);

            $selects = [
                $paginateByHousing
                    ? 'h.objectid as export_row_id'
                    : 'b.objectid as export_row_id',
            ];

            foreach ($buildingColumns as $column) {
                if ($column === $buildingUnitsCountColumn) {
                    $selects[] = 'COALESCE(housing_counts.housing_units_count, 0) as `building_housing_units_count`';

                    continue;
                }

                $selects[] = "b.`{$column}` as `building_{$column}`";
            }

            foreach ($housingColumns as $column) {
                $selects[] = "h.`{$column}` as `housing_{$column}`";
            }

            if ($needsFamily) {
                $selects[] = 'fam.family_members_total as family_members_total';
            }

            $query->selectRaw(implode(', ', $selects));

            foreach ($filters as $field => $values) {
                $values = array_filter((array) $values);

                if (empty($values)) {
                    continue;
                }

                if (Schema::hasColumn('buildings', $field)) {
                    $query->whereIn("b.$field", $values);
                } elseif (Schema::hasColumn('housing_units', $field)) {
                    $query->whereIn("h.$field", $values);
                }
            }

            \Log::info('Export Query', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            $hasData = (clone $query)->exists();

            if (! $hasData) {
                $export->update([
                    'status' => 'done',
                    'progress' => 100,
                    'processed' => 0,
                    'file_name' => null,
                ]);

                \Log::warning('No data for export', ['id' => $export->id]);

                return;
            }

            $fileName = 'exports/export_'.now()->timestamp.'.xlsx';
            $fullPath = storage_path('app/public/'.$fileName);

            if (! is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0777, true);
            }

            $generator = function () use ($query, $paginateByHousing, $export) {
                $lastId = 0;
                $limit = 200;

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

                    $rows = $batchQuery->limit($limit)->get();

                    if ($rows->isEmpty()) {
                        break;
                    }

                    foreach ($rows as $row) {
                        yield (array) $row;
                        $lastId = max($lastId, (int) $row->export_row_id);
                    }
                }
            };

            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setRightToLeft(true);

            $rowNumber = 1;
            $processed = 0;
            $headersWritten = false;

            foreach ($generator() as $row) {
                $processed++;

                if (! $headersWritten) {
                    $colIndex = 1;

                    foreach (array_keys($row) as $header) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($colLetter.'1', $header);
                        $colIndex++;
                    }

                    $headersWritten = true;
                    $rowNumber++;
                }

                $colIndex = 1;

                foreach ($row as $value) {
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter.$rowNumber, $value);
                    $colIndex++;
                }

                if ($processed % 200 === 0) {
                    $export->update([
                        'progress' => min(95, $processed / 10),
                        'processed' => $processed,
                    ]);
                }

                $rowNumber++;
            }

            (new Xlsx($spreadsheet))->save($fullPath);

            $export->update([
                'status' => 'done',
                'progress' => 100,
                'processed' => $processed,
                'file_name' => $fileName,
            ]);

            \Log::info('Export finished', ['id' => $export->id]);
        } catch (\Throwable $e) {
            $export->update(['status' => 'failed']);

            \Log::error('Export failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
