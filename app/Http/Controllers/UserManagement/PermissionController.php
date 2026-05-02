<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Database Officer');
    }

    public function index()
    {
        return view('UserManagement.permissions');
    }

    public function data()
    {
        $permissions = Permission::with('roles')->select('permissions.*');

        return DataTables::of($permissions)
            ->addColumn('assigned_to', function ($permission) {
                if ($permission->roles->isEmpty()) {
                    return '<span class="text-muted">'.e(__('ui.permissions.not_assigned')).'</span>';
                }

                $colors = [
                    'badge-light-primary',
                    'badge-light-danger',
                    'badge-light-success',
                    'badge-light-info',
                    'badge-light-warning',
                ];

                $html = '';

                foreach ($permission->roles as $index => $role) {
                    $color = $colors[$index % count($colors)];
                    $html .= '<span class="badge '.$color.' fs-7 m-1">'.e($role->name).'</span>';
                }

                return $html;
            })
            ->editColumn('created_at', function ($permission) {
                return optional($permission->created_at)->format('d M Y, h:i a');
            })
            ->addColumn('action', function ($permission) {
                return '
                    <div class="text-end">
                        <button
                            class="btn btn-icon btn-active-light-primary w-30px h-30px me-3 btn-edit-permission"
                            type="button"
                            data-id="'.$permission->id.'"
                        >
                            <i class="ki-duotone ki-setting-3 fs-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        </button>

                        <button
                            class="btn btn-icon btn-active-light-primary w-30px h-30px btn-delete-permission"
                            type="button"
                            data-id="'.$permission->id.'"
                            data-name="'.e($permission->name).'"
                        >
                            <i class="ki-duotone ki-trash fs-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        </button>
                    </div>
                ';
            })
            ->rawColumns(['assigned_to', 'action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        Permission::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        return response()->json([
            'message' => __('ui.permissions.saved'),
        ]);
    }

    public function edit(Permission $permission)
    {
        return response()->json([
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
            ],
        ]);
    }

    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($permission->id)],
        ]);

        $permission->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => __('ui.permissions.updated'),
        ]);
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();

        return response()->json([
            'message' => __('ui.permissions.deleted'),
        ]);
    }
}
