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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 0;

    public function __construct(public int $exportId)
    {
    }

    public function handle(): void
    {
        $export = Export::find($this->exportId);

        if (!$export) {
            return;
        }

        try {
            ini_set('memory_limit', '1024M');
            set_time_limit(0);

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

            $query = DB::table('buildings as b');

            if (!empty($housingColumns)) {
                $query->leftJoin('housing_units as h', 'b.globalid', '=', 'h.parentglobalid');
            }

            $needsFamily = !is_null($familyMembersFrom) || !is_null($familyMembersTo);

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

                if (!is_null($familyMembersFrom)) {
                    $query->where('fam.family_members_total', '>=', (int) $familyMembersFrom);
                }

                if (!is_null($familyMembersTo)) {
                    $query->where('fam.family_members_total', '<=', (int) $familyMembersTo);
                }
            }

            $paginateByHousing = !empty($housingColumns);

            $selects = [
                $paginateByHousing
                    ? 'h.objectid as export_row_id'
                    : 'b.objectid as export_row_id',
            ];

            foreach ($buildingColumns as $column) {
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
                $values = array_filter((array) $values, fn($value) => $value !== null && $value !== '');

                if (empty($values)) {
                    continue;
                }

                if (Schema::hasColumn('buildings', $field)) {
                    $query->whereIn("b.$field", $values);
                } elseif (Schema::hasColumn('housing_units', $field)) {
                    $query->whereIn("h.$field", $values);
                }
            }

            $hasData = (clone $query)->exists();

            if (!$hasData) {
                $export->update([
                    'status' => 'failed',
                    'progress' => 0,
                    'processed' => 0,
                ]);

                \Log::warning('Export has no matching rows', [
                    'export_id' => $this->exportId,
                ]);

                return;
            }

            $fileName = 'exports/export_' . now()->timestamp . '.xlsx';
            $fullPath = storage_path('app/public/' . $fileName);

            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            $generator = function () use ($query, $paginateByHousing) {
                $lastId = 0;
                $loopCount = 0;
                $limit = 200;

                while (true) {
                    $loopCount++;

                    if ($loopCount > 10000) {
                        throw new \RuntimeException('Infinite loop detected during export.');
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

                    $newLastId = $lastId;

                    foreach ($rows as $row) {
                        if (!isset($row->export_row_id) || !$row->export_row_id) {
                            continue;
                        }

                        yield (array) $row;

                        if ((int) $row->export_row_id > $newLastId) {
                            $newLastId = (int) $row->export_row_id;
                        }
                    }

                    if ($newLastId === $lastId) {
                        throw new \RuntimeException('Stuck loop detected: export_row_id not changing.');
                    }

                    $lastId = $newLastId;

                    \Log::info('Export batch processed', [
                        'export_id' => $this->exportId,
                        'last_id' => $lastId,
                        'rows' => $rows->count(),
                        'loop' => $loopCount,
                        'mode' => $paginateByHousing ? 'housing_objectid' : 'building_objectid',
                    ]);
                }
            };

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setRightToLeft(true);

            $rowNumber = 1;
            $processed = 0;
            $headersWritten = false;
            $lastColLetter = 'A';

            foreach ($generator() as $row) {
                $processed++;

                if (!$headersWritten) {
                    $colIndex = 1;

                    foreach (array_keys($row) as $header) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($colLetter . '1', $header);
                        $colIndex++;
                    }

                    $lastCol = $colIndex - 1;
                    $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);

                    $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 13,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '2C3E50'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    $sheet->freezePane('A2');
                    $sheet->setAutoFilter("A1:{$lastColLetter}1");

                    $headersWritten = true;
                    $rowNumber++;
                }

                $colIndex = 1;
                foreach ($row as $value) {
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter . $rowNumber, $value);
                    $colIndex++;
                }

                if ($rowNumber % 2 === 0) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColLetter}{$rowNumber}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F4F6F7');
                }

                if ($processed % 200 === 0) {
                    $progress = min(95, max(1, (int) floor($processed / 100)));

                    $export->update([
                        'progress' => $progress,
                        'processed' => $processed,
                    ]);
                }

                $rowNumber++;

                if ($rowNumber % 1000 === 0) {
                    $spreadsheet->garbageCollect();
                }
            }

            for ($i = 1; $i <= Coordinate::columnIndexFromString($lastColLetter); $i++) {
                $colLetter = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }

            $sheet->getStyle("A1:{$lastColLetter}" . ($rowNumber - 1))
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            $writer = new Xlsx($spreadsheet);
            $writer->save($fullPath);

            $export->update([
                'status' => 'done',
                'progress' => 100,
                'processed' => $processed,
                'file_name' => $fileName,
            ]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
            ]);

            \Log::error('Export Job Failed', [
                'export_id' => $this->exportId,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}