<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.attendance.view') || $user->can('hr.attendance.view-own');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('hr.attendance.view')
            || ($user->can('hr.attendance.view-own') && $employee->user_id === $user->id);
    }

    public function visibleTo(User $user): Builder
    {
        $query = Employee::query();
        if (! $user->can('hr.attendance.view')) {
            $query->where('user_id', $user->can('hr.attendance.view-own') ? $user->id : 0);
        }

        return $query;
    }
}
