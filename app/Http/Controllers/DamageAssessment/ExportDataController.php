<?php

declare(strict_types=1);

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\DamageAssessment\ObjectIdImportRequest;
use App\Models\Assessment;
use App\Models\Export;
use App\Support\Exports\ExportDataColumns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportDataController extends Controller
{
    private const OBJECT_ID_FILTER_SESSION_KEY = 'exports.imported_object_ids';

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

        return view('exports.index', [
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
        Export::query()
            ->where('user_id', auth()->id())
            ->whereNull('file_name')
            ->where(function ($query) {
                $query->where(function ($pending) {
                    $pending->where('status', 'pending')
                        ->where('updated_at', '<', now()->subMinutes(10));
                })->orWhere(function ($processing) {
                    $processing->where('status', 'processing')
                        ->where('updated_at', '<', now()->subHours(2));
                });
            })
            ->update([
                'status' => 'failed',
            ]);

        $export = Export::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => $export->status,
            'progress' => $export->progress ?? 0,
            'processed' => $export->processed ?? 0,
            'file' => $export->file_name ? asset('storage/'.$export->file_name) : null,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        try {
            Export::query()
                ->where('user_id', auth()->id())
                ->whereNull('file_name')
                ->where(function ($query) {
                    $query->where(function ($pending) {
                        $pending->where('status', 'pending')
                            ->where('updated_at', '<', now()->subMinutes(10));
                    })->orWhere(function ($processing) {
                        $processing->where('status', 'processing')
                            ->where('updated_at', '<', now()->subHours(2));
                    });
                })
                ->update([
                    'status' => 'failed',
                ]);

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

            $this->startExportProcess($export->id);

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

    private function startExportProcess(int $exportId): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $command = [
            $this->phpCliBinary(),
            base_path('artisan'),
            'exports:run',
            (string) $exportId,
        ];

        $logPath = storage_path("logs/export-process-{$exportId}.log");

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $batchPath = storage_path("app/export-process-{$exportId}.bat");
                file_put_contents($batchPath, implode("\r\n", [
                    '@echo off',
                    'echo [%date% %time%] starting export '.$exportId.' >> "'.$logPath.'"',
                    'cd /d "'.base_path().'"',
                    $this->escapeWindowsCommand($command).' >> "'.$logPath.'" 2>&1',
                    'echo [%date% %time%] finished export '.$exportId.' with exit code %ERRORLEVEL% >> "'.$logPath.'"',
                    '',
                ]));

                $startCommand = 'cmd /c start "" /min "'.$batchPath.'"';

                \Log::info('Starting export process', [
                    'export_id' => $exportId,
                    'batch_path' => $batchPath,
                    'command' => $startCommand,
                ]);

                pclose(popen($startCommand, 'r'));

                return;
            }

            exec($this->escapeUnixCommand($command).' >> '.escapeshellarg($logPath).' 2>&1 &');
        } catch (\Throwable $e) {
            \Log::warning('Unable to start export process', [
                'export_id' => $exportId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function phpCliBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $phpExe = PHP_BINDIR.DIRECTORY_SEPARATOR.'php.exe';

            if (is_file($phpExe)) {
                return $phpExe;
            }
        }

        return PHP_BINARY;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function escapeWindowsCommand(array $command): string
    {
        return collect($command)
            ->map(fn (string $part): string => '"'.str_replace('"', '\"', $part).'"')
            ->implode(' ');
    }

    /**
     * @param  array<int, string>  $command
     */
    private function escapeUnixCommand(array $command): string
    {
        return collect($command)
            ->map(fn (string $part): string => escapeshellarg($part))
            ->implode(' ');
    }
}
