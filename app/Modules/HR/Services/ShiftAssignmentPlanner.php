<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ShiftAssignmentPlanner
{
    public function __construct(private AttendanceCalculator $calculator) {}

    public function assertAvailable(Employee $employee, WorkSchedule $schedule, string $from, string $to): void
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        if ($start->diffInDays($end) > 365) {
            throw ValidationException::withMessages(['ends_on' => 'حدد فترة تكليف لا تتجاوز سنة واحدة.']);
        }
        $existing = $employee->assignments()->with(['schedule', 'retirements'])
            ->where('starts_on', '<=', $end->addDay()->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $start->subDay()->toDateString()))->get();
        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            if (! in_array($date->isoWeekday(), array_map('intval', $schedule->working_days), true)) {
                continue;
            }
            [$newStart, $newEnd] = $this->calculator->window($schedule, $date->toDateString());
            if ($schedule->flexible) {
                $newEnd = $this->calculator->latestStart($schedule, $date->toDateString())->addMinutes((int) $newStart->diffInMinutes($newEnd));
            }
            foreach ($existing as $assignment) {
                foreach ([$date->subDay(), $date, $date->addDay()] as $otherDate) {
                    if (! $assignment->covers($otherDate->toDateString()) || ! in_array($otherDate->isoWeekday(), array_map('intval', $assignment->schedule->working_days), true)) {
                        continue;
                    }
                    [$oldStart, $oldEnd] = $this->calculator->window($assignment->schedule, $otherDate->toDateString());
                    if ($assignment->schedule->flexible) {
                        $oldEnd = $this->calculator->latestStart($assignment->schedule, $otherDate->toDateString())->addMinutes((int) $oldStart->diffInMinutes($oldEnd));
                    }
                    if ($newStart->lessThan($oldEnd) && $newEnd->greaterThan($oldStart)) {
                        throw ValidationException::withMessages(['starts_on' => 'تتداخل أوقات الوردية مع تكليف موجود، بما يشمل الورديات الليلية والمرنة.']);
                    }
                }
            }
        }
    }
}
