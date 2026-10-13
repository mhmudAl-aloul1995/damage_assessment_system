<?php

namespace App\Modules\HR\Jobs;

use App\Models\User;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\AttendanceReconciliation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Gate;

class ReconcileAttendance implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $actorId, public string $from, public string $to, public ?int $employeeId = null)
    {
        $this->afterCommit();
    }

    public function handle(AttendanceReconciliation $reconciliation, EmployeePolicy $policy): void
    {
        $actor = User::query()->findOrFail($this->actorId);
        abort_unless($actor->is_active, 403);
        Gate::forUser($actor)->authorize('hr.attendance.reconcile');
        $employees = $policy->visibleTo($actor)->when($this->employeeId, fn ($query) => $query->whereKey($this->employeeId));
        $reconciliation->range($employees, $this->from, $this->to, $actor->id);
    }
}
