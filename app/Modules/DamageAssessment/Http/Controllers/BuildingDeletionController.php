<?php

namespace App\Modules\DamageAssessment\Http\Controllers;

use App\Enums\BuildingDeletionSignatureAction;
use App\Enums\BuildingDeletionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewBuildingDeletionRequest;
use App\Http\Requests\StoreBuildingDeletionRequest;
use App\Jobs\ProcessBuildingDeletionRequest;
use App\Models\Building;
use App\Models\BuildingDeletionRequest;
use App\services\BuildingDeletion\BuildingDeletionAuditLogger;
use App\services\BuildingDeletion\BuildingDeletionLayerDiscovery;
use App\services\BuildingDeletion\BuildingDeletionSignatureService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuildingDeletionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', BuildingDeletionRequest::class);

        $canViewAllRequests = $request->user()?->can('damage-assessment.building-deletion.view')
            || $request->user()?->can('damage-assessment.building-deletion.gis-review')
            || $request->user()?->can('damage-assessment.building-deletion.process');

        $requests = BuildingDeletionRequest::query()
            ->with(['requester', 'gisReviewer', 'latestSnapshot'])
            ->when(! $canViewAllRequests, function ($query) use ($request): void {
                $query->where('requested_by', $request->user()->id);
            })
            ->latest()
            ->paginate(25);

        return view('damage-assessment::building-deletions.index', [
            'requests' => $requests,
        ]);
    }

    public function create(Request $request, BuildingDeletionLayerDiscovery $layers): View
    {
        $this->authorize('create', BuildingDeletionRequest::class);

        $selectedBuildingGlobalId = (string) $request->query('building_globalid', '');
        $buildings = Building::query()
            ->select(['id', 'objectid', 'globalid', 'building_name', 'governorate', 'municipalitie', 'neighborhood'])
            ->when($selectedBuildingGlobalId !== '', function ($query) use ($selectedBuildingGlobalId): void {
                $query->orderByRaw('CASE WHEN globalid = ? THEN 0 ELSE 1 END', [$selectedBuildingGlobalId]);
            })
            ->orderBy('objectid')
            ->limit(200)
            ->get();

        return view('damage-assessment::building-deletions.create', [
            'buildings' => $buildings,
            'selectedBuildingGlobalId' => $selectedBuildingGlobalId,
            'deletionPlan' => $layers->deletionPlan(),
            'dryRun' => (bool) config('services.arcgis.building_deletion_dry_run', false),
        ]);
    }

    public function store(
        StoreBuildingDeletionRequest $request,
        BuildingDeletionSignatureService $signatures,
        BuildingDeletionAuditLogger $audit,
    ): RedirectResponse {
        $building = Building::query()
            ->where('globalid', $request->validated('building_globalid'))
            ->firstOrFail();

        $deletionRequest = DB::transaction(function () use ($request, $building, $signatures, $audit): BuildingDeletionRequest {
            $deletionRequest = BuildingDeletionRequest::query()->create([
                'building_id' => $building->id,
                'building_globalid' => $building->globalid,
                'building_objectid' => $building->objectid,
                'requested_by' => $request->user()->id,
                'reason' => $request->validated('reason'),
                'notes' => $request->validated('notes'),
                'status' => BuildingDeletionStatus::PendingGisReview,
            ]);

            $signatures->store(
                $deletionRequest,
                $request->user(),
                BuildingDeletionSignatureAction::Requested,
                $request->validated('signature'),
            );

            $audit->log($deletionRequest, 'request_submitted', 'pending_gis_review', 'Applicant submitted and signed the deletion request.', [
                'building_globalid' => $building->globalid,
                'building_objectid' => $building->objectid,
            ], $request->user()->id);

            return $deletionRequest;
        });

        return redirect()
            ->route('building-deletions.show', $deletionRequest)
            ->with('success', 'تم إرسال طلب حذف المبنى لمراجعة GIS.');
    }

    public function show(BuildingDeletionRequest $buildingDeletionRequest): View
    {
        $this->authorize('view', $buildingDeletionRequest);

        $buildingDeletionRequest->load([
            'requester',
            'gisReviewer',
            'signatures.user',
            'latestSnapshot',
            'auditLogs.user',
        ]);

        $building = Building::query()
            ->where('globalid', $buildingDeletionRequest->building_globalid)
            ->first();

        return view('damage-assessment::building-deletions.show', [
            'request' => $buildingDeletionRequest,
            'building' => $building,
            'canReview' => request()->user()?->can('reviewGis', $buildingDeletionRequest) ?? false,
            'canProcess' => request()->user()?->can('process', $buildingDeletionRequest) ?? false,
            'canViewRawSnapshot' => request()->user()?->can('viewRawSnapshot', $buildingDeletionRequest) ?? false,
            'dryRun' => (bool) config('services.arcgis.building_deletion_dry_run', false),
        ]);
    }

    public function review(
        ReviewBuildingDeletionRequest $request,
        BuildingDeletionRequest $buildingDeletionRequest,
        BuildingDeletionSignatureService $signatures,
        BuildingDeletionAuditLogger $audit,
    ): RedirectResponse {
        if ($buildingDeletionRequest->status !== BuildingDeletionStatus::PendingGisReview) {
            return back()->withErrors(['decision' => 'This request is not pending GIS review.']);
        }

        $decision = $request->validated('decision');

        DB::transaction(function () use ($request, $buildingDeletionRequest, $signatures, $audit, $decision): void {
            if ($decision === 'approve') {
                $buildingDeletionRequest->forceFill([
                    'status' => BuildingDeletionStatus::Approved,
                    'gis_reviewed_by' => $request->user()->id,
                    'gis_reviewed_at' => now(),
                    'gis_notes' => $request->validated('gis_notes'),
                ])->save();

                $signatures->store(
                    $buildingDeletionRequest,
                    $request->user(),
                    BuildingDeletionSignatureAction::GisApproved,
                    $request->validated('signature'),
                    $request->validated('gis_notes'),
                );

                $audit->log($buildingDeletionRequest, 'gis_approved', 'approved', 'GIS reviewer approved and signed the request.', null, $request->user()->id);
                ProcessBuildingDeletionRequest::dispatch($buildingDeletionRequest->id)->afterCommit()->onQueue('arcgis');

                return;
            }

            $status = $decision === 'reject' ? BuildingDeletionStatus::Rejected : BuildingDeletionStatus::Returned;
            $action = $decision === 'reject' ? BuildingDeletionSignatureAction::GisRejected : BuildingDeletionSignatureAction::Returned;

            $buildingDeletionRequest->forceFill([
                'status' => $status,
                'gis_reviewed_by' => $request->user()->id,
                'gis_reviewed_at' => now(),
                'gis_notes' => $request->validated('gis_notes'),
            ])->save();

            if (filled($request->validated('signature'))) {
                $signatures->store($buildingDeletionRequest, $request->user(), $action, $request->validated('signature'), $request->validated('gis_notes'));
            }

            $audit->log($buildingDeletionRequest, 'gis_'.$decision, $status->value, 'GIS reviewer completed decision: '.$decision.'.', null, $request->user()->id);
        });

        return redirect()
            ->route('building-deletions.show', $buildingDeletionRequest)
            ->with('success', 'تم تسجيل قرار مراجعة GIS.');
    }

    public function retry(BuildingDeletionRequest $buildingDeletionRequest): RedirectResponse
    {
        $this->authorize('process', $buildingDeletionRequest);

        if ($buildingDeletionRequest->status !== BuildingDeletionStatus::Failed) {
            return back()->withErrors(['retry' => 'Only failed requests can be retried.']);
        }

        ProcessBuildingDeletionRequest::dispatch($buildingDeletionRequest->id)->onQueue('arcgis');

        return back()->with('success', 'تم إرسال الطلب لإعادة المحاولة عبر Queue.');
    }

    public function rawSnapshot(BuildingDeletionRequest $buildingDeletionRequest): View
    {
        $this->authorize('viewRawSnapshot', $buildingDeletionRequest);

        $buildingDeletionRequest->load('latestSnapshot');

        return view('damage-assessment::building-deletions.raw-snapshot', [
            'request' => $buildingDeletionRequest,
            'snapshot' => $buildingDeletionRequest->latestSnapshot,
        ]);
    }
}
