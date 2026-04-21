<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request, ImageService $imageService)
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($request, $user, &$data, $imageService) {
            if ($request->hasFile('avatar')) {
                if (! empty($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }

                $data['avatar'] = $imageService->processAvatar(
                    $request->file('avatar'),
                    $user->id
                );
            }

            $user->fill($data);
            $user->save();
        });

        return response()->json([
            'status' => 'success',
            'message' => __('ui.messages.profile_updated'),
            'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
