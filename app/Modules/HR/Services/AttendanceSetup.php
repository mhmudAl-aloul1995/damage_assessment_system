<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AssignmentRetirement;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\ShiftAssignment;
use App\Modules\HR\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceSetup
{
    public function __construct(private ShiftAssignmentPlanner $planner) {}

    public function employee(User $actor, array $data): Employee
    {
        Gate::forUser($actor)->authorize('hr.attendance.setup');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $data): Employee {
            $employee = Employee::query()->create($data);
            $this->audit($actor, 'employee.mapped', $employee);

            return $employee;
        });
    }

    public function schedule(User $actor, array $data): WorkSchedule
    {
        Gate::forUser($actor)->authorize('hr.attendance.setup');

        return WorkSchedule::query()->getConnection()->transaction(function () use ($actor, $data): WorkSchedule {
            $schedule = WorkSchedule::query()->create($data);
            $this->audit($actor, 'schedule.created', $schedule);

            return $schedule;
        });
    }

    public function assign(User $actor, array $data): ShiftAssignment
    {
        Gate::forUser($actor)->authorize('hr.attendance.setup');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $data): ShiftAssignment {
            $employee = Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);
            if (! $employee->employedOn($data['starts_on']) || ! $employee->employedOn($data['ends_on'])) {
                throw ValidationException::withMessages(['starts_on' => 'يجب أن تقع الوردية ضمن فترة عمل الموظف.']);
            }
            $schedule = WorkSchedule::query()->findOrFail($data['work_schedule_id']);
            $this->planner->assertAvailable($employee, $schedule, $data['starts_on'], $data['ends_on']);
            $assignment = $employee->assignments()->create($data);
            $this->audit($actor, 'shift.assigned', $assignment);

            return $assignment;
        });
    }

    public function retire(User $actor, ShiftAssignment $assignment, array $data): AssignmentRetirement
    {
        Gate::forUser($actor)->authorize('hr.attendance.setup');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $assignment, $data): AssignmentRetirement {
            Employee::query()->lockForUpdate()->findOrFail($assignment->employee_id);
            $assignment = ShiftAssignment::query()->with('retirements')->findOrFail($assignment->id);
            $end = $data['ends_on'];
            $previous = $assignment->retirements->sortByDesc('id')->first();
            if ($previous && $previous->ends_on->toDateString() === $end && $previous->reason === $data['reason'] && (int) $previous->created_by === $actor->id) {
                return $previous;
            }
            if ($end < CarbonImmutable::now(config('hr.timezone'))->toDateString() || $end < $assignment->starts_on->toDateString()
                || ($assignment->effectiveEndsOn() !== null && $end >= $assignment->effectiveEndsOn()->toDateString())
                || $assignment->employee->events()->where('shift_assignment_id', $assignment->id)->where('work_date', '>', $end)->exists()) {
                throw ValidationException::withMessages(['ends_on' => 'اختر نهاية اليوم أو تاريخاً مستقبلياً أقصر من التكليف الحالي، دون التأثير على حضور مسجل.']);
            }
            $retirement = $assignment->retirements()->create([...$data, 'created_by' => $actor->id]);
            $this->audit($actor, 'shift.retired', $retirement);

            return $retirement;
        });
    }

    private function audit(User $actor, string $action, Model $subject): void
    {
        AuditLog::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject->getTable(),
            'subject_id' => $subject->getKey(),
            'created_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);
    }
}
