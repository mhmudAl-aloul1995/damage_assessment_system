<?php

return [
    ...require __DIR__.'/../app/Modules/DamageAssessment/config/sidebar.php',
    ...require __DIR__.'/../app/Modules/DamageAssessmentBorrowers/config/sidebar.php',
    ...require __DIR__.'/../app/Modules/Heks/config/sidebar.php',
    [
        'module' => 'administration',
        'title' => 'menu.user_management.title',
        'icon' => 'ki-user',
        'roles' => ['Database Officer'],
        'active_patterns' => [
            'user*',
            'admin/team-leader-field-engineers*',
            'admin/dashboard-cards*',
            'admin/local-database-import*',
            'admin/artisan-commands*',
            'login-logs*',
            'user-activity-logs*',
        ],
        'items' => [
            [
                'title' => 'menu.user_management.users',
                'url' => 'user-management/user',
                'permissions' => ['users.view'],
                'pattern' => 'user',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'menu.user_management.attendance',
                'url' => 'Attendance/attendance',
                'pattern' => 'attendance',
                'roles' => ['Database Officer', 'Area Manager'],
            ],
            [
                'title' => 'menu.user_management.roles',
                'url' => 'user-management/roles',
                'permissions' => ['roles.view'],
                'pattern' => 'user',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'menu.user_management.permissions',
                'url' => 'user-management/permissions',
                'permissions' => ['permissions.view'],
                'pattern' => 'user',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'menu.user_management.team_leader_field_engineers',
                'url' => 'admin/team-leader-field-engineers',
                'pattern' => 'admin/team-leader-field-engineers*',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'إدارة بطاقات لوحة التحكم',
                'url' => 'admin/dashboard-cards',
                'pattern' => 'admin/dashboard-cards*',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'Login Logs',
                'url' => 'login-logs',
                'pattern' => 'login-logs*',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'Activity Logs',
                'url' => 'user-activity-logs',
                'pattern' => 'user-activity-logs*',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'Local DB Import',
                'url' => 'admin/local-database-import',
                'pattern' => 'admin/local-database-import*',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'menu.user_management.artisan_commands',
                'url' => 'admin/artisan-commands',
                'pattern' => 'admin/artisan-commands*',
                'roles' => ['Database Officer'],
            ],
        ],
    ],
];
