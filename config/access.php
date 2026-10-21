<?php

$rehPermissions = ['reh.view', 'reh.review', 'reh.review.engineering', 'reh.review.social', 'reh.decide', 'reh.scores.view', 'reh.sensitive', 'reh.sync', 'reh.export', 'reh.pricing.view', 'reh.pricing.manage', 'reh.pricing.approve'];
$rehRoles = [
    'REH Viewer' => ['viewer', ['reh.view']],
    'مدقق هندسي REH' => ['engineering', ['reh.view', 'reh.review', 'reh.review.engineering', 'reh.pricing.view', 'reh.pricing.manage']],
    'مدقق اجتماعي REH' => ['social', ['reh.view', 'reh.review', 'reh.review.social', 'reh.sensitive', 'reh.scores.view']],
    'REH Project Officer' => ['decision', ['reh.view', 'reh.scores.view', 'reh.decide', 'reh.pricing.view', 'reh.pricing.approve']],
    'REH Module Manager' => ['manager', $rehPermissions],
];
$rehProfiles = [];
foreach ($rehRoles as $name => [$key, $permissions]) {
    $rehProfiles[$name] = [
        'module' => 'reh',
        'read_only' => $key === 'viewer',
        'allowed_permissions' => $key === 'viewer' ? ['reh.view', 'reh.scores.view'] : $rehPermissions,
        'default_permissions' => $permissions,
        'description_key' => 'access.reh_roles.'.$key,
        'notice_key' => 'access.reh_role_profile_notice',
    ];
}

return [
    'reh_permissions' => $rehPermissions,
    'role_profiles' => [
        ...$rehProfiles,
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
