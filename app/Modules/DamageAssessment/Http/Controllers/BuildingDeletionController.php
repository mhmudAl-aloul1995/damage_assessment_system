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
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $buildings = $this->deletionCandidateBuildings($request, $selectedBuildingGlobalId);

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
            ->first();
        $auditedBuilding = $building === null && Schema::hasTable('audited_buildings')
            ? DB::table('audited_buildings')->where('globalid', $request->validated('building_globalid'))->first()
            : null;

        abort_if($building === null && $auditedBuilding === null, 404);
        abort_unless($this->canRequestDeletionForBuilding($request, $request->validated('building_globalid')), 403);

        $deletionRequest = DB::transaction(function () use ($request, $building, $auditedBuilding, $signatures, $audit): BuildingDeletionRequest {
            $deletionRequest = BuildingDeletionRequest::query()->create([
                'building_id' => $building?->id,
                'building_globalid' => $building?->globalid ?? $auditedBuilding->globalid,
                'building_objectid' => $building?->objectid ?? $auditedBuilding->objectid,
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
                'building_globalid' => $building?->globalid ?? $auditedBuilding->globalid,
                'building_objectid' => $building?->objectid ?? $auditedBuilding->objectid,
            ], $request->user()->id);

            return $deletionRequest;
        });

        return redirect()
            ->route('building-deletions.show', $deletionRequest)
            ->with('success', __('ui.building_deletions.messages.submitted'));
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
            return back()->withErrors(['decision' => __('ui.building_deletions.messages.not_pending_gis_review')]);
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
            ->with('success', __('ui.building_deletions.messages.reviewed'));
    }

    public function retry(BuildingDeletionRequest $buildingDeletionRequest): RedirectResponse
    {
        $this->authorize('process', $buildingDeletionRequest);

        if ($buildingDeletionRequest->status !== BuildingDeletionStatus::Failed) {
            return back()->withErrors(['retry' => __('ui.building_deletions.messages.only_failed_retry')]);
        }

        ProcessBuildingDeletionRequest::dispatch($buildingDeletionRequest->id)->onQueue('arcgis');

        return back()->with('success', __('ui.building_deletions.messages.retry_sent'));
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

    /**
     * @return Collection<int, object>
     */
    private function deletionCandidateBuildings(Request $request, string $selectedBuildingGlobalId): Collection
    {
        $user = $request->user();
        $fieldEngineerUsername = strtolower(trim((string) $user?->username_arcgis));
        $isOnlyFieldEngineer = $this->isOnlyFieldEngineer($user);
        $queries = [];

        if (Schema::hasTable('buildings')) {
            $queries[] = $this->buildingCandidateQuery('buildings', 'base', $isOnlyFieldEngineer, $fieldEngineerUsername);
        }

        if (Schema::hasTable('audited_buildings')) {
            $queries[] = $this->buildingCandidateQuery('audited_buildings', 'audited', $isOnlyFieldEngineer, $fieldEngineerUsername);
        }

        if ($queries === []) {
            return collect();
        }

        $query = array_shift($queries);

        foreach ($queries as $candidateQuery) {
            $query->unionAll($candidateQuery);
        }

        return DB::query()
            ->fromSub($query, 'building_candidates')
            ->selectRaw('MAX(id) as id, MAX(objectid) as objectid, globalid, MAX(building_name) as building_name, MAX(governorate) as governorate, MAX(municipalitie) as municipalitie, MAX(neighborhood) as neighborhood, GROUP_CONCAT(source) as source')
            ->whereNotNull('globalid')
            ->where('globalid', '!=', '')
            ->groupBy('globalid')
            ->when($selectedBuildingGlobalId !== '', function (Builder $query) use ($selectedBuildingGlobalId): void {
                $query->orderByRaw('CASE WHEN globalid = ? THEN 0 ELSE 1 END', [$selectedBuildingGlobalId]);
            })
            ->orderBy('objectid')
            ->limit(200)
            ->get();
    }

    private function buildingCandidateQuery(string $table, string $source, bool $isOnlyFieldEngineer, string $fieldEngineerUsername): Builder
    {
        $query = DB::table($table)
            ->select([
                $this->nullableColumn($table, 'id'),
                $this->nullableColumn($table, 'objectid'),
                $this->nullableColumn($table, 'globalid'),
                $this->nullableColumn($table, 'building_name'),
                $this->nullableColumn($table, 'governorate'),
                $this->nullableColumn($table, 'municipalitie'),
                $this->nullableColumn($table, 'neighborhood'),
                DB::raw("'{$source}' as source"),
            ]);

        if ($isOnlyFieldEngineer) {
            if (! Schema::hasColumn($table, 'assignedto')) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereRaw('LOWER(TRIM(assignedto)) = ?', [$fieldEngineerUsername]);
            }
        }

        return $query;
    }

    private function nullableColumn(string $table, string $column): mixed
    {
        if (Schema::hasColumn($table, $column)) {
            return $column;
        }

        return DB::raw('NULL as '.$column);
    }

    private function isOnlyFieldEngineer(?\App\Models\User $user): bool
    {
        $roleNames = $user?->getRoleNames() ?? collect();

        return $roleNames->count() === 1
            && $roleNames->contains(fn (string $role): bool => in_array($role, ['Field Engineer', 'field Engineer'], true));
    }

    private function canRequestDeletionForBuilding(Request $request, string $buildingGlobalId): bool
    {
        if (! $this->isOnlyFieldEngineer($request->user())) {
            return true;
        }

        $fieldEngineerUsername = strtolower(trim((string) $request->user()?->username_arcgis));

        foreach (['buildings', 'audited_buildings'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'assignedto')) {
                continue;
            }

            if (DB::table($table)
                ->where('globalid', $buildingGlobalId)
                ->whereRaw('LOWER(TRIM(assignedto)) = ?', [$fieldEngineerUsername])
                ->exists()) {
                return true;
            }
        }

        return false;
    }
}
