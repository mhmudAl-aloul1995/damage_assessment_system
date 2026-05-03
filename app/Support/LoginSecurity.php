<?php

namespace App\Support;

use App\Models\LoginLog;

class LoginSecurity
{
    public static function detectBrowser(?string $agent): string
    {
        $agent = strtolower($agent ?? '');

        return match (true) {
            str_contains($agent, 'edg') => 'Edge',
            str_contains($agent, 'chrome') => 'Chrome',
            str_contains($agent, 'firefox') => 'Firefox',
            str_contains($agent, 'safari') => 'Safari',
            default => 'Unknown',
        };
    }

    public static function detectDevice(?string $agent): string
    {
        $agent = strtolower($agent ?? '');

        return str_contains($agent, 'mobile')
            ? 'Mobile'
            : 'Desktop';
    }

    public static function detectPlatform(?string $agent): string
    {
        $agent = strtolower($agent ?? '');

        return match (true) {
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'iphone') => 'iPhone',
            str_contains($agent, 'mac') => 'Mac',
            default => 'Unknown',
        };
    }

    public static function failedAttemptsFromIp(?string $ip): int
    {
        if (! $ip) {
            return 0;
        }

        return LoginLog::where('ip_address', $ip)
            ->where('is_success', false)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->count();
    }

    public static function isNewIpForUser(int $userId, ?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        return ! LoginLog::where('user_id', $userId)
            ->where('ip_address', $ip)
            ->where('is_success', true)
            ->exists();
    }
}