<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\AttendanceEvent;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\AttendanceSessionClosure;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceRecorder
{
    public function __construct(
        private AttendanceGatewayService $gateway,
        private AttendanceCalculator $calculator,
    ) {}

    /** Local/test simulator only. No ordinary attendance endpoint is exposed in Phase 1. */
    public function simulate(Employee $employee, string $action, string $idempotencyKey, ?int $assignmentId = null): AttendanceEvent
    {
        $this->gateway->assertSimulationAllowed();

        return $this->record($employee, $action, $idempotencyKey, $assignmentId, 'local_simulation');
    }

    public function recordSigned(Employee $employee, string $action, string $challengeId, ?int $assignmentId, Request $request): AttendanceEvent
    {
        $this->gateway->assertCaptureAllowed($request);

        return $this->record($employee, $action, $challengeId, $assignmentId, 'signed_native');
    }

    private function record(Employee $employee, string $action, string $idempotencyKey, ?int $assignmentId, string $source): AttendanceEvent
    {
        if (! in_array($action, ['check_in', 'check_out'], true) || ! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['action' => 'Invalid attendance action or request identifier.']);
        }

        return Employee::query()->getConnection()->transaction(function () use ($employee, $action, $idempotencyKey, $assignmentId, $source): AttendanceEvent {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $prior = AttendanceEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($prior) {
                if ($prior->employee_id !== $employee->id || $prior->action !== $action || ($assignmentId !== null && $prior->shift_assignment_id !== $assignmentId)) {
                    throw ValidationException::withMessages(['action' => 'Request identifier already used.']);
                }

                return $prior;
            }
            $now = CarbonImmutable::now('UTC');
            $last = $employee->events()->orderByDesc('id')->first();
            $openSession = $last?->action === 'check_in' && ! AttendanceSessionClosure::query()->where('check_in_event_id', $last->id)->exists();
            if ($last && $now->lessThan(CarbonImmutable::parse($last->occurred_at, 'UTC'))) {
                throw ValidationException::withMessages(['action' => 'وقت الخادم يسبق آخر حدث؛ يلزم التحقق من الساعة قبل التسجيل.']);
            }
            if ($employee->user !== null && ! $employee->user->is_active) {
                throw ValidationException::withMessages(['employee' => 'Inactive employee account.']);
            }
            if ($action === 'check_out') {
                if (! $last || ! $openSession) {
                    throw ValidationException::withMessages(['action' => 'Check-in is required before check-out.']);
                }
                $assignment = $last->assignment()->with('schedule')->firstOrFail();
                if ($assignmentId !== null && $assignment->id !== $assignmentId) {
                    throw ValidationException::withMessages(['action' => 'طلب الانصراف لا يطابق الوردية المفتوحة.']);
                }
                $date = $last->work_date->toDateString();
                if ($now->lessThanOrEqualTo(CarbonImmutable::parse($last->occurred_at, 'UTC'))
                    || CarbonImmutable::parse($last->occurred_at, 'UTC')->diffInHours($now) > 24) {
                    throw ValidationException::withMessages(['action' => 'Invalid duration; an attendance correction is required.']);
                }
            } else {
                if ($openSession) {
                    throw ValidationException::withMessages(['action' => 'An open attendance session already exists.']);
                }
                $assignment = null;
                $date = null;
                foreach ($employee->assignments()->with(['schedule', 'retirements'])->get() as $candidate) {
                    if ($assignmentId !== null && $candidate->id !== $assignmentId) {
                        continue;
                    }
                    $localDate = $now->setTimezone($candidate->schedule->timezone)->toDateString();
                    foreach ([CarbonImmutable::parse($localDate)->subDay()->toDateString(), $localDate] as $candidateDate) {
                        $employee->unsetRelation('attendanceHolidays');
                        $row = $this->calculator->day($employee, $candidateDate, $now);
                        $exception = $employee->scheduleExceptions->firstWhere('id', $row['schedule_exception_id']);
                        $effectiveSchedule = $exception?->kind === 'work_schedule' ? $exception->schedule : $candidate->schedule;
                        [$start, $end] = $this->calculator->window($effectiveSchedule, $candidateDate);
                        $slot = collect($row['sessions'])->firstWhere('assignment_id', $candidate->id);
                        if ($candidate->covers($candidateDate) && $employee->employedOn($candidateDate)
                            && ($slot['active'] ?? false)
                            && ! $employee->events()->where('shift_assignment_id', $candidate->id)->where('work_date', $candidateDate)->where('action', 'check_in')->exists()
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
            if ($employee->events()->where('work_date', $date)->where('shift_assignment_id', $assignment->id)->where('action', $action)->exists()) {
                throw ValidationException::withMessages(['action' => 'Attendance action already recorded for this shift.']);
            }
            $event = $employee->events()->create([
                'shift_assignment_id' => $assignment->id,
                'work_date' => $date,
                'action' => $action,
                'occurred_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
            ]);
            AuditLog::query()->create([
                'actor_id' => $source === 'signed_native' ? $employee->user_id : null,
                'action' => $source === 'signed_native' ? 'attendance.signed' : 'attendance.simulated',
                'subject_type' => 'hr_attendance_events',
                'subject_id' => $event->id,
                'metadata' => ['action' => $action],
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);

            return $event;
        });
    }
}
