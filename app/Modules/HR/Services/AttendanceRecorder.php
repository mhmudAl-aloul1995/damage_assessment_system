<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\AttendanceEvent;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceRecorder
{
    public function __construct(
        private AttendanceGatewayService $gateway,
        private AttendanceCalculator $calculator,
    ) {}

    /** Local/test simulator only. No ordinary attendance endpoint is exposed in Phase 1. */
    public function simulate(Employee $employee, string $action, string $idempotencyKey): AttendanceEvent
    {
        $this->gateway->assertSimulationAllowed();
        if (! in_array($action, ['check_in', 'check_out'], true) || ! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['action' => 'Invalid attendance action or request identifier.']);
        }

        return Employee::query()->getConnection()->transaction(function () use ($employee, $action, $idempotencyKey): AttendanceEvent {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $prior = AttendanceEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($prior) {
                if ($prior->employee_id !== $employee->id || $prior->action !== $action) {
                    throw ValidationException::withMessages(['action' => 'Request identifier already used.']);
                }

                return $prior;
            }
            $now = CarbonImmutable::now('UTC');
            $last = $employee->events()->orderByDesc('id')->first();
            if ($employee->user !== null && ! $employee->user->is_active) {
                throw ValidationException::withMessages(['employee' => 'Inactive employee account.']);
            }
            if ($action === 'check_out') {
                if (! $last || $last->action !== 'check_in') {
                    throw ValidationException::withMessages(['action' => 'Check-in is required before check-out.']);
                }
                $assignment = $last->assignment()->with('schedule')->firstOrFail();
                $date = $last->work_date->toDateString();
                if ($now->lessThanOrEqualTo(CarbonImmutable::parse($last->occurred_at, 'UTC'))
                    || CarbonImmutable::parse($last->occurred_at, 'UTC')->diffInHours($now) > 24) {
                    throw ValidationException::withMessages(['action' => 'Invalid duration; an attendance correction is required.']);
                }
            } else {
                if ($last?->action === 'check_in') {
                    throw ValidationException::withMessages(['action' => 'An open attendance session already exists.']);
                }
                $assignment = null;
                $date = null;
                foreach ($employee->assignments()->with('schedule')->get() as $candidate) {
                    $localDate = $now->setTimezone($candidate->schedule->timezone)->toDateString();
                    foreach ([CarbonImmutable::parse($localDate)->subDay()->toDateString(), $localDate] as $candidateDate) {
                        [$start, $end] = $this->calculator->window($candidate->schedule, $candidateDate);
                        if ($candidate->covers($candidateDate) && $employee->employedOn($candidateDate)
                            && in_array($start->isoWeekday(), array_map('intval', $candidate->schedule->working_days), true)
                            && $now->greaterThanOrEqualTo($start->subHours(2)) && $now->lessThan($end)) {
                            $assignment = $candidate;
                            $date = $candidateDate;
                            break 2;
                        }
                    }
                }
                if (! $assignment) {
                    throw ValidationException::withMessages(['action' => 'No eligible assigned shift at the current server time.']);
                }
            }
            if ($employee->events()->where('work_date', $date)->where('action', $action)->exists()) {
                throw ValidationException::withMessages(['action' => 'Attendance action already recorded for this shift.']);
            }
            $event = $employee->events()->create([
                'shift_assignment_id' => $assignment->id,
                'work_date' => $date,
                'action' => $action,
                'occurred_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
                'source' => 'local_simulation',
                'idempotency_key' => $idempotencyKey,
            ]);
            AuditLog::query()->create([
                'actor_id' => null,
                'action' => 'attendance.simulated',
                'subject_type' => 'hr_attendance_events',
                'subject_id' => $event->id,
                'metadata' => ['action' => $action],
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);

            return $event;
        });
    }
}
