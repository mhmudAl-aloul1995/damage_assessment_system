<?php

namespace App\Modules\HR\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureHrToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()->currentAccessToken();
        abort_unless($request->user()->is_active && $token instanceof PersonalAccessToken && $token->abilities === ['hr:native'], 403);

        return $next($request);
    }
}
