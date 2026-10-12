<?php

namespace App\Modules\HR\Providers;

use App\Models\User;
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
        Gate::before(static function (User $user, string $ability): ?bool {
            if (in_array($ability, [
                'hr.attendance.view',
                'hr.attendance.view-own',
                'hr.attendance.setup',
                'hr.attendance.export',
            ], true) && $user->hasRole('Database Officer')) {
                return true;
            }

            return null;
        });
    }
}
