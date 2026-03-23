<?php

return [
    //        'roles' => ['Administrator','General Supervisor','Team Leader', 'Auditing Supervisor', 'Legal Auditor', 'Engineering Auditor','Field Engineer'],

    [
        'title' => 'حصر الأضرار',
        'icon' => 'ki-abstract-28',

        'roles' => ['Administrator', 'General Supervisor', 'Team Leader', 'Auditing Supervisor'],

        'active_patterns' => [
            'damageAssessment*',
            'building*',
            'housing*',
            'engineer*',
        ],

        'items' => [

            [
                'title' => 'الرئيسية',
                'url' => 'damageAssessment',
                'pattern' => 'damageAssessment*',
                'roles' => ['Administrator', 'General Supervisor', 'Team Leader', 'Auditing Supervisor']
            ],

            [
                'title' => 'المباني',
                'url' => 'building',
                'pattern' => 'building*',
                'roles' => ['Administrator', 'General Supervisor', 'Team Leader', 'Auditing Supervisor']
            ],

            [
                'title' => 'الوحدات السكنية',
                'url' => 'housing',
                'pattern' => 'housing*',
                'roles' => ['Administrator', 'General Supervisor', 'Team Leader', 'Auditing Supervisor']
            ],

            [
                'title' => 'الباحثين',
                'url' => 'engineer',
                'pattern' => 'engineer*',
                'roles' => ['Administrator', 'General Supervisor', 'Team Leader', 'Auditing Supervisor']
            ],

        ],


    ],
    [
        'title' => 'التقارير',
        'icon' => 'ki-chart-line',

        'roles' => ['Administrator', 'General Supervisor', 'Team Leader'],

        'active_patterns' => [
            'reports*'
        ],

        'items' => [

            [
                'title' => 'إنتاجية المناطق',
                'url' => 'reports/commulative',
                'pattern' => 'reports/commulative*',
                'roles' => ['Administrator', 'General Supervisor', 'Team Leader']
            ],

            [
                'title' => 'إنتاجية المهندسين',
                'url' => 'reports/productivity',
                'pattern' => 'reports/productivity*',
                'roles' => ['Administrator', 'General Supervisor', 'Team Leader']
            ],

        ]

    ],

    [
        'title' => 'التدقيق',
        'icon' => 'ki-medal-star',

        'roles' => ['Administrator', 'Legal Auditor', 'Engineering Auditor', 'Auditing Supervisor', 'General Supervisor'],

        'active_patterns' => [
            'audit*'
        ],

        'items' => [

            [
                'title' => 'الرئيسية',
                'url' => 'audit',
                'pattern' => 'audit',
                'roles' => ['Administrator','Auditing Supervisor', 'General Supervisor'],
            ],
            [
                'title' => 'تدقيق المبنى',
                'url' => 'auditBuilding',
                'pattern' => 'auditBuilding',
                'roles' => ['Administrator', 'Legal Auditor', 'Engineering Auditor', 'Auditing Supervisor', 'General Supervisor'],
            ],

        ]

    ],
    [
        'title' => 'إدارة المستخدمين',
        'icon' => 'ki-user',

        'roles' => ['Administrator'],

        'active_patterns' => [
            'user*'
        ],

        'items' => [

            [
                'title' => 'المستخدمين',
                'url' => 'user-management/user',
                'pattern' => 'user',
                'roles' => ['Administrator']
            ],
            [
                'title' => 'الأدوار',
                'url' => 'user-management/roles',
                'pattern' => 'user',
                'roles' => ['Administrator']
            ],
            [
                'title' => 'الصلاحيات',
                'url' => 'user-management/permissions',
                'pattern' => 'user',
                'roles' => ['Administrator']
            ],

        ]

    ],
];
