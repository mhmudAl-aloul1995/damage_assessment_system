<?php

return [
    [
        'module' => 'damage_assessment',
        'title' => 'menu.hud.title',
        'icon' => 'ki-chart-pie-4',
        'url' => 'damageAssessment/hud',
        'pattern' => 'damageAssessment/hud*',
        'roles' => ['Database Officer', 'Project Officer', 'MOPWH', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager'],
        'active_patterns' => [
            'damageAssessment/hud*',
        ],
    ],
    [
        'module' => 'damage_assessment',
        'title' => 'menu.damage_assessment.title',
        'icon' => 'ki-abstract-28',
        'roles' => ['Database Officer', 'Project Officer', 'MOPWH', 'undp-Project Manager', 'Team Leader', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer', 'Field Engineer'],
        'active_patterns' => [
            'damageAssessment*',
            'building*',
            'housing*',
            'engineer*',
            'public-buildings*',
            'road-facilities*',
            'field-engineer/building-survey-return-requests*',
        ],
        'items' => [
            [
                'title' => 'menu.damage_assessment.dashboard',
                'url' => 'damageAssessment',
                'pattern' => 'damageAssessment*',
                'roles' => ['Database Officer', 'Project Officer', 'MOPWH', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor'],
            ],
            [
                'title' => 'menu.damage_assessment.assessments',
                'url' => 'assessmentAll',
                'pattern' => 'assessmentAll*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.building_survey_return_requests',
                'url' => 'field-engineer/building-survey-return-requests',
                'pattern' => 'field-engineer/building-survey-return-requests*',
                'roles' => ['Database Officer', 'Field Engineer', 'Team Leader', 'Team Leader', 'Area Manager'],
            ],
            [
                'title' => 'menu.damage_assessment.buildings',
                'url' => 'building',
                'pattern' => 'building*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.housing_units',
                'url' => 'housing',
                'pattern' => 'housing*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.public_buildings',
                'url' => 'public-buildings',
                'pattern' => 'public-buildings*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.road_facilities',
                'url' => 'road-facilities',
                'pattern' => 'road-facilities*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.engineers',
                'url' => 'engineer',
                'pattern' => 'engineer*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor'],
            ],
            [
                'title' => 'menu.damage_assessment.team_leader_field_engineers',
                'url' => 'admin/team-leader-field-engineers',
                'pattern' => 'admin/team-leader-field-engineers*',
                'roles' => [
                    'Database Officer',
                    'Team Leader',
                    'Team Leader',
                    'Area Manager',
                ],
            ],
        ],
    ],
    [
        'module' => 'damage_assessment',
        'title' => 'menu.inf_audit.title',
        'icon' => 'ki-shield-tick',
        'roles' => ['Inf - QC/QA Engineer', 'Team Leader -INF', 'Database Officer'],
        'active_patterns' => [
            'inf-audit*',
        ],
        'items' => [
            [
                'title' => 'menu.inf_audit.public_buildings',
                'url' => 'inf-audit/public-buildings',
                'pattern' => 'inf-audit/public-buildings*',
                'roles' => ['Inf - QC/QA Engineer', 'Team Leader -INF', 'Database Officer'],
            ],
            [
                'title' => 'menu.inf_audit.roads',
                'url' => 'inf-audit/roads',
                'pattern' => 'inf-audit/roads*',
                'roles' => ['Inf - QC/QA Engineer', 'Team Leader -INF', 'Database Officer'],
            ],
        ],
    ],
    [
        'module' => 'damage_assessment',
        'title' => 'menu.reports.title',
        'icon' => 'ki-chart-line',
        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Team Leader', 'Area Manager', 'QC/QA Engineer'],
        'active_patterns' => [
            'reports*',
            'export-data*',
        ],
        'items' => [
            [
                'title' => 'menu.reports.area_productivity',
                'children' => [
                    [
                        'title' => 'menu.reports.productivity_items.housing_units',
                        'url' => 'reports/area-productivity/housing-units',
                        'pattern' => 'reports/area-productivity/housing-units*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.buildings',
                        'url' => 'reports/area-productivity/buildings',
                        'pattern' => 'reports/area-productivity/buildings*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.public_buildings',
                        'url' => 'reports/area-productivity/public-buildings',
                        'pattern' => 'reports/area-productivity/public-buildings*',
                        'roles' => ['Database Officer', 'Team Leader -INF', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.road_facilities',
                        'url' => 'reports/area-productivity/road-facilities',
                        'pattern' => 'reports/area-productivity/road-facilities*',
                        'roles' => ['Database Officer', 'Team Leader -INF', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.building_assessment',
                        'url' => 'reports/building-productivity',
                        'pattern' => 'reports/building-productivity*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Team Leader', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.engineers',
                        'url' => 'reports/productivity',
                        'pattern' => 'reports/productivity*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                ],
            ],
            [
                'title' => 'menu.reports.groups.operations',
                'children' => [
                    [
                        'title' => 'menu.reports.field_engineer',
                        'url' => 'reports/field-engineer',
                        'pattern' => 'reports/field-engineer*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Team Leader -INF', 'Team Leader', 'Auditing Supervisor'],
                    ],
                    [
                        'title' => 'menu.reports.daily_audit',
                        'url' => 'reports/daily-achievement',
                        'pattern' => 'reports/daily-achievement*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Auditing Supervisor'],
                    ],
                ],
            ],
            [
                'title' => 'menu.reports.groups.auditing',
                'children' => [
                    [
                        'title' => 'menu.reports.hlp',
                        'url' => 'reports/hlp-audit',
                        'pattern' => 'reports/hlp-audit*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Auditing Supervisor'],
                    ],
                ],
            ],
            [
                'title' => 'menu.reports.groups.surveys',
                'children' => [
                    [
                        'title' => 'menu.reports.public_buildings',
                        'url' => 'reports/public-buildings',
                        'pattern' => 'reports/public-buildings*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.road_facilities',
                        'url' => 'reports/road-facilities',
                        'pattern' => 'reports/road-facilities*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                ],
            ],
            [
                'title' => 'menu.reports.groups.exports',
                'children' => [
                    [
                        'title' => 'menu.reports.export_data',
                        'url' => 'export-data',
                        'pattern' => 'export-data*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'QC/QA Engineer', 'Team Leader -INF', 'Team Leader', 'Area Manager'],
                    ],
                ],
            ],
        ],
    ],
    [
        'module' => 'damage_assessment',
        'title' => 'menu.audit.title',
        'icon' => 'ki-medal-star',
        'roles' => ['Database Officer', 'Legal Auditor', 'QC/QA Engineer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
        'active_patterns' => [
            'audit*',
            'area-manager-review*',
        ],
        'items' => [
            [
                'title' => 'menu.audit.dashboard',
                'url' => 'audit/dashboard',
                'pattern' => 'audit/dashboard',
                'roles' => ['Database Officer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager'],
            ],
            [
                'title' => 'menu.audit.home',
                'url' => 'audit',
                'pattern' => 'audit',
                'roles' => ['Database Officer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
            ],
            [
                'title' => 'menu.audit.building_audit',
                'url' => 'auditBuilding',
                'pattern' => 'auditBuilding',
                'roles' => ['Database Officer', 'Legal Auditor', 'QC/QA Engineer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager'],
            ],
            [
                'title' => 'menu.audit.area_manager_review',
                'url' => 'area-manager-review',
                'pattern' => 'area-manager-review*',
                'roles' => ['Area Manager', 'Database Officer'],
            ],
        ],
    ],
    [
        'module' => 'damage_assessment',
        'title' => 'menu.attendance.title',
        'icon' => 'ki-calendar-8',
        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
        'active_patterns' => [
            'attendance*',
        ],
        'items' => [
            [
                'title' => 'menu.attendance.records',
                'url' => 'attendance',
                'pattern' => 'attendance',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
            ],
            [
                'title' => 'menu.attendance.report',
                'url' => 'attendance/dashboard',
                'pattern' => 'attendance/dashboard',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
            ],
        ],
    ],
    [
        'module' => 'damage_assessment',
        'title' => 'menu.committee.title',
        'icon' => 'ki-shield-search',
        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Auditing Supervisor', 'QC/QA Engineer', 'Legal Auditor'],
        'active_patterns' => [
            'committee-decisions*',
            'committee-members*',
        ],
        'items' => [
            [
                'title' => 'menu.committee.decisions',
                'url' => 'committee-decisions',
                'pattern' => 'committee-decisions*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Auditing Supervisor', 'QC/QA Engineer', 'Legal Auditor'],
            ],
            [
                'title' => 'menu.committee.members',
                'url' => 'committee-members',
                'pattern' => 'committee-members*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Auditing Supervisor'],
            ],
        ],
    ],
    [
        'module' => 'administration',
        'title' => 'menu.user_management.title',
        'icon' => 'ki-user',
        'roles' => ['Database Officer'],
        'active_patterns' => [
            'user*',
            'admin/team-leader-field-engineers*',
            'admin/local-database-import*',
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
            [
                'title' => 'menu.user_management.team_leader_field_engineers',
                'url' => 'admin/team-leader-field-engineers',
                'pattern' => 'admin/team-leader-field-engineers*',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'Login Logs',
                'url' => 'login-logs',
                'pattern' => 'login-logs*',
                'roles' => ['Database Officer'],
            ],
            [
                'title' => 'Local DB Import',
                'url' => 'admin/local-database-import',
                'pattern' => 'admin/local-database-import*',
                'roles' => ['Database Officer'],
            ],
        ],
    ],
];
