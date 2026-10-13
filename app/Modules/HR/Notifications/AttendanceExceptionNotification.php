<?php

namespace App\Modules\HR\Notifications;

use Illuminate\Notifications\Notification;

class AttendanceExceptionNotification extends Notification
{
    public function __construct(public int $employeeId, public string $date, public string $kind) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'hr_attendance_exception', 'title' => 'استثناء حضور يحتاج مراجعة',
            'message' => $this->date.' — '.__('hr::attendance.statuses.'.$this->kind),
            'action_url' => route('hr.attendance.index', ['date' => $this->date, 'employee_id' => $this->employeeId]),
        ];
    }
}
