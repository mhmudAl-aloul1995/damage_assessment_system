<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use App\Services\ImageService;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;

class userController extends Controller
{
    protected ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
        $this->middleware('role:Database Officer');
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

        return View::make('UserManagement.roles', $data);
    }

    public function show(Request $request)
    {
        $users = User::with('roles')->where('id', '!=', Auth::id());

        return DataTables::of($users)
            ->addColumn('checkbox', function ($user) {
                return '<div class="form-check form-check-sm form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" value="'.$user->id.'" />
                </div>';
            })
            ->editColumn('name', fn ($user) => $user->name ?? '-')
            ->editColumn('name_en', fn ($user) => $user->name_en ?? '-')
            ->editColumn('email', fn ($user) => $user->email ?? '-')
            ->editColumn('id_no', fn ($user) => $user->id_no ?? '-')
            ->editColumn('contract_type', fn ($user) => strtoupper($user->contract_type ?? '-'))
            ->editColumn('phone', fn ($user) => $user->phone ?? '-')
            ->editColumn('created_at', fn ($user) => optional($user->created_at)->format('Y-m-d h:i A'))
            ->addColumn('action', function ($user) {
                return '
        <a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
            '.e(__('ui.users.actions')).'
            <i class="ki-duotone ki-down fs-5 ms-1"></i>
        </a>
        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
            <div class="menu-item px-3">
                <a href="javascript:;" onclick="showUser('.$user->id.')" class="menu-link px-3">'.e(__('ui.buttons.edit')).'</a>
            </div>
            <div class="menu-item px-3">
                <a href="javascript:;" class="menu-link px-3">'.e(__('ui.buttons.delete')).'</a>
            </div>
        </div>
    ';
            })
            ->rawColumns(['checkbox', 'action'])
            ->make(true);
    }

    public function edit($id)
    {
        $user = User::with('roles')->find($id);

        if (! $user) {
            return response()->json([
                'message' => __('ui.users.not_found'),
            ], 404);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'name_en' => $user->name_en,
                'email' => $user->email,
                'id_no' => $user->id_no,
                'contract_type' => $user->contract_type,
                'phone' => $user->phone,
                'address' => $user->address,
                'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
                'region' => $user->region,
            ],
            'roles' => $user->roles->pluck('name')->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'id_no' => 'nullable|string|max:255|unique:users,id_no',
            'contract_type' => 'nullable|in:phc,undp,mopwh,pef',
            'phone' => 'required|string|max:255',
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string|exists:roles,name',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $avatarPath = null;
        $user = null;

        $randomPassword = (string) random_int(100000, 999999);
        $hashedPassword = Hash::make($randomPassword);

        DB::transaction(function () use ($request, &$avatarPath, &$user, $hashedPassword) {
            $user = User::create([
                'name' => $request->name,
                'name_en' => $request->name_en,
                'email' => $request->email,
                'id_no' => $request->id_no,
                'contract_type' => $request->contract_type,
                'phone' => $request->phone,
                'address' => $request->address,
                'password' => $hashedPassword,
                'avatar' => null,
            ]);

            if ($request->hasFile('avatar')) {
                $avatarPath = $this->imageService->processAvatar(
                    $request->file('avatar'),
                    $user->id
                );

                $user->update([
                    'avatar' => $avatarPath,
                ]);
            }

            $user->syncRoles($request->roles);
        });

        Mail::to($user->email)->send(
            new WelcomeUserMail($user->email, $randomPassword)
        );

        return response()->json([
            'message' => __('ui.users.saved'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'id_no' => 'nullable|string|max:255|unique:users,id_no,'.$user->id,
            'contract_type' => 'nullable|in:phc,undp,mopwh,pef',
            'phone' => 'required|string|max:255',
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string|exists:roles,name',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $data = [
            'name' => $request->name,
            'name_en' => $request->name_en,
            'email' => $request->email,
            'id_no' => $request->id_no,
            'contract_type' => $request->contract_type,
            'phone' => $request->phone,
            'address' => $request->address,
        ];

        $newPassword = null;

        DB::transaction(function () use ($request, $user, &$data, &$newPassword) {
            if ($request->hasFile('avatar')) {
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }

                $data['avatar'] = $this->imageService->processAvatar(
                    $request->file('avatar'),
                    $user->id
                );
            }

            $user->update($data);
            $user->syncRoles($request->roles);

            if ($request->filled('send_password') && $request->send_password === 'yes') {
                $newPassword = (string) random_int(100000, 999999);

                $user->update([
                    'password' => Hash::make($newPassword),
                ]);
            }
        });

        if ($newPassword) {
            Mail::to($user->email)->send(
                new WelcomeUserMail($user->email, $newPassword)
            );
        }

        return response()->json([
            'message' => __('ui.users.saved'),
        ]);
    }

    private function processAvatar($file, $userId = null)
    {
        $realPath = $file->getRealPath();
        $mime = $file->getMimeType();

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $sourceImage = imagecreatefromjpeg($realPath);
                break;

            case 'image/png':
                $sourceImage = imagecreatefrompng($realPath);
                break;

            case 'image/webp':
                if (! function_exists('imagecreatefromwebp')) {
                    throw new \Exception('WEBP not supported');
                }

                $sourceImage = imagecreatefromwebp($realPath);
                break;

            default:
                throw new \Exception('Unsupported image type');
        }

        if (! $sourceImage) {
            throw new \Exception('Invalid image');
        }

        $srcWidth = imagesx($sourceImage);
        $srcHeight = imagesy($sourceImage);
        $targetSize = 300;
        $srcRatio = $srcWidth / $srcHeight;

        if ($srcRatio > 1) {
            $newHeight = $srcHeight;
            $newWidth = $srcHeight;
            $srcX = ($srcWidth - $newWidth) / 2;
            $srcY = 0;
        } else {
            $newWidth = $srcWidth;
            $newHeight = $srcWidth;
            $srcX = 0;
            $srcY = ($srcHeight - $newHeight) / 2;
        }

        $finalImage = imagecreatetruecolor($targetSize, $targetSize);

        imagecopyresampled(
            $finalImage,
            $sourceImage,
            0,
            0,
            $srcX,
            $srcY,
            $targetSize,
            $targetSize,
            $newWidth,
            $newHeight
        );

        $fileName = 'avatar_'.($userId ?? 'tmp').'_'.time().'_'.uniqid().'.jpg';
        $relativePath = 'avatars/'.$fileName;
        $fullPath = storage_path('app/public/'.$relativePath);

        if (! file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        imagejpeg($finalImage, $fullPath, 85);

        imagedestroy($sourceImage);
        imagedestroy($finalImage);

        return $relativePath;
    }

    public function destroy(Request $request, $id)
    {
        if (User::find($id)?->delete()) {
            return response()->json([
                'message' => __('ui.users.deleted'),
                'success' => true,
            ]);
        }

        return response(['message' => __('ui.users.delete_failed')], 500);
    }
}
