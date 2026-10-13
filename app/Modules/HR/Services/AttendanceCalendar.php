<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Holiday;
use App\Modules\HR\Models\HolidayCancellation;
use App\Modules\HR\Models\ScheduleException;
use App\Modules\HR\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceCalendar
{
    public function holiday(User $actor, array $data): Holiday
    {
        Gate::forUser($actor)->authorize('hr.attendance.setup');

        return Holiday::query()->getConnection()->transaction(function () use ($actor, $data): Holiday {
            $holiday = Holiday::query()->create([...$data, 'created_by' => $actor->id]);
            $this->audit($actor, 'holiday.created', 'hr_holidays', $holiday->id);

            return $holiday;
        });
    }

    public function exception(User $actor, array $data): ScheduleException
    {
        Gate::forUser($actor)->authorize('hr.attendance.exceptions.manage');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $data): ScheduleException {
            $employee = Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);
            Gate::forUser($actor)->authorize('view', $employee);
            abort_if($employee->user_id === $actor->id, 403, 'لا يمكنك اعتماد استثناء حضور لنفسك.');
            $until = $data['until_date'] ?? $data['work_date'];
            if (! $employee->employedOn($data['work_date']) || ! $employee->employedOn($until)
                || $until < $data['work_date'] || CarbonImmutable::parse($data['work_date'])->diffInDays(CarbonImmutable::parse($until)) > 30) {
                throw ValidationException::withMessages(['work_date' => 'التاريخ خارج فترة عمل الموظف.']);
            }
            $schedule = $data['kind'] === 'work_schedule' ? WorkSchedule::query()->findOrFail($data['work_schedule_id']) : null;
            unset($data['until_date']);
            for ($date = CarbonImmutable::parse($data['work_date']); $date->toDateString() <= $until; $date = $date->addDay()) {
                $record = [...$data, 'work_date' => $date->toDateString(), 'created_by' => $actor->id];
                if ($schedule && $data['work_date'] !== $until && ! in_array($date->isoWeekday(), array_map('intval', $schedule->working_days), true)) {
                    $record['kind'] = 'off_day';
                    $record['work_schedule_id'] = null;
                }
                $exception = $employee->scheduleExceptions()->create($record);
                $this->audit($actor, 'schedule.exception_recorded', 'hr_schedule_exceptions', $exception->id);
            }

            return $exception;
        });
    }

    public function cancelHoliday(User $actor, Holiday $holiday, array $data): HolidayCancellation
    {
        Gate::forUser($actor)->authorize('hr.attendance.setup');

        return Holiday::query()->getConnection()->transaction(function () use ($actor, $holiday, $data): HolidayCancellation {
            $holiday = Holiday::query()->with('cancellation')->lockForUpdate()->findOrFail($holiday->id);
            if ($holiday->cancellation) {
                if ($holiday->cancellation->cancelled_from->toDateString() === $data['cancelled_from'] && $holiday->cancellation->reason === $data['reason']) {
                    return $holiday->cancellation;
                }
                throw ValidationException::withMessages(['cancelled_from' => 'توجد معاملة إلغاء محفوظة لهذه العطلة.']);
            }
            if ($data['cancelled_from'] < $holiday->starts_on->toDateString() || $data['cancelled_from'] > $holiday->ends_on->toDateString()) {
                throw ValidationException::withMessages(['cancelled_from' => 'بداية الإلغاء يجب أن تقع ضمن فترة العطلة.']);
            }
            $cancellation = $holiday->cancellation()->create([...$data, 'created_by' => $actor->id]);
            $this->audit($actor, 'holiday.cancelled', 'hr_holiday_cancellations', $cancellation->id);

            return $cancellation;
        });
    }

    private function audit(User $actor, string $action, string $type, int $id): void
    {
        AuditLog::query()->create(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
    }
}
