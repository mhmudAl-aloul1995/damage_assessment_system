<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('role_or_permission:Database Officer|roles.view')->only(['index', 'edit']);
        $this->middleware('role_or_permission:Database Officer|roles.create')->only('store');
        $this->middleware('role_or_permission:Database Officer|roles.update')->only('update');
        $this->middleware('role_or_permission:Database Officer|roles.delete')->only('destroy');
    }

    public function index()
    {
        $roles = Role::with('permissions')
            ->orderBy('id')
            ->get()
            ->map(function ($role) {
                $role->users_count = $role->users()->count();

                return $role;
            });

        $permissionGroups = $this->permissionGroups();

        return view('UserManagement.roles', compact('roles', 'permissionGroups'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($request->permissions ?? []);
        $role->load('permissions');
        $role->users_count = $role->users()->count();

        return response()->json([
            'message' => __('ui.roles.saved'),
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
                'users_count' => $role->users_count,
            ],
        ]);
    }

    public function edit(Role $role)
    {
        $role->load('permissions');

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
            ],
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update([
            'name' => $request->name,
        ]);

        $role->syncPermissions($request->permissions ?? []);
        $role->load('permissions');
        $role->users_count = $role->users()->count();

        return response()->json([
            'message' => __('ui.roles.updated'),
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
                'users_count' => $role->users_count,
            ],
        ]);
    }

    public function destroy(Role $role)
    {
        $role->delete();

        return response()->json([
            'message' => __('ui.roles.deleted'),
        ]);
    }

    private function permissionGroups(): Collection
    {
        $groups = [
            'user_management' => [
                'label' => __('ui.permission_groups.user_management'),
                'prefixes' => ['users.', 'roles.', 'permissions.'],
            ],
            'reports' => [
                'label' => __('ui.permission_groups.reports'),
                'prefixes' => ['reports.'],
            ],
            'audit' => [
                'label' => __('ui.permission_groups.audit'),
                'prefixes' => ['audit.'],
            ],
            'committee' => [
                'label' => __('ui.permission_groups.committee'),
                'prefixes' => ['committee-', 'view committee', 'create committee', 'edit committee', 'sign committee', 'manage committee', 'sync committee'],
            ],
            'attendance' => [
                'label' => __('ui.permission_groups.attendance'),
                'prefixes' => ['attendance.'],
            ],
            'exports' => [
                'label' => __('ui.permission_groups.exports'),
                'prefixes' => ['exports.'],
            ],
            'inf_audit' => [
                'label' => __('ui.permission_groups.inf_audit'),
                'prefixes' => ['inf-audit.'],
            ],
            'system' => [
                'label' => __('ui.permission_groups.system'),
                'prefixes' => ['system.', 'system-', 'login-logs.', 'arcgis.'],
            ],
            'team_leader_assignments' => [
                'label' => __('ui.permission_groups.team_leader_assignments'),
                'prefixes' => ['team-leader-field-engineers.'],
            ],
            'building_survey_return_requests' => [
                'label' => __('ui.permission_groups.building_survey_return_requests'),
                'prefixes' => ['building-survey-return-requests.'],
            ],
            'damage_assessment' => [
                'label' => __('ui.permission_groups.damage_assessment'),
                'prefixes' => ['damage-assessments.', 'buildings.', 'housing-units.'],
            ],
        ];

        return Permission::orderBy('name')
            ->get()
            ->groupBy(function (Permission $permission) use ($groups): string {
                foreach ($groups as $key => $group) {
                    foreach ($group['prefixes'] as $prefix) {
                        if (str_starts_with($permission->name, $prefix)) {
                            return $key;
                        }
                    }
                }

                return 'other';
            })
            ->map(function (Collection $permissions, string $key) use ($groups): array {
                return [
                    'key' => $key,
                    'label' => $groups[$key]['label'] ?? __('ui.permission_groups.other'),
                    'permissions' => $permissions,
                ];
            })
            ->sortBy(fn (array $group): string => $group['label']);
    }
}
