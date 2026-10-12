<?php

return [
    'timezone' => env('HR_TIMEZONE', 'Asia/Hebron'),
    'attendance_enabled' => (bool) env('HR_ATTENDANCE_ENABLED', false),
    'gateway_url' => env('HR_ATTENDANCE_GATEWAY_URL'),
    'network_mode' => env('HR_ATTENDANCE_NETWORK_MODE', 'disabled'),
    'allowed_networks' => array_values(array_filter(explode(',', (string) env('HR_ALLOWED_NETWORKS', '')))),
    'device_approval_required' => true,
    'mikrotik' => [
        'enabled' => (bool) env('HR_MIKROTIK_ENABLED', false),
        'host' => env('HR_MIKROTIK_HOST'),
        'port' => env('HR_MIKROTIK_PORT'),
        'username' => env('HR_MIKROTIK_USERNAME'),
        'password' => env('HR_MIKROTIK_PASSWORD'),
    ],
];
