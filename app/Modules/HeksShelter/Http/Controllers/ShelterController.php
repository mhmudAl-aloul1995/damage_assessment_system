<?php

namespace App\Modules\HeksShelter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MobileFormAttachment;
use App\Modules\HeksShelter\Http\Requests\ShelterActionRequest;
use App\Modules\HeksShelter\Http\Requests\ShelterFilterRequest;
use App\Modules\HeksShelter\Models\ShelterCase;
use App\Modules\HeksShelter\Services\ShelterExport;
use App\Modules\HeksShelter\Services\ShelterSurvey;
use App\Modules\HeksShelter\Services\ShelterWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShelterController extends Controller
{
    public function __construct(private ShelterSurvey $survey, private ShelterWorkflow $workflow) {}

    private function query(ShelterFilterRequest $request): Builder
    {
        $filters = $request->validated();
        $query = ShelterCase::query()->where('phase', 3)->visibleTo($request->user());
        foreach (['status', 'governorate', 'engineer' => 'engineer_id'] as $key => $column) {
            $key = is_int($key) ? $column : $key;
            if (filled($filters[$key] ?? null)) {
                $query->where($column, $filters[$key]);
            }
        }
        if (filled($filters['search'] ?? null)) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
            $query->where(function (Builder $query) use ($pattern): void {
                foreach (['name', 'identity_number', 'phone', 'code', 'area'] as $column) {
                    $query->orWhereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        app(\App\Modules\HeksShelter\Services\SurveyDesigner::class)->applyFilters($query, $filters['survey_filters'] ?? []);

        return $query;
    }

    public function index(ShelterFilterRequest $request): View
    {
        $filters = $request->validated();
        $tab = $filters['tab'] ?? 'overview';
        $query = $this->query($request);
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $totals = ['cases' => $counts->sum(), 'grants' => (clone $query)->whereNotNull('approved_at')->sum('grant_amount'),
            'paid' => \App\Modules\HeksShelter\Models\ShelterPayment::query()->whereIn('case_id', (clone $query)->select('id'))->sum('amount')];
        $cases = (clone $query)->with('engineer')->withSum('payments', 'amount')->withCount('visits')->latest('updated_at')->paginate(20)->withQueryString();
        $mapCases = $tab === 'map' ? (clone $query)->whereNotNull('latitude')->whereNotNull('longitude')->limit(1000)->get(['id', 'name', 'code', 'latitude', 'longitude', 'status']) : collect();
        $engineers = ShelterCase::query()->where('phase', 3)->visibleTo($request->user())->with('engineer')->select('engineer_id')->distinct()->get()->pluck('engineer')->filter();

        return view('heks-shelter::workspace', ['survey' => $this->survey, 'filters' => $filters, 'tab' => $tab, 'counts' => $counts, 'totals' => $totals, 'cases' => $cases, 'mapCases' => $mapCases, 'engineers' => $engineers]);
    }

    private function visibleCase(Request $request, ShelterCase $case): ShelterCase
    {
        return ShelterCase::query()->where('phase', 3)->visibleTo($request->user())->findOrFail($case->id);
    }

    public function show(Request $request, ShelterCase $case): View|JsonResponse
    {
        $case = $this->visibleCase($request, $case)->load(['engineer', 'events.user', 'payments.user', 'visits.user', 'boqLines', 'submissions.attachments']);
        $data = ['case' => $case, 'survey' => $this->survey, 'blockers' => $this->workflow->blockers($case), 'legacyBenefit' => $this->workflow->legacyBenefit($case)];

        return $request->expectsJson() ? response()->json(['html' => view('heks-shelter::detail', $data)->render()]) : view('heks-shelter::case', $data);
    }

    public function act(ShelterActionRequest $request, ShelterCase $case): JsonResponse|RedirectResponse
    {
        $case = $this->visibleCase($request, $case);
        $this->workflow->act($request->user(), $case, $request->validated());

        return $request->expectsJson() ? response()->json(['message' => 'تم حفظ الإجراء وتوثيقه.']) : back()->with('status', 'تم حفظ الإجراء وتوثيقه.');
    }

    public function attachment(Request $request, ShelterCase $case, MobileFormAttachment $attachment): BinaryFileResponse
    {
        $case = $this->visibleCase($request, $case);
        abort_unless($case->submissions()->whereKey($attachment->mobile_form_submission_id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return response()->file(Storage::disk('local')->path($attachment->path), ['Content-Type' => $attachment->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function export(ShelterFilterRequest $request, ShelterExport $export): StreamedResponse
    {
        $query = $this->query($request);
        abort_if((clone $query)->count() > 1000, 422, 'ضيّق الفلاتر إلى ألف ملف أو أقل لتصدير إجابات الاستبيان كاملة.');

        return $export->excel($query->with('engineer')->withSum('payments', 'amount')->orderBy('id')->get());
    }

    public function pdf(Request $request, ShelterCase $case, ShelterExport $export): Response
    {
        return $export->pdf($this->visibleCase($request, $case)->load(['engineer', 'boqLines', 'payments', 'visits']));
    }
}
