<?php

namespace App\Modules\HR\Providers;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class HRServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'hr';

    public function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }

    public function boot(): void
    {
        parent::boot();
        Gate::policy(Employee::class, EmployeePolicy::class);
    }
}
