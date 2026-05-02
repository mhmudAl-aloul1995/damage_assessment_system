<?php

namespace App\Providers;

use App\services\Messaging\MessagingProvider;
use App\services\Messaging\TelegramMessagingProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Models\LoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MessagingProvider::class, TelegramMessagingProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
  public function boot(): void
{
    Event::listen(Login::class, function (Login $event) {
        LoginLog::create([
            'user_id' => $event->user->id,
            'name' => $event->user->name ?? null,
            'email' => $event->user->email ?? null,
            'username' => $event->user->username ?? null,
            'role' => method_exists($event->user, 'getRoleNames')
                ? $event->user->getRoleNames()->first()
                : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'is_success' => true,
            'logged_in_at' => now(),
        ]);
    });

    Event::listen(Failed::class, function (Failed $event) {
        LoginLog::create([
            'user_id' => optional($event->user)->id,
            'name' => optional($event->user)->name,
            'email' => $event->credentials['email'] ?? null,
            'username' => $event->credentials['username'] ?? null,
            'role' => optional($event->user)->getRoleNames()?->first(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'is_success' => false,
            'failure_reason' => 'Invalid credentials',
        ]);
    });

    Event::listen(Logout::class, function (Logout $event) {
        if (! $event->user) {
            return;
        }

        LoginLog::where('user_id', $event->user->id)
            ->where('is_success', true)
            ->whereNull('logged_out_at')
            ->latest('logged_in_at')
            ->first()
            ?->update([
                'logged_out_at' => now(),
            ]);
    });
}
}
