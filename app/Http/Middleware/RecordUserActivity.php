<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordUserActivity
{
    /**
     * @var array<int, string>
     */
    private const TECHNICAL_PATHS = [
        'background-sync.js',
        'manifest.webmanifest',
        'offline.html',
        'pwa-install.js',
        'sw.js',
    ];

    /**
     * @var array<int, string>
     */
    private const STATIC_FILE_EXTENSIONS = [
        'css',
        'eot',
        'gif',
        'ico',
        'jpeg',
        'jpg',
        'js',
        'json',
        'map',
        'png',
        'svg',
        'ttf',
        'webmanifest',
        'woff',
        'woff2',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            $this->record($request, $response);
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! $request->user() || $response->getStatusCode() >= 500) {
            return false;
        }

        if ($request->is('user-activity-logs/data') || Str::endsWith($request->path(), 'user-activity-logs/data')) {
            return false;
        }

        if ($this->isTechnicalRequest($request)) {
            return false;
        }

        if ($request->isMethod('GET') && ($request->ajax() || $request->expectsJson())) {
            return false;
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && Str::endsWith($routeName, '.data')) {
            return false;
        }

        return true;
    }

    private function isTechnicalRequest(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if (in_array($path, self::TECHNICAL_PATHS, true)) {
            return true;
        }

        if (Str::is([
            'livewire/*',
            '_debugbar/*',
            'build/*',
            'storage/*',
            'api/*',
            'assets/*',
            'icon-*.png',
        ], $path)) {
            return true;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::STATIC_FILE_EXTENSIONS, true);
    }

    private function record(Request $request, Response $response): void
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        try {
            UserActivityLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'action_type' => $request->isMethod('GET') ? 'page_visit' : 'action',
                'method' => $request->method(),
                'url' => '/'.ltrim($request->path(), '/'),
                'route_name' => $routeName,
                'description' => $this->description($request, $routeName),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => $response->getStatusCode(),
                'metadata' => $this->metadata($request),
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function description(Request $request, ?string $routeName): string
    {
        if ($routeName !== null) {
            return Str::headline(str_replace(['.', '-'], ' ', $routeName));
        }

        return $request->isMethod('GET') ? 'Visited page' : 'Performed action';
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(Request $request): array
    {
        return [
            'query' => $request->query(),
            'input_keys' => array_values(array_diff(
                array_keys(Arr::except($request->input(), [
                    'password',
                    'password_confirmation',
                    'current_password',
                    '_token',
                ])),
                []
            )),
            'route_parameters' => collect($request->route()?->parameters() ?? [])
                ->map(fn (mixed $value): mixed => is_scalar($value) || $value === null ? $value : (string) $value)
                ->all(),
        ];
    }
}
