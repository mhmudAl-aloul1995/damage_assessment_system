<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_active, 403, __('auth.inactive'));
        abort_unless($request->bearerToken() && $request->user()->currentAccessToken() instanceof \Laravel\Sanctum\PersonalAccessToken, 401);
        abort_unless($request->user()->tokenCan('mobile:read'), 403);

        app()->setLocale(in_array($request->user()->preferred_locale, ['ar', 'en'], true) ? $request->user()->preferred_locale : 'ar');

        return $next($request);
    }
}
