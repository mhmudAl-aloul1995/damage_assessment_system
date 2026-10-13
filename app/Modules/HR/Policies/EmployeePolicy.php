<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.attendance.view') || $user->can('hr.attendance.view-own') || $user->can('hr.attendance.view-team');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('hr.attendance.view')
            || ($user->can('hr.attendance.view-team') && $employee->supervisor_user_id === $user->id)
            || ($user->can('hr.attendance.view-own') && $employee->user_id === $user->id);
    }

    public function visibleTo(User $user): Builder
    {
        $query = Employee::query();
        if (! $user->can('hr.attendance.view')) {
            $query->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->can('hr.attendance.view-own') ? $user->id : 0);
                if ($user->can('hr.attendance.view-team')) {
                    $query->orWhere('supervisor_user_id', $user->id);
                }
            });
        }

        return $query;
    }

    public function requestCorrection(User $user, Employee $employee): bool
    {
        return $this->view($user, $employee) && ($user->can('hr.attendance.correct')
            || ($employee->user_id === $user->id && $user->can('hr.attendance.correct-own')));
    }
}
