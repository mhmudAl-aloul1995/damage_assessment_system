<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Telegram\TelegramDestinationSyncService;
use Illuminate\Console\Command;

class SyncTelegramUserDestinationsCommand extends Command
{
    protected $signature = 'telegram:sync-user-destinations';

    protected $description = 'Sync existing users into telegram destinations';

    public function handle(TelegramDestinationSyncService $syncService): int
    {
        $count = 0;

        User::query()
            ->chunk(100, function ($users) use ($syncService, &$count) {
                foreach ($users as $user) {
                    $syncService->syncUser($user);
                    $count++;
                }
            });

        $this->info("Synced {$count} users into telegram destinations.");

        return self::SUCCESS;
    }
}
