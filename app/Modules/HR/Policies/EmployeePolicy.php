<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\EmployeeOrganizationScope;
use Illuminate\Database\Eloquent\Builder;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.attendance.view') || $user->can('hr.attendance.view-own') || $user->can('hr.attendance.view-team');
    }

    public function view(User $user, Employee $employee): bool
    {
        return app(EmployeeOrganizationScope::class)->view($user, $employee, 'hr.attendance');
    }

    public function visibleTo(User $user): Builder
    {
        return app(EmployeeOrganizationScope::class)->query($user, 'hr.attendance');
    }

    public function requestCorrection(User $user, Employee $employee): bool
    {
        return $this->view($user, $employee) && ($user->can('hr.attendance.correct')
            || ($employee->user_id === $user->id && $user->can('hr.attendance.correct-own')));
    }
}
