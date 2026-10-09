<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileLoginRequest;
use App\Http\Resources\MobileModuleResource;
use App\Http\Resources\MobileUserResource;
use App\Models\User;
use App\Support\Navigation\Sidebar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAccessController extends Controller
{
    public function login(MobileLoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $expiresAt = now()->addDay();
        $token = $user->createToken($request->validated('device_name'), ['mobile:read'], $expiresAt);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => new MobileUserResource($user),
        ]);
    }

    public function me(Request $request): MobileUserResource
    {
        return new MobileUserResource($request->user());
    }

    public function modules(Request $request): AnonymousResourceCollection
    {
        return MobileModuleResource::collection(Sidebar::forUser($request->user()));
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
