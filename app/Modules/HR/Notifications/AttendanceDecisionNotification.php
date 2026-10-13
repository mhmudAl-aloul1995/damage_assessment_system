<?php

namespace App\Modules\HR\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AttendanceDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $adjustmentId, public string $decision)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'hr_attendance_decision', 'adjustment_id' => $this->adjustmentId, 'decision' => $this->decision, 'url' => route('hr.attendance.corrections', ['state' => 'all'])];
    }
}
