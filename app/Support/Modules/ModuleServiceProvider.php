<?php

namespace App\Support\Modules;

use App\Http\Middleware\EnforceDamageAssessmentReadOnly;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class ModuleServiceProvider extends ServiceProvider
{
    protected string $module;

    /** @var array<int, class-string> */
    protected array $moduleCommands = [];

    public function register(): void
    {
        foreach (glob($this->modulePath('config/*.php')) ?: [] as $configFile) {
            $key = basename($configFile, '.php');

            if (! in_array($key, ['module', 'sidebar'], true)) {
                $this->mergeConfigFrom($configFile, $key);
            }
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom($this->modulePath('resources/views'), $this->module);
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));

        if (is_dir($this->modulePath('lang'))) {
            $this->loadTranslationsFrom($this->modulePath('lang'), $this->module);
        }

        if (! $this->app->routesAreCached()) {
            $this->loadModuleRoutes('web', $this->module);
            $this->loadModuleRoutes('api', 'api');
        }

        $this->commands($this->moduleCommands);
    }

    public function modulePath(string $path = ''): string
    {
        $root = dirname((new ReflectionClass($this))->getFileName(), 3);

        return $root.($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }

    protected function loadModuleRoutes(string $middleware, string $prefix): void
    {
        $path = $this->modulePath('routes/'.$middleware.'.php');

        if (is_file($path)) {
            $middlewareStack = $middleware === 'web' && $this->module === 'damage-assessment'
                ? [$middleware, EnforceDamageAssessmentReadOnly::class]
                : [$middleware];

            Route::middleware($middlewareStack)->prefix($prefix)->group($path);
        }
    }
}
