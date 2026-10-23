<?php

namespace App\Modules\HeksShelter\Providers;

use App\Models\User;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class HeksShelterServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'heks-shelter';

    public function register(): void
    {
        parent::register();
        $this->app->scoped(\App\Modules\HeksShelter\Services\SurveyDesigner::class);
    }

    public function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }

    public function boot(): void
    {
        parent::boot();
        Gate::before(static fn (User $user, string $ability): ?bool => str_starts_with($ability, 'heks3.') && $user->hasRole('Database Officer') ? true : null);
    }
}
