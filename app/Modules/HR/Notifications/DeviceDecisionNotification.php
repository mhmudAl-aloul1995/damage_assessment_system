<?php

namespace App\Modules\HR\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeviceDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $credentialId, public string $decision)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'hr_device_decision', 'credential_id' => $this->credentialId, 'decision' => $this->decision,
            'title' => 'تحديث جهاز الحضور', 'message' => ['approved' => 'اعتُمد الجهاز؛ حدّث حالته في تطبيق PHC.', 'rejected' => 'رُفض طلب الجهاز.', 'revoked' => 'أُلغي اعتماد الجهاز.'][$this->decision],
            'action_url' => route('hr.attendance.index')];
    }
}
