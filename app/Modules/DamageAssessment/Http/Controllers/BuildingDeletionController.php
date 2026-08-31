<?php

namespace App\Modules\DamageAssessment\Http\Controllers;

use App\Enums\BuildingDeletionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewBuildingDeletionRequest;
use App\Http\Requests\StoreBuildingDeletionRequest;
use App\Jobs\ProcessBuildingDeletionRequest;
use App\Models\Building;
use App\Models\BuildingDeletionRequest;
use App\Models\TeamLeaderFieldEngineer;
use App\Models\User;
use App\services\BuildingDeletion\BuildingDeletionAuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BuildingDeletionController extends Controller
{
    private const FIELD_ENGINEER_ROLES = ['Field Engineer', 'field Engineer'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BuildingDeletionRequest::class);

        $canViewAllRequests = $request->user()?->can('damage-assessment.building-deletion.view')
            || $request->user()?->can('damage-assessment.building-deletion.gis-review')
            || $request->user()?->can('damage-assessment.building-deletion.process');
        $teamLeaderFieldEngineerIds = $request->user()?->hasRole('Team Leader')
            ? TeamLeaderFieldEngineer::query()
                ->where('team_leader_id', $request->user()->id)
                ->pluck('field_engineer_id')
            : collect();

        $requests = BuildingDeletionRequest::query()
            ->with(['requester', 'teamLeaderReviewer', 'areaManagerReviewer', 'gisReviewer', 'latestSnapshot'])
            ->when(! $canViewAllRequests, function ($query) use ($request, $teamLeaderFieldEngineerIds): void {
                $query->where(function ($query) use ($request, $teamLeaderFieldEngineerIds): void {
                    $query->where('requested_by', $request->user()->id);

                    if ($teamLeaderFieldEngineerIds->isNotEmpty()) {
                        $query->orWhere(function ($query) use ($teamLeaderFieldEngineerIds): void {
                            $query
                                ->where('status', BuildingDeletionStatus::PendingTeamLeaderReview->value)
                                ->whereIn('requested_by', $teamLeaderFieldEngineerIds);
                        });
                    }

                    if ($request->user()?->hasRole('Area Manager')) {
                        $query->orWhere(function ($query) use ($request): void {
                            $query
                                ->where('status', BuildingDeletionStatus::PendingAreaManagerReview->value)
                                ->where('area_manager_reviewed_by', $request->user()->id);
                        });
                    }
                });
            })
            ->latest()
            ->paginate(25);

        return view('damage-assessment::building-deletions.index', [
            'requests' => $requests,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', BuildingDeletionRequest::class);

        $selectedBuildingGlobalId = (string) $request->query('building_globalid', '');
        $buildings = $this->deletionCandidateBuildings($request, $selectedBuildingGlobalId);
        $viewData = [
            'buildings' => $buildings,
            'selectedBuildingGlobalId' => $selectedBuildingGlobalId,
            'isModal' => $request->ajax(),
        ];

        if ($request->ajax()) {
            return view('damage-assessment::building-deletions._form', $viewData);
        }

        return view('damage-assessment::building-deletions.create', $viewData);
    }

    public function store(
        StoreBuildingDeletionRequest $request,
        BuildingDeletionAuditLogger $audit,
    ): RedirectResponse|JsonResponse {
        $building = Building::query()
            ->where('globalid', $request->validated('building_globalid'))
            ->first();
        $auditedBuilding = $building === null && Schema::hasTable('audited_buildings')
            ? DB::table('audited_buildings')->where('globalid', $request->validated('building_globalid'))->first()
            : null;

        abort_if($building === null && $auditedBuilding === null, 404);
        abort_unless($this->canRequestDeletionForBuilding($request, $request->validated('building_globalid')), 403);

        $deletionRequest = DB::transaction(function () use ($request, $building, $auditedBuilding, $audit): BuildingDeletionRequest {
            $requiresFieldEngineerApprovals = $this->requiresFieldEngineerApprovals($request->user());
            $teamLeaderId = $requiresFieldEngineerApprovals ? $this->teamLeaderIdForFieldEngineer($request->user()) : null;
            $initialStatus = $requiresFieldEngineerApprovals
                ? BuildingDeletionStatus::PendingTeamLeaderReview
                : BuildingDeletionStatus::PendingGisReview;

            $deletionRequest = BuildingDeletionRequest::query()->create([
                'building_id' => $building?->id,
                'building_globalid' => $building?->globalid ?? $auditedBuilding->globalid,
                'building_objectid' => $building?->objectid ?? $auditedBuilding->objectid,
                'requested_by' => $request->user()->id,
                'reason' => $request->validated('reason'),
                'notes' => $request->validated('notes'),
                'status' => $initialStatus,
                'requires_field_engineer_approvals' => $requiresFieldEngineerApprovals,
                'team_leader_reviewed_by' => $teamLeaderId,
            ]);

            $audit->log($deletionRequest, 'request_submitted', $initialStatus->value, 'Applicant submitted the deletion request.', [
                'building_globalid' => $building?->globalid ?? $auditedBuilding->globalid,
                'building_objectid' => $building?->objectid ?? $auditedBuilding->objectid,
            ], $request->user()->id);

            return $deletionRequest;
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => __('ui.building_deletions.messages.submitted'),
                'redirect_url' => route('building-deletions.show', $deletionRequest),
            ], 201);
        }

        return redirect()
            ->route('building-deletions.show', $deletionRequest)
            ->with('success', __('ui.building_deletions.messages.submitted'));
    }

    public function show(BuildingDeletionRequest $buildingDeletionRequest): View
    {
        $this->authorize('view', $buildingDeletionRequest);

        $buildingDeletionRequest->load([
            'requester',
            'teamLeaderReviewer',
            'areaManagerReviewer',
            'gisReviewer',
            'latestSnapshot',
            'auditLogs.user',
        ]);

        $building = Building::query()
            ->where('globalid', $buildingDeletionRequest->building_globalid)
            ->first();

        return view('damage-assessment::building-deletions.show', [
            'request' => $buildingDeletionRequest,
            'building' => $building,
            'canReview' => $this->canReviewCurrentStage(request()->user(), $buildingDeletionRequest),
            'reviewTitleKey' => $this->reviewTitleKey($buildingDeletionRequest),
            'canProcess' => request()->user()?->can('process', $buildingDeletionRequest) ?? false,
            'canViewRawSnapshot' => request()->user()?->can('viewRawSnapshot', $buildingDeletionRequest) ?? false,
        ]);
    }

    public function review(
        ReviewBuildingDeletionRequest $request,
        BuildingDeletionRequest $buildingDeletionRequest,
        BuildingDeletionAuditLogger $audit,
    ): RedirectResponse {
        if (! in_array($buildingDeletionRequest->status, [
            BuildingDeletionStatus::PendingTeamLeaderReview,
            BuildingDeletionStatus::PendingAreaManagerReview,
            BuildingDeletionStatus::PendingGisReview,
        ], true)) {
            return back()->withErrors(['decision' => __('ui.building_deletions.messages.not_pending_review')]);
        }

        $decision = $request->validated('decision');
        $reviewNotes = $request->validated('review_notes');

        $shouldDispatchDeletion = DB::transaction(function () use ($request, $buildingDeletionRequest, $audit, $decision, $reviewNotes): bool {
            return match ($buildingDeletionRequest->status) {
                BuildingDeletionStatus::PendingTeamLeaderReview => $this->reviewTeamLeader($request, $buildingDeletionRequest, $audit, $decision, $reviewNotes),
                BuildingDeletionStatus::PendingAreaManagerReview => $this->reviewAreaManager($request, $buildingDeletionRequest, $audit, $decision, $reviewNotes),
                BuildingDeletionStatus::PendingGisReview => $this->reviewGis($request, $buildingDeletionRequest, $audit, $decision, $reviewNotes),
                default => false,
            };
        });

        if ($shouldDispatchDeletion) {
            Queue::pushOn('arcgis', new ProcessBuildingDeletionRequest($buildingDeletionRequest->id));
        }

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

        Queue::pushOn('arcgis', new ProcessBuildingDeletionRequest($buildingDeletionRequest->id));

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
        $isFieldEngineer = $this->requiresFieldEngineerApprovals($user);
        $queries = [];

        if (Schema::hasTable('buildings')) {
            $queries[] = $this->buildingCandidateQuery('buildings', 'base', $isFieldEngineer, $fieldEngineerUsername);
        }

        if (Schema::hasTable('audited_buildings')) {
            $queries[] = $this->buildingCandidateQuery('audited_buildings', 'audited', $isFieldEngineer, $fieldEngineerUsername);
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

    private function buildingCandidateQuery(string $table, string $source, bool $isFieldEngineer, string $fieldEngineerUsername): Builder
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

        if ($isFieldEngineer) {
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

    private function requiresFieldEngineerApprovals(?User $user): bool
    {
        return $user?->hasAnyRole(self::FIELD_ENGINEER_ROLES) ?? false;
    }

    private function teamLeaderIdForFieldEngineer(?User $user): int
    {
        $link = TeamLeaderFieldEngineer::query()
            ->where('field_engineer_id', $user?->id)
            ->first();

        if (! $link) {
            throw ValidationException::withMessages([
                'building_globalid' => __('ui.building_deletions.messages.missing_team_leader'),
            ]);
        }

        return (int) $link->team_leader_id;
    }

    private function canRequestDeletionForBuilding(Request $request, string $buildingGlobalId): bool
    {
        if (! $this->requiresFieldEngineerApprovals($request->user())) {
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

    private function reviewTeamLeader(
        ReviewBuildingDeletionRequest $request,
        BuildingDeletionRequest $buildingDeletionRequest,
        BuildingDeletionAuditLogger $audit,
        string $decision,
        string $reviewNotes,
    ): bool {
        if ($decision === 'approve') {
            $areaManager = $this->areaManagerForBuilding($buildingDeletionRequest);

            if (! $areaManager) {
                throw ValidationException::withMessages([
                    'review_notes' => __('ui.building_deletions.messages.missing_area_manager'),
                ]);
            }

            $buildingDeletionRequest->forceFill([
                'status' => BuildingDeletionStatus::PendingAreaManagerReview,
                'team_leader_reviewed_by' => $request->user()->id,
                'team_leader_reviewed_at' => now(),
                'team_leader_notes' => $reviewNotes,
                'area_manager_reviewed_by' => $areaManager->id,
            ])->save();

            $audit->log($buildingDeletionRequest, 'team_leader_approved', BuildingDeletionStatus::PendingAreaManagerReview->value, 'Team Leader approved the request.', null, $request->user()->id);

            return false;
        }

        $this->recordStoppedReview(
            $request,
            $buildingDeletionRequest,
            $audit,
            $decision,
            $reviewNotes,
            'team_leader',
            [
                'team_leader_reviewed_by' => $request->user()->id,
                'team_leader_reviewed_at' => now(),
                'team_leader_notes' => $reviewNotes,
            ],
        );

        return false;
    }

    private function reviewAreaManager(
        ReviewBuildingDeletionRequest $request,
        BuildingDeletionRequest $buildingDeletionRequest,
        BuildingDeletionAuditLogger $audit,
        string $decision,
        string $reviewNotes,
    ): bool {
        if ($decision === 'approve') {
            $buildingDeletionRequest->forceFill([
                'status' => BuildingDeletionStatus::PendingGisReview,
                'area_manager_reviewed_by' => $request->user()->id,
                'area_manager_reviewed_at' => now(),
                'area_manager_notes' => $reviewNotes,
            ])->save();

            $audit->log($buildingDeletionRequest, 'area_manager_approved', BuildingDeletionStatus::PendingGisReview->value, 'Area Manager approved the request.', null, $request->user()->id);

            return false;
        }

        $this->recordStoppedReview(
            $request,
            $buildingDeletionRequest,
            $audit,
            $decision,
            $reviewNotes,
            'area_manager',
            [
                'area_manager_reviewed_by' => $request->user()->id,
                'area_manager_reviewed_at' => now(),
                'area_manager_notes' => $reviewNotes,
            ],
        );

        return false;
    }

    private function reviewGis(
        ReviewBuildingDeletionRequest $request,
        BuildingDeletionRequest $buildingDeletionRequest,
        BuildingDeletionAuditLogger $audit,
        string $decision,
        string $reviewNotes,
    ): bool {
        if ($decision === 'approve') {
            $buildingDeletionRequest->forceFill([
                'status' => BuildingDeletionStatus::Approved,
                'gis_reviewed_by' => $request->user()->id,
                'gis_reviewed_at' => now(),
                'gis_notes' => $reviewNotes,
            ])->save();

            $audit->log($buildingDeletionRequest, 'gis_approved', BuildingDeletionStatus::Approved->value, 'GIS reviewer approved the request.', null, $request->user()->id);

            return true;
        }

        $this->recordStoppedReview(
            $request,
            $buildingDeletionRequest,
            $audit,
            $decision,
            $reviewNotes,
            'gis',
            [
                'gis_reviewed_by' => $request->user()->id,
                'gis_reviewed_at' => now(),
                'gis_notes' => $reviewNotes,
            ],
        );

        return false;
    }

    /**
     * @param  array<string, mixed>  $stageFields
     */
    private function recordStoppedReview(
        ReviewBuildingDeletionRequest $request,
        BuildingDeletionRequest $buildingDeletionRequest,
        BuildingDeletionAuditLogger $audit,
        string $decision,
        string $reviewNotes,
        string $stage,
        array $stageFields,
    ): void {
        $status = $decision === 'reject' ? BuildingDeletionStatus::Rejected : BuildingDeletionStatus::Returned;

        $buildingDeletionRequest->forceFill(array_merge($stageFields, [
            'status' => $status,
        ]))->save();

        $audit->log($buildingDeletionRequest, $stage.'_'.$decision, $status->value, ucfirst(str_replace('_', ' ', $stage)).' completed decision: '.$decision.'.', null, $request->user()->id);
    }

    private function areaManagerForBuilding(BuildingDeletionRequest $buildingDeletionRequest): ?User
    {
        $governorate = $this->buildingGovernorate($buildingDeletionRequest->building_globalid);
        $region = $this->regionFromGovernorate($governorate);

        if (! $region) {
            return null;
        }

        return User::role('Area Manager')
            ->where('region', $region)
            ->orderBy('id')
            ->first();
    }

    private function buildingGovernorate(string $buildingGlobalId): ?string
    {
        foreach (['buildings', 'audited_buildings'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'governorate')) {
                continue;
            }

            $governorate = DB::table($table)
                ->where('globalid', $buildingGlobalId)
                ->value('governorate');

            if (filled($governorate)) {
                return (string) $governorate;
            }
        }

        return null;
    }

    private function regionFromGovernorate(?string $governorate): ?string
    {
        $normalizedGovernorate = strtolower(trim((string) $governorate));

        return match ($normalizedGovernorate) {
            'north gaza', 'gaza' => 'north',
            'deir al-balah', 'deir al balah', 'middle area', 'khan younis', 'rafah' => 'south',
            default => null,
        };
    }

    private function canReviewCurrentStage(?User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        if (! $user) {
            return false;
        }

        return match ($buildingDeletionRequest->status) {
            BuildingDeletionStatus::PendingTeamLeaderReview => $user->can('reviewTeamLeader', $buildingDeletionRequest),
            BuildingDeletionStatus::PendingAreaManagerReview => $user->can('reviewAreaManager', $buildingDeletionRequest),
            BuildingDeletionStatus::PendingGisReview => $user->can('reviewGis', $buildingDeletionRequest),
            default => false,
        };
    }

    private function reviewTitleKey(BuildingDeletionRequest $buildingDeletionRequest): string
    {
        return match ($buildingDeletionRequest->status) {
            BuildingDeletionStatus::PendingTeamLeaderReview => 'team_leader_decision',
            BuildingDeletionStatus::PendingAreaManagerReview => 'area_manager_decision',
            default => 'gis_decision',
        };
    }
}
