<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Access\PermissionCatalog;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserAccessController extends Controller
{
    public function __construct(private PermissionCatalog $catalog)
    {
        $this->middleware('role_or_permission:Database Officer|users.view');
    }

    public function show(User $user): View
    {
        $user->load(['roles.permissions', 'permissions']);
        $accessGroups = $user->getAllPermissions()->where('guard_name', 'web')->sortBy('name')
            ->map(fn (Permission $permission): array => [
                ...$this->catalog->describe($permission->name),
                'group' => $this->catalog->groupKey($permission->name),
                'direct' => $user->permissions->contains('id', $permission->id),
                'roles' => $user->roles->filter(fn (Role $role): bool => $role->permissions->contains('id', $permission->id))->pluck('name'),
            ])->groupBy('group');
        $groupLabels = $this->catalog->groups()->pluck('label', 'key');

        return view('UserManagement.access', compact('user', 'accessGroups', 'groupLabels'));
    }
}
