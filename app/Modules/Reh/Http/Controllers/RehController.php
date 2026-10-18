<?php

namespace App\Modules\Reh\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reh\Http\Requests\RehFilterRequest;
use App\Modules\Reh\Http\Requests\RehReviewRequest;
use App\Modules\Reh\Jobs\SyncRehSurvey;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehSyncIssue;
use App\Modules\Reh\Models\RehSyncRun;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehArcgisClient;
use App\Modules\Reh\Services\RehReviewService;
use App\Modules\Reh\Services\RehSurvey;
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
    public function __construct(private RehSurvey $survey) {}

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
        $hasUnitFilter = ! empty($filters['q']) || ! empty($filters['status']) || ! empty($filters['mine']) || ! empty($filters['building_id']);
        $stats = [
            'units' => (int) $groups->sum('total'),
            'buildings' => $tab !== 'buildings' && $hasUnitFilter ? (clone $query)->distinct()->count('building_id') : $this->buildings($filters)->count(),
            'ready' => $counts['ready'] ?? 0,
            'returned' => $counts['returned'] ?? 0,
            'second_assessment' => $counts['second_assessment'] ?? 0,
            'selected' => (int) $groups->where('decision', 'selected')->sum('total'),
            'excluded' => (int) $groups->where('decision', 'excluded')->sum('total'),
        ];
        $unitRows = in_array($tab, ['overview', 'units'], true)
            ? $query->with(['building', 'reviewer:id,name'])->latest('updated_at')->orderByDesc('id')->paginate(20)->withQueryString() : null;
        $buildingRows = $tab === 'buildings' ? $this->buildings($filters)->withCount('units')->orderBy('id')->paginate(20)->withQueryString() : null;
        $runs = $tab === 'sync' && $request->user()->can('reh.sync') ? RehSyncRun::query()->latest()->limit(10)->get() : collect();
        $lastSync = RehSyncRun::query()->whereIn('status', ['completed', 'completed_with_warnings'])->latest('finished_at')->first();
        $issueCount = RehSyncIssue::query()->whereNull('resolved_at')->count();
        $syncIssues = $tab === 'sync' && $request->user()->can('reh.sync') ? RehSyncIssue::query()->whereNull('resolved_at')->select(['id', 'object_id', 'reason'])->orderBy('id')->limit(100)->get() : collect();
        $view = view('reh::partials.results', compact('tab', 'filters', 'stats', 'counts', 'unitRows', 'buildingRows', 'runs', 'lastSync', 'issueCount', 'syncIssues') + ['survey' => $this->survey])->render();

        return response()->json(['html' => $view, 'sync_active' => $runs->contains(fn (RehSyncRun $run): bool => in_array($run->status, ['queued', 'running'], true))])->header('Cache-Control', 'private, no-store');
    }

    public function unit(Request $request, RehUnit $unit): View|JsonResponse
    {
        $unit->load(['building', 'reviewer:id,name', 'reviews' => fn ($query) => $query->with('user:id,name')->latest('id')->limit(100)]);
        $html = view('reh::partials.unit', ['unit' => $unit, 'survey' => $this->survey, 'issues' => $this->survey->issues($unit), 'quantities' => $this->survey->quantities($unit)])->render();

        return $request->expectsJson() ? response()->json(['html' => $html])->header('Cache-Control', 'private, no-store') : $this->workspace($html, ['tab' => 'units']);
    }

    public function building(Request $request, RehBuilding $building): View|JsonResponse
    {
        $building->loadCount('units');
        $html = view('reh::partials.building', ['building' => $building, 'survey' => $this->survey])->render();

        return $request->expectsJson() ? response()->json(['html' => $html])->header('Cache-Control', 'private, no-store') : $this->workspace($html, ['tab' => 'buildings']);
    }

    public function review(RehReviewRequest $request, RehUnit $unit, RehReviewService $service): View|JsonResponse
    {
        $unit = $service->apply($unit, $request->user(), $request->validated());

        return $this->unit($request, $unit);
    }

    public function sync(Request $request): JsonResponse
    {
        $lock = Cache::lock('reh:sync:dispatch', 10);
        abort_unless($lock->get(), 409, 'يتم تجهيز المزامنة؛ حاول بعد لحظات.');
        try {
            $run = RehSyncRun::query()->whereIn('status', ['queued', 'running'])->first();
            if ($run) {
                return response()->json(['message' => 'توجد مزامنة قيد الانتظار أو التنفيذ.', 'run_id' => $run->id], 202);
            }
            $run = RehSyncRun::query()->create(['user_id' => $request->user()->id]);
            try {
                SyncRehSurvey::dispatch($run->id);
            } catch (Throwable) {
                $run->update(['status' => 'failed', 'finished_at' => now(), 'message' => 'تعذر إرسال مهمة المزامنة إلى قائمة الانتظار.']);

                return response()->json(['message' => 'تعذر بدء المزامنة. تحقق من إعدادات قائمة الانتظار.'], 503);
            }

            return response()->json(['message' => 'أُرسلت المزامنة إلى قائمة الانتظار.', 'run_id' => $run->id], 202);
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

        return response($body)->withHeaders(['Content-Type' => $inline ? $mime : 'application/octet-stream', 'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="reh-attachment-'.$attachment.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function export(RehFilterRequest $request): StreamedResponse
    {
        $query = $this->units($request->validated())->with('building');
        $sensitive = $request->user()->can('reh.sensitive');

        return response()->streamDownload(function () use ($query, $sensitive): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['رقم الوحدة', 'المبنى', 'المالك', 'الهوية', 'المحافظة', 'الحي', 'التدقيق', 'القرار', 'التسعير', 'آخر مزامنة']);
            foreach ($query->lazyById(200) as $unit) {
                $row = [$unit->code ?? $unit->id, $unit->building->name, $unit->owner_name, $sensitive ? $unit->owner_identity : 'محجوب', $this->survey->value('governorate', $unit->building->governorate), $this->survey->value('neighborhood', $unit->building->neighborhood), RehSurvey::WORKFLOW[$unit->workflow_status], ['pending' => 'لم يُقرر', 'selected' => 'مقبول مبدئيًا', 'excluded' => 'مستبعد'][$unit->decision], 'بانتظار الأسعار', $unit->synced_at?->toDateTimeString()];
                fputcsv($output, array_map(static fn (mixed $value): string => preg_match('/^[\s]*[=+@\-]/u', (string) $value) ? "'".$value : (string) $value, $row));
            }
            fclose($output);
        }, 'REH-units-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function units(array $filters): Builder
    {
        return RehUnit::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', '%'.$search.'%')->orWhere('owner_name', 'like', '%'.$search.'%');
                    if (auth()->user()->can('reh.sensitive')) {
                        $query->orWhere('owner_identity', $search);
                    }
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('workflow_status', $status))
            ->when($filters['building_id'] ?? null, fn (Builder $query, int $id) => $query->where('building_id', $id))
            ->when($filters['mine'] ?? false, fn (Builder $query) => $query->where('reviewer_id', auth()->id()))
            ->when($filters['governorate'] ?? null, fn (Builder $query, string $value) => $query->whereHas('building', fn (Builder $building) => $building->where('governorate', $value)))
            ->when($filters['engineer'] ?? null, fn (Builder $query, string $value) => $query->whereHas('building', fn (Builder $building) => $building->where('engineer', $value)));
    }

    private function workspace(?string $initialDetail, array $filters): View
    {
        return view('reh::workspace', ['initialDetail' => $initialDetail, 'filters' => $filters, 'engineers' => RehBuilding::query()->whereNotNull('engineer')->distinct()->orderBy('engineer')->pluck('engineer')]);
    }

    private function buildings(array $filters): Builder
    {
        return RehBuilding::query()
            ->when($filters['q'] ?? null, fn (Builder $query, string $value) => $query->where('name', 'like', '%'.$value.'%'))
            ->when($filters['building_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($filters['governorate'] ?? null, fn (Builder $query, string $value) => $query->where('governorate', $value))
            ->when($filters['engineer'] ?? null, fn (Builder $query, string $value) => $query->where('engineer', $value));
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
