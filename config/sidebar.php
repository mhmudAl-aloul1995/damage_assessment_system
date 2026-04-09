<?php

return [
    //        'roles' => ['Database Officer','Project Officer','Team Leader', 'Auditing Supervisor', 'Legal Auditor', 'QC/QA Engineer','Field Engineer'],

    [
        'title' => 'حصر الأضرار',
        'icon' => 'ki-abstract-28',

        'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor','QC/QA Engineer'],

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
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor'],
            ],

            [
                'title' => 'الإستبيانات',
                'url' => 'assessmentAll',
                'pattern' => 'assessmentAll*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor','QC/QA Engineer'],
            ],
            [
                'title' => 'المباني',
                'url' => 'building',
                'pattern' => 'building*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor','QC/QA Engineer'],
            ],

            [
                'title' => 'الوحدات السكنية',
                'url' => 'housing',
                'pattern' => 'housing*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor','QC/QA Engineer'],
            ],

            [
                'title' => 'الباحثين',
                'url' => 'engineer',
                'pattern' => 'engineer*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor'],
            ],

        ],


    ],
    [
        'title' => 'التقارير',
        'icon' => 'ki-chart-line',

        'roles' => ['Database Officer', 'Project Officer', 'Area Manager','QC/QA Engineer'],

        'active_patterns' => [
            'reports*'
        ],

        'items' => [

            [
                'title' => 'إنتاجية المناطق',
                'url' => 'reports/commulative',
                'pattern' => 'reports/commulative*',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager']
            ],

            [
                'title' => 'إنتاجية المهندسين',
                'url' => 'reports/productivity',
                'pattern' => 'reports/productivity*',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager']
            ],
             [
                'title' => 'تصدير البيانات',
                'url' => 'export-data',
                'pattern' => 'export-data*',
                'roles' => ['Database Officer', 'Project Officer','QC/QA Engineer']
            ],

        ]

    ],

    [
        'title' => 'التدقيق',
        'icon' => 'ki-medal-star',

        'roles' => ['Database Officer', 'Legal Auditor', 'QC/QA Engineer', 'Auditing Supervisor', 'Project Officer'],

        'active_patterns' => [
            'audit*'
        ],

        'items' => [

            [
                'title' => 'الرئيسية',
                'url' => 'audit',
                'pattern' => 'audit',
                'roles' => ['Database Officer', 'Auditing Supervisor', 'Project Officer'],
            ],
            [
                'title' => 'تدقيق المبنى',
                'url' => 'auditBuilding',
                'pattern' => 'auditBuilding',
                'roles' => ['Database Officer', 'Legal Auditor', 'QC/QA Engineer', 'Auditing Supervisor', 'Project Officer'],
            ],

        ]

    ],
    [
        'title' => 'الحضور والغياب',
        'icon' => 'ki-calendar-8',

        'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],

        'active_patterns' => [
            'attendance*'
        ],

        'items' => [


            [
                'title' => 'حضور/غياب',
                'url' => 'attendance',
                'pattern' => 'attendance',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],
            ],
            [
                'title' => 'التقرير',
                'url' => 'attendance/dashboard',
                'pattern' => 'attendance/dashboard',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],
            ],

        ]

    ],
    [
        'title' => 'إدارة المستخدمين',
        'icon' => 'ki-user',

        'roles' => ['Database Officer'],

        'active_patterns' => [
            'user*'
        ],

        'items' => [

            [
                'title' => 'المستخدمين',
                'url' => 'user-management/user',
                'pattern' => 'user',
                'roles' => ['Database Officer']
            ],
            [
                'title' => 'حضور/غياب',
                'url' => 'Attendance/attendance',
                'pattern' => 'attendance',
                'roles' => ['Database Officer', 'Area Manager']
            ],
            [
                'title' => 'الأدوار',
                'url' => 'user-management/roles',
                'pattern' => 'user',
                'roles' => ['Database Officer']
            ],
            [
                'title' => 'الصلاحيات',
                'url' => 'user-management/permissions',
                'pattern' => 'user',
                'roles' => ['Database Officer']
            ],

        ]

    ],


];
