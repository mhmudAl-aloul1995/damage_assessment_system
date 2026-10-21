<?php

namespace App\Modules\Reh\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reh\Http\Requests\RehFieldAuditRequest;
use App\Modules\Reh\Http\Requests\RehFilterRequest;
use App\Modules\Reh\Http\Requests\RehReviewRequest;
use App\Modules\Reh\Jobs\SyncRehSurvey;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehSyncIssue;
use App\Modules\Reh\Models\RehSyncRun;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehAdvancedFilters;
use App\Modules\Reh\Services\RehArcgisClient;
use App\Modules\Reh\Services\RehExportColumns;
use App\Modules\Reh\Services\RehFieldAudit;
use App\Modules\Reh\Services\RehFilterChoices;
use App\Modules\Reh\Services\RehPdf;
use App\Modules\Reh\Services\RehReviewService;
use App\Modules\Reh\Services\RehScoring;
use App\Modules\Reh\Services\RehSurvey;
use App\Modules\Reh\Services\RehSurveyFilters;
use App\Modules\Reh\Services\RehSyncWorker;
use App\Modules\Reh\Services\RehUnitsExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RehController extends Controller
{
    public function __construct(private RehSurvey $survey, private RehScoring $scoring) {}

    public function index(RehFilterRequest $request): View
    {
        return $this->workspace(null, $request->validated());
    }

    public function data(RehFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $tab = $filters['tab'] ?? 'overview';
        $query = $this->units($filters);
        $groups = (clone $query)->selectRaw('workflow_status, decision, count(*) as total')->groupBy('workflow_status', 'decision')->get();
        $counts = $groups->groupBy('workflow_status')->map(fn ($rows): int => (int) $rows->sum('total'))->all();
        $advancedUnitFilter = count(array_filter(array_intersect_key($filters, array_flip([...RehAdvancedFilters::UNIT_FILTERS, ...app(RehSurveyFilters::class)->keys('unit')])), fn ($value): bool => $value !== null && $value !== '')) > 0;
        $hasUnitFilter = $advancedUnitFilter || ! empty($filters['q']) || ! empty($filters['status']) || ! empty($filters['mine']) || ! empty($filters['building_id']) || ! empty($filters['building_object_id']) || ! empty($filters['building_old_objectid']) || ! empty($filters['priority']);
        $selectedBuilding = ! empty($filters['building_id']) ? RehBuilding::query()->find($filters['building_id']) : (! empty($filters['building_object_id']) ? RehBuilding::query()->where('object_id', $filters['building_object_id'])->first() : null);
        $buildingObjectId = $selectedBuilding?->old_objectid ?? (($filters['building_old_objectid_op'] ?? 'is') === 'is' ? ($filters['building_old_objectid'] ?? null) : null);
        $stats = [
            'units' => (int) $groups->sum('total'),
            'buildings' => $hasUnitFilter ? (clone $query)->distinct()->count('building_id') : $this->buildings($filters)->count(),
            'ready' => $counts['ready'] ?? 0,
            'returned' => $counts['returned'] ?? 0,
            'second_assessment' => $counts['second_assessment'] ?? 0,
            'selected' => (int) $groups->where('decision', 'selected')->sum('total'),
            'excluded' => (int) $groups->where('decision', 'excluded')->sum('total'),
        ];
        $unitRows = in_array($tab, ['overview', 'units'], true)
            ? $query->with(['building', 'reviewer:id,name'])->latest('updated_at')->orderByDesc('id')->paginate(20)->withQueryString() : null;
        $buildingRows = null;
        $mapBuildings = collect();
        if ($tab === 'buildings') {
            $unitFilters = $filters;
            $matchingUnitIds = $this->units($unitFilters)->select('id');
            $buildingQuery = $this->buildings($filters);
            if ($advancedUnitFilter || ! empty($filters['mine'])) {
                $buildingQuery->whereHas('units', fn (Builder $units) => $units->whereIn('id', clone $matchingUnitIds));
            }
            $stats['buildings'] = (clone $buildingQuery)->count();
            $buildingRows = $buildingQuery->withCount('units')->with(['units' => fn ($units) => $units
                ->whereIn('id', clone $matchingUnitIds)->orderBy('object_id')])->orderBy('id')->paginate(20)->withQueryString();
            $mapBuildings = $buildingRows->getCollection()->map(function (RehBuilding $building) use ($request): array {
                $fieldStatus = $building->surveyData()['Field_status'] ?? null;
                $canViewPriority = $request->user()->can('reh.scores.view');
                $units = $building->units->map(fn (RehUnit $unit): array => [
                    'old_objectid' => $unit->old_objectid,
                    'url' => route('reh.units.show', $unit),
                    'status' => $unit->workflow_status,
                    'source_missing' => $unit->source_missing,
                    ...($canViewPriority ? ['priority' => $this->scoring->calculate($unit)['priority'] ?? 'unclassified'] : []),
                ])->values()->all();

                return [
                    'id' => $building->id,
                    'name' => 'معرّف حصر المبنى '.$building->old_objectid.($building->name ? ' · '.$building->name : ''),
                    'geometry' => $building->geometry,
                    'url' => route('reh.buildings.show', $building),
                    'survey' => match ($fieldStatus) {
                        'COMPLETED' => 'completed',
                        'Not_Completed', 'notcomplete' => 'not_completed',
                        default => 'unrecorded',
                    },
                    'source_missing' => $building->source_missing,
                    'total_units' => $building->units_count,
                    'units' => $units,
                    ...($canViewPriority ? $this->scoring->summarizePriorities($units) : []),
                ];
            });
        }
        $runs = $tab === 'sync' && $request->user()->can('reh.sync') ? RehSyncRun::query()->latest()->limit(10)->get() : collect();
        $lastSync = RehSyncRun::query()->whereIn('status', ['completed', 'completed_with_warnings'])->latest('finished_at')->first();
        $issueCount = $tab === 'sync' && $request->user()->can('reh.sync') ? RehSyncIssue::query()->whereNull('resolved_at')->count() : 0;
        $syncIssues = $tab === 'sync' && $request->user()->can('reh.sync') ? RehSyncIssue::query()->whereNull('resolved_at')->select(['id', 'source', 'reason'])->orderBy('id')->limit(100)->get() : collect();
        $scores = $unitRows?->getCollection()->mapWithKeys(fn (RehUnit $unit): array => [$unit->id => $request->user()->canAny(['reh.sensitive', 'reh.scores.view']) ? $this->scoring->calculate($unit) : ['engineering' => $this->scoring->engineering($unit)]]) ?? collect();
        $view = view('reh::partials.results', compact('tab', 'filters', 'stats', 'counts', 'unitRows', 'buildingRows', 'mapBuildings', 'runs', 'lastSync', 'issueCount', 'syncIssues', 'scores', 'buildingObjectId') + ['survey' => $this->survey])->render();

        return response()->json(['html' => $view, 'total_units' => $stats['units'], 'total_buildings' => $stats['buildings'], 'sync_active' => $runs->contains(fn (RehSyncRun $run): bool => in_array($run->status, ['queued', 'running'], true))])->header('Cache-Control', 'private, no-store');
    }

    public function unit(Request $request, RehUnit $unit): View|JsonResponse
    {
        $unit->load(['building', 'reviewer:id,name', 'reviews' => fn ($query) => $query->with('user:id,name')->latest('id')->limit(100)]);
        $score = $request->user()->canAny(['reh.sensitive', 'reh.scores.view']) ? $this->scoring->calculate($unit) : ['engineering' => $this->scoring->engineering($unit)];
        $missingScoreFields = $this->scoring->missingFields($score, $unit->surveyData(), $request->user()->can('reh.sensitive'));
        $html = view('reh::partials.unit', ['unit' => $unit, 'survey' => $this->survey, 'issues' => $this->survey->issues($unit), 'quantities' => $this->survey->quantities($unit), 'score' => $score, 'missingScoreFields' => $missingScoreFields, ...$this->auditView($unit, $request)])->render();

        return $request->expectsJson() ? response()->json(['html' => $html])->header('Cache-Control', 'private, no-store') : $this->workspace($html, ['tab' => 'units']);
    }

    public function building(Request $request, RehBuilding $building): View|JsonResponse
    {
        $building->loadCount('units');
        $html = view('reh::partials.building', ['building' => $building, 'survey' => $this->survey, ...$this->auditView($building, $request)])->render();

        return $request->expectsJson() ? response()->json(['html' => $html])->header('Cache-Control', 'private, no-store') : $this->workspace($html, ['tab' => 'buildings']);
    }

    public function review(RehReviewRequest $request, RehUnit $unit, RehReviewService $service): View|JsonResponse
    {
        $unit = $service->apply($unit, $request->user(), $request->validated());

        return $this->unit($request, $unit);
    }

    private function auditView(RehUnit|RehBuilding $record, Request $request): array
    {
        $audit = app(RehFieldAudit::class);

        return ['auditReady' => $audit->ready(), 'auditRecord' => $record, 'auditKind' => $audit->kind($record), 'auditFields' => $audit->fields($record, $request->user()), 'auditRevision' => $audit->revision($record), 'auditBlocked' => $audit->blocked($record), 'auditHistory' => $audit->history($record, $request->user())];
    }

    public function audit(RehFieldAuditRequest $request, string $kind, int $id, RehFieldAudit $service): View|JsonResponse
    {
        [$record] = $this->attachmentRecord($kind, $id);
        $service->save($record, $request->user(), $request->validated());

        return $kind === 'unit' ? $this->unit($request, $record->fresh()) : $this->building($request, $record->fresh());
    }

    public function reconcileAudit(Request $request, int $log, RehFieldAudit $service): View|JsonResponse
    {
        $service->ensureReady();
        $record = $service->reconcile(RehAuditLog::query()->findOrFail($log), $request->user());

        return $record instanceof RehUnit ? $this->unit($request, $record) : $this->building($request, $record);
    }

    public function sync(Request $request, RehSyncWorker $worker): JsonResponse
    {
        $lock = Cache::lock('reh:sync:dispatch', 10);
        abort_unless($lock->get(), 409, 'يتم تجهيز المزامنة؛ حاول بعد لحظات.');
        try {
            $run = RehSyncRun::query()->whereIn('status', ['queued', 'running'])->first();
            if ($run?->status === 'running') {
                return response()->json(['message' => 'المزامنة قيد التنفيذ؛ تُحدّث الحالة تلقائيًا.', 'run_id' => $run->id], 202);
            }
            if (! $run) {
                $run = RehSyncRun::query()->create(['user_id' => $request->user()->id]);
                try {
                    SyncRehSurvey::dispatch($run->id);
                } catch (Throwable) {
                    $run->update(['status' => 'failed', 'finished_at' => now(), 'message' => 'تعذر إرسال مهمة المزامنة إلى قائمة الانتظار.']);

                    return response()->json(['message' => 'تعذر بدء المزامنة. تحقق من إعدادات قائمة الانتظار.'], 503);
                }
            }
            if (! $worker->start()) {
                $run->update(['message' => 'حُفظ الطلب، لكن تعذر بدء التنفيذ التلقائي. أعد المحاولة أو تواصل مع مسؤول النظام.']);

                return response()->json(['message' => $run->message, 'run_id' => $run->id], 503);
            }

            RehSyncRun::query()->whereKey($run->id)->where('status', 'queued')->update(['message' => 'جاري بدء المزامنة تلقائيًا…']);

            return response()->json(['message' => 'جاري بدء المزامنة بالخلفية؛ تُحدّث الحالة تلقائيًا.', 'run_id' => $run->id], 202);
        } finally {
            $lock->release();
        }
    }

    public function attachments(Request $request, string $kind, int $id, RehArcgisClient $client): JsonResponse
    {
        [$record, $layer] = $this->attachmentRecord($kind, $id);
        try {
            $attachments = collect($client->attachments($layer, $record->object_id))->filter(fn (array $attachment): bool => $this->canViewAttachment($request, $attachment))->map(function (array $attachment) use ($kind, $id): array {
                return ['name' => $attachment['name'], 'label' => $this->survey->label($attachment['keywords'] ?? '') ?: $attachment['name'], 'url' => route('reh.attachment', [$kind, $id, $attachment['id']]), 'image' => in_array($attachment['contentType'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)];
            })->values();
        } catch (Throwable) {
            return response()->json(['message' => 'تعذر تحميل المرفقات. حاول مجددًا بعد التحقق من الاتصال.'], 502);
        }

        return response()->json(['attachments' => $attachments])->header('Cache-Control', 'private, no-store');
    }

    public function attachment(Request $request, string $kind, int $id, int $attachment, RehArcgisClient $client): Response
    {
        [$record, $layer] = $this->attachmentRecord($kind, $id);
        try {
            $info = collect($client->attachments($layer, $record->object_id))->firstWhere('id', $attachment);
        } catch (Throwable) {
            abort(502, 'تعذر الاتصال بمصدر المرفقات.');
        }
        abort_unless($info, 404);
        abort_unless($this->canViewAttachment($request, $info), 403);
        abort_if(($info['size'] ?? 0) > 25 * 1024 * 1024, 413, 'حجم المرفق يتجاوز حد المعاينة.');
        try {
            $body = $client->download($layer, $record->object_id, $attachment);
        } catch (Throwable) {
            abort(502, 'تعذر تحميل المرفق.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
        $inline = in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
        $filename = 'reh-'.$kind.'-'.preg_replace('/[^\pL\pN _-]/u', '_', $record->old_objectid).'-attachment-'.$attachment;
        $fallback = preg_match('/^[A-Za-z0-9_-]+$/', $record->old_objectid) ? $filename : 'reh-'.$kind.'-attachment-'.$attachment;
        $disposition = \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $filename, $fallback);

        return response($body)->withHeaders(['Content-Type' => $inline ? $mime : 'application/octet-stream', 'Content-Disposition' => $disposition, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function exports(RehFilterRequest $request): View
    {
        return $this->workspace(null, [...$request->validated(), 'tab' => 'units'], true);
    }

    public function exportData(RehFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = $this->units($filters);
        $totalUnits = (clone $query)->count();
        $totalBuildings = (clone $query)->distinct()->count('building_id');
        $preview = (clone $query)->with('building')->orderBy('id')->limit(10)->get();
        $summary = app(RehAdvancedFilters::class)->summary($filters);
        $availableColumns = RehExportColumns::available($request->user());
        $selectedColumns = $filters['columns'] ?? array_values(array_intersect(RehExportColumns::defaults(), array_keys($availableColumns)));
        $export = new RehUnitsExport($query, $request->user()->can('reh.sensitive'), $request->user()->can('reh.scores.view'), array_keys($availableColumns));
        $previewRows = $preview->map(fn (RehUnit $unit): array => array_combine(array_keys($availableColumns), $export->row($unit)));
        $html = view('reh::partials.export-results', compact('totalUnits', 'totalBuildings', 'preview', 'summary', 'availableColumns', 'selectedColumns', 'previewRows'))->render();

        return response()->json(['html' => $html, 'total_units' => $totalUnits, 'total_buildings' => $totalBuildings])->header('Cache-Control', 'private, no-store');
    }

    public function exportExcel(RehFilterRequest $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $export = new RehUnitsExport($this->units($request->validated())->with('building'), $request->user()->can('reh.sensitive'), $request->user()->can('reh.scores.view'), $request->validated('columns'));

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'REH-units-'.now()->format('Y-m-d').'.xlsx', \Maatwebsite\Excel\Excel::XLSX, ['Cache-Control' => 'private, no-store']);
    }

    public function export(RehFilterRequest $request): StreamedResponse
    {
        $export = new RehUnitsExport($this->units($request->validated())->with('building'), $request->user()->can('reh.sensitive'), $request->user()->can('reh.scores.view'), $request->validated('columns'));

        return response()->streamDownload(function () use ($export): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $export->headings());
            foreach ($export->generator() as $row) {
                fputcsv($output, array_map(static fn (mixed $value): string => preg_match('/^[\s]*[=+@\-]/u', (string) $value) ? "'".$value : (string) $value, $row));
            }
            fclose($output);
        }, 'REH-units-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function exportPdf(RehFilterRequest $request, RehPdf $pdf): Response
    {
        $filters = $request->validated();
        $query = $this->units($filters)->with('building')->orderBy('id');
        abort_if((clone $query)->count() > 500, 422, 'تصدير PDF يدعم حتى 500 وحدة في التقرير. ضيّق الفلاتر أو استخدم Excel لجميع النتائج.');

        return $pdf->results($query->get(), $filters, $request->user());
    }

    public function unitPdf(Request $request, RehUnit $unit, RehPdf $pdf): Response
    {
        return $pdf->unit($unit->load('building'), $request->user());
    }

    private function units(array $filters): Builder
    {
        $query = RehUnit::query()
            ->when(($filters['survey_status'] ?? 'completed') !== 'all' || ($filters['survey_status_op'] ?? 'is') === 'is_not', fn (Builder $query) => $query->whereHas('building', fn (Builder $building) => $this->filterSurveyStatus($building, $filters['survey_status'] ?? 'completed', $filters['survey_status_op'] ?? 'is')))
            ->when($filters['building_id'] ?? null, fn (Builder $query, int $id) => $query->where('building_id', $id))
            ->when($filters['building_object_id'] ?? null, fn (Builder $query, int $objectId) => $query->whereHas('building', fn (Builder $building) => $building->where('object_id', $objectId)))
            ->whereHas('building', fn (Builder $building) => app(RehAdvancedFilters::class)->buildings($building, $filters));

        app(RehAdvancedFilters::class)->search($query, $filters);

        return app(RehAdvancedFilters::class)->units($query, $filters);
    }

    private function workspace(?string $initialDetail, array $filters, bool $exportPage = false): View
    {
        return view('reh::workspace', ['exportPage' => $exportPage, 'initialDetail' => $initialDetail, 'filters' => ['survey_status' => 'completed', ...$filters], 'engineers' => RehBuilding::query()->whereNotNull('engineer')->distinct()->orderBy('engineer')->pluck('engineer')]);
    }

    private function buildings(array $filters): Builder
    {
        $query = RehBuilding::query()
            ->when(($filters['survey_status'] ?? 'completed') !== 'all' || ($filters['survey_status_op'] ?? 'is') === 'is_not', fn (Builder $query) => $this->filterSurveyStatus($query, $filters['survey_status'] ?? 'completed', $filters['survey_status_op'] ?? 'is'))
            ->when($filters['building_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($filters['building_object_id'] ?? null, fn (Builder $query, int $objectId) => $query->where('object_id', $objectId));

        app(RehAdvancedFilters::class)->search($query, $filters, true);

        return app(RehAdvancedFilters::class)->buildings($query, $filters);
    }

    private function filterSurveyStatus(Builder $query, array|string $status, string $operator = 'is'): Builder
    {
        $values = RehFilterChoices::values($status);
        if (in_array('all', $values, true) && $operator === 'is_not') {
            return $query->whereRaw('1 = 0');
        }
        if ($values === [] || in_array('all', $values, true)) {
            return $query;
        }

        if ($operator === 'is_not') {
            $values = array_values(array_diff(['completed', 'not_completed', 'unrecorded'], $values));
            if ($values === []) {
                return $query->whereRaw('1 = 0');
            }
        }

        return $query->where(function (Builder $q) use ($values): void {
            foreach ($values as $value) {
                $q->orWhere(fn (Builder $part) => match ($value) {
                    'completed' => $part->where('field_status', 'COMPLETED'),
                    'not_completed' => $part->whereIn('field_status', ['Not_Completed', 'notcomplete']),
                    default => $part->where(fn (Builder $missing) => $missing->whereNull('field_status')->orWhereNotIn('field_status', ['COMPLETED', 'Not_Completed', 'notcomplete'])),
                });
            }
        });
    }

    private function attachmentRecord(string $kind, int $id): array
    {
        return $kind === 'building' ? [RehBuilding::query()->findOrFail($id), (int) config('reh.buildings_layer')] : [RehUnit::query()->findOrFail($id), (int) config('reh.units_layer')];
    }

    private function canViewAttachment(Request $request, array $attachment): bool
    {
        return $request->user()->can('reh.sensitive') || in_array($attachment['keywords'] ?? '', ['building_photo', 'building_exclusion_photo', 'unit_exclusion_photo', 'unit_overview_photo', 'kitchen_photo', 'bathroom_photo', 'entrance_photo', 'damaged_walls_photo_1', 'damaged_walls_photo_2'], true);
    }
}
