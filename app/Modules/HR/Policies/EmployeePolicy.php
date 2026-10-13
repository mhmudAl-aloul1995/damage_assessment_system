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

    public function viewAnyProfiles(User $user): bool
    {
        return $user->is_active && ($user->can('hr.employees.view') || $user->can('hr.employees.view-own') || $user->can('hr.employees.view-team'));
    }

    public function viewProfile(User $user, Employee $employee): bool
    {
        return $user->is_active && app(EmployeeOrganizationScope::class)->view($user, $employee, 'hr.employees');
    }

    public function profilesVisibleTo(User $user): Builder
    {
        return app(EmployeeOrganizationScope::class)->query($user, 'hr.employees');
    }

    public function manageProfile(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.manage') && $this->viewProfile($user, $employee);
    }

    public function sensitiveProfile(User $user, Employee $employee): bool
    {
        return $this->viewProfile($user, $employee) && ($employee->user_id === $user->id || $user->can('hr.employees.sensitive'));
    }

    public function viewDocuments(User $user, Employee $employee): bool
    {
        return $this->viewProfile($user, $employee) && $user->can('hr.documents.view')
            && ($employee->user_id === $user->id || $user->can('hr.documents.sensitive'));
    }

    public function uploadDocument(User $user, Employee $employee): bool
    {
        return $this->viewProfile($user, $employee) && $user->can('hr.documents.upload');
    }

    public function requestCorrection(User $user, Employee $employee): bool
    {
        return $this->view($user, $employee) && ($user->can('hr.attendance.correct')
            || ($employee->user_id === $user->id && $user->can('hr.attendance.correct-own')));
    }
}
