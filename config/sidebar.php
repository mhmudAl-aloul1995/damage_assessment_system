<?php

return [
    [
        'module' => 'damage_assessment',
        'title' => 'menu.hud.title',
        'icon' => 'ki-chart-pie-4',
        'variant' => 'hud',
        'url' => 'damage-assessment/damageAssessment/hud',
        'pattern' => 'damage-assessment/damageAssessment/hud*',
        'roles' => ['Database Officer', 'Project Officer', 'MOPWH', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager'],
        'active_patterns' => [
            'damage-assessment/damageAssessment/hud*',
        ],
    ],
    [
        'module' => 'damage_assessment',
        'title' => 'menu.damage_assessment.title',
        'icon' => 'ki-abstract-28',
        'roles' => ['Database Officer', 'Project Officer', 'MOPWH', 'undp-Project Manager', 'Team Leader', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer', 'Field Engineer'],
        'active_patterns' => [
            'damage-assessment/damageAssessment*',
            'damage-assessment/building*',
            'damage-assessment/housing*',
            'damage-assessment/engineer*',
            'damage-assessment/public-buildings*',
            'damage-assessment/road-facilities*',
            'damage-assessment/field-engineer/building-survey-return-requests*',
        ],
        'items' => [
            [
                'title' => 'menu.damage_assessment.dashboard',
                'url' => 'damage-assessment/damageAssessment',
                'pattern' => 'damage-assessment/damageAssessment*',
                'roles' => ['Database Officer', 'Project Officer', 'MOPWH', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor'],
            ],
            [
                'title' => 'menu.damage_assessment.assessments',
                'url' => 'damage-assessment/assessmentAll',
                'pattern' => 'damage-assessment/assessmentAll*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.building_survey_return_requests',
                'url' => 'damage-assessment/field-engineer/building-survey-return-requests',
                'pattern' => 'damage-assessment/field-engineer/building-survey-return-requests*',
                'roles' => ['Database Officer', 'Field Engineer', 'Team Leader', 'Team Leader', 'Area Manager'],
            ],
            [
                'title' => 'menu.damage_assessment.buildings',
                'url' => 'damage-assessment/building',
                'pattern' => 'damage-assessment/building*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.housing_units',
                'url' => 'damage-assessment/housing',
                'pattern' => 'damage-assessment/housing*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.public_buildings',
                'url' => 'damage-assessment/public-buildings',
                'pattern' => 'damage-assessment/public-buildings*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.road_facilities',
                'url' => 'damage-assessment/road-facilities',
                'pattern' => 'damage-assessment/road-facilities*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
            ],
            [
                'title' => 'menu.damage_assessment.engineers',
                'url' => 'damage-assessment/engineer',
                'pattern' => 'damage-assessment/engineer*',
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
        'roles' => ['Inf - QC/QA Engineer', 'undp-Project Manager', 'Project Officer', 'Team Leader -INF', 'Database Officer'],
        'active_patterns' => [
            'damage-assessment/inf-audit*',
        ],
        'items' => [
            [
                'title' => 'menu.inf_audit.public_buildings',
                'url' => 'damage-assessment/inf-audit/public-buildings',
                'pattern' => 'damage-assessment/inf-audit/public-buildings*',
                'roles' => ['Inf - QC/QA Engineer', 'Team Leader -INF', 'Database Officer'],
            ],
            [
                'title' => 'menu.inf_audit.roads',
                'url' => 'damage-assessment/inf-audit/roads',
                'pattern' => 'damage-assessment/inf-audit/roads*',
                'roles' => ['Inf - QC/QA Engineer', 'Team Leader -INF', 'Database Officer'],
            ],
        ],
    ],
    [
        'module' => 'damage_assessment',
        'title' => 'menu.reports.title',
        'icon' => 'ki-chart-line',
        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Team Leader', 'Area Manager', 'QC/QA Engineer', 'Auditing Supervisor'],
        'active_patterns' => [
            'damage-assessment/reports*',
            'damage-assessment/export-data*',
        ],
        'items' => [
            [
                'title' => 'menu.reports.area_productivity',
                'children' => [
                    [
                        'title' => 'menu.reports.productivity_items.housing_units',
                        'url' => 'damage-assessment/reports/area-productivity/housing-units',
                        'pattern' => 'damage-assessment/reports/area-productivity/housing-units*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.buildings',
                        'url' => 'damage-assessment/reports/area-productivity/buildings',
                        'pattern' => 'damage-assessment/reports/area-productivity/buildings*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.public_buildings',
                        'url' => 'damage-assessment/reports/area-productivity/public-buildings',
                        'pattern' => 'damage-assessment/reports/area-productivity/public-buildings*',
                        'roles' => ['Database Officer', 'Team Leader -INF', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.road_facilities',
                        'url' => 'damage-assessment/reports/area-productivity/road-facilities',
                        'pattern' => 'damage-assessment/reports/area-productivity/road-facilities*',
                        'roles' => ['Database Officer', 'Team Leader -INF', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.building_assessment',
                        'url' => 'damage-assessment/reports/building-productivity',
                        'pattern' => 'damage-assessment/reports/building-productivity*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Team Leader', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.productivity_items.engineers',
                        'url' => 'damage-assessment/reports/productivity',
                        'pattern' => 'damage-assessment/reports/productivity*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                ],
            ],
            [
                'title' => 'menu.reports.groups.operations',
                'children' => [
                    [
                        'title' => 'menu.reports.field_engineer',
                        'url' => 'damage-assessment/reports/field-engineer',
                        'pattern' => 'damage-assessment/reports/field-engineer*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Team Leader -INF', 'Team Leader', 'Auditing Supervisor'],
                    ],
                    [
                        'title' => 'menu.reports.daily_audit',
                        'url' => 'damage-assessment/reports/daily-achievement',
                        'pattern' => 'damage-assessment/reports/daily-achievement*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Auditing Supervisor'],
                    ],
                ],
            ],
            [
                'title' => 'menu.reports.groups.auditing',
                'children' => [
                    [
                        'title' => 'menu.reports.hlp',
                        'url' => 'damage-assessment/reports/hlp-audit',
                        'pattern' => 'damage-assessment/reports/hlp-audit*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Auditing Supervisor'],
                    ],
                    [
                        'title' => 'menu.reports.engineer_audit',
                        'url' => 'damage-assessment/reports/engineer-audit',
                        'pattern' => 'damage-assessment/reports/engineer-audit*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'],
                    ],
                ],
            ],
            [
                'title' => 'menu.reports.groups.surveys',
                'children' => [
                    [
                        'title' => 'menu.reports.public_buildings',
                        'url' => 'damage-assessment/reports/public-buildings',
                        'pattern' => 'damage-assessment/reports/public-buildings*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                    [
                        'title' => 'menu.reports.road_facilities',
                        'url' => 'damage-assessment/reports/road-facilities',
                        'pattern' => 'damage-assessment/reports/road-facilities*',
                        'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
                    ],
                ],
            ],
            [
                'title' => 'menu.reports.groups.exports',
                'children' => [
                    [
                        'title' => 'menu.reports.export_data',
                        'url' => 'damage-assessment/export-data',
                        'pattern' => 'damage-assessment/export-data*',
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
            'damage-assessment/audit*',
            'damage-assessment/area-manager-review*',
        ],
        'items' => [
            [
                'title' => 'menu.audit.dashboard',
                'url' => 'damage-assessment/audit/dashboard',
                'pattern' => 'damage-assessment/audit/dashboard',
                'roles' => ['Database Officer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager'],
            ],
            [
                'title' => 'menu.audit.home',
                'url' => 'damage-assessment/audit',
                'pattern' => 'damage-assessment/audit',
                'roles' => ['Database Officer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
            ],
            [
                'title' => 'menu.audit.building_audit',
                'url' => 'damage-assessment/auditBuilding',
                'pattern' => 'damage-assessment/auditBuilding',
                'roles' => ['Database Officer', 'Legal Auditor', 'QC/QA Engineer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager'],
            ],
            [
                'title' => 'menu.audit.area_manager_review',
                'url' => 'damage-assessment/area-manager-review',
                'pattern' => 'damage-assessment/area-manager-review*',
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
            'damage-assessment/attendance*',
        ],
        'items' => [
            [
                'title' => 'menu.attendance.records',
                'url' => 'damage-assessment/attendance',
                'pattern' => 'damage-assessment/attendance',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'],
            ],
            [
                'title' => 'menu.attendance.report',
                'url' => 'damage-assessment/attendance/dashboard',
                'pattern' => 'damage-assessment/attendance/dashboard',
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
            'damage-assessment/committee-decisions*',
            'damage-assessment/committee-members*',
        ],
        'items' => [
            [
                'title' => 'menu.committee.decisions',
                'url' => 'damage-assessment/committee-decisions',
                'pattern' => 'damage-assessment/committee-decisions*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Auditing Supervisor', 'QC/QA Engineer', 'Legal Auditor'],
            ],
            [
                'title' => 'menu.committee.members',
                'url' => 'damage-assessment/committee-members',
                'pattern' => 'damage-assessment/committee-members*',
                'roles' => ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Auditing Supervisor'],
            ],
        ],
    ],
    [
        'module' => 'damage_assessment_borrowers',
        'title' => 'استبيان المقترضين',
        'icon' => 'ki-profile-user',
        'url' => 'damage-assessment-borrowers',
        'pattern' => 'damage-assessment-borrowers*',
        'roles' => ['Database Officer'],
        'active_patterns' => [
            'damage-assessment-borrowers*',
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
