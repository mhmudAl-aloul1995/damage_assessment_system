<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('modules', []) as $module) {
            if (isset($module['provider'])) {
                $this->app->register($module['provider']);
            }
        }
    }
}
