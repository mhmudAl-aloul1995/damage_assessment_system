<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Models\Assessment;
use App\Models\Export;
use App\Jobs\ExportDataJob;

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


    public function check($id)
    {
        $export = Export::findOrFail($id);

        return response()->json([
            'status' => $export->status,
            'progress' => $export->progress,
            'processed' => $export->processed,
            'file' => $export->file_name ? asset('storage/' . $export->file_name) : null,
        ]);
    }

 

    public function export(Request $request)
    {
        try {
            $hasRunning = Export::whereIn('status', ['pending', 'processing'])->exists();

            if ($hasRunning) {
                return response()->json([
                    'status' => false,
                    'message' => 'يوجد تصدير جارٍ بالفعل. يرجى الانتظار حتى ينتهي.'
                ], 422);
            }

            $export = Export::create([
                'status' => 'pending',
                'filters' => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
                'user_id' => auth()->id(),
                'progress' => 0,
                'processed' => 0,
                'file_name' => null,
            ]);

            ExportDataJob::dispatch($export->id);

            return response()->json([
                'status' => true,
                'message' => 'تم بدء التصدير',
                'export_id' => $export->id,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Export start failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'فشل بدء التصدير: ' . $e->getMessage(),
            ], 500);
        }
    }
    /*     public function export(Request $request)
        {
            try {

                $export = Export::create([
                    'status' => 'pending',
                    'filters' => json_encode($request->all()),
                    'user_id' => auth()->id()
                ]);

                ExportDataJob::dispatch($export->id);

                return response()->json([
                    'status' => true,
                    'message' => 'تم بدء التصدير',
                    'export_id' => $export->id
                ]);

            } catch (\Throwable $e) {

                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage()
                ]);
            }
        } */
}