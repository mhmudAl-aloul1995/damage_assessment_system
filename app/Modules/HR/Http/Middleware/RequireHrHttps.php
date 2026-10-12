<?php

namespace App\Modules\HR\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireHrHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'testing']) && ! $request->isSecure()) {
            abort(403, 'HR access requires HTTPS.');
        }

        return $next($request);
    }
}
