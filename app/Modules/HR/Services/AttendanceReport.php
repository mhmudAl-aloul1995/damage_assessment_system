<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Policies\EmployeePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AttendanceReport
{
    public function __construct(private EmployeePolicy $policy, private AttendanceCalculator $calculator) {}

    /** @return array{0: string, 1: string} */
    public function dates(array $filters): array
    {
        if (! empty($filters['month'])) {
            $date = CarbonImmutable::createFromFormat('!Y-m', $filters['month'], config('hr.timezone'));

            return [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()];
        }
        $date = $filters['date'] ?? CarbonImmutable::now(config('hr.timezone'))->toDateString();

        return [$date, $date];
    }

    public function query(User $user, array $filters): Builder
    {
        [$start, $end] = $this->dates($filters);

        return $this->policy->visibleTo($user)
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->with([
                'assignments' => fn ($query) => $query->with('schedule')->where('starts_on', '<=', $end)
                    ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $start)),
                'events' => fn ($query) => $query->whereBetween('work_date', [$start, $end]),
                'legacyAttendances' => fn ($query) => $query->whereBetween('date', [$start, $end]),
            ])->orderBy('employee_number');
    }

    /** @param iterable<Employee> $employees */
    public function rows(iterable $employees, array $filters): Collection
    {
        [$start, $end] = $this->dates($filters);
        $rows = collect();
        foreach ($employees as $employee) {
            for ($date = CarbonImmutable::parse($start); $date->toDateString() <= $end; $date = $date->addDay()) {
                $rows->push($this->calculator->day($employee, $date->toDateString()));
            }
        }

        return $rows;
    }
}
