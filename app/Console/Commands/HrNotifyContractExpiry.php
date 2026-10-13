<?php
namespace App\Console\Commands;
use App\Modules\HR\Jobs\NotifyContractExpiry;
use App\Modules\HR\Models\EmploymentContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
class HrNotifyContractExpiry extends Command {
    protected $signature='hr:notify-contract-expiry';
    protected $description='Queue scoped and deduplicated HR contract expiry reminders';
    public function handle(): int {
        if (!Schema::hasTable('hr_contracts') || !Schema::hasTable('hr_contract_alerts')) { $this->warn('Contract storage is not installed.'); return self::SUCCESS; }
        EmploymentContract::query()->select(['id'])->chunkById(100,function ($contracts): void { foreach ($contracts as $contract) { NotifyContractExpiry::dispatch($contract->id); } });
        $this->info('Contract reminder checks queued.'); return self::SUCCESS;
    }
}
