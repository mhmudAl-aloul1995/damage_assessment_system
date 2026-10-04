<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\SaveRoleRequest;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\Access\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private PermissionCatalog $catalog)
    {
        $this->middleware('role_or_permission:Database Officer|roles.view')->only(['index', 'edit']);
        $this->middleware('role_or_permission:Database Officer|roles.create')->only('store');
        $this->middleware('role_or_permission:Database Officer|roles.update')->only('update');
        $this->middleware('role_or_permission:Database Officer|roles.delete')->only('destroy');
        $this->middleware('role_or_permission:Database Officer|users.view')->only('members');
    }

    public function index(Request $request): View
    {
        $roles = Role::query()->where('guard_name', 'web')->with('permissions')->withCount('users')->orderBy('name')->get();
        $permissionGroups = $this->catalog->groups();
        $roleData = $roles->map(fn (Role $role): array => $this->roleData($role));
        $can = collect(['view', 'create', 'update', 'delete'])->mapWithKeys(fn (string $action): array => [
            $action => $request->user()->hasRole('Database Officer') || $request->user()->can('roles.'.$action),
        ])->all();
        $canViewUsers = $request->user()->hasRole('Database Officer') || $request->user()->can('users.view');
        $grantablePermissions = $request->user()->hasRole('Database Officer') ? null : $request->user()->getAllPermissions()->pluck('name');

        return view('UserManagement.roles', compact('roles', 'permissionGroups', 'roleData', 'can', 'canViewUsers', 'grantablePermissions'));
    }

    public function store(SaveRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        abort_if($this->catalog->isSystemRole($data['name']) && ! $request->user()->hasRole('Database Officer'), 403);
        $this->authorizePermissions($request, $data['permissions']);
        $role = (new Role)->getConnection()->transaction(function () use ($data, $request): Role {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions']);
            $this->recordChange($request, $role, null);

            return $role;
        });

        return response()->json(['message' => __('ui.roles.saved'), 'role' => $this->roleData($role->load('permissions')->loadCount('users'))]);
    }

    public function edit(Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        return response()->json(['role' => $this->roleData($role->load('permissions')->loadCount('users'))]);
    }

    public function update(SaveRoleRequest $request, Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        $data = $request->validated();
        $role = $role->getConnection()->transaction(function () use ($request, $role, $data): Role {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->id)->load('permissions');
            abort_unless(hash_equals($this->catalog->revision($lockedRole), $data['revision']), 409, __('access.conflict'));
            abort_if($lockedRole->name === 'Database Officer', 403, __('access.admin_protected'));

            if (($this->catalog->isSystemRole($lockedRole->name) || $this->catalog->isSystemRole($data['name'])) && $data['name'] !== $lockedRole->name) {
                throw ValidationException::withMessages(['name' => __('access.system_role_name')]);
            }

            $this->authorizePermissions($request, array_merge($lockedRole->permissions->pluck('name')->all(), $data['permissions']));
            $before = ['name' => $lockedRole->name, 'permissions' => $lockedRole->permissions->pluck('name')->all()];
            $lockedRole->update(['name' => $data['name']]);
            $lockedRole->syncPermissions($data['permissions']);
            $this->recordChange($request, $lockedRole, $before);

            return $lockedRole;
        });

        return response()->json(['message' => __('ui.roles.updated'), 'role' => $this->roleData($role->load('permissions')->loadCount('users'))]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        $role->getConnection()->transaction(function () use ($request, $role): void {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->id)->load('permissions');
            abort_if($this->catalog->isSystemRole($lockedRole->name), 403, __('access.system_role_name'));
            abort_if($lockedRole->users()->exists(), 422, __('access.assigned_role'));
            $this->authorizePermissions($request, $lockedRole->permissions->pluck('name')->all());
            $this->recordChange($request, $lockedRole, ['name' => $lockedRole->name, 'permissions' => $lockedRole->permissions->pluck('name')->all()], true);
            $lockedRole->delete();
        });

        return response()->json(['message' => __('ui.roles.deleted')]);
    }

    public function members(Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        $members = $role->users()->orderBy('name')->paginate(25, ['users.id', 'users.name', 'users.email']);
        $members->through(fn (User $user): array => ['name' => $user->name, 'email' => $user->email, 'url' => route('users.access', $user->id)]);

        return response()->json($members);
    }

    private function roleData(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values(),
            'users_count' => $role->users_count ?? 0,
            'system' => $this->catalog->isSystemRole($role->name),
            'protected' => $role->name === 'Database Officer',
            'revision' => $this->catalog->revision($role),
            'edit_url' => route('roles.edit', $role),
            'update_url' => route('roles.update', $role),
            'delete_url' => route('roles.destroy', $role),
            'members_url' => route('roles.members', $role),
        ];
    }

    private function authorizePermissions(Request $request, array $permissions): void
    {
        if (! $request->user()->hasRole('Database Officer')) {
            abort_unless(collect($permissions)->every(fn (string $permission): bool => $request->user()->can($permission)), 403, __('access.grant_limit'));
        }
    }

    private function recordChange(Request $request, Role $role, ?array $before, bool $deleted = false): void
    {
        UserActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'user_email' => $request->user()->email,
            'action_type' => 'action',
            'method' => $request->method(),
            'url' => '/'.$request->path(),
            'route_name' => $request->route()->getName(),
            'description' => __('access.role_changed', ['name' => $role->name]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => 200,
            'metadata' => ['access_control' => true, 'role_id' => $role->id, 'before' => $before, 'after' => $deleted ? null : ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->all()]],
            'occurred_at' => now(),
        ]);
    }
}
