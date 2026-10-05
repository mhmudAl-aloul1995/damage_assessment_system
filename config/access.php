<?php

return [
    'role_profiles' => [
        'UNDP' => [
            'module' => 'damage_assessment',
            'read_only' => true,
            'allowed_permissions' => [
                'damage-assessments.view',
                'buildings.view',
                'housing-units.view',
                'audit.view',
                'audit.view-history',
                'committee-decisions.view',
                'committee-members.view',
                'view committee decisions',
                'inf-audit.public-buildings.view',
                'inf-audit.roads.view',
                'inf-audit.cso.view',
                'cso-surveys.view',
                'reports.view',
                'reports.damage-statistics.view',
                'reports.productivity.view',
                'reports.area-productivity.view',
                'reports.field-engineer.view',
                'reports.daily-achievement.view',
                'reports.hlp-audit.view',
                'reports.public-buildings.view',
                'reports.road-facilities.view',
                'reports.area-productivity.cso-surveys.view',
                'attendance.view',
                'attendance.reports.view',
                'building-survey-return-requests.view',
                'damage-assessment.building-deletion.view',
            ],
        ],
    ],
];
