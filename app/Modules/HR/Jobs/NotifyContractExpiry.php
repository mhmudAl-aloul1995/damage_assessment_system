<?php

namespace App\Modules\HR\Jobs;

use App\Models\User;
use App\Modules\HR\Models\ContractAlert;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmploymentContract;
use App\Modules\HR\Notifications\ContractNotification;
use App\Modules\HR\Services\ContractReporting;
use App\Modules\HR\Services\EmployeeOrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyContractExpiry implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $contractId)
    {
        $this->afterCommit();
    }

    public function handle(ContractReporting $report): void
    {
        $contract = EmploymentContract::query()->find($this->contractId);
        if (! $contract) {
            return;
        }
        Employee::query()->getConnection()->transaction(function () use ($contract, $report): void {
            Employee::query()->lockForUpdate()->findOrFail($contract->employee_id);
            $contract = EmploymentContract::query()->with(['employee', 'revisions.decision', 'revisions.type'])->findOrFail($contract->id);
            $row = $report->row($contract);
            if (! in_array($row['status'], ['active', 'scheduled'], true) || $row['ends_on'] === null || ! $row['expiry_revision']) {
                return;
            }
            $remaining = (int) CarbonImmutable::parse(now(config('hr.timezone'))->toDateString())->diffInDays(CarbonImmutable::parse($row['ends_on']), false);
            if ($remaining < 0) {
                return;
            }
            $threshold = collect($row['expiry_revision']->type->reminder_days)->map(fn ($value): int => (int) $value)->filter(fn ($value): bool => $value >= $remaining)->min();
            if ($threshold === null) {
                return;
            }
            app(EmployeeOrganizationScope::class)->forget();
            User::query()->where('is_active', true)->chunkById(100, function ($users) use ($contract, $row, $threshold): void {
                foreach ($users as $recipient) {
                    if (! $recipient->can('view', $contract) || (! $recipient->can('hr.contracts.manage') && ! $recipient->can('hr.contracts.approve'))) {
                        continue;
                    }
                    if (ContractAlert::query()->where('revision_id', $row['expiry_revision']->id)->where('recipient_id', $recipient->id)->where('threshold_days', $threshold)->exists()) {
                        continue;
                    }
                    ContractAlert::query()->create(['revision_id' => $row['expiry_revision']->id, 'recipient_id' => $recipient->id, 'threshold_days' => $threshold, 'expires_on' => $row['ends_on'], 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
                    $recipient->notifyNow(new ContractNotification($contract->id, 'expiry', $row['ends_on']), ['database']);
                }
            });
        });
    }
}
