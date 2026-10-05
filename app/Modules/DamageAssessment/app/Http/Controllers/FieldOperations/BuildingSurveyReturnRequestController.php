<?php

namespace App\Modules\DamageAssessment\Http\Controllers\FieldOperations;

use App\Http\Controllers\Controller;
use App\Models\AuditedBuilding;
use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\BuildingSurveyReturnRequest;
use App\Models\BuildingSurveyReturnRequestLog;
use App\Models\EditAssessment;
use App\Models\TeamLeaderFieldEngineer;
use App\Models\User;
use App\services\ArcgisService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BuildingSurveyReturnRequestController extends Controller
{
    public function index(): View
    {
        $query = BuildingSurveyReturnRequest::query()
            ->with(['building', 'requester', 'teamLeader', 'areaManager']);

        if (auth()->user()->hasRole('Area Manager')) {
            $query->where('area_manager_id', auth()->id());
        } elseif ($this->currentUserIsTeamLeader()) {
            $query->where('team_leader_id', auth()->id());
        } elseif (auth()->user()->hasRole('Field Engineer')) {
            $query->where('requested_by', auth()->id());
        }

        $requests = $query->latest()->get();

        $buildings = $this->buildingOptionsFor(auth()->user());

        return view('damage-assessment::field-operations.building-survey-return-requests.index', compact('requests', 'buildings'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->hasRole('Field Engineer'), 403);

        $buildings = $this->buildingOptionsFor(auth()->user());

        return view('damage-assessment::field-operations.building-survey-return-requests.create', compact('buildings'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (! auth()->user()->hasRole('Field Engineer')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'فقط المهندس الميداني يمكنه إنشاء طلب إرجاع استبيان.',
                ], 403);
            }

            abort(403, 'فقط المهندس الميداني يمكنه إنشاء طلب إرجاع استبيان.');
        }

        $validated = $request->validate([
            'building_objectid' => ['required', 'exists:audited_buildings,objectid'],
            'reason' => ['nullable', 'string'],
        ]);

        $auditedBuilding = AuditedBuilding::query()
            ->where('objectid', $validated['building_objectid'])
            ->firstOrFail();

        $building = Building::query()
            ->where('objectid', $validated['building_objectid'])
            ->first();

        $assignedTo = trim((string) auth()->user()->username_arcgis);

        if ($assignedTo === '' || strtolower(trim((string) $auditedBuilding->assignedto)) !== strtolower($assignedTo)) {
            throw ValidationException::withMessages([
                'building_objectid' => 'لا يمكنك إنشاء طلب إرجاع لمبنى غير مخصص لك.',
            ]);
        }

        $link = TeamLeaderFieldEngineer::query()
            ->where('field_engineer_id', auth()->id())
            ->first();

        if (! $link) {
            throw ValidationException::withMessages([
                'building_objectid' => 'لا يوجد Team Leader مربوط بهذا المهندس الميداني.',
            ]);
        }

        $openRequestExists = BuildingSurveyReturnRequest::query()
            ->where('building_objectid', $auditedBuilding->objectid)
            ->whereNotIn('status', ['completed', 'rejected'])
            ->exists();

        if ($openRequestExists) {
            throw ValidationException::withMessages([
                'building_objectid' => 'يوجد طلب مفتوح لنفس المبنى.',
            ]);
        }

        $returnRequest = DB::transaction(function () use ($auditedBuilding, $building, $link, $validated): BuildingSurveyReturnRequest {
            $returnRequest = BuildingSurveyReturnRequest::query()->create([
                'building_id' => $building?->id,
                'building_objectid' => $auditedBuilding->objectid,
                'building_globalid' => $auditedBuilding->globalid,
                'requested_by' => auth()->id(),
                'team_leader_id' => $link->team_leader_id,
                'current_step' => 'team_leader',
                'status' => 'pending',
                'reason' => $validated['reason'] ?? null,
                'requested_at' => now(),
            ]);

            $this->log($returnRequest, 'created', 'field_engineer', $validated['reason'] ?? null);

            return $returnRequest;
        });

        if ($request->expectsJson()) {
            $returnRequest->load(['building', 'requester', 'teamLeader', 'areaManager']);

            return response()->json([
                'status' => true,
                'message' => 'تم إرسال طلب إرجاع الاستبيان بنجاح.',
                'return_request_id' => $returnRequest->id,
                'redirect_url' => route('building-survey-return-requests.show', $returnRequest),
                'table_row' => $this->tableRow($returnRequest),
            ]);
        }

        return redirect()
            ->route('building-survey-return-requests.index')
            ->with('success', 'تم إرسال طلب إرجاع الاستبيان بنجاح.');
    }

    public function show(BuildingSurveyReturnRequest $returnRequest): View
    {
        $returnRequest->load(['building', 'requester', 'teamLeader', 'areaManager', 'logs.user', 'archiveObject']);

        $areaManagers = User::role('Area Manager')->orderBy('name')->get(['id', 'name', 'region']);

        return view('damage-assessment::field-operations.building-survey-return-requests.show', compact('returnRequest', 'areaManagers'));
    }

    public function approveByTeamLeader(Request $request, BuildingSurveyReturnRequest $returnRequest): RedirectResponse|JsonResponse
    {
        $this->ensureTeamLeaderCanHandle($returnRequest);

        if ($returnRequest->current_step !== 'team_leader') {
            abort(422, 'الطلب ليس في خطوة Team Leader.');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $building = $returnRequest->building()->firstOrFail();
        $areaManager = $this->areaManagerForBuildingGovernorate($building);

        if (! $areaManager) {
            throw ValidationException::withMessages([
                'area_manager_id' => 'لا يوجد Area Manager مرتبط بمحافظة المبنى.',
            ]);
        }

        DB::transaction(function () use ($returnRequest, $validated, $areaManager): void {
            $returnRequest->forceFill([
                'status' => 'approved_by_team_leader',
                'current_step' => 'area_manager',
                'area_manager_id' => $areaManager->id,
                'team_leader_approved_at' => now(),
                'team_leader_notes' => $validated['notes'] ?? null,
            ])->save();

            $this->log($returnRequest, 'approved', 'team_leader', $validated['notes'] ?? null);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'message' => 'تمت موافقة Team Leader بنجاح.',
                'redirect_url' => route('building-survey-return-requests.index'),
            ]);
        }

        return back()->with('success', 'تمت موافقة Team Leader بنجاح.');
    }

    public function approveByAreaManager(Request $request, BuildingSurveyReturnRequest $returnRequest, ArcgisService $arcgisService): RedirectResponse|JsonResponse
    {
        $this->ensureAreaManagerCanHandle($returnRequest);

        if ($returnRequest->current_step !== 'area_manager') {
            abort(422, 'الطلب ليس في خطوة Area Manager.');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($returnRequest, $validated, $arcgisService): void {
            $arcgisResult = $arcgisService->updateBuildingFieldStatus($returnRequest->building_objectid, 'Not_Completed');

            if (! ($arcgisResult['success'] ?? false)) {
                throw ValidationException::withMessages([
                    'notes' => 'فشل تحديث field_status على ArcGIS: '.($arcgisResult['message'] ?? 'خطأ غير معروف'),
                ]);
            }

            $returnRequest->forceFill([
                'status' => 'completed',
                'current_step' => 'completed',
                'area_manager_approved_at' => now(),
                'completed_at' => now(),
                'area_manager_notes' => $validated['notes'] ?? null,
            ])->save();

            $buildingSnapshot = $this->buildingSnapshot($returnRequest);

            BuildingSurveyArchiveObject::query()->create([
                'building_objectid' => $returnRequest->building_objectid,
                'building_globalid' => $returnRequest->building_globalid,
                'source_type' => 'return_request',
                'return_request_id' => $returnRequest->id,
                'archived_by' => auth()->id(),
                'archived_at' => now(),
                'notes' => $validated['notes'] ?? null,
                'building_snapshot' => $buildingSnapshot,
            ]);

            foreach ($this->housingUnitSnapshots($returnRequest) as $unitSnapshot) {
                BuildingSurveyArchiveObject::query()->create([
                    'building_objectid' => $returnRequest->building_objectid,
                    'building_globalid' => $returnRequest->building_globalid,
                    'housing_unit_objectid' => $unitSnapshot['objectid'] ?? null,
                    'housing_unit_globalid' => $unitSnapshot['globalid'] ?? null,
                    'source_type' => 'return_request',
                    'return_request_id' => $returnRequest->id,
                    'archived_by' => auth()->id(),
                    'archived_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                    'building_snapshot' => $buildingSnapshot,
                    'housing_unit_snapshot' => $unitSnapshot,
                ]);
            }

            $this->log($returnRequest, 'approved', 'area_manager', $validated['notes'] ?? null);
            $this->log($returnRequest, 'completed', 'system', 'تمت أرشفة objectid بعد الموافقة النهائية وتحديث field_status على ArcGIS إلى Not_Completed.');
        });

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'message' => 'تمت الموافقة النهائية وأرشفة المبنى بنجاح.',
                'redirect_url' => route('building-survey-return-requests.index'),
            ]);
        }

        return back()->with('success', 'تمت الموافقة النهائية وأرشفة المبنى بنجاح.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildingSnapshot(BuildingSurveyReturnRequest $returnRequest): ?array
    {
        $building = DB::table('buildings')
            ->where('objectid', $returnRequest->building_objectid)
            ->first();

        $snapshot = $building
            ? (array) $building
            : $returnRequest->building?->getAttributes();

        if ($snapshot === null) {
            return null;
        }

        return $this->applyLatestAuditEdits($snapshot, 'building_table');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function housingUnitSnapshots(BuildingSurveyReturnRequest $returnRequest): array
    {
        if ($returnRequest->building_globalid === null || $returnRequest->building_globalid === '') {
            return [];
        }

        return DB::table('housing_units')
            ->where('parentglobalid', $returnRequest->building_globalid)
            ->get()
            ->map(fn (object $unit): array => $this->applyLatestAuditEdits((array) $unit, 'housing_table'))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function applyLatestAuditEdits(array $snapshot, string $type): array
    {
        $globalId = (string) data_get($snapshot, 'globalid', '');

        if ($globalId === '') {
            return $snapshot;
        }

        EditAssessment::query()
            ->where('global_id', $globalId)
            ->where('type', $type)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->unique('field_name')
            ->each(function (EditAssessment $edit) use (&$snapshot): void {
                $snapshot[$edit->field_name] = $edit->field_value;
            });

        return $snapshot;
    }

    public function reject(Request $request, BuildingSurveyReturnRequest $returnRequest): RedirectResponse|JsonResponse
    {
        if ($returnRequest->current_step === 'team_leader') {
            $this->ensureTeamLeaderCanHandle($returnRequest);
        } elseif ($returnRequest->current_step === 'area_manager') {
            $this->ensureAreaManagerCanHandle($returnRequest);
        } else {
            abort(422, 'لا يمكن رفض الطلب في الخطوة الحالية.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($returnRequest, $validated): void {
            $returnRequest->forceFill([
                'status' => 'rejected',
                'current_step' => 'rejected',
                'reason' => $validated['reason'],
                'rejected_at' => now(),
            ])->save();

            $step = $this->currentUserIsTeamLeader() ? 'team_leader' : 'area_manager';
            $this->log($returnRequest, 'rejected', $step, $validated['reason']);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'message' => 'تم رفض الطلب.',
                'redirect_url' => route('building-survey-return-requests.index'),
            ]);
        }

        return back()->with('success', 'تم رفض الطلب.');
    }

    private function ensureTeamLeaderCanHandle(BuildingSurveyReturnRequest $returnRequest): void
    {
        abort_unless($this->currentUserIsTeamLeader() && (int) $returnRequest->team_leader_id === (int) auth()->id(), 403);
    }

    private function ensureAreaManagerCanHandle(BuildingSurveyReturnRequest $returnRequest): void
    {
        abort_unless(auth()->user()->hasRole('Area Manager') && (int) $returnRequest->area_manager_id === (int) auth()->id(), 403);
    }

    private function currentUserIsTeamLeader(): bool
    {
        return auth()->user()->hasAnyRole(['Team Leader', 'Team Leader']);
    }

    /**
     * @return Collection<int, AuditedBuilding>
     */
    private function buildingOptionsFor(User $user): Collection
    {
        $assignedTo = trim((string) $user->username_arcgis);

        if ($assignedTo === '') {
            return AuditedBuilding::query()
                ->whereKey([])
                ->get(['id', 'objectid', 'globalid', 'building_name']);
        }

        return AuditedBuilding::query()
            ->where('assignedto', $assignedTo)
            ->orderBy('objectid')
            ->get(['id', 'objectid', 'globalid', 'building_name']);
    }

    private function areaManagerForBuildingGovernorate(Building $building): ?User
    {
        $region = $this->regionFromGovernorate($building->governorate);

        if (! $region) {
            return null;
        }

        return User::role('Area Manager')
            ->where('region', $region)
            ->orderBy('id')
            ->first();
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

    private function log(BuildingSurveyReturnRequest $returnRequest, string $action, string $step, ?string $notes = null): void
    {
        BuildingSurveyReturnRequestLog::query()->create([
            'request_id' => $returnRequest->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'step' => $step,
            'notes' => $notes,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function tableRow(BuildingSurveyReturnRequest $returnRequest): array
    {
        $statusClass = [
            'pending' => 'badge-light-warning',
            'approved_by_team_leader' => 'badge-light-info',
            'approved_by_area_manager' => 'badge-light-info',
            'completed' => 'badge-light-success',
            'rejected' => 'badge-light-danger',
        ][$returnRequest->status] ?? 'badge-light';

        return [
            e($returnRequest->building?->building_name ?? '-'),
            e((string) $returnRequest->building_objectid),
            e($returnRequest->requester?->name ?? '-'),
            e($returnRequest->teamLeader?->name ?? '-'),
            e($returnRequest->areaManager?->name ?? '-'),
            '<span class="badge '.$statusClass.'">'.e($returnRequest->status).'</span>',
            '<span class="badge badge-light-primary">'.e($returnRequest->current_step).'</span>',
            e($returnRequest->requested_at?->format('Y-m-d h:i A') ?? '-'),
            '<a href="'.e(route('building-survey-return-requests.show', $returnRequest)).'" class="btn btn-sm btn-light-primary">عرض</a>',
        ];
    }
}
