<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Holiday;
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

        return $this->relations($this->policy->visibleTo($user)
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when(isset($filters['search']) && $filters['search'] !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('display_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('employee_number', 'like', '%'.$filters['search'].'%'))), $start, $end)->orderBy('employee_number');
    }

    public function relations(Builder $query, string $start, string $end): Builder
    {
        return $query->with([
            'assignments' => fn ($query) => $query->with(['schedule', 'retirements', 'project'])->where('starts_on', '<=', $end)->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $start)),
            'events' => fn ($query) => $query->whereBetween('work_date', [$start, $end]),
            'legacyAttendances' => fn ($query) => $query->whereBetween('date', [$start, $end]),
            'adjustments' => fn ($query) => $query->with('decision')->whereBetween('work_date', [$start, $end]),
            'scheduleExceptions' => fn ($query) => $query->with('schedule')->whereBetween('work_date', [$start, $end]),
        ]);
    }

    /** @param iterable<Employee> $employees */
    public function rows(iterable $employees, array $filters): Collection
    {
        [$start, $end] = $this->dates($filters);
        $holidays = Holiday::query()->with('cancellation')->where('starts_on', '<=', $end)->where('ends_on', '>=', $start)->get();
        $rows = collect();
        foreach ($employees as $employee) {
            $employee->setRelation('attendanceHolidays', $holidays);
            for ($date = CarbonImmutable::parse($start); $date->toDateString() <= $end; $date = $date->addDay()) {
                $rows->push($this->calculator->day($employee, $date->toDateString()));
            }
        }

        return $rows;
    }

    public function totals(User $user, array $filters): Collection
    {
        $totals = collect();
        $this->query($user, $filters)->reorder()->chunkById(100, function ($employees) use ($filters, $totals): void {
            foreach ($this->rows($employees, $filters) as $row) {
                $totals->put($row['status'], ($totals->get($row['status']) ?? 0) + 1);
            }
        });

        return $totals;
    }

    public function metrics(User $user, array $filters): array
    {
        $metrics = ['days' => 0, 'known_days' => 0, 'unknown_days' => 0, 'worked_minutes' => 0, 'late_minutes' => 0, 'early_minutes' => 0, 'conflicts' => 0, 'statuses' => []];
        $this->query($user, $filters)->reorder()->chunkById(100, function ($employees) use ($filters, &$metrics): void {
            foreach ($this->rows($employees, $filters) as $row) {
                $metrics['days']++;
                $metrics[$row['worked_minutes'] === null ? 'unknown_days' : 'known_days']++;
                $metrics['worked_minutes'] += $row['worked_minutes'] ?? 0;
                $metrics['late_minutes'] += $row['late_minutes'] ?? 0;
                $metrics['early_minutes'] += $row['early_minutes'] ?? 0;
                $metrics['conflicts'] += (int) $row['conflict'];
                $metrics['statuses'][$row['status']] = ($metrics['statuses'][$row['status']] ?? 0) + 1;
            }
        });

        return $metrics;
    }
}
