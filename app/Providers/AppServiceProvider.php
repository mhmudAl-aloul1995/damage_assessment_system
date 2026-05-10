<?php

namespace App\Providers;

use App\Models\LoginLog;
use App\Support\Forms\LoginSecurity;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event) {
            $agent = request()->userAgent();
            $ip = request()->ip();

            $failedAttempts = LoginSecurity::failedAttemptsFromIp($ip);
            $isNewIp = LoginSecurity::isNewIpForUser($event->user->id, $ip);

            $isSuspicious = $failedAttempts >= 5 || $isNewIp;

            $reason = null;

            if ($failedAttempts >= 5) {
                $reason = 'Multiple failed attempts from same IP';
            }

            if ($isNewIp) {
                $reason = $reason
                    ? $reason.' + New IP for this user'
                    : 'New IP for this user';
            }

            LoginLog::create([
                'user_id' => $event->user->id,
                'name' => $event->user->name ?? null,
                'email' => $event->user->email ?? null,
                'username' => $event->user->username_arcgis ?? null,
                'role' => method_exists($event->user, 'getRoleNames')
                    ? $event->user->getRoleNames()->first()
                    : null,

                'ip_address' => $ip,
                'user_agent' => $agent,
                'browser' => LoginSecurity::detectBrowser($agent),
                'device' => LoginSecurity::detectDevice($agent),
                'platform' => LoginSecurity::detectPlatform($agent),

                'is_success' => true,
                'is_suspicious' => $isSuspicious,
                'suspicious_reason' => $reason,
                'failed_attempts_from_ip' => $failedAttempts,

                'logged_in_at' => now(),
            ]);
        });

        Event::listen(Failed::class, function (Failed $event) {
            $agent = request()->userAgent();
            $ip = request()->ip();

            $failedAttempts = LoginSecurity::failedAttemptsFromIp($ip) + 1;

            LoginLog::create([
                'user_id' => optional($event->user)->id,
                'name' => optional($event->user)->name,
                'email' => $event->credentials['email'] ?? null,
                'username' => $event->credentials['username'] ?? null,

                'ip_address' => $ip,
                'user_agent' => $agent,
                'browser' => LoginSecurity::detectBrowser($agent),
                'device' => LoginSecurity::detectDevice($agent),
                'platform' => LoginSecurity::detectPlatform($agent),

                'is_success' => false,
                'is_suspicious' => $failedAttempts >= 5,
                'suspicious_reason' => $failedAttempts >= 5
                    ? 'Too many failed attempts from same IP'
                    : null,
                'failed_attempts_from_ip' => $failedAttempts,

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
