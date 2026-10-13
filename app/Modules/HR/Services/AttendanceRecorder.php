<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\AttendanceEvent;
use App\Modules\HR\Models\AttendanceSessionClosure;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceRecorder
{
    public function __construct(private AttendanceGatewayService $gateway, private AttendanceCalculator $calculator) {}

    /** @return array{open_session: ?array, options: array<int, array{action: string, assignment_id: int, work_date: string, schedule: string, timezone: string}>} */
    public function captureState(Employee $employee, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $last = $employee->events()->latest('id')->first();
        $open = $last?->action === 'check_in' && ! AttendanceSessionClosure::query()->where('check_in_event_id', $last->id)->exists();
        $options = [];
        $openData = null;
        if ($open) {
            $assignment = $last->assignment()->with('schedule')->firstOrFail();
            $start = CarbonImmutable::parse($last->occurred_at, 'UTC');
            $eligible = $employee->employedOn($last->work_date->toDateString()) && $now->greaterThan($start) && $start->diffInHours($now) <= 24;
            $openData = ['event_id' => $last->id, 'assignment_id' => $assignment->id, 'work_date' => $last->work_date->toDateString(),
                'check_in_utc' => $last->occurred_at, 'checkout_eligible' => $eligible];
            if ($eligible) {
                $options[] = ['action' => 'check_out', 'assignment_id' => $assignment->id, 'work_date' => $last->work_date->toDateString(), 'schedule' => $assignment->schedule->name, 'timezone' => $assignment->schedule->timezone];
            }
        } elseif (! $last || $now->greaterThanOrEqualTo(CarbonImmutable::parse($last->occurred_at, 'UTC'))) {
            foreach ($employee->assignments()->with(['schedule', 'retirements'])->get() as $candidate) {
                $localDate = $now->setTimezone($candidate->schedule->timezone)->toDateString();
                foreach ([CarbonImmutable::parse($localDate)->subDay()->toDateString(), $localDate] as $date) {
                    if (! $candidate->covers($date) || ! $employee->employedOn($date)) {
                        continue;
                    }
                    $employee->unsetRelation('attendanceHolidays');
                    $row = $this->calculator->day($employee, $date, $now);
                    $exception = $employee->scheduleExceptions->firstWhere('id', $row['schedule_exception_id']);
                    $schedule = $exception?->kind === 'work_schedule' ? $exception->schedule : $candidate->schedule;
                    [$start, $end] = $this->calculator->window($schedule, $date);
                    $slot = collect($row['sessions'])->firstWhere('assignment_id', $candidate->id);
                    if (($slot['active'] ?? false) && ! $employee->events()->where('shift_assignment_id', $candidate->id)->where('work_date', $date)->where('action', 'check_in')->exists()
                        && $now->greaterThanOrEqualTo($start->subHours(2)) && $now->lessThan($end)) {
                        $options[] = ['action' => 'check_in', 'assignment_id' => $candidate->id, 'work_date' => $date, 'schedule' => $schedule->name, 'timezone' => $schedule->timezone];
                    }
                }
            }
        }

        return ['open_session' => $openData, 'options' => $options];
    }

    /** @return array{action: string, assignment_id: int, work_date: string, schedule: string, timezone: string} */
    public function eligibleOption(Employee $employee, string $action, ?int $assignmentId): array
    {
        $options = array_values(array_filter($this->captureState($employee)['options'], fn (array $option): bool => $option['action'] === $action && ($assignmentId === null || $option['assignment_id'] === $assignmentId)));
        if (count($options) !== 1) {
            throw ValidationException::withMessages(['action' => count($options) > 1 ? 'اختر الوردية المطلوبة.' : 'لا توجد وردية مؤهلة لهذه العملية الآن؛ قد تلزم مراجعة HR لتصحيح السجل.']);
        }

        return $options[0];
    }

    /** Local/test simulator only. No ordinary attendance endpoint is exposed. */
    public function simulate(Employee $employee, string $action, string $idempotencyKey, ?int $assignmentId = null): AttendanceEvent
    {
        $this->gateway->assertSimulationAllowed();

        return $this->record($employee, $action, $idempotencyKey, $assignmentId, 'local_simulation');
    }

    public function recordSigned(Employee $employee, string $action, string $challengeId, ?int $assignmentId, Request $request, string $workDate): AttendanceEvent
    {
        $this->gateway->assertCaptureAllowed($request);

        return $this->record($employee, $action, $challengeId, $assignmentId, 'signed_native', $workDate);
    }

    private function record(Employee $employee, string $action, string $idempotencyKey, ?int $assignmentId, string $source, ?string $expectedDate = null): AttendanceEvent
    {
        if (! in_array($action, ['check_in', 'check_out'], true) || ! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['action' => 'Invalid attendance action or request identifier.']);
        }

        return Employee::query()->getConnection()->transaction(function () use ($employee, $action, $idempotencyKey, $assignmentId, $source, $expectedDate): AttendanceEvent {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $prior = AttendanceEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($prior) {
                if ($prior->employee_id !== $employee->id || $prior->action !== $action || ($assignmentId !== null && $prior->shift_assignment_id !== $assignmentId)) {
                    throw ValidationException::withMessages(['action' => 'Request identifier already used.']);
                }

                return $prior;
            }
            $now = CarbonImmutable::now('UTC');
            $last = $employee->events()->latest('id')->first();
            if ($last && $now->lessThan(CarbonImmutable::parse($last->occurred_at, 'UTC'))) {
                throw ValidationException::withMessages(['action' => 'وقت الخادم يسبق آخر حدث؛ يلزم التحقق من الساعة قبل التسجيل.']);
            }
            if ($employee->user !== null && ! $employee->user->is_active) {
                throw ValidationException::withMessages(['employee' => 'Inactive employee account.']);
            }
            $option = $this->eligibleOption($employee, $action, $assignmentId);
            if ($expectedDate !== null && $expectedDate !== $option['work_date']) {
                throw ValidationException::withMessages(['challenge_id' => 'تغير يوم العمل المؤهل؛ اطلب تحدياً جديداً.']);
            }
            $event = $employee->events()->create([
                'shift_assignment_id' => $option['assignment_id'], 'work_date' => $option['work_date'], 'action' => $action,
                'occurred_at' => $now->format('Y-m-d H:i:s'), 'created_at' => $now->format('Y-m-d H:i:s'),
                'source' => $source, 'idempotency_key' => $idempotencyKey,
            ]);
            AuditLog::query()->create([
                'actor_id' => $source === 'signed_native' ? $employee->user_id : null,
                'action' => $source === 'signed_native' ? 'attendance.signed' : 'attendance.simulated',
                'subject_type' => 'hr_attendance_events', 'subject_id' => $event->id,
                'metadata' => ['action' => $action], 'created_at' => $now->format('Y-m-d H:i:s'),
            ]);

            return $event;
        });
    }
}
