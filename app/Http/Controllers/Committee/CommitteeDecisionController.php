<?php

declare(strict_types=1);

namespace App\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\SaveCommitteeDecisionRequest;
use App\Http\Requests\Committee\SignCommitteeDecisionRequest;
use App\Jobs\SendCommitteeDecisionTelegramJob;
use App\Models\Building;
use App\Models\CommitteeDecision;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\services\CommitteeDecisionWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Yajra\DataTables\Facades\DataTables;

class CommitteeDecisionController extends Controller
{
    public function __construct(private readonly CommitteeDecisionWorkflowService $workflowService) {}

    public function index(): View
    {
        return view('Committee.Decisions.index', [
            'buildingCount' => $this->buildingQuery()->count(),
            'housingCount' => $this->housingUnitQuery()->count(),
        ]);
    }

    public function buildingsData(): JsonResponse
    {
        return DataTables::eloquent($this->buildingQuery())
            ->addColumn('has_decision', fn (Building $building): string => $building->committeeDecision !== null
                ? '<span class="badge badge-light-success">يوجد</span>'
                : '<span class="badge badge-light-warning">لا يوجد</span>')
            ->addColumn('signatures_count', fn (Building $building): string => $this->signatureBadge($building->committeeDecision))
            ->addColumn('arcgis_status', fn (Building $building): string => $this->syncBadge($building->committeeDecision?->arcgis_sync_status, 'ArcGIS'))
            ->addColumn('telegram_status', fn (Building $building): string => $this->syncBadge($building->committeeDecision?->telegram_status, 'Telegram'))
            ->addColumn('actions', fn (Building $building): string => '<a class="btn btn-light-primary btn-sm" href="'.route('committee-decisions.buildings.show', $building).'">فتح القرار</a>')
            ->rawColumns(['has_decision', 'signatures_count', 'arcgis_status', 'telegram_status', 'actions'])
            ->toJson();
    }

    public function housingUnitsData(): JsonResponse
    {
        return DataTables::eloquent($this->housingUnitQuery())
            ->addColumn('building_name', fn (HousingUnit $unit): string => e($unit->building?->building_name ?? '-'))
            ->addColumn('assignedto', fn (HousingUnit $unit): string => e($unit->building?->assignedto ?? '-'))
            ->addColumn('has_decision', fn (HousingUnit $unit): string => $unit->committeeDecision !== null
                ? '<span class="badge badge-light-success">يوجد</span>'
                : '<span class="badge badge-light-warning">لا يوجد</span>')
            ->addColumn('signatures_count', fn (HousingUnit $unit): string => $this->signatureBadge($unit->committeeDecision))
            ->addColumn('arcgis_status', fn (HousingUnit $unit): string => $this->syncBadge($unit->committeeDecision?->arcgis_sync_status, 'ArcGIS'))
            ->addColumn('telegram_status', fn (HousingUnit $unit): string => $this->syncBadge($unit->committeeDecision?->telegram_status, 'Telegram'))
            ->addColumn('actions', fn (HousingUnit $unit): string => '<a class="btn btn-light-primary btn-sm" href="'.route('committee-decisions.housing-units.show', $unit).'">فتح القرار</a>')
            ->rawColumns(['has_decision', 'signatures_count', 'arcgis_status', 'telegram_status', 'actions'])
            ->toJson();
    }

    public function showBuilding(Building $building): View
    {
        $decision = $this->workflowService->findOrCreateDecision($building->load('committeeDecision.signatures'), auth()->user());

        return $this->decisionView($decision, 'building');
    }

    public function showHousingUnit(HousingUnit $housingUnit): View
    {
        $decision = $this->workflowService->findOrCreateDecision($housingUnit->load('building', 'committeeDecision.signatures'), auth()->user());

        return $this->decisionView($decision, 'housing-unit');
    }

    public function update(SaveCommitteeDecisionRequest $request, CommitteeDecision $committeeDecision): RedirectResponse
    {
        $this->workflowService->saveDecisionContent($committeeDecision, $request->validated(), auth()->user());

        return redirect()
            ->back()
            ->with('success', 'تم حفظ قرار اللجنة وإرساله إلى مسار التواقيع.');
    }

    public function sign(SignCommitteeDecisionRequest $request, CommitteeDecision $committeeDecision): RedirectResponse
    {
        $member = CommitteeMember::query()->findOrFail($request->integer('committee_member_id'));

        $this->workflowService->recordSignature($committeeDecision, $member, $request->validated(), auth()->user());

        return redirect()
            ->back()
            ->with('success', 'تم تسجيل التوقيع بنجاح.');
    }

    public function retryTelegram(CommitteeDecision $committeeDecision): RedirectResponse
    {
        abort_unless(auth()->user()->can('send committee telegram'), 403);
        abort_unless($committeeDecision->isCompleted(), 422, 'لا يمكن إعادة محاولة تيليجرام قبل اكتمال القرار.');

        $committeeDecision->forceFill([
            'telegram_status' => 'retrying',
            'telegram_last_error' => null,
        ])->save();

        SendCommitteeDecisionTelegramJob::dispatch($committeeDecision->id)->afterCommit();

        return redirect()
            ->back()
            ->with('success', 'تمت جدولة إعادة محاولة إرسال تيليجرام.');
    }

    private function buildingQuery(): Builder
    {
        return Building::query()
            ->with(['committeeDecision.signatures.committeeMember'])
            ->select(['id', 'objectid', 'globalid', 'building_name', 'neighborhood', 'assignedto', 'building_damage_status'])
            ->whereIn('building_damage_status', ['commite_review', 'committee_review']);
    }

    private function housingUnitQuery(): Builder
    {
        return HousingUnit::query()
            ->with(['building:id,globalid,building_name,assignedto,neighborhood', 'committeeDecision.signatures.committeeMember'])
            ->select([
                'id',
                'objectid',
                'globalid',
                'parentglobalid',
                'housing_unit_number',
                'unit_owner',
                'q_9_3_1_first_name',
                'q_9_3_2_second_name__father',
                'q_9_3_4_last_name',
                'neighborhood',
                'unit_damage_status',
            ])
            ->whereIn('unit_damage_status', ['commite_review', 'committee_review', 'committee_review2']);
    }

    private function decisionView(CommitteeDecision $decision, string $recordType): View
    {
        $decision->load([
            'decisionable',
            'committeeManager',
            'signatures.committeeMember.user',
            'signatures.signedByUser',
        ]);

        $decisionable = $decision->decisionable;
        $building = $decisionable instanceof HousingUnit ? $decisionable->loadMissing('building')->building : $decisionable;

        return view('Committee.Decisions.show', [
            'decision' => $decision,
            'decisionable' => $decisionable,
            'building' => $building,
            'recordType' => $recordType,
            'canManageContent' => auth()->user()->can('manage committee decision content'),
            'canSign' => auth()->user()->can('sign committee decisions'),
            'canRetryTelegram' => auth()->user()->can('send committee telegram'),
            'decisionTypes' => [
                'accepted' => 'مقبول',
                'rejected' => 'مرفوض',
                'needs_completion' => 'بحاجة لاستكمال',
                'needs_review' => 'بحاجة لمراجعة',
                'recount' => 'إعادة حصر',
                'full_demolition' => 'هدم كلي',
                'partial_demolition' => 'هدم جزئي',
                'other' => 'أخرى',
            ],
            'statusLabels' => [
                CommitteeDecision::STATUS_DRAFT => 'مسودة',
                CommitteeDecision::STATUS_PENDING_SIGNATURES => 'بانتظار التواقيع',
                CommitteeDecision::STATUS_APPROVED => 'معتمد',
                CommitteeDecision::STATUS_REJECTED => 'مرفوض',
                CommitteeDecision::STATUS_COMPLETED => 'مكتمل',
            ],
        ]);
    }

    private function signatureBadge(?CommitteeDecision $decision): string
    {
        if ($decision === null) {
            return '<span class="badge badge-light-secondary">0 / 0</span>';
        }

        $required = $decision->signatures->filter(fn ($signature): bool => $signature->committeeMember?->is_active && $signature->committeeMember?->is_required);
        $approvedCount = $required->where('status', 'approved')->count();

        return sprintf(
            '<span class="badge badge-light-info">%s / %s</span>',
            $approvedCount,
            $required->count(),
        );
    }

    private function syncBadge(?string $status, string $label): string
    {
        $map = [
            'synced' => 'success',
            'sent' => 'success',
            'missing_chat_id' => 'warning',
            'not_configured' => 'warning',
            'failed' => 'danger',
            'retrying' => 'info',
            'skipped' => 'secondary',
            null => 'secondary',
        ];

        $color = $map[$status] ?? 'secondary';
        $text = $status ? str($status)->replace('_', ' ')->title() : 'Pending';

        return '<span class="badge badge-light-'.$color.'">'.$label.': '.e((string) $text).'</span>';
    }
}
