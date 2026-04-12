<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Export;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
class ExportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public $exportId)
    {
    }


    public function handle()
    {
        $export = Export::find($this->exportId);

        if (!$export)
            return;

        try {

            // 🔥 مهم
            ini_set('memory_limit', '1024M');
            set_time_limit(0);

            $export->update(['status' => 'processing']);

            $params = json_decode($export->filters, true);

            $buildingColumns = $params['building_columns'] ?? [];
            $housingColumns = $params['housing_columns'] ?? [];
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
                : 'b.objectid as export_row_id'
            ];
            foreach ($buildingColumns as $c) {
                $selects[] = "b.$c as building_$c";
            }

            foreach ($housingColumns as $c) {
                $selects[] = "h.$c as housing_$c";
            }

            if ($needsFamily) {
                $selects[] = "fam.family_members_total as family_members_total";
            }

            $query->selectRaw(implode(',', $selects));

            // 🔥 مهم جدًا
            $fileName = 'exports/export_' . now()->timestamp . '.xlsx';
            $fullPath = storage_path('app/public/' . $fileName);

            // تأكد المجلد موجود
            if (!file_exists(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0777, true);
            }


            $totalProcessed = 0;

            $generator = function () use ($query, $paginateByHousing, $export, &$totalProcessed) {

                $lastId = 0;
                $loopCount = 0;
                $limit = 200;

                while (true) {

                    $loopCount++;

                    $batchQuery = clone $query;

                    if ($paginateByHousing) {
                        $batchQuery->where('h.objectid', '>', $lastId)
                            ->orderBy('h.objectid');
                    } else {
                        $batchQuery->where('b.objectid', '>', $lastId)
                            ->orderBy('b.objectid');
                    }

                    $rows = $batchQuery->limit($limit)->get();

                    if ($rows->isEmpty())
                        break;

                    foreach ($rows as $row) {

                        if (!isset($row->export_row_id))
                            continue;

                        yield (array) $row;

                        $lastId = $row->export_row_id;
                        $totalProcessed++;
                    }

                    // 🔥 تحديث progress كل batch
                    $progress = min(95, intval($totalProcessed / 100)); // fake % بسيط

                    $export->update([
                        'progress' => $progress
                    ]);
                }
            };

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 🔥 RTL
            $sheet->setRightToLeft(true);

            $rowNumber = 1;
            $headersWritten = false;

            foreach ($generator() as $row) {

                // ✅ HEADER
                if (!$headersWritten) {

                    $colIndex = 1;

                    foreach (array_keys($row) as $header) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($colLetter . '1', $header);
                        $colIndex++;
                    }

                    $lastCol = $colIndex - 1;
                    $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);

                    // 🎨 HEADER STYLE
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

                    // 🔥 Freeze header
                    $sheet->freezePane('A2');

                    // 🔥 Auto filter
                    $sheet->setAutoFilter("A1:{$lastColLetter}1");

                    // 🔥 Auto size
                    for ($i = 1; $i <= $lastCol; $i++) {
                        $colLetter = Coordinate::stringFromColumnIndex($i);
                        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                    }

                    $headersWritten = true;
                    $rowNumber++;
                }

                // ✅ DATA
                $colIndex = 1;

                foreach ($row as $value) {
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter . $rowNumber, $value);
                    $colIndex++;
                }

                // 🎨 Zebra Rows
                if ($rowNumber % 2 == 0) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColLetter}{$rowNumber}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F4F6F7');
                }

                $rowNumber++;

                // 🔥 memory protection
                if ($rowNumber % 1000 === 0) {
                    $spreadsheet->garbageCollect();
                }
            }

            // 🔥 Borders
            $sheet->getStyle("A1:{$lastColLetter}" . ($rowNumber - 1))
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            // 🔥 Save
            $writer = new Xlsx($spreadsheet);
            $writer->save($fullPath);

            // 🔥 save file
            $writer = new Xlsx($spreadsheet);
            $writer->save($fullPath);
            $export->update([
                'status' => 'done',
                'progress' => 100,
                'file_name' => $fileName
            ]);

        } catch (\Throwable $e) {

            // ❌ الفشل
            $export->update([
                'status' => 'failed'
            ]);

            \Log::error('Export Job Failed', [
                'export_id' => $this->exportId,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            throw $e;
        }
    }
}