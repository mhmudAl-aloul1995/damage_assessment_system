<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Jobs\ExportDataJob;
use App\Models\Assessment;
use App\Models\Export;
use App\Modules\DamageAssessment\Http\Requests\ObjectIdImportRequest;
use App\Support\Exports\ExportDataColumns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportDataController extends Controller
{
    private const OBJECT_ID_FILTER_SESSION_KEY = 'exports.imported_object_ids';

    private const ORPHANED_PENDING_MINUTES = 1;

    private const ORPHANED_PROCESSING_MINUTES = 2;

    public function index()
    {
        $buildingColumns = ExportDataColumns::visibleBuildingColumns();
        $housingColumns = ExportDataColumns::visibleHousingColumns();

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
                    ],
                ];
            })
            ->toArray();

        $filters = DB::table('filters')
            ->select('list_name', 'name', 'label')
            ->orderBy('list_name')
            ->orderBy('label')
            ->get()
            ->groupBy('list_name');

        $filters['neighborhood'] = DB::table(ExportDataColumns::BUILDINGS_TABLE)
            ->select('neighborhood')
            ->whereNotNull('neighborhood')
            ->where('neighborhood', '<>', '')
            ->distinct()
            ->orderBy('neighborhood')
            ->pluck('neighborhood')
            ->map(fn (string $neighborhood): object => (object) [
                'name' => $neighborhood,
                'label' => $neighborhood,
            ]);

        $auditingStatuses = DB::table('assessment_statuses')
            ->select('id as name', 'label_ar as label')
            ->orderBy('label_ar')
            ->get();

        $filters['building_states_auditig'] = $auditingStatuses;

        $buildingUnitsCountColumn = ExportDataColumns::BUILDING_UNITS_COUNT_COLUMN;

        $assessmentMeta[$buildingUnitsCountColumn] = [
            'label' => 'عدد الوحدات للمبنى',
            'hint' => 'حقل مخصص يعرض عدد الوحدات السكنية المرتبطة بالمبنى',
        ];

        $assessmentLabels = Assessment::pluck('label', 'name');
        $assessmentLabels['building_states_auditig'] = 'حالات المبنى - التدقيق';

        return view('damage-assessment::exports.index', [
            'assessmentLabels' => $assessmentLabels,
            'buildingColumns' => $buildingColumns,
            'housingColumns' => $housingColumns,
            'assessmentMeta' => $assessmentMeta,
            'filters' => $filters,
            'importedObjectIds' => $this->importedObjectIds(),
        ]);
    }

    public function check(int $id): JsonResponse
    {
        $this->failStaleExports();

        $export = Export::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if ($this->isOrphanedPendingExport($export)) {
            ExportDataJob::dispatch($export->id)->onQueue('exports');
            $export->touch();
            $export->refresh();
        }

        if ($this->isOrphanedProcessingExport($export)) {
            $export->update([
                'status' => 'failed',
            ]);

            $export->refresh();
        }

        return response()->json([
            'status' => $export->status,
            'progress' => $export->progress ?? 0,
            'processed' => $export->processed ?? 0,
            'file' => $export->file_name ? asset('storage/'.$export->file_name) : null,
            'message' => $export->status === 'done' && (int) $export->processed === 0
                ? 'لا توجد بيانات مطابقة لخيارات التصدير.'
                : null,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        try {
            $this->failStaleExports();

            $runningExport = Export::query()
                ->where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'processing'])
                ->where('updated_at', '>=', now()->subHours(2))
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

            $payload = $request->all();
            $importedObjectIds = $this->importedObjectIds();

            if (! empty($importedObjectIds)) {
                $payload['imported_object_ids'] = $importedObjectIds;
            }

            $export = Export::query()->create([
                'status' => 'pending',
                'filters' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'user_id' => auth()->id(),
                'progress' => 0,
                'processed' => 0,
                'file_name' => null,
            ]);

            ExportDataJob::dispatch($export->id)->onQueue('exports');

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
                'message' => 'فشل بدء التصدير: '.$e->getMessage(),
            ], 500);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        $export = Export::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if (! in_array($export->status, ['pending', 'processing'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن إلغاء هذا التصدير.',
            ], 422);
        }

        $export->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إلغاء التصدير السابق بنجاح.',
        ]);
    }

    public function importObjectIds(ObjectIdImportRequest $request): JsonResponse
    {
        $rows = Excel::toArray([], $request->file('objectids_file'));
        $sheetRows = collect($rows[0] ?? []);

        if ($sheetRows->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => __('ui.exports.objectid_import_empty'),
            ], 422);
        }

        $headerRow = collect((array) $sheetRows->first())
            ->map(fn ($value) => Str::lower(trim((string) $value)))
            ->values();

        $objectIdColumnIndex = $headerRow->search('objectid');
        $dataRows = $sheetRows;

        if ($objectIdColumnIndex !== false) {
            $dataRows = $sheetRows->slice(1)->values();
        } else {
            $objectIdColumnIndex = 0;
        }

        $objectIds = $dataRows
            ->map(function ($row) use ($objectIdColumnIndex) {
                $values = is_array($row) ? array_values($row) : [(string) $row];

                return trim((string) ($values[$objectIdColumnIndex] ?? ''));
            })
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($objectIds)) {
            return response()->json([
                'status' => false,
                'message' => __('ui.exports.objectid_import_no_valid_rows'),
            ], 422);
        }

        session([self::OBJECT_ID_FILTER_SESSION_KEY => $objectIds]);

        return response()->json([
            'status' => true,
            'message' => __('ui.exports.objectid_import_success', ['count' => count($objectIds)]),
            'count' => count($objectIds),
        ]);
    }

    public function resetImportedObjectIds(Request $request): JsonResponse
    {
        $request->session()->forget(self::OBJECT_ID_FILTER_SESSION_KEY);

        return response()->json([
            'status' => true,
            'message' => __('ui.exports.objectid_import_reset_success'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function importedObjectIds(): array
    {
        return collect(session(self::OBJECT_ID_FILTER_SESSION_KEY, []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function failStaleExports(): void
    {
        Export::query()
            ->where('user_id', auth()->id())
            ->whereNull('file_name')
            ->where('status', 'processing')
            ->where('progress', '<=', 1)
            ->where('processed', 0)
            ->where('updated_at', '<', now()->subMinutes(self::ORPHANED_PROCESSING_MINUTES))
            ->get()
            ->each(function (Export $export): void {
                if ($this->hasExportsQueueJob()) {
                    return;
                }

                $export->update([
                    'status' => 'failed',
                ]);
            });
    }

    private function isOrphanedProcessingExport(Export $export): bool
    {
        return $export->status === 'processing'
            && $export->file_name === null
            && (int) ($export->progress ?? 0) <= 1
            && (int) ($export->processed ?? 0) === 0
            && $export->updated_at?->lt(now()->subMinutes(self::ORPHANED_PROCESSING_MINUTES))
            && ! $this->hasExportsQueueJob();
    }

    private function isOrphanedPendingExport(Export $export): bool
    {
        return $export->status === 'pending'
            && $export->file_name === null
            && (int) ($export->progress ?? 0) === 0
            && (int) ($export->processed ?? 0) === 0
            && $export->updated_at?->lt(now()->subMinutes(self::ORPHANED_PENDING_MINUTES))
            && ! $this->hasExportsQueueJob();
    }

    private function hasExportsQueueJob(): bool
    {
        if (! Schema::hasTable('jobs')) {
            return false;
        }

        return DB::table('jobs')
            ->where('queue', 'exports')
            ->exists();
    }
}
