<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use Mpdf\Mpdf;
use App\Models\Assessment;

class ExportDataController extends Controller
{
    public function index()
    {
        $buildingColumns = DB::getSchemaBuilder()->getColumnListing('buildings');
        $housingColumns = DB::getSchemaBuilder()->getColumnListing('housing_units');

        $assessmentMeta = DB::table('assessments')
            ->select('name', 'label', 'hint')
            ->whereNotNull('name')
            ->get()
            ->mapWithKeys(function ($item) {
                $name = trim($item->name);

                return [
                    $name => [
                        'label' => trim($item->label ?? ''),
                        'hint' => trim($item->hint ?? ''),
                    ]
                ];
            })
            ->toArray();

        $assessmentNames = array_keys($assessmentMeta);

        $buildingColumns = array_values(array_filter($buildingColumns, function ($column) use ($assessmentNames) {
            return in_array(trim($column), $assessmentNames);
        }));

        $housingColumns = array_values(array_filter($housingColumns, function ($column) use ($assessmentNames) {
            return in_array(trim($column), $assessmentNames);
        }));

        $filters = DB::table('filters')
            ->select('list_name', 'name', 'label')
            ->orderBy('list_name')
            ->orderBy('label')
            ->get()
            ->groupBy('list_name');

        $assessmentLabels = Assessment::pluck('label', 'name');

        return view('exports.index', [
            'assessmentLabels' => $assessmentLabels,
            'buildingColumns' => $buildingColumns,
            'housingColumns' => $housingColumns,
            'assessmentMeta' => $assessmentMeta,
            'filters' => $filters,
        ]);
    }


    public function export(Request $request)
    {
        try {
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 600);

            $buildingColumns = $request->input('building_columns', []);
            $housingColumns = $request->input('housing_columns', []);
            $filters = $request->input('filters', []);

            if (empty($buildingColumns) && empty($housingColumns)) {
                return back()->with('error', 'يرجى اختيار عمود واحد على الأقل.');
            }

            $validBuildingColumns = DB::getSchemaBuilder()->getColumnListing('buildings');
            $validHousingColumns = DB::getSchemaBuilder()->getColumnListing('housing_units');

            $buildingColumns = array_values(array_intersect($buildingColumns, $validBuildingColumns));
            $housingColumns = array_values(array_intersect($housingColumns, $validHousingColumns));

            // labels
            $assessmentLabels = DB::table('assessments')
                ->selectRaw('TRIM(name) as name, COALESCE(NULLIF(TRIM(hint), ""), TRIM(name)) as hint')
                ->pluck('hint', 'name')
                ->toArray();

            // query
            $query = DB::table('buildings as b');

            if (!empty($housingColumns)) {
                $query->leftJoin('housing_units as h', 'b.globalid', '=', 'h.parentglobalid');
            }

            // select
            $selects = [];

            foreach ($buildingColumns as $c) {
                $selects[] = "b.`$c` as `building_$c`";
            }

            foreach ($housingColumns as $c) {
                $selects[] = "h.`$c` as `housing_$c`";
            }

            $query->selectRaw(implode(',', $selects));

            // filters
            foreach ($filters as $field => $values) {
                $values = array_filter((array) $values);

                if (in_array($field, $validBuildingColumns)) {
                    $query->whereIn("b.$field", $values);
                } elseif (in_array($field, $validHousingColumns)) {
                    $query->whereIn("h.$field", $values);
                }
            }

            // headers
            $rawHeaders = [];

            foreach ($buildingColumns as $c)
                $rawHeaders[] = "building_$c";
            foreach ($housingColumns as $c)
                $rawHeaders[] = "housing_$c";

            $displayHeaders = [];

            foreach ($rawHeaders as $h) {
                $clean = str_replace(['building_', 'housing_'], '', $h);
                $label = $assessmentLabels[$clean] ?? $clean;

              

                // shorten long labels
                if (mb_strlen($label) > 40) {
                    $label = mb_substr($label, 0, 40) . '...';
                }

                $displayHeaders[$h] = $label;
            }

            /*
            |--------------------------------------------------------------------------
            | Decide small vs large
            |--------------------------------------------------------------------------
            */
            $count = (clone $query)->limit(5000)->count();

            if ($count <= 3000) {
                return $this->exportWithAutoSize($query, $rawHeaders, $displayHeaders);
            }

            return $this->exportFast($query, $rawHeaders, $displayHeaders);

        } catch (\Throwable $e) {

            \Log::error('Export failed', [
                'message' => $e->getMessage()
            ]);

            return back()->with('error', $e->getMessage());
        }
    }
    // ✅ لازم تضيف هذه
    private function exportWithAutoSize($query, $rawHeaders, $displayHeaders)
    {
        $rows = $query->limit(3000)->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $colIndex = 1;
        foreach ($rawHeaders as $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter . '1', $displayHeaders[$header]);
            $colIndex++;
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($rawHeaders as $header) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($colLetter . $rowNumber, $row->$header ?? '');
                $colIndex++;
            }
            $rowNumber++;
        }

        // auto size
        for ($i = 1; $i <= count($rawHeaders); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $fileName = 'export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
        $path = storage_path('app/public/' . $fileName);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ✅ ولازم هذه أيضًا
    private function exportFast($query, $rawHeaders, $displayHeaders)
    {
        $fileName = 'exports/export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
        $fullPath = storage_path('app/public/' . $fileName);

        $generator = function () use ($query, $rawHeaders, $displayHeaders) {
            foreach ($query->cursor() as $row) {

                $row = (array) $row;
                $out = [];

                foreach ($rawHeaders as $h) {
                    $label = $displayHeaders[$h];
                    $val = $row[$h] ?? '';

                    if (is_bool($val)) {
                        $val = $val ? 1 : 0;
                    } elseif (is_array($val) || is_object($val) || is_null($val)) {
                        $val = '';
                    }

                    $out[$label] = $val;
                }

                yield $out;
            }
        };

        (new \Rap2hpoutre\FastExcel\FastExcel($generator()))->export($fullPath);

        return response()->download($fullPath)->deleteFileAfterSend(true);
    }
}
