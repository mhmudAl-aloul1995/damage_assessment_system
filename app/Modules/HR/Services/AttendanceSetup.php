<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\ShiftAssignment;
use App\Modules\HR\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceSetup
{
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
            $overlap = $employee->assignments()
                ->where('starts_on', '<=', $data['ends_on'])
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $data['starts_on']))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_on' => 'توجد وردية متداخلة؛ اختر فترة أخرى.']);
            }
            $assignment = $employee->assignments()->create($data);
            $this->audit($actor, 'shift.assigned', $assignment);

            return $assignment;
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
