<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\AttendanceAdjustment;

class AttendanceAdjustmentPolicy
{
    public function view(User $user, AttendanceAdjustment $adjustment): bool
    {
        return app(EmployeePolicy::class)->view($user, $adjustment->employee);
    }

    public function decide(User $user, AttendanceAdjustment $adjustment): bool
    {
        return $user->can('hr.attendance.approve') && $this->view($user, $adjustment)
            && $adjustment->requested_by !== $user->id && $adjustment->employee->user_id !== $user->id;
    }
}
