<?php

return [
    'timezone' => env('HR_TIMEZONE', 'Asia/Hebron'),
    'web_require_https' => (bool) env('HR_WEB_REQUIRE_HTTPS', false),
    'native_require_https' => (bool) env('HR_NATIVE_REQUIRE_HTTPS', false),
    'attendance_enabled' => (bool) env('HR_ATTENDANCE_ENABLED', true),
    'gateway_url' => env('HR_ATTENDANCE_GATEWAY_URL'),
    'network_mode' => env('HR_ATTENDANCE_NETWORK_MODE', 'unrestricted'),
    'allowed_networks' => array_values(array_filter(explode(',', (string) env('HR_ALLOWED_NETWORKS', '')))),
    'device_approval_required' => true,
    'export_ttl_hours' => (int) env('HR_EXPORT_TTL_HOURS', 24),
    'mikrotik' => [
        'enabled' => (bool) env('HR_MIKROTIK_ENABLED', false),
        'host' => env('HR_MIKROTIK_HOST'),
        'port' => env('HR_MIKROTIK_PORT'),
        'username' => env('HR_MIKROTIK_USERNAME'),
        'password' => env('HR_MIKROTIK_PASSWORD'),
    ],
];
