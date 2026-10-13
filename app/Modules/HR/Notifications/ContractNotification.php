<?php
namespace App\Modules\HR\Notifications;
use App\Modules\HR\Models\EmploymentContract;
use App\Modules\HR\Services\EmployeeOrganizationScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
class ContractNotification extends Notification implements ShouldQueue {
    use Queueable;
    public function __construct(public int $contractId, public string $event, public ?string $expiresOn=null) {}
    public function via(object $notifiable): array { return ['database']; }
    public function shouldSend(object $notifiable, string $channel): bool {
        app(EmployeeOrganizationScope::class)->forget();
        $contract=EmploymentContract::query()->find($this->contractId);
        return $contract!==null && $notifiable->fresh()?->can('view',$contract);
    }
    public function toArray(object $notifiable): array {
        return ['type'=>'hr_contract','contract_id'=>$this->contractId,'event'=>$this->event,'title'=>'تحديث عقد العمل',
            'message'=>$this->event==='expiry'?'ينتهي عقد العمل بتاريخ '.$this->expiresOn:($this->event==='approved'?'اعتُمد مقترح عقد العمل.':'رُفض مقترح عقد العمل.'),
            'action_url'=>route('hr.contracts.show',$this->contractId)];
    }
}
