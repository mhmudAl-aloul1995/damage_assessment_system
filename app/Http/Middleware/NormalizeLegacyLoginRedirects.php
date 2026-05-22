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
            $normalizedLocation = $this->normalizeDuplicatedBasePath($location);

            if ($normalizedLocation !== $location) {
                $response->headers->set('Location', $normalizedLocation);
            }

            return $response;
        }

        $response->headers->set('Location', app_route('login'));

        return $response;
    }

    private function normalizeDuplicatedBasePath(string $location): string
    {
        $path = parse_url($location, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return $location;
        }

        $normalizedPath = $path;

        foreach ($this->basePaths() as $basePath) {
            $duplicatedPrefix = '/'.$basePath.'/'.$basePath;

            while ($normalizedPath === $duplicatedPrefix || str_starts_with($normalizedPath, $duplicatedPrefix.'/')) {
                $normalizedPath = '/'.$basePath.substr($normalizedPath, strlen($duplicatedPrefix));
            }
        }

        if ($normalizedPath === $path) {
            return $location;
        }

        $query = parse_url($location, PHP_URL_QUERY);

        return $normalizedPath.(is_string($query) ? '?'.$query : '');
    }

    /**
     * @return array<int, string>
     */
    private function basePaths(): array
    {
        $configuredBasePath = trim(app_deduplicated_path((string) parse_url((string) config('app.url'), PHP_URL_PATH)), '/');

        return array_values(array_unique(array_filter([
            $configuredBasePath,
            'phc',
            'damage_assessment_system',
        ])));
    }
}
