<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\SaveCommitteeDecisionRequest;
use App\Http\Requests\Committee\SignCommitteeDecisionRequest;
use App\Models\Building;
use App\Models\CommitteeDecision;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\services\ArcGisStatusUpdaterService;
use App\services\CommitteeDecisionWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CommitteeDecisionController extends Controller
{
    public function __construct(
        private readonly CommitteeDecisionWorkflowService $workflowService,
        private readonly ArcGisStatusUpdaterService $arcGisStatusUpdaterService,
    ) {}

    public function index(): View
    {
        return view('damage-assessment::committee.decisions.index', [
            'buildingCount' => $this->buildingQuery()->count(),
            'housingCount' => $this->housingUnitQuery()->count(),
            'municipalities' => $this->municipalityOptions(),
        ]);
    }

    public function buildingsData(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->applyBuildingFilters($this->buildingQuery(), $request))
            ->addColumn('has_decision', fn (Building $building): string => $building->committeeDecision !== null
                ? '<span class="badge badge-light-success">يوجد</span>'
                : '<span class="badge badge-light-warning">لا يوجد</span>')
            ->addColumn('signatures_count', fn (Building $building): string => $this->signatureBadge($building->committeeDecision))
            ->addColumn('arcgis_status', fn (Building $building): string => $this->syncBadge($building->committeeDecision?->arcgis_sync_status, 'ArcGIS'))
            ->addColumn('actions', fn (Building $building): string => $this->actionButtons($building))
            ->rawColumns(['has_decision', 'signatures_count', 'arcgis_status', 'actions'])
            ->toJson();
    }

    public function housingUnitsData(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->applyHousingUnitFilters($this->housingUnitQuery(), $request))
            ->addColumn('building_name', fn (HousingUnit $unit): string => e($unit->building?->building_name ?? '-'))
            ->addColumn('assignedto', fn (HousingUnit $unit): string => e($unit->building?->assignedto ?? '-'))
            ->addColumn('has_decision', fn (HousingUnit $unit): string => $unit->committeeDecision !== null
                ? '<span class="badge badge-light-success">يوجد</span>'
                : '<span class="badge badge-light-warning">لا يوجد</span>')
            ->addColumn('signatures_count', fn (HousingUnit $unit): string => $this->signatureBadge($unit->committeeDecision))
            ->addColumn('arcgis_status', fn (HousingUnit $unit): string => $this->syncBadge($unit->committeeDecision?->arcgis_sync_status, 'ArcGIS'))
            ->addColumn('actions', fn (HousingUnit $unit): string => $this->actionButtons($unit))
            ->rawColumns(['has_decision', 'signatures_count', 'arcgis_status', 'actions'])
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

    public function retryArcgis(CommitteeDecision $committeeDecision): RedirectResponse
    {
        abort_unless(auth()->user()?->can('sync committee decision arcgis'), 403);
        abort_unless($committeeDecision->isCompleted(), 422, 'لا يمكن مزامنة ArcGIS قبل اكتمال القرار.');

        $committeeDecision->forceFill([
            'arcgis_sync_status' => 'retrying',
            'arcgis_last_error' => null,
            'arcgis_last_response' => null,
        ])->save();

        $this->workflowService->markArcGisResult(
            $committeeDecision,
            $this->arcGisStatusUpdaterService->syncDecisionStatus($committeeDecision->load('decisionable')),
        );

        return redirect()
            ->back()
            ->with('success', 'تمت محاولة مزامنة ArcGIS.');
    }

    private function buildingQuery(): Builder
    {
        return Building::query()
            ->with(['committeeDecision.signatures.committeeMember'])
            ->select(['id', 'objectid', 'globalid', 'building_name', 'municipalitie', 'neighborhood', 'assignedto', 'building_damage_status', 'field_status'])
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
                'municipalitie',
                'unit_owner',
                'q_9_3_1_first_name',
                'q_9_3_2_second_name__father',
                'q_9_3_4_last_name',
                'neighborhood',
                'unit_damage_status',
            ])
            ->whereIn('unit_damage_status', ['commite_review', 'committee_review', 'committee_review2']);
    }

    private function applyBuildingFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('objectid'), fn (Builder $query) => $query->where('objectid', $request->string('objectid')->toString()))
            ->when($request->filled('municipality'), fn (Builder $query) => $query->where('municipalitie', $request->string('municipality')->toString()))
            ->when($request->filled('current_damage_status'), fn (Builder $query) => $query->where('building_damage_status', $request->string('current_damage_status')->toString()))
            ->when($request->filled('field_status'), fn (Builder $query) => $query->where('field_status', $request->string('field_status')->toString()))
            ->when($request->filled('has_decision'), function (Builder $query) use ($request): void {
                $request->string('has_decision')->toString() === 'yes'
                    ? $query->whereHas('committeeDecision')
                    : $query->whereDoesntHave('committeeDecision');
            })
            ->when($request->filled('decision_type'), fn (Builder $query) => $query->whereHas('committeeDecision', fn (Builder $query) => $query->where('decision_type', $request->string('decision_type')->toString())))
            ->when($request->filled('decision_status'), fn (Builder $query) => $query->whereHas('committeeDecision', fn (Builder $query) => $query->where('status', $request->string('decision_status')->toString())))
            ->when($request->filled('arcgis_status'), fn (Builder $query) => $this->filterArcgisStatus($query, $request->string('arcgis_status')->toString()));
    }

    private function applyHousingUnitFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('objectid'), fn (Builder $query) => $query->where('objectid', $request->string('objectid')->toString()))
            ->when($request->filled('municipality'), function (Builder $query) use ($request): void {
                $municipality = $request->string('municipality')->toString();

                $query->where(function (Builder $query) use ($municipality): void {
                    $query
                        ->where('municipalitie', $municipality)
                        ->orWhereHas('building', fn (Builder $query) => $query->where('municipalitie', $municipality));
                });
            })
            ->when($request->filled('current_damage_status'), fn (Builder $query) => $query->where('unit_damage_status', $request->string('current_damage_status')->toString()))
            ->when($request->filled('field_status'), fn (Builder $query) => $query->whereHas('building', fn (Builder $query) => $query->where('field_status', $request->string('field_status')->toString())))
            ->when($request->filled('has_decision'), function (Builder $query) use ($request): void {
                $request->string('has_decision')->toString() === 'yes'
                    ? $query->whereHas('committeeDecision')
                    : $query->whereDoesntHave('committeeDecision');
            })
            ->when($request->filled('decision_type'), fn (Builder $query) => $query->whereHas('committeeDecision', fn (Builder $query) => $query->where('decision_type', $request->string('decision_type')->toString())))
            ->when($request->filled('decision_status'), fn (Builder $query) => $query->whereHas('committeeDecision', fn (Builder $query) => $query->where('status', $request->string('decision_status')->toString())))
            ->when($request->filled('arcgis_status'), fn (Builder $query) => $this->filterArcgisStatus($query, $request->string('arcgis_status')->toString()));
    }

    private function filterArcgisStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('committeeDecision', function (Builder $query) use ($status): void {
            $status === 'pending'
                ? $query->whereNull('arcgis_sync_status')
                : $query->where('arcgis_sync_status', $status);
        });
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

        return view('damage-assessment::committee.decisions.show', [
            'decision' => $decision,
            'decisionable' => $decisionable,
            'building' => $building,
            'recordType' => $recordType,
            'committeeMembers' => CommitteeMember::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'suggestedCommitteeMembers' => $decision->signatures->isEmpty()
                ? $this->workflowService->latestSignatureTemplate($decision)
                : [],
            'canManageContent' => auth()->user()->can('manage committee decision content'),
            'canSign' => auth()->user()->can('sign committee decisions'),
            'canRetryArcgis' => auth()->user()->can('sync committee decision arcgis'),
            'decisionTypes' => [
                'fully_damaged' => 'كلي',
                'partially_damaged' => 'جزئي',
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

        $required = $decision->signatures->filter(fn ($signature): bool => $signature->committeeMember?->is_active && $signature->is_required);
        $approvedCount = $required->where('status', 'approved')->count();

        return sprintf(
            '<span class="badge badge-light-info">%s / %s</span>',
            $approvedCount,
            $required->count(),
        );
    }

    private function actionButtons(Building|HousingUnit $record): string
    {
        $decision = $record->committeeDecision;
        $showRoute = $record instanceof Building
            ? route('committee-decisions.buildings.show', $record)
            : route('committee-decisions.housing-units.show', $record);

        $buttons = '<div class="d-flex gap-2 justify-content-end flex-wrap">';
        $buttons .= '<a class="btn btn-light-primary btn-sm" href="'.$showRoute.'">فتح القرار</a>';

        if ($decision?->isCompleted() && $decision->arcgis_sync_status !== 'synced' && auth()->user()?->can('sync committee decision arcgis')) {
            $buttons .= '<form method="POST" action="'.route('committee-decisions.retry-arcgis', $decision).'">';
            $buttons .= csrf_field();
            $buttons .= '<button type="submit" class="btn btn-light-warning btn-sm">مزامنة ArcGIS</button>';
            $buttons .= '</form>';
        }

        return $buttons.'</div>';
    }

    private function syncBadge(?string $status, string $label): string
    {
        $map = [
            'synced' => 'success',
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

    /**
     * @return list<string>
     */
    private function municipalityOptions(): array
    {
        return collect()
            ->merge(Building::query()
                ->whereNotNull('municipalitie')
                ->distinct()
                ->orderBy('municipalitie')
                ->pluck('municipalitie'))
            ->merge(HousingUnit::query()
                ->whereNotNull('municipalitie')
                ->distinct()
                ->orderBy('municipalitie')
                ->pluck('municipalitie'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
