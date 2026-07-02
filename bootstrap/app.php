<?php

use App\Http\Middleware\NormalizeDuplicatedBasePath;
use App\Http\Middleware\NormalizeLegacyLoginRedirects;
use App\Http\Middleware\RecordUserActivity;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(fn () => app_route('dashboard'));

        $middleware->append([
            NormalizeDuplicatedBasePath::class,
            NormalizeLegacyLoginRedirects::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            RecordUserActivity::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function ($response) {
            if ($response->getStatusCode() === 419) {
                return redirect()->to(app_route('login'))->with('error', __('ui.messages.session_expired'));
            }

            return $response;
        });
    })
    ->create();
