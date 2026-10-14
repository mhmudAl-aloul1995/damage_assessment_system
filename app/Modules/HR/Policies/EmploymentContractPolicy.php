<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmploymentContract;
use App\Modules\HR\Services\EmployeeOrganizationScope;

class EmploymentContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ($user->can('hr.contracts.view') || $user->can('hr.contracts.view-own') || $user->can('hr.contracts.view-team'));
    }

    public function view(User $user, EmploymentContract $contract): bool
    {
        return $user->is_active && app(EmployeeOrganizationScope::class)->view($user, $contract->employee, 'hr.contracts');
    }

    public function manage(User $user, EmploymentContract $contract): bool
    {
        return $this->view($user, $contract) && $user->can('hr.contracts.manage');
    }

    public function createFor(User $user, Employee $employee): bool
    {
        return $user->is_active && $user->can('hr.contracts.manage') && app(EmployeeOrganizationScope::class)->view($user, $employee, 'hr.contracts');
    }

    public function sensitive(User $user, EmploymentContract $contract): bool
    {
        return $this->view($user, $contract) && ($contract->employee->user_id === $user->id || $user->can('hr.contracts.sensitive'));
    }

    public function approve(User $user, EmploymentContract $contract): bool
    {
        return $this->view($user, $contract) && $user->can('hr.contracts.approve') && $user->can('hr.contracts.sensitive') && $contract->employee->user_id !== $user->id;
    }
}
