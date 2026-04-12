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
        // تنظيف السجلات العالقة القديمة لنفس المستخدم
        Export::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->whereNull('file_name')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'status' => 'failed',
            ]);

        $export = Export::where('user_id', auth()->id())->findOrFail($id);

        return response()->json([
            'status' => $export->status,
            'progress' => $export->progress ?? 0,
            'processed' => $export->processed ?? 0,
            'file' => $export->file_name ? asset('storage/' . $export->file_name) : null,
        ]);
    }


    public function export(Request $request)
    {
        try {
            // تنظيف السجلات العالقة القديمة
            Export::where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'processing'])
                ->whereNull('file_name')
                ->where('updated_at', '<', now()->subMinutes(10))
                ->update([
                    'status' => 'failed',
                ]);

            // هل يوجد تصدير حالي؟
            $runningExport = Export::where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'processing'])
                ->where('updated_at', '>=', now()->subMinutes(10))
                ->latest('id')
                ->first();

            if ($runningExport) {
                return response()->json([
                    'status' => false,
                    'needs_cancel' => true,
                    'message' => 'يوجد تصدير جارٍ بالفعل.',
                    'running_export' => [
                        'id' => $runningExport->id,
                        'status' => $runningExport->status,
                        'progress' => $runningExport->progress ?? 0,
                        'processed' => $runningExport->processed ?? 0,
                    ],
                ], 409);
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

    public function cancel($id)
    {
        $export = Export::where('user_id', auth()->id())->findOrFail($id);

        if (!in_array($export->status, ['pending', 'processing'])) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن إلغاء هذا التصدير.'
            ], 422);
        }

        $export->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إلغاء التصدير السابق بنجاح.'
        ]);
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