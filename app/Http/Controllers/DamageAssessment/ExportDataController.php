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

            $request->validate([
                'building_columns' => ['nullable', 'array'],
                'housing_columns' => ['nullable', 'array'],
                'filters' => ['nullable', 'array'],
                'export_type' => ['required', 'in:excel,pdf'],
            ]);

            $buildingColumns = $request->input('building_columns', []);
            $housingColumns = $request->input('housing_columns', []);
            $selectedFilters = $request->input('filters', []);
            $exportType = $request->input('export_type', 'excel');

            if (empty($buildingColumns) && empty($housingColumns)) {
                return back()->with('error', 'يرجى اختيار عمود واحد على الأقل.');
            }

            $validBuildingColumns = DB::getSchemaBuilder()->getColumnListing('buildings');
            $validHousingColumns = DB::getSchemaBuilder()->getColumnListing('housing_units');

            $buildingColumns = array_intersect($buildingColumns, $validBuildingColumns);
            $housingColumns = array_intersect($housingColumns, $validHousingColumns);

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

            // selects
            $selects = [];

            foreach ($buildingColumns as $col) {
                $selects[] = "b.`$col` as `building_$col`";
            }

            foreach ($housingColumns as $col) {
                $selects[] = "h.`$col` as `housing_$col`";
            }

            $query->selectRaw(implode(',', $selects));

            // filters
            foreach ($selectedFilters as $field => $values) {
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

                // accessories 🔥
                if (str_starts_with($h, 'building_')) {
                    $label = '🏢 ' . $label;
                } elseif (str_starts_with($h, 'housing_')) {
                    $label = '🏠 ' . $label;
                }

                $displayHeaders[$h] = $label;
            }

            /*
            |--------------------------------------------------------------------------
            | EXCEL EXPORT (FAST + SAFE)
            |--------------------------------------------------------------------------
            */

            if ($exportType === 'excel') {

                $fileName = 'exports/export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
                $fullPath = storage_path('app/public/' . $fileName);

                // generator
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

                // save file instead of streaming
                (new FastExcel($generator()))->export($fullPath);

                return response()->download($fullPath)->deleteFileAfterSend(true);
            }

            /*
            |--------------------------------------------------------------------------
            | PDF (small only)
            |--------------------------------------------------------------------------
            */

            if ($exportType === 'pdf') {

                $rows = $query->limit(3000)->get();

                $html = view('exports.buildings_pdf', [
                    'rows' => $rows,
                    'rawHeaders' => $rawHeaders,
                    'displayHeaders' => $displayHeaders,
                ])->render();

                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4-L',
                    'default_font' => 'dejavusans',
                ]);

                $mpdf->WriteHTML($html);

                return response($mpdf->Output('export.pdf', 'S'))
                    ->header('Content-Type', 'application/pdf');
            }

        } catch (\Throwable $e) {

            \Log::error('Export failed', [
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'فشل التصدير: ' . $e->getMessage());
        }
    }
}