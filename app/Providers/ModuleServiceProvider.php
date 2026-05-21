<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        foreach ($this->modulePaths() as $modulePath) {
            $this->loadModuleRoutes($modulePath);
            $this->loadModuleViews($modulePath);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function modulePaths(): array
    {
        $paths = glob(app_path('Modules/*'), GLOB_ONLYDIR);

        return $paths === false ? [] : $paths;
    }

    protected function loadModuleRoutes(string $modulePath): void
    {
        $routeFile = $modulePath.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'web.php';

        if (is_file($routeFile)) {
            Route::prefix(Str::kebab(basename($modulePath)))
                ->middleware('web')
                ->group($routeFile);
        }
    }

    protected function loadModuleViews(string $modulePath): void
    {
        $viewPath = $modulePath.DIRECTORY_SEPARATOR.'views';

        if (is_dir($viewPath)) {
            $this->loadViewsFrom($viewPath, Str::kebab(basename($modulePath)));
        }
    }
}
