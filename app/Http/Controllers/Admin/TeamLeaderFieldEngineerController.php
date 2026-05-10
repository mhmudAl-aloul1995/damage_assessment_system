<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeamLeaderFieldEngineer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class TeamLeaderFieldEngineerController extends Controller
{
    public function index()
    {
        $regions = User::query()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        return view('team_leader_field_engineers.index', compact('regions'));
    }

    public function data(Request $request)
    {
        $query = TeamLeaderFieldEngineer::query()
            ->with(['teamLeader', 'fieldEngineer', 'creator']);

        if ($request->filled('region')) {
            $query->whereHas('fieldEngineer', function ($q) use ($request) {
                $q->where('region', $request->region);
            });
        }

        if ($request->filled('team_leader_id')) {
            $query->where('team_leader_id', $request->team_leader_id);
        }

        if ($request->filled('field_engineer_id')) {
            $query->where('field_engineer_id', $request->field_engineer_id);
        }

        $recordsTotal = TeamLeaderFieldEngineer::count();
        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $items = $query
            ->latest('id')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $items->map(function ($item, $index) use ($start) {
            return [
                'index' => $start + $index + 1,
                'team_leader' => $item->teamLeader?->name ?? '-',
                'field_engineer' => $item->fieldEngineer?->name ?? '-',
                'field_engineer_region' => $item->fieldEngineer?->region ?? '-',
                'created_by' => $item->creator?->name ?? '-',
                'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
                'actions' => view('team_leader_field_engineers.partials.actions', compact('item'))->render(),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function teamLeadersSelect(Request $request)
    {
        $search = $request->input('q');

        $users = User::role('Team Leader')
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
        $search = $request->input('q');
        $region = $request->input('region');

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
            'field_engineer_id' => [
                'required',
                'exists:users,id',
                Rule::unique('team_leader_field_engineers')
                    ->where('team_leader_id', $request->team_leader_id),
            ],
        ], [
            'team_leader_id.required' => 'يرجى اختيار قائد الفريق.',
            'field_engineer_id.required' => 'يرجى اختيار المهندس الميداني.',
            'field_engineer_id.unique' => 'هذا المهندس مربوط مسبقاً مع نفس قائد الفريق.',
        ]);

        $teamLeader = User::findOrFail($request->team_leader_id);
        $fieldEngineer = User::findOrFail($request->field_engineer_id);

        if (! $teamLeader->hasRole('Team Leader')) {
            return response()->json([
                'message' => 'المستخدم المختار ليس قائد فريق.',
            ], 422);
        }

        if (! $fieldEngineer->hasRole('Field Engineer')) {
            return response()->json([
                'message' => 'المستخدم المختار ليس مهندس ميداني.',
            ], 422);
        }

        TeamLeaderFieldEngineer::create([
            'team_leader_id' => $teamLeader->id,
            'field_engineer_id' => $fieldEngineer->id,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'تم ربط المهندس الميداني بقائد الفريق بنجاح.',
        ]);
    }

    public function destroy(TeamLeaderFieldEngineer $teamLeaderFieldEngineer)
    {
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
