<?php

use Illuminate\Support\Collection;

if (! function_exists('getFilterLabel')) {

    function getFilterLabel(Collection $filters, string $listName, $value): string
    {
        return optional(
            $filters->get($listName, collect())->firstWhere('name', $value)
        )->label ?? '-';
    }

}

if (! function_exists('app_path_url')) {
    function app_path_url(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $configuredBasePath = trim(app_deduplicated_path((string) parse_url((string) config('app.url'), PHP_URL_PATH)), '/');
        $appPath = $configuredBasePath;
        $normalizedPath = app_deduplicated_path('/'.ltrim($path, '/'));

        if ($appPath === '') {
            return $normalizedPath;
        }

        $prefix = '/'.$appPath;

        while ($normalizedPath === $prefix.$prefix || str_starts_with($normalizedPath, $prefix.$prefix.'/')) {
            $normalizedPath = $prefix.substr($normalizedPath, strlen($prefix.$prefix));
        }

        if ($normalizedPath === $prefix || str_starts_with($normalizedPath, $prefix.'/')) {
            return $normalizedPath;
        }

        return $prefix.$normalizedPath;
    }
}

if (! function_exists('app_deduplicated_path')) {
    function app_deduplicated_path(string $path): string
    {
        $segments = array_values(array_filter(explode('/', str_replace('\\', '/', $path)), 'strlen'));
        $deduplicated = [];

        foreach ($segments as $segment) {
            if (end($deduplicated) === $segment) {
                continue;
            }

            $deduplicated[] = $segment;
        }

        return '/'.implode('/', $deduplicated);
    }
}

if (! function_exists('app_route')) {
    /**
     * @param  array<string, mixed>|mixed  $parameters
     */
    function app_route(string $name, mixed $parameters = [], bool $absolute = false): string
    {
        $route = route($name, $parameters, $absolute);

        return $absolute ? $route : app_path_url($route);
    }
}
