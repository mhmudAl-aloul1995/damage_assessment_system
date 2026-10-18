<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AssignmentRetirement;
use App\Modules\HR\Models\AttendanceProject;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\ShiftAssignment;
use App\Modules\HR\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AttendanceSetup
{
    public function __construct(private ShiftAssignmentPlanner $planner) {}

    public function employee(User $actor, array $data): Employee
    {
        abort_unless($actor->is_active, 403);
        Gate::forUser($actor)->authorize('hr.attendance.setup');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $data): Employee {
            $enableNative = (bool) ($data['enable_native_attendance'] ?? false);
            unset($data['enable_native_attendance'], $data['user_email']);
            if (! empty($data['user_id'])) {
                User::query()->lockForUpdate()->findOrFail($data['user_id']);
                if (Employee::query()->where('user_id', $data['user_id'])->exists()) {
                    throw ValidationException::withMessages(['user_id' => 'الحساب مرتبط بموظف بالفعل؛ استخدم الموظف الموجود لإسناد مشروع آخر.']);
                }
            }
            $data['employee_number'] = trim($data['employee_number'] ?? '') ?: 'PHC-'.Str::ulid();
            $employee = Employee::query()->create($data);
            $this->audit($actor, 'employee.mapped', $employee);
            if ($enableNative) {
                $this->enableNative($actor, $employee);
            }

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
        abort_unless($actor->is_active, 403);
        Gate::forUser($actor)->authorize('hr.attendance.setup');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $data): ShiftAssignment {
            $employee = Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);
            $enableNative = (bool) ($data['enable_native_attendance'] ?? false);
            unset($data['enable_native_attendance']);
            if (! $employee->employedOn($data['starts_on']) || ! $employee->employedOn($data['ends_on'])) {
                throw ValidationException::withMessages(['starts_on' => 'يجب أن تقع الوردية ضمن فترة عمل الموظف.']);
            }
            $schedule = WorkSchedule::query()->findOrFail($data['work_schedule_id']);
            if (! empty($data['project_id'])) {
                $project = AttendanceProject::query()->findOrFail($data['project_id']);
                if ($data['starts_on'] < $project->starts_on->toDateString()
                    || ($project->ends_on !== null && $data['ends_on'] > $project->ends_on->toDateString())) {
                    throw ValidationException::withMessages(['project_id' => 'يجب أن تقع فترة تكليف الموظف ضمن مدة المشروع.']);
                }
            }
            $this->planner->assertAvailable($employee, $schedule, $data['starts_on'], $data['ends_on']);
            $assignment = $employee->assignments()->create($data);
            $this->audit($actor, 'shift.assigned', $assignment);
            if ($enableNative) {
                $this->enableNative($actor, $employee);
            }

            return $assignment;
        });
    }

    public function project(User $actor, array $data): AttendanceProject
    {
        abort_unless($actor->is_active, 403);
        Gate::forUser($actor)->authorize('hr.attendance.setup');

        return AttendanceProject::query()->getConnection()->transaction(function () use ($actor, $data): AttendanceProject {
            $project = AttendanceProject::query()->create([...$data, 'created_by' => $actor->id]);
            $this->audit($actor, 'project.created', $project);

            return $project;
        });
    }

    private function enableNative(User $actor, Employee $employee): void
    {
        $user = $employee->user_id ? User::query()->lockForUpdate()->find($employee->user_id) : null;
        if (! $user || ! $user->is_active) {
            throw ValidationException::withMessages(['user_id' => 'يلزم حساب مستخدم نشط لتفعيل الحضور عبر التطبيق.']);
        }
        $changed = [];
        foreach (['hr.attendance.view-own', 'hr.attendance.capture', 'hr.devices.register'] as $name) {
            $permission = Permission::findOrCreate($name, 'web');
            if (! $user->hasPermissionTo($permission)) {
                $user->givePermissionTo($permission);
                $changed[] = $name;
            }
        }
        if ($changed !== []) {
            AuditLog::query()->create(['actor_id' => $actor->id, 'action' => 'attendance.native_access_enabled',
                'subject_type' => 'users', 'subject_id' => $user->id, 'metadata' => ['permissions' => $changed, 'employee_id' => $employee->id],
                'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
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
