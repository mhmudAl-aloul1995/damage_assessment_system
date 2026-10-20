<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Access\AccessPreview;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class ApplyAccessPreview
{
    public function __construct(private AccessPreview $preview) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $state = $request->session()->get(AccessPreview::SESSION_KEY);

        if (! is_array($state)) {
            return $next($request);
        }

        $guard = Auth::guard('web');
        $actor = $guard->user() ? User::query()->find($guard->id()) : null;

        if (! $actor || $actor->id !== $state['actor_id'] || ! $actor->is_active || ! $actor->hasRole('Database Officer')) {
            $this->preview->stop($request, $actor, 'revoked');

            return redirect()->route('dashboard');
        }

        $guard->setUser($actor);

        if ($request->routeIs('access-preview.destroy', 'logout')) {
            if ($request->routeIs('logout')) {
                $this->preview->stop($request, $actor, 'logout');
            }

            return $next($request);
        }

        $target = User::query()->with(['roles.permissions', 'permissions'])->find($state['user_id']);
        $role = $state['mode'] === 'role'
            ? Role::query()->with('permissions')->where('guard_name', 'web')->find($state['role_id'])
            : null;

        if (! $target?->is_active || ($state['mode'] === 'role' && ! $role)) {
            $this->preview->stop($request, $actor, 'unavailable');

            return redirect()->route('access-preview.create')->with('error', __('access_preview.unavailable'));
        }

        if ($role) {
            $target->setRelation('roles', $role->newCollection([$role]));
            $target->setRelation('permissions', $target->permissions->take(0));
        }

        $request->attributes->set(AccessPreview::SESSION_KEY, $this->preview->context([
            ...$state,
            'user_name' => $target->name,
            'role_name' => $role?->name,
        ]));
        $guard->setUser($target);

        try {
            return $next($request);
        } finally {
            if ($guard->check()) {
                $guard->setUser($actor);
            }
        }
    }
}
