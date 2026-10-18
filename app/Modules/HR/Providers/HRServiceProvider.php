<?php

namespace App\Modules\HR\Providers;

use App\Models\User;
use App\Modules\HR\Models\AttendanceAdjustment;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Policies\AttendanceAdjustmentPolicy;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\EmployeeOrganizationScope;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class HRServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'hr';

    public function register(): void
    {
        parent::register();
        $this->app->scoped(EmployeeOrganizationScope::class);
    }

    public function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }

    public function boot(): void
    {
        parent::boot();
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(AttendanceAdjustment::class, AttendanceAdjustmentPolicy::class);
        Gate::before(static function (User $user, string $ability): ?bool {
            if (in_array($ability, [
                'hr.attendance.view',
                'hr.attendance.view-own',
                'hr.attendance.setup',
                'hr.attendance.export',
                'hr.attendance.correct',
                'hr.attendance.approve',
                'hr.attendance.exceptions.manage',
                'hr.attendance.reconcile',
                'hr.attendance.audit',
            ], true) && $user->hasRole('Database Officer')) {
                return true;
            }

            return null;
        });
    }
}
