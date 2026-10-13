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
        return ['type' => 'hr_device_decision', 'credential_id' => $this->credentialId, 'decision' => $this->decision, 'url' => route('hr.attendance.index')];
    }
}
