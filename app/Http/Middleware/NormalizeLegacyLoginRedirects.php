<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeLegacyLoginRedirects
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->isRedirection()) {
            return $response;
        }

        $location = $response->headers->get('Location');

        if (! is_string($location) || $location === '') {
            return $response;
        }

        $path = parse_url($location, PHP_URL_PATH);

        if ($path !== '/login.php' && $path !== 'login.php') {
            return $response;
        }

        $response->headers->set('Location', app_route('login'));

        return $response;
    }
}
