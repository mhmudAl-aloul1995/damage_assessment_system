<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\DepartmentScope;
use App\Modules\HR\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

class EmployeeOrganizationScope
{
    private array $departmentCache = [];

    private array $visibleCache = [];

    public function forget(): void
    {
        $this->departmentCache = [];
        $this->visibleCache = [];
    }

    public function supervisorId(Employee $employee): ?int
    {
        $date = now(config('hr.timezone'))->toDateString();
        $assignment = $employee->organizationAssignments()->with('manager')->where('primary', true)->effectiveOn($date)->latest('id')->first();
        if ($assignment) {
            return $assignment->manager?->user_id;
        }
        if ($employee->organizationAssignments()->where('primary', true)->where('starts_on', '<=', $date)->exists()) {
            return null;
        }

        return $employee->supervisor_user_id;
    }

    public function query(User $actor, string $permissionPrefix): Builder
    {
        $query = Employee::query();
        if ($actor->can($permissionPrefix.'.view')) {
            return $query;
        }
        $date = now(config('hr.timezone'))->toDateString();
        $departments = $actor->can($permissionPrefix.'.view-team') ? $this->departments($actor, $date) : [];

        return $query->where(function (Builder $query) use ($actor, $permissionPrefix, $date, $departments): void {
            $query->where('user_id', $actor->can($permissionPrefix.'.view-own') ? $actor->id : 0);
            if (! $actor->can($permissionPrefix.'.view-team')) {
                return;
            }
            $query->orWhere(function (Builder $query) use ($actor, $date, $departments): void {
                $query->where('starts_on', '<=', $date)->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $date))
                    ->where(function (Builder $query) use ($actor, $date, $departments): void {
                        $query->where(function (Builder $query) use ($actor, $date): void {
                            $query->where('supervisor_user_id', $actor->id)->whereDoesntHave('organizationAssignments', fn (Builder $query) => $query->where('primary', true)->where('starts_on', '<=', $date));
                        })->orWhereHas('organizationAssignments', function (Builder $query) use ($actor, $date, $departments): void {
                            $query->where('primary', true)->effectiveOn($date)->where(function (Builder $query) use ($actor, $departments): void {
                                $query->whereHas('manager', fn (Builder $query) => $query->where('user_id', $actor->id));
                                if ($departments !== []) {
                                    $query->orWhereIn('department_id', $departments);
                                }
                            });
                        });
                    });
            });
        });
    }

    public function view(User $actor, Employee $employee, string $prefix): bool
    {
        if ($actor->can($prefix.'.view') || ($actor->can($prefix.'.view-own') && $employee->user_id === $actor->id)) {
            return true;
        }
        if (! $actor->can($prefix.'.view-team')) {
            return false;
        }
        $key = $actor->id.'|'.$prefix.'|'.now(config('hr.timezone'))->toDateString();
        if (! array_key_exists($key, $this->visibleCache)) {
            $this->visibleCache[$key] = $this->query($actor, $prefix)->pluck('id')->flip()->all();
        }

        return array_key_exists($employee->id, $this->visibleCache[$key]);
    }

    /** @return array<int, int> */
    private function departments(User $actor, string $date): array
    {
        $key = $actor->id.'|'.$date;
        if (array_key_exists($key, $this->departmentCache)) {
            return $this->departmentCache[$key];
        }
        $scopes = DepartmentScope::query()->effectiveOn($date)->where('user_id', $actor->id)->get();
        $ids = $scopes->pluck('department_id')->map(fn ($id): int => (int) $id)->all();
        if ($scopes->contains('include_descendants', true)) {
            $departments = Department::query()->get(['id', 'parent_id']);
            $pending = $scopes->where('include_descendants', true)->pluck('department_id')->all();
            $visited = [];
            while ($pending !== []) {
                $parent = (int) array_shift($pending);
                if (isset($visited[$parent])) {
                    continue;
                }
                $visited[$parent] = true;
                foreach ($departments->where('parent_id', $parent) as $department) {
                    $ids[] = $department->id;
                    $pending[] = $department->id;
                }
            }
        }

        return $this->departmentCache[$key] = array_values(array_unique($ids));
    }
}
