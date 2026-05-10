<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TeamLeaderFieldEngineersExport;
use App\Http\Controllers\Controller;
use App\Models\TeamLeaderFieldEngineer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class TeamLeaderFieldEngineerController extends Controller
{
    private function authUser()
    {
        return Auth::user();
    }

    private function normalizeRegion(?string $region): ?string
    {
        return $region ? trim($region) : null;
    }

    public function index()
    {
        $authUser = $this->authUser();

        $regions = User::query()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->when($authUser && $authUser->hasRole('Area Manager'), function ($q) use ($authUser) {
                $q->where('region', $this->normalizeRegion($authUser->region));
            })
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        return view('team_leader_field_engineers.index', compact('regions'));
    }

    public function datatable(Request $request)
    {
        $authUser = $this->authUser();

        $query = TeamLeaderFieldEngineer::query()
            ->with(['teamLeader', 'fieldEngineer', 'creator']);

        if ($authUser && $authUser->hasAnyRole(['Team Leader', 'Team leader'])) {
            $query->where('team_leader_id', $authUser->id);
        }

        if ($authUser && $authUser->hasRole('Area Manager')) {
            $query->whereHas('fieldEngineer', function ($q) use ($authUser) {
                $q->where('region', $this->normalizeRegion($authUser->region));
            });
        } elseif ($request->filled('region')) {
            $query->whereHas('fieldEngineer', function ($q) use ($request) {
                $q->where('region', $request->region);
            });
        }

        if ($request->filled('team_leader_id') && ! ($authUser && $authUser->hasAnyRole(['Team Leader', 'Team leader']))) {
            $query->whereIn('team_leader_id', (array) $request->team_leader_id);
        }

        if ($request->filled('field_engineer_id')) {
            $query->whereIn('field_engineer_id', (array) $request->field_engineer_id);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('team_leader', fn ($item) => e($item->teamLeader?->name ?? '-'))
            ->addColumn('field_engineer', fn ($item) => e($item->fieldEngineer?->name ?? '-'))
            ->addColumn('field_engineer_region', fn ($item) => e($item->fieldEngineer?->region ?? '-'))
            ->addColumn('created_by', fn ($item) => e($item->creator?->name ?? '-'))
            ->addColumn('created_at', fn ($item) => optional($item->created_at)->format('Y-m-d h:i A'))
            ->addColumn('actions', function ($item) {
                return '
                    <button type="button"
                        class="btn btn-sm btn-light-danger js-delete-link"
                        data-url="'.e(route('admin.team-leader-field-engineers.destroy', $item->id)).'">
                        حذف
                    </button>
                ';
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function teamLeadersSelect(Request $request)
    {
        $authUser = $this->authUser();
        $search = $request->input('q');

        if ($authUser && $authUser->hasAnyRole(['Team Leader', 'Team leader'])) {
            return response()->json([
                'results' => [[
                    'id' => $authUser->id,
                    'text' => trim(($authUser->name ?? '').' - '.($authUser->name_en ?? '')),
                ]],
            ]);
        }

        $users = User::role(['Team Leader', 'Team leader'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $users->map(fn ($user) => [
                'id' => $user->id,
                'text' => trim(($user->name ?? '').' - '.($user->name_en ?? '')),
            ]),
        ]);
    }

    public function fieldEngineersSelect(Request $request)
    {
        $authUser = $this->authUser();
        $search = $request->input('q');
        $region = $request->input('region');

        if ($authUser && $authUser->hasRole('Area Manager')) {
            $region = $this->normalizeRegion($authUser->region);
        }

        $users = User::role('Field Engineer')
            ->when($region, fn ($q) => $q->where('region', $region))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('id_no', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $users->map(fn ($user) => [
                'id' => $user->id,
                'text' => trim(($user->name ?? '').' - '.($user->name_en ?? '').' - '.($user->region ?? '')),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'team_leader_id' => ['required', 'exists:users,id'],
            'field_engineer_id' => ['required', 'array'],
            'field_engineer_id.*' => ['exists:users,id'],
        ], [
            'team_leader_id.required' => 'يرجى اختيار قائد الفريق.',
            'field_engineer_id.required' => 'يرجى اختيار المهندس الميداني.',
        ]);

        $authUser = $this->authUser();

        $teamLeader = User::findOrFail($request->team_leader_id);

        if ($authUser && $authUser->hasAnyRole(['Team Leader', 'Team leader'])) {
            $teamLeader = $authUser;
        }

        if (! $teamLeader->hasAnyRole(['Team Leader', 'Team leader'])) {
            return response()->json([
                'message' => 'المستخدم المختار ليس قائد فريق.',
            ], 422);
        }

        if ($authUser && $authUser->hasRole('Area Manager')) {
            $areaRegion = $this->normalizeRegion($authUser->region);

            foreach ($request->field_engineer_id as $fieldEngineerId) {
                $fieldEngineer = User::findOrFail($fieldEngineerId);

                if ($this->normalizeRegion($fieldEngineer->region) !== $areaRegion) {
                    return response()->json([
                        'message' => 'لا يمكنك ربط مهندس ميداني خارج منطقتك.',
                    ], 422);
                }
            }
        }

        $created = 0;

        foreach ($request->field_engineer_id as $fieldEngineerId) {
            $fieldEngineer = User::findOrFail($fieldEngineerId);

            if (! $fieldEngineer->hasRole('Field Engineer')) {
                continue;
            }

            $link = TeamLeaderFieldEngineer::firstOrCreate(
                [
                    'team_leader_id' => $teamLeader->id,
                    'field_engineer_id' => $fieldEngineer->id,
                ],
                [
                    'created_by' => Auth::id(),
                ]
            );

            if ($link->wasRecentlyCreated) {
                $created++;
            }
        }

        return response()->json([
            'message' => $created > 0
                ? 'تم ربط المهندسين الميدانيين بقائد الفريق بنجاح.'
                : 'جميع الروابط المحددة موجودة مسبقاً.',
        ]);
    }

    public function destroy(TeamLeaderFieldEngineer $teamLeaderFieldEngineer)
    {
        $authUser = $this->authUser();

        if ($authUser && $authUser->hasAnyRole(['Team Leader', 'Team leader'])) {
            if ((int) $teamLeaderFieldEngineer->team_leader_id !== (int) $authUser->id) {
                return response()->json([
                    'message' => 'لا يمكنك حذف ربط لا يخصك.',
                ], 403);
            }
        }

        if ($authUser && $authUser->hasRole('Area Manager')) {
            $teamLeaderFieldEngineer->loadMissing('fieldEngineer');

            if (
                $this->normalizeRegion($teamLeaderFieldEngineer->fieldEngineer?->region)
                !== $this->normalizeRegion($authUser->region)
            ) {
                return response()->json([
                    'message' => 'لا يمكنك حذف ربط خارج منطقتك.',
                ], 403);
            }
        }

        $teamLeaderFieldEngineer->delete();

        return response()->json([
            'message' => 'تم حذف الربط بنجاح.',
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(
            new TeamLeaderFieldEngineersExport($request->all()),
            'team_leader_field_engineers.xlsx'
        );
    }
}
