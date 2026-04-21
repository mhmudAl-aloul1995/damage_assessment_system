<?php

return [
    [
        'title' => 'menu.damage_assessment.title',
        'icon' => 'ki-abstract-28',
        'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
        'active_patterns' => [
            'damageAssessment*',
            'building*',
            'housing*',
            'engineer*',
            'public-buildings*',
            'road-facilities*',
        ],
        'items' => [
            [
                'title' => 'menu.damage_assessment.dashboard',
                'url' => 'damageAssessment',
                'pattern' => 'damageAssessment*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor'],
            ],
            [
                'title' => 'menu.damage_assessment.assessments',
                'url' => 'assessmentAll',
                'pattern' => 'assessmentAll*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.buildings',
                'url' => 'building',
                'pattern' => 'building*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.housing_units',
                'url' => 'housing',
                'pattern' => 'housing*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.public_buildings',
                'url' => 'public-buildings',
                'pattern' => 'public-buildings*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.road_facilities',
                'url' => 'road-facilities',
                'pattern' => 'road-facilities*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.engineers',
                'url' => 'engineer',
                'pattern' => 'engineer*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Area Manager', 'Auditing Supervisor'],
            ],
        ],
    ],
    [
        'title' => 'menu.reports.title',
        'icon' => 'ki-chart-line',
        'roles' => ['Database Officer', 'Project Officer', 'Area Manager', 'QC/QA Engineer'],
        'active_patterns' => [
            'reports*',
        ],
        'items' => [
            [
                'title' => 'menu.reports.area_productivity',
                'url' => 'reports/commulative',
                'pattern' => 'reports/commulative*',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],
            ],
            [
                'title' => 'menu.reports.engineer_productivity',
                'url' => 'reports/productivity',
                'pattern' => 'reports/productivity*',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],
            ],
            [
                'title' => 'menu.reports.daily_audit',
                'url' => 'reports/daily-achievement',
                'pattern' => 'reports/daily-achievement*',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager', 'Auditing Supervisor'],
            ],
            [
                'title' => 'menu.reports.public_buildings',
                'url' => 'reports/public-buildings',
                'pattern' => 'reports/public-buildings*',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],
            ],
            [
                'title' => 'menu.reports.road_facilities',
                'url' => 'reports/road-facilities',
                'pattern' => 'reports/road-facilities*',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],
            ],
            [
                'title' => 'menu.reports.export_data',
                'url' => 'export-data',
                'pattern' => 'export-data*',
                'roles' => ['Database Officer', 'Project Officer', 'QC/QA Engineer', 'Area Manager'],
            ],
        ],
    ],
    [
        'title' => 'menu.audit.title',
        'icon' => 'ki-medal-star',
        'roles' => ['Database Officer', 'Legal Auditor', 'QC/QA Engineer', 'Auditing Supervisor', 'Project Officer'],
        'active_patterns' => [
            'audit*',
        ],
        'items' => [
            [
                'title' => 'menu.audit.dashboard',
                'url' => 'audit/dashboard',
                'pattern' => 'audit/dashboard',
                'roles' => ['Database Officer', 'Auditing Supervisor', 'Project Officer'],
            ],
            [
                'title' => 'menu.audit.home',
                'url' => 'audit',
                'pattern' => 'audit',
                'roles' => ['Database Officer', 'Auditing Supervisor', 'Project Officer'],
            ],
            [
                'title' => 'menu.audit.building_audit',
                'url' => 'auditBuilding',
                'pattern' => 'auditBuilding',
                'roles' => ['Database Officer', 'Legal Auditor', 'QC/QA Engineer', 'Auditing Supervisor', 'Project Officer'],
            ],
        ],
    ],
    [
        'title' => 'menu.attendance.title',
        'icon' => 'ki-calendar-8',
        'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],
        'active_patterns' => [
            'attendance*',
        ],
        'items' => [
            [
                'title' => 'menu.attendance.records',
                'url' => 'attendance',
                'pattern' => 'attendance',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],
            ],
            [
                'title' => 'menu.attendance.report',
                'url' => 'attendance/dashboard',
                'pattern' => 'attendance/dashboard',
                'roles' => ['Database Officer', 'Project Officer', 'Area Manager'],
            ],
        ],
    ],
    [
        'title' => 'menu.committee.title',
        'icon' => 'ki-shield-search',
        'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Auditing Supervisor', 'QC/QA Engineer', 'Legal Auditor'],
        'active_patterns' => [
            'committee-decisions*',
            'committee-members*',
        ],
        'items' => [
            [
                'title' => 'menu.committee.decisions',
                'url' => 'committee-decisions',
                'pattern' => 'committee-decisions*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Auditing Supervisor', 'QC/QA Engineer', 'Legal Auditor'],
            ],
            [
                'title' => 'menu.committee.members',
                'url' => 'committee-members',
                'pattern' => 'committee-members*',
                'roles' => ['Database Officer', 'Project Officer', 'Team Leader', 'Auditing Supervisor'],
            ],
        ],
    ],
    [
        'title' => 'menu.user_management.title',
        'icon' => 'ki-user',
        'roles' => ['Database Officer'],
        'active_patterns' => [
            'user*',
        ],
        'items' => [
            [
                'title' => 'menu.user_management.users',
                'url' => 'user-management/user',
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
                'pattern' => 'user',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'menu.user_management.permissions',
                'url' => 'user-management/permissions',
                'pattern' => 'user',
                'roles' => ['Database Officer'],
            ],
        ],
    ],
];
