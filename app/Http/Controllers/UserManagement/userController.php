<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Project;
use Yajra\Datatables\Datatables;
use Rap2hpoutre\FastExcel\FastExcel;
use Yajra\Datatables\Enginges\EloquentEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Hash;
use Spatie\Permission\Models\Role;
use App\Models\Builing;
use App\Models\HousingUnit;
use App\Mail\WelcomeUserMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class userController extends Controller
{


    function __construct()
    {
        $this->middleware('role:Administrator');
        /*  $this->middleware('permission:user-create', ['only' => ['create', 'store']]);
          $this->middleware('permission:user-edit', ['only' => ['edit', 'update']]);
          $this->middleware('permission:user-delete', ['only' => ['destroy']]);*/
    }


    public function index()
    {



        $data['user'] = User::all();
        $data['roles'] = Role::all();
        return View::make('UserManagement.users', $data);
    }

    public function showRoles()
    {



        $data['user'] = User::all();
        /*  $data['roles'] = Role::all(); */
        return View::make('UserManagement.roles', $data);
    }

    public function show(Request $request)
    {
        $users = User::with('roles')->where('id', '!=', Auth::id());

        return DataTables::of($users)
            ->addColumn('checkbox', function ($user) {
                return '
                <div class="form-check form-check-sm form-check-custom form-check-solid me-3">
                    <input class="form-check-input row-checkbox" type="checkbox" value="' . $user->id . '" />
                </div>
            ';
            })
            ->addColumn('action', function ($user) {
                return '
        <a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
            إجراءات
            <i class="ki-duotone ki-down fs-5 ms-1"></i>
        </a>
        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
            <div class="menu-item px-3">
                <a href="javascript:;" onclick="showUser(' . $user->id . ')" class="menu-link px-3">تعديل</a>
            </div>
            <div class="menu-item px-3">
                <a href="javascript:;" class="menu-link px-3">حذف</a>
            </div>
        </div>
    ';
            })
            ->editColumn('created_at', function ($user) {
                return optional($user->created_at)->format('Y-m-d h:i A');
            })
            ->rawColumns(['checkbox', 'action'])
            ->make(true);
    }
    public function edit(Request $request, $id)
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ],
            'role' => $user->roles->pluck('name')->first()
        ]);
    }

    public function store(Request $request)
    {

        // 1. Validation including the avatar image
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'role' => 'required',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048' // max 2MB
        ]);

        // 2. Handle file upload
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            // Stores in storage/app/public/avatars
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }
        $randomPassword = Str::password(6, false, true, false, false);
        $hashedPassword = Hash::make($randomPassword);
        DB::transaction(function () use ($request,$hashedPassword, $avatarPath, &$user) {

            $user = User::create([
                'phone' => $request->phone,
                'address' => $request->address,
                'name' => $request->name,
                'email' => $request->email,
                'password' => $hashedPassword,
                'avatar' => $avatarPath,
            ]);

            $role = Role::findByName($request->role);
            $user->assignRole($role);
        });

        Mail::to($user->email)->send(new WelcomeUserMail($user->email, $randomPassword));

        return response()->json(['message' => 'تم إضافة المستخدم وتعيين دوره بنجاح']);
    }



    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'role' => 'required|string|exists:roles,name',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('users', 'public');
            $user->avatar = $avatarPath;
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->address = $request->address;
        $user->save();

        $user->syncRoles([$request->role]);

        return response()->json([
            'message' => 'تم تعديل المستخدم بنجاح'
        ]);
    }

    public function destroy(Request $request, $id)
    {

        if (user::find($id)->delete()) {
            return response()->json([
                'message' => 'تمت العملية بنجاح',
                'success' => true
            ]);
        }

        return response(['message' => 'فشلت العملية'], 500);
    }
}
