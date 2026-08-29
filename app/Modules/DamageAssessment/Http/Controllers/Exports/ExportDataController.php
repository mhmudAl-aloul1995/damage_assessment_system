<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Jobs\ExportAttachmentsJob;
use App\Jobs\ExportDataJob;
use App\Models\Assessment;
use App\Models\Export;
use App\Modules\DamageAssessment\Http\Requests\ObjectIdImportRequest;
use App\services\ArcgisService;
use App\Support\Exports\ExportDataColumns;
use App\Support\Phase\PhaseContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportDataController extends Controller
{
    private const ASSESSMENT_OBSTACLE_FILTER = 'assessment_obstacle';

    private const OBJECT_ID_FILTER_SESSION_KEY = 'exports.imported_object_ids';

    private const OBJECT_ID_FILTER_TARGET_SESSION_KEY = 'exports.imported_object_id_target';

    private const OBJECT_ID_FILTER_TARGET_BUILDING = 'building';

    private const OBJECT_ID_FILTER_TARGET_HOUSING_UNIT = 'housing_unit';

    private const OBJECT_ID_COLUMN_ALIASES = [
        self::OBJECT_ID_FILTER_TARGET_BUILDING => [
            'buildingobjectid',
            'building_objectid',
            'objectidbuilding',
            'objectidمبنى',
            'objectidالمبنى',
            'objectidللمبنى',
            'objectidللمبني',
            'objectidللمبنا',
        ],
        self::OBJECT_ID_FILTER_TARGET_HOUSING_UNIT => [
            'housingunitobjectid',
            'housing_unit_objectid',
            'housingobjectid',
            'unitobjectid',
            'unit_objectid',
            'objectidhousingunit',
            'objectidunit',
            'objectidالوحدة',
            'objectidللوحدة',
            'objectidرقمالوحدة',
        ],
    ];

    private const PENDING_INLINE_FALLBACK_SECONDS = 10;

    private const ORPHANED_PROCESSING_MINUTES = 2;

    public function index(Request $request)
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

        $this->ensureAssessmentObstacleFilter($filters);

        $neighborhoodQuery = DB::table(ExportDataColumns::BUILDINGS_TABLE)
            ->select('neighborhood')
            ->whereNotNull('neighborhood')
            ->where('neighborhood', '<>', '')
            ->distinct()
            ->orderBy('neighborhood');

        app(PhaseContext::class)->applyToBase($neighborhoodQuery, ExportDataColumns::BUILDINGS_TABLE.'.phase_number');

        $filters['neighborhood'] = $neighborhoodQuery
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
        $assessmentLabels[self::ASSESSMENT_OBSTACLE_FILTER] = 'Assessment Obstacle';
        $assessmentLabels['building_states_auditig'] = 'حالات المبنى - التدقيق';

        $importedObjectIds = $this->importedObjectIds();
        $importedObjectIdTarget = $this->importedObjectIdTarget();

        if (! empty($importedObjectIds)) {
            $this->clearImportedObjectIdFilter($request);
        }

        return view('damage-assessment::exports.index', [
            'assessmentLabels' => $assessmentLabels,
            'buildingColumns' => $buildingColumns,
            'housingColumns' => $housingColumns,
            'assessmentMeta' => $assessmentMeta,
            'filters' => $filters,
            'importedObjectIds' => $importedObjectIds,
            'importedObjectIdTarget' => $importedObjectIdTarget,
            'defaultExportSource' => ExportDataColumns::SOURCE_BASE,
        ]);
    }

    public function check(int $id): JsonResponse
    {
        $this->failStaleExports();

        $export = Export::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $exportPayload = json_decode((string) $export->filters, true) ?: [];

        if ($this->shouldRunPendingExportInline($export, $exportPayload)) {
            if ($this->shouldUseAttachmentsJob($exportPayload)) {
                (new ExportAttachmentsJob($export->id))->handle(app(ArcgisService::class));
            } else {
                (new ExportDataJob($export->id))->handle();
            }

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
            'total_rows' => $export->total_rows,
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
                        'total_rows' => $runningExport->total_rows,
                    ],
                ], 409);
            }

            $payload = $request->all();
            $selectedPhase = app(PhaseContext::class)->selected();

            if ($selectedPhase !== null) {
                $payload['selected_phase_number'] = $selectedPhase;
            }

            if (($payload['export_type'] ?? null) === 'zip' && ($payload['export_mode'] ?? 'data') === 'data') {
                $payload['export_mode'] = 'attachments';
            }

            if (
                ($payload['attachment_excel_display'] ?? null) === 'images'
                || ($payload['export_mode'] ?? null) === 'data_with_attachments'
            ) {
                $payload['include_attachment_excel_columns'] = '1';
            }

            if (in_array(($payload['export_mode'] ?? 'data'), ['attachments', 'data_with_attachments'], true)) {
                $payload['export_type'] = 'zip';
            }

            if (filled($payload['legal_notes_filter'] ?? null)) {
                $payload['include_legal_notes'] = '1';
            }

            if (filled($payload['engineering_notes_filter'] ?? null)) {
                $payload['include_engineering_notes'] = '1';
            }

            $requestedImportedObjectIds = $this->importedObjectIdsFromRequest($request);
            $sessionImportedObjectIds = $this->importedObjectIds();
            $importedObjectIds = ! empty($requestedImportedObjectIds)
                ? $requestedImportedObjectIds
                : $sessionImportedObjectIds;
            $importedObjectIdTarget = $this->objectIdFilterTarget(
                $request->input('imported_object_id_target', session(self::OBJECT_ID_FILTER_TARGET_SESSION_KEY)),
            );

            if (! empty($importedObjectIds)) {
                $payload['imported_object_ids'] = $importedObjectIds;
                $payload['imported_object_id_target'] = $importedObjectIdTarget;

                if ($this->requiresDataColumns($payload) && ! $this->hasSelectedDataColumns($payload)) {
                    $defaultColumnKey = $importedObjectIdTarget === self::OBJECT_ID_FILTER_TARGET_HOUSING_UNIT
                        ? 'housing_columns'
                        : 'building_columns';

                    $payload[$defaultColumnKey] = ['objectid'];
                }
            }

            if ($this->requiresDataColumns($payload) && ! $this->hasSelectedDataColumns($payload)) {
                return response()->json([
                    'status' => false,
                    'message' => 'يرجى اختيار عمود واحد على الأقل من أعمدة البيانات قبل التصدير.',
                ], 422);
            }

            $export = Export::query()->create([
                'status' => 'pending',
                'filters' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'user_id' => auth()->id(),
                'progress' => 0,
                'processed' => 0,
                'total_rows' => null,
                'file_name' => null,
            ]);

            $this->clearImportedObjectIdFilter($request);

            if ($this->shouldUseAttachmentsJob($payload)) {
                ExportAttachmentsJob::dispatch($export->id)->onQueue('exports');
            } else {
                ExportDataJob::dispatch($export->id)->onQueue('exports');
            }

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
        $target = $this->objectIdFilterTarget($request->input('objectid_filter_target'));

        if ($request->filled('objectids_text')) {
            $objectIdsText = (string) $request->input('objectids_text');
            $target = $this->objectIdTargetFromText($objectIdsText) ?? $target;
            $objectIds = $this->objectIdsFromText($objectIdsText);
        } else {
            $objectIds = $this->objectIdsFromFile($request, $target);
        }

        if (empty($objectIds)) {
            return response()->json([
                'status' => false,
                'message' => __('ui.exports.objectid_import_no_valid_rows'),
            ], 422);
        }

        session([
            self::OBJECT_ID_FILTER_SESSION_KEY => $objectIds,
            self::OBJECT_ID_FILTER_TARGET_SESSION_KEY => $target,
        ]);

        return response()->json([
            'status' => true,
            'message' => __('ui.exports.objectid_import_success', ['count' => count($objectIds)]),
            'count' => count($objectIds),
            'target' => $target,
            'object_ids' => $objectIds,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function objectIdsFromFile(ObjectIdImportRequest $request, string $target): array
    {
        $rows = Excel::toArray([], $request->file('objectids_file'));
        $sheetRows = collect($rows[0] ?? []);

        if ($sheetRows->isEmpty()) {
            return [];
        }

        $headerRow = collect((array) $sheetRows->first())
            ->map(fn ($value) => $this->normalizeObjectIdColumnName((string) $value))
            ->values();

        $objectIdColumnIndex = $this->objectIdColumnIndex($headerRow->all(), $target);
        $dataRows = $sheetRows;

        if ($objectIdColumnIndex !== null) {
            $dataRows = $sheetRows->slice(1)->values();
        } else {
            $objectIdColumnIndex = 0;
        }

        $objectIds = $dataRows
            ->map(function ($row) use ($objectIdColumnIndex) {
                $values = is_array($row) ? array_values($row) : [(string) $row];

                return $this->normalizeObjectIdValue($values[$objectIdColumnIndex] ?? '');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $objectIds;
    }

    /**
     * @return array<int, string>
     */
    private function objectIdsFromText(string $text): array
    {
        preg_match_all('/\d+(?:\.0+)?/', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($value) => $this->normalizeObjectIdValue($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function objectIdTargetFromText(string $text): ?string
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        foreach ($lines as $line) {
            $normalizedLine = $this->normalizeObjectIdColumnName((string) $line);

            if ($normalizedLine === '' || preg_match('/[^\d]+/u', $normalizedLine) !== 1) {
                continue;
            }

            foreach (self::OBJECT_ID_COLUMN_ALIASES as $target => $aliases) {
                foreach ($aliases as $alias) {
                    if (str_contains($normalizedLine, $alias)) {
                        return $target;
                    }
                }
            }
        }

        return null;
    }

    public function resetImportedObjectIds(Request $request): JsonResponse
    {
        $this->clearImportedObjectIdFilter($request);

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
        return $this->normalizeObjectIdValues(session(self::OBJECT_ID_FILTER_SESSION_KEY, []));
    }

    /**
     * @return array<int, string>
     */
    private function importedObjectIdsFromRequest(Request $request): array
    {
        $jsonObjectIds = $this->objectIdsFromJson((string) $request->input('imported_object_ids_json', ''));

        if ($jsonObjectIds !== []) {
            return $jsonObjectIds;
        }

        return $this->normalizeObjectIdValues($request->input('imported_object_ids', []));
    }

    /**
     * @return array<int, string>
     */
    private function objectIdsFromJson(string $json): array
    {
        if (! filled($json)) {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $this->normalizeObjectIdValues($decoded);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeObjectIdValues(mixed $values): array
    {
        return collect((array) $values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function importedObjectIdTarget(): string
    {
        return $this->objectIdFilterTarget(session(self::OBJECT_ID_FILTER_TARGET_SESSION_KEY));
    }

    private function clearImportedObjectIdFilter(Request $request): void
    {
        $request->session()->forget([
            self::OBJECT_ID_FILTER_SESSION_KEY,
            self::OBJECT_ID_FILTER_TARGET_SESSION_KEY,
        ]);
    }

    private function objectIdFilterTarget(mixed $target): string
    {
        return $target === self::OBJECT_ID_FILTER_TARGET_HOUSING_UNIT
            ? self::OBJECT_ID_FILTER_TARGET_HOUSING_UNIT
            : self::OBJECT_ID_FILTER_TARGET_BUILDING;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiresDataColumns(array $payload): bool
    {
        return ($payload['export_mode'] ?? 'data') !== 'attachments';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasSelectedDataColumns(array $payload): bool
    {
        $buildingColumns = array_filter((array) ($payload['building_columns'] ?? []), fn ($column): bool => filled($column));
        $housingColumns = array_filter((array) ($payload['housing_columns'] ?? []), fn ($column): bool => filled($column));

        return $buildingColumns !== []
            || $housingColumns !== []
            || ExportDataColumns::requestsAuditNoteColumns($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldUseAttachmentsJob(array $payload): bool
    {
        $exportType = (string) ($payload['export_type'] ?? 'excel');
        $exportMode = (string) ($payload['export_mode'] ?? 'data');
        $includeAttachmentExcelColumns = (string) ($payload['include_attachment_excel_columns'] ?? '0') === '1';

        return $exportType === 'zip'
            || $includeAttachmentExcelColumns
            || in_array($exportMode, ['attachments', 'data_with_attachments'], true);
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function objectIdColumnIndex(array $headers, string $target): ?int
    {
        $targetAliases = array_flip(self::OBJECT_ID_COLUMN_ALIASES[$target] ?? []);

        foreach ($headers as $index => $header) {
            if (isset($targetAliases[$header])) {
                return $index;
            }
        }

        $genericIndex = array_search('objectid', $headers, true);

        return $genericIndex === false ? null : (int) $genericIndex;
    }

    private function normalizeObjectIdColumnName(string $value): string
    {
        return Str::lower(preg_replace('/[\s\-_]+/u', '', trim($value)) ?? '');
    }

    private function normalizeObjectIdValue(mixed $value): string
    {
        $value = trim((string) $value);

        if (preg_match('/^\d+(?:\.0+)?$/', $value) !== 1) {
            return '';
        }

        return (string) (int) $value;
    }

    private function ensureAssessmentObstacleFilter(Collection $filters): void
    {
        if ($filters->has(self::ASSESSMENT_OBSTACLE_FILTER) && $filters[self::ASSESSMENT_OBSTACLE_FILTER]->isNotEmpty()) {
            return;
        }

        $filters[self::ASSESSMENT_OBSTACLE_FILTER] = collect([
            (object) [
                'name' => 'no',
                'label' => 'No',
            ],
            (object) [
                'name' => 'yes',
                'label' => 'Yes',
            ],
        ]);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldRunPendingExportInline(Export $export, array $payload): bool
    {
        return $export->status === 'pending'
            && ! $this->shouldUseAttachmentsJob($payload)
            && $export->file_name === null
            && (int) ($export->progress ?? 0) === 0
            && (int) ($export->processed ?? 0) === 0
            && $export->updated_at?->lt(now()->subSeconds(self::PENDING_INLINE_FALLBACK_SECONDS));
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
