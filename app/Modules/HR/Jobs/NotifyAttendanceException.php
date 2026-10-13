<?php

namespace App\Modules\HR\Jobs;

use App\Models\User;
use App\Modules\HR\Models\AttendanceAlert;
use App\Modules\HR\Models\AttendanceException;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Notifications\AttendanceExceptionNotification;
use App\Modules\HR\Policies\EmployeePolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyAttendanceException implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $exceptionId)
    {
        $this->afterCommit();
    }

    public function handle(EmployeePolicy $policy): void
    {
        $exception = AttendanceException::query()->with('summary')->find($this->exceptionId);
        if (! $exception) {
            return;
        }
        Employee::query()->getConnection()->transaction(function () use ($exception, $policy): void {
            $employee = Employee::query()->lockForUpdate()->findOrFail($exception->summary->employee_id);
            $exception = AttendanceException::query()->with('summary')->findOrFail($exception->id);
            if ($exception->status !== 'open') {
                return;
            }
            $recipients = array_unique(array_filter([$employee->user_id, $employee->supervisor_user_id]));
            foreach (User::query()->whereKey($recipients)->where('is_active', true)->get() as $recipient) {
                if (! $policy->view($recipient, $employee)) {
                    continue;
                }
                $exists = AttendanceAlert::query()->where('attendance_exception_id', $exception->id)
                    ->where('recipient_id', $recipient->id)->where('source_fingerprint', $exception->summary->source_fingerprint)->exists();
                if ($exists) {
                    continue;
                }
                AttendanceAlert::query()->create([
                    'attendance_exception_id' => $exception->id, 'recipient_id' => $recipient->id,
                    'source_fingerprint' => $exception->summary->source_fingerprint, 'created_at' => now('UTC')->format('Y-m-d H:i:s'),
                ]);
                $recipient->notify(new AttendanceExceptionNotification($employee->id, $exception->summary->work_date->toDateString(), $exception->kind));
            }
        });
    }
}
