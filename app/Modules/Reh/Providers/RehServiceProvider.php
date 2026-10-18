<?php

namespace App\Modules\Reh\Providers;

use App\Models\User;
use App\Modules\Reh\Console\Commands\SyncRehCommand;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class RehServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'reh';

    protected array $moduleCommands = [SyncRehCommand::class];

    public function modulePath(string $path = ''): string
    {
        return dirname(__DIR__).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::before(static function (User $user, string $ability): ?bool {
            return str_starts_with($ability, 'reh.') && $user->hasRole('Database Officer') ? true : null;
        });
    }
}
