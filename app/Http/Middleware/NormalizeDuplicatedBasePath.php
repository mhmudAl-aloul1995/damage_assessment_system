<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class NormalizeDuplicatedBasePath
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->basePaths() as $basePath) {
            $requestPath = '/'.trim($request->path(), '/');
            $duplicatedPrefix = '/'.$basePath.'/'.$basePath;

            if ($requestPath !== $duplicatedPrefix && ! str_starts_with($requestPath, $duplicatedPrefix.'/')) {
                continue;
            }

            $normalizedPath = '/'.$basePath.substr($requestPath, strlen($duplicatedPrefix));
            $queryString = $request->getQueryString();

            return new RedirectResponse($normalizedPath.($queryString !== null ? '?'.$queryString : ''));
        }

        return $next($request);
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
