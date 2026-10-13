<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Holiday;
use App\Modules\HR\Models\WorkSchedule;
use Carbon\CarbonImmutable;

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

    public function latestStart(WorkSchedule $schedule, string $date): CarbonImmutable
    {
        [$start] = $this->window($schedule, $date);
        if (! $schedule->flexible || ! $schedule->latest_start_at) {
            return $start;
        }
        $latest = CarbonImmutable::parse($date.' '.$schedule->latest_start_at, $schedule->timezone);

        return $latest->lessThan($start) ? $latest->addDay() : $latest;
    }

    public function day(Employee $employee, string $date, ?CarbonImmutable $asOf = null): array
    {
        $employee->loadMissing(['assignments.schedule', 'assignments.retirements', 'events', 'legacyAttendances', 'adjustments.decision', 'scheduleExceptions.schedule']);
        if (! $employee->relationLoaded('attendanceHolidays')) {
            $employee->setRelation('attendanceHolidays', Holiday::query()->with('cancellation')->where('starts_on', '<=', $date)->where('ends_on', '>=', $date)->get());
        }
        $asOf ??= CarbonImmutable::now('UTC');
        $assignments = $employee->assignments->filter(fn ($item): bool => $item->covers($date))->sortBy(fn ($item): int => $this->window($item->schedule, $date)[0]->timestamp)->values();
        $exception = $employee->scheduleExceptions->filter(fn ($item): bool => $item->work_date->toDateString() === $date)->sortByDesc('id')->first();
        $events = $employee->events->filter(fn ($event): bool => $event->work_date->toDateString() === $date);
        $legacy = $employee->user_id === null ? null : $employee->legacyAttendances->first(fn ($item): bool => $item->date->toDateString() === $date);
        $approvedItems = $employee->adjustments->filter(fn ($item): bool => $item->work_date->toDateString() === $date && $item->decision?->decision === 'approved')->sortByDesc('id')->values();
        $approved = $approvedItems->first();
        $previous = $approvedItems->get(1);
        $scheduleIds = $assignments->pluck('work_schedule_id');
        if ($exception?->kind === 'work_schedule') {
            $scheduleIds->push($exception->work_schedule_id);
        }
        $holidays = $employee->getRelation('attendanceHolidays')->filter(fn ($item): bool => $item->activeOn($date)
            && ($item->work_schedule_id === null || $scheduleIds->contains($item->work_schedule_id)));
        $basis = [
            'employee' => [$employee->id, $employee->user_id, $employee->starts_on->toDateString(), $employee->ends_on?->toDateString()],
            'date' => $date, 'assignments' => $assignments->pluck('id')->sort()->values()->all(),
            'events' => $events->sortBy('id')->map(fn ($item): array => $item->getAttributes())->values()->all(),
            'legacy' => $legacy?->getAttributes(), 'holidays' => $holidays->pluck('id')->sort()->values()->all(),
            'exception' => $exception?->id, 'approved_adjustment' => $approved?->id, 'decision' => $approved?->decision?->id,
        ];
        $postApprovalChange = $approved !== null && ! hash_equals($approved->basis_fingerprint, hash('sha256', json_encode(
            [...$basis, 'approved_adjustment' => $previous?->id, 'decision' => $previous?->decision?->id], JSON_THROW_ON_ERROR)));
        $frames = $assignments->map(fn ($item): array => ['assignment_id' => $item->id, 'schedule' => $item->schedule])->all();
        if ($exception?->kind === 'work_schedule') {
            $frames = [['assignment_id' => $assignments->first()?->id, 'schedule' => $exception->schedule]];
        }
        if (empty($frames)) {
            $frames = [['assignment_id' => null, 'schedule' => null]];
        }
        $sessions = [];
        $totalExpected = 0;
        $expectedKnown = false;
        $worked = 0;
        $late = 0;
        $early = 0;
        $activeCount = 0;
        $missingPast = 0;
        $futureMissing = 0;
        $openSession = false;
        $missingCheckout = false;
        $missingCheckin = false;
        $observed = false;
        $firstIn = null;
        $lastOut = null;
        $anyHoliday = false;
        foreach ($frames as $index => $frame) {
            $schedule = $frame['schedule'];
            $timezone = $schedule?->timezone ?? config('hr.timezone');
            $holiday = $holidays->first(fn ($item): bool => $item->covers($date, $schedule?->id));
            $anyHoliday = $anyHoliday || $holiday !== null;
            $active = $employee->employedOn($date) && $schedule !== null
                && ($exception?->kind === 'work_schedule' || in_array(CarbonImmutable::parse($date, $timezone)->isoWeekday(), array_map('intval', $schedule->working_days), true))
                && ($holiday === null || $exception?->kind === 'work_schedule') && ! in_array($exception?->kind, ['leave', 'excused_absence', 'off_day'], true);
            $raw = $events->filter(fn ($item): bool => (int) $item->shift_assignment_id === $frame['assignment_id']);
            $inValue = $raw->firstWhere('action', 'check_in')?->occurred_at;
            $outValue = $raw->firstWhere('action', 'check_out')?->occurred_at;
            if ($approved) {
                $interval = collect($approved->sessions ?? [])->firstWhere('assignment_id', $frame['assignment_id']);
                $inValue = $interval['check_in_at'] ?? ($index === 0 && empty($approved->sessions) ? $approved->check_in_at : null);
                $outValue = $interval['check_out_at'] ?? ($index === 0 && empty($approved->sessions) ? $approved->check_out_at : null);
            }
            $checkIn = $inValue ? CarbonImmutable::parse($inValue, 'UTC') : null;
            $checkOut = $outValue ? CarbonImmutable::parse($outValue, 'UTC') : null;
            $expected = null;
            $deadline = null;
            $sessionLate = null;
            $sessionEarly = null;
            if ($schedule) {
                [$start, $end] = $this->window($schedule, $date);
                $duration = (int) $start->diffInMinutes($end);
                if ($active) {
                    $expected = max(0, $duration - $schedule->break_minutes);
                    $expectedKnown = true;
                    $totalExpected += $expected;
                    $activeCount++;
                    $deadline = $schedule->flexible
                        ? ($checkIn ?? $this->latestStart($schedule, $date))->addMinutes($duration) : $end;
                    if (! $checkIn) {
                        if ($asOf->greaterThanOrEqualTo($deadline)) {
                            $missingPast++;
                        } else {
                            $futureMissing++;
                        }
                    }
                    if ($checkIn) {
                        $sessionLate = max(0, (int) $this->latestStart($schedule, $date)->diffInMinutes($checkIn, false) - $schedule->grace_minutes);
                        $late += $sessionLate;
                    }
                    if ($checkOut && $checkIn) {
                        $sessionEarly = max(0, (int) $checkOut->diffInMinutes($deadline, false));
                        $early += $sessionEarly;
                    }
                } elseif ($holiday || in_array($exception?->kind, ['leave', 'excused_absence', 'off_day'], true)) {
                    $expected = 0;
                    $expectedKnown = true;
                }
            }
            $sessionWorked = $checkIn && $checkOut ? max(0, (int) $checkIn->diffInMinutes($checkOut) - ($schedule?->break_minutes ?? 0)) : null;
            if ($checkIn || $checkOut) {
                $observed = true;
            }
            if ($checkIn && (! $firstIn || $checkIn->lessThan($firstIn))) {
                $firstIn = $checkIn;
            }
            if ($checkOut && (! $lastOut || $checkOut->greaterThan($lastOut))) {
                $lastOut = $checkOut;
            }
            $worked += $sessionWorked ?? 0;
            $openSession = $openSession || ($checkIn !== null && $checkOut === null);
            $missingCheckout = $missingCheckout || ($checkIn !== null && $checkOut === null && $deadline !== null && $asOf->greaterThanOrEqualTo($deadline));
            $missingCheckin = $missingCheckin || ($checkOut !== null && $checkIn === null);
            $sessions[] = [
                'assignment_id' => $frame['assignment_id'], 'schedule' => $schedule?->name, 'timezone' => $timezone,
                'active' => $active, 'expected_minutes' => $expected,
                'check_in' => $checkIn?->setTimezone($timezone)->format('Y-m-d H:i'),
                'check_out' => $checkOut?->setTimezone($timezone)->format('Y-m-d H:i'),
                'worked_minutes' => $sessionWorked, 'late_minutes' => $sessionLate, 'early_minutes' => $sessionEarly,
            ];
        }
        $status = $assignments->isEmpty() ? 'unscheduled' : 'off_day';
        if (! $employee->employedOn($date)) {
            $status = 'outside_employment';
        } elseif (in_array($exception?->kind, ['leave', 'excused_absence', 'off_day'], true)) {
            $status = $exception->kind;
            $expectedKnown = true;
        } elseif ($activeCount === 0 && $anyHoliday && $exception?->kind !== 'work_schedule') {
            $status = 'holiday';
            $expectedKnown = true;
        } elseif ($activeCount > 0) {
            $status = $futureMissing > 0 ? 'pending' : 'absence_candidate';
            if ($missingPast > 0 && $futureMissing > 0) {
                $status = 'missing_session';
            }
        }
        if ($observed && $employee->employedOn($date)) {
            $status = $openSession ? 'working' : ($activeCount > 0 || $assignments->isEmpty() ? 'present' : 'off_day_worked');
            if ($futureMissing > 0 && ! $openSession) {
                $status = 'pending';
            }
            if ($missingPast > 0) {
                $status = 'missing_session';
            }
            if ($missingCheckout) {
                $status = 'missing_checkout';
            }
            if ($missingCheckin) {
                $status = 'missing_checkin';
            }
        }
        if (! $observed && $legacy !== null && $approved === null && ! in_array($status, ['holiday', 'leave', 'excused_absence', 'off_day', 'outside_employment'], true)) {
            $status = $legacy->status ? 'legacy_present' : 'legacy_absent';
        }
        if ($approved) {
            if ($approved->requested_status === 'absent') {
                $status = 'approved_absent';
            } elseif ($approved->requested_status === 'excused') {
                $status = 'excused_absence';
                $expectedKnown = true;
                $totalExpected = 0;
            } elseif (! $observed) {
                $status = 'approved_present';
            }
        }
        $conflict = $postApprovalChange || ($approved === null && $observed && (($legacy !== null && ! $legacy->status) || in_array($exception?->kind, ['leave', 'excused_absence'], true)));
        $timezone = $frames[0]['schedule']?->timezone ?? config('hr.timezone');
        $workedMinutes = $observed && ! $openSession && ! $missingCheckin ? $worked : null;

        return [
            'employee_id' => $employee->id, 'employee_number' => $employee->employee_number, 'name' => $employee->display_name,
            'date' => $date, 'schedule' => collect($sessions)->pluck('schedule')->filter()->unique()->implode(' / ') ?: null,
            'timezone' => $timezone, 'check_in' => $firstIn?->setTimezone($timezone)->format('Y-m-d H:i'),
            'check_out' => $lastOut?->setTimezone($timezone)->format('Y-m-d H:i'), 'status' => $conflict ? 'conflict' : $status,
            'source' => $approved ? 'approved_correction' : ($events->isNotEmpty() ? $events->pluck('source')->unique()->sort()->implode(', ') : ($legacy ? 'legacy' : 'none')),
            'legacy_status' => $legacy ? (int) $legacy->status : null, 'expected_minutes' => $expectedKnown ? $totalExpected : null,
            'worked_minutes' => $workedMinutes, 'late_minutes' => $observed && $activeCount > 0 ? $late : null,
            'early_minutes' => $workedMinutes !== null ? $early : null,
            'overtime_minutes' => $workedMinutes !== null && $expectedKnown ? max(0, $workedMinutes - $totalExpected) : null,
            'conflict' => $conflict, 'post_approval_change' => $postApprovalChange, 'adjustment_id' => $approved?->id,
            'schedule_exception_id' => $exception?->id, 'holiday' => $holidays->pluck('name')->unique()->implode(' / ') ?: null,
            'sessions' => $sessions, 'source_fingerprint' => hash('sha256', json_encode($basis, JSON_THROW_ON_ERROR)),
        ];
    }
}
