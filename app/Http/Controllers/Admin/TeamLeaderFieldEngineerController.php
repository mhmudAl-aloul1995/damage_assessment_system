<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeamLeaderFieldEngineer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class TeamLeaderFieldEngineerController extends Controller
{
    public function index()
    {
        return view('admin.team_leader_field_engineers.index', [
            'teamLeaders' => User::role(['Team Leader', 'Team leader'])->orderBy('name')->get(['id', 'name']),
            'fieldEngineers' => User::role('Field Engineer')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'team_leader_id' => ['required', 'exists:users,id'],
            'field_engineer_id' => ['required', 'exists:users,id'],
        ]);

        $teamLeader = User::query()->findOrFail($validated['team_leader_id']);
        $fieldEngineer = User::query()->findOrFail($validated['field_engineer_id']);

        if (! $teamLeader->hasAnyRole(['Team Leader', 'Team leader'])) {
            throw ValidationException::withMessages([
                'team_leader_id' => 'المستخدم المحدد ليس Team Leader.',
            ]);
        }

        if (! $fieldEngineer->hasRole('Field Engineer')) {
            throw ValidationException::withMessages([
                'field_engineer_id' => 'المستخدم المحدد ليس Field Engineer.',
            ]);
        }

        $exists = TeamLeaderFieldEngineer::query()
            ->where('team_leader_id', $teamLeader->id)
            ->where('field_engineer_id', $fieldEngineer->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'field_engineer_id' => 'هذا الربط موجود مسبقاً.',
            ]);
        }

        $link = TeamLeaderFieldEngineer::query()->create([
            'team_leader_id' => $teamLeader->id,
            'field_engineer_id' => $fieldEngineer->id,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم ربط Team Leader مع Field Engineer بنجاح.',
            'data' => $link,
        ]);
    }

    public function destroy(TeamLeaderFieldEngineer $teamLeaderFieldEngineer)
    {
        $teamLeaderFieldEngineer->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الربط بنجاح.',
        ]);
    }

    public function datatable()
    {
        $query = TeamLeaderFieldEngineer::query()
            ->with(['teamLeader', 'fieldEngineer', 'creator'])
            ->latest();

        return DataTables::eloquent($query)
            ->addColumn('team_leader', fn (TeamLeaderFieldEngineer $link): string => e($link->teamLeader?->name ?? '-'))
            ->addColumn('field_engineer', fn (TeamLeaderFieldEngineer $link): string => e($link->fieldEngineer?->name ?? '-'))
            ->addColumn('created_by', fn (TeamLeaderFieldEngineer $link): string => e($link->creator?->name ?? '-'))
            ->addColumn('created_at_formatted', fn (TeamLeaderFieldEngineer $link): string => $link->created_at?->format('Y-m-d h:i A') ?? '-')
            ->addColumn('actions', function (TeamLeaderFieldEngineer $link): string {
                return '<button type="button" class="btn btn-sm btn-light-danger js-delete-link" data-url="'.e(route('team-leader-field-engineers.destroy', $link)).'">حذف</button>';
            })
            ->rawColumns(['actions'])
            ->toJson();
    }
}
