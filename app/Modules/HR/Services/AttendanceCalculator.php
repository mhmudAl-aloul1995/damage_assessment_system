<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\AttendanceEvent;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AttendanceCalculator
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function window(WorkSchedule $schedule, string $date): array
    {
        $start = CarbonImmutable::parse($date.' '.$schedule->starts_at, $schedule->timezone);
        $end = CarbonImmutable::parse($date.' '.$schedule->ends_at, $schedule->timezone);
        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }

    public function day(Employee $employee, string $date, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now('UTC');
        $assignment = $employee->assignments->first(fn ($item): bool => $item->covers($date));
        $schedule = $assignment?->schedule;
        $events = $employee->events->filter(fn ($event): bool => $event->work_date->toDateString() === $date);
        $legacy = $employee->user_id === null ? null : $employee->legacyAttendances
            ->first(fn ($item): bool => $item->date->toDateString() === $date);
        $checkIn = $this->time($events, 'check_in');
        $checkOut = $this->time($events, 'check_out');
        $timezone = $schedule?->timezone ?? config('hr.timezone');
        $workingDay = $employee->employedOn($date) && $schedule !== null
            && in_array(CarbonImmutable::parse($date, $timezone)->isoWeekday(), array_map('intval', $schedule->working_days), true);
        $row = [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'name' => $employee->display_name,
            'date' => $date,
            'schedule' => $schedule?->name,
            'timezone' => $timezone,
            'check_in' => $checkIn?->setTimezone($timezone)->format('Y-m-d H:i'),
            'check_out' => $checkOut?->setTimezone($timezone)->format('Y-m-d H:i'),
            'status' => $schedule === null ? 'unscheduled' : 'off_day',
            'source' => $events->isNotEmpty() ? $events->pluck('source')->unique()->implode(', ') : ($legacy ? 'legacy' : 'none'),
            'legacy_status' => $legacy ? (int) $legacy->status : null,
            'expected_minutes' => null,
            'worked_minutes' => null,
            'late_minutes' => null,
            'early_minutes' => null,
            'overtime_minutes' => null,
            'conflict' => $legacy !== null && $checkIn !== null && ! $legacy->status,
        ];
        if (! $employee->employedOn($date)) {
            $row['status'] = 'outside_employment';
        } elseif ($workingDay) {
            [$start, $end] = $this->window($schedule, $date);
            $row['expected_minutes'] = max(0, (int) $start->diffInMinutes($end) - $schedule->break_minutes);
            $row['status'] = $asOf->lessThan($end) ? 'pending' : 'absence_candidate';
            if ($checkIn) {
                $row['late_minutes'] = max(0, (int) $start->diffInMinutes($checkIn, false) - $schedule->grace_minutes);
                $row['status'] = $checkOut ? 'present' : ($asOf->greaterThanOrEqualTo($end) ? 'missing_checkout' : 'working');
            }
            if ($checkIn && $checkOut) {
                $row['worked_minutes'] = max(0, (int) $checkIn->diffInMinutes($checkOut) - $schedule->break_minutes);
                $row['early_minutes'] = max(0, (int) $checkOut->diffInMinutes($end, false));
                $row['overtime_minutes'] = max(0, $row['worked_minutes'] - $row['expected_minutes']);
            }
        }
        if ($checkIn === null && $legacy !== null) {
            $row['status'] = $legacy->status ? 'legacy_present' : 'legacy_absent';
        }
        if ($row['conflict']) {
            $row['status'] = 'conflict';
        }

        return $row;
    }

    private function time(Collection $events, string $action): ?CarbonImmutable
    {
        $event = $events->first(fn (AttendanceEvent $item): bool => $item->action === $action);

        return $event ? CarbonImmutable::parse($event->occurred_at, 'UTC') : null;
    }
}
