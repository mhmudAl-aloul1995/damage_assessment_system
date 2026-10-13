<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\CostCenter;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeAssignment;
use App\Modules\HR\Models\EmployeeAssignmentEnding;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Models\EmploymentHistory;
use App\Modules\HR\Models\JobTitle;
use App\Modules\HR\Models\OfficeLocation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EmployeeManagement
{
    public function __construct(private EmployeeOrganizationPlanner $planner, private EmployeeOrganizationScope $scope) {}

    public function create(User $actor, array $data): Employee
    {
        abort_unless($actor->is_active, 403);
        Gate::forUser($actor)->authorize('hr.employees.manage');
        Gate::forUser($actor)->authorize('hr.employees.view');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $data): Employee {
            $employee = Employee::query()->create($data);
            $this->history($actor, $employee, 'identity_created', $employee->starts_on->toDateString(), null);
            $this->audit($actor, 'employee.profile_created', $employee->id);

            return $employee;
        });
    }

    public function update(User $actor, Employee $employee, array $data): EmployeeProfile
    {
        return Employee::query()->getConnection()->transaction(function () use ($actor, $employee, $data): EmployeeProfile {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $this->scope->forget();
            Gate::forUser($actor)->authorize('manageProfile', $employee);
            $private = array_intersect(array_keys($data), ['birth_date', 'emergency_contact']);
            if ($private !== []) {
                Gate::forUser($actor)->authorize('hr.employees.sensitive');
            }
            $profile = $employee->profile()->firstOrNew();
            $before = ['display_name' => $employee->display_name, ...$profile->only(['name_en', 'work_email', 'work_phone', 'birth_date', 'emergency_contact'])];
            if (array_key_exists('display_name', $data)) {
                $employee->update(['display_name' => $data['display_name']]);
            }
            $profile->fill(array_intersect_key($data, array_flip(['name_en', 'work_email', 'work_phone', 'birth_date', 'emergency_contact'])))->save();
            $this->history($actor, $employee, 'profile_updated', now(config('hr.timezone'))->toDateString(), json_encode(['before' => array_intersect_key($before, $data), 'after' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $this->audit($actor, 'employee.profile_updated', $employee->id, ['changed_fields' => array_keys($data)]);

            return $profile;
        });
    }

    public function assign(User $actor, Employee $employee, array $data): EmployeeAssignment
    {
        Gate::forUser($actor)->authorize('hr.organization.manage');

        return Department::query()->getConnection()->transaction(function () use ($actor, $employee, $data): EmployeeAssignment {
            Department::query()->orderBy('id')->lockForUpdate()->firstOrFail();
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $this->scope->forget();
            Gate::forUser($actor)->authorize('manageProfile', $employee);
            abort_if($employee->user_id === $actor->id, 403, 'يلزم مسؤول HR آخر لتغيير تكليفك الإداري.');
            $data['primary'] = (bool) ($data['primary'] ?? true);
            $data['manager_employee_id'] = empty($data['manager_employee_id']) ? null : (int) $data['manager_employee_id'];
            $this->planner->validate($employee, $data);
            $department = Department::query()->where('active', true)->findOrFail($data['department_id']);
            $title = JobTitle::query()->where('active', true)->findOrFail($data['job_title_id']);
            $office = empty($data['office_location_id']) ? null : OfficeLocation::query()->where('active', true)->findOrFail($data['office_location_id']);
            $cost = empty($data['cost_center_id']) ? null : CostCenter::query()->where('active', true)->findOrFail($data['cost_center_id']);
            $manager = empty($data['manager_employee_id']) ? null : Employee::query()->findOrFail($data['manager_employee_id']);
            if ($manager && (! $manager->employedOn($data['starts_on']) || ($manager->ends_on !== null && (empty($data['ends_on']) || $data['ends_on'] > $manager->ends_on->toDateString())))) {
                throw ValidationException::withMessages(['manager_employee_id' => 'يجب أن تغطي فترة عمل المسؤول فترة التكليف.']);
            }
            $assignment = $employee->organizationAssignments()->create([...$data, 'created_by' => $actor->id,
                'snapshot' => ['department' => $department->name, 'job_title' => $title->name, 'office' => $office?->name, 'cost_center' => $cost?->name, 'manager' => $manager?->display_name]]);
            $this->history($actor, $employee, 'assignment_created', $data['starts_on'], null, ['assignment_id' => $assignment->id, ...$assignment->snapshot]);
            $this->audit($actor, 'employee.assignment_created', $assignment->id, [], $assignment->getTable());
            $this->scope->forget();

            return $assignment;
        });
    }

    public function endAssignment(User $actor, EmployeeAssignment $assignment, array $data): EmployeeAssignmentEnding
    {
        Gate::forUser($actor)->authorize('hr.organization.manage');

        return Department::query()->getConnection()->transaction(function () use ($actor, $assignment, $data): EmployeeAssignmentEnding {
            Department::query()->orderBy('id')->lockForUpdate()->firstOrFail();
            $employee = Employee::query()->lockForUpdate()->findOrFail($assignment->employee_id);
            $this->scope->forget();
            Gate::forUser($actor)->authorize('manageProfile', $employee);
            abort_if($employee->user_id === $actor->id, 403);
            $assignment = EmployeeAssignment::query()->with('latestEnding')->findOrFail($assignment->id);
            $end = $data['ends_on'];
            $currentEnd = $assignment->effectiveEndsOn();
            if ($end < now(config('hr.timezone'))->toDateString() || $end < $assignment->starts_on->toDateString() || ($currentEnd !== null && $end >= $currentEnd)) {
                throw ValidationException::withMessages(['ends_on' => 'اختر نهاية اليوم أو نهاية مستقبلية أقصر من التكليف الحالي.']);
            }
            $ending = $assignment->endings()->create([...$data, 'created_by' => $actor->id]);
            $this->history($actor, $employee, 'assignment_ended', $end, $data['reason'], ['assignment_id' => $assignment->id]);
            $this->audit($actor, 'employee.assignment_ended', $ending->id, [], $ending->getTable());
            $this->scope->forget();

            return $ending;
        });
    }

    public function note(User $actor, Employee $employee, array $data): EmploymentHistory
    {
        return Employee::query()->getConnection()->transaction(function () use ($actor, $employee, $data): EmploymentHistory {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $this->scope->forget();
            Gate::forUser($actor)->authorize('manageProfile', $employee);
            Gate::forUser($actor)->authorize('hr.employees.sensitive');
            $history = $this->history($actor, $employee, 'administrative_note', $data['occurred_on'], $data['note']);
            $this->audit($actor, 'employee.history_note_created', $history->id, [], $history->getTable());

            return $history;
        });
    }

    public function archive(User $actor, Employee $employee, bool $archive): void
    {
        Employee::query()->getConnection()->transaction(function () use ($actor, $employee, $archive): void {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $this->scope->forget();
            Gate::forUser($actor)->authorize('manageProfile', $employee);
            if ($archive && ($employee->ends_on === null || $employee->ends_on->toDateString() >= now(config('hr.timezone'))->toDateString())) {
                throw ValidationException::withMessages(['archive' => 'الأرشفة متاحة بعد انتهاء العمل؛ إنهاء العمل نفسه يمر بمسار إنهاء الخدمة.']);
            }
            $profile = $employee->profile()->firstOrNew();
            if (($profile->archived_at !== null) === $archive) {
                return;
            }
            $profile->fill(['archived_at' => $archive ? now('UTC')->format('Y-m-d H:i:s') : null])->save();
            $this->history($actor, $employee, $archive ? 'profile_archived' : 'profile_restored', now(config('hr.timezone'))->toDateString(), null);
            $this->audit($actor, $archive ? 'employee.profile_archived' : 'employee.profile_restored', $employee->id);
        });
    }

    private function history(User $actor, Employee $employee, string $type, string $date, ?string $note, array $metadata = []): EmploymentHistory
    {
        return $employee->employmentHistory()->create(['event_type' => $type, 'occurred_on' => $date, 'note' => $note, 'metadata' => $metadata, 'created_by' => $actor->id]);
    }

    private function audit(User $actor, string $action, int $id, array $metadata = [], string $table = 'hr_employees'): void
    {
        AuditLog::query()->create(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => $table, 'subject_id' => $id, 'metadata' => $metadata, 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
    }
}
