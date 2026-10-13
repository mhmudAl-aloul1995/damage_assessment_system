<?php

namespace App\Modules\HR\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AttendanceExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $exportId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'hr_export_ready', 'export_id' => $this->exportId, 'title' => 'تقرير الحضور جاهز',
            'message' => 'اكتملت ملفات Excel المطلوبة؛ افتح التقارير لتنزيلها.', 'action_url' => route('hr.attendance.reports')];
    }
}
