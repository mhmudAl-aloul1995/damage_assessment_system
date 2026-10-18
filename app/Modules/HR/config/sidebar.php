<?php

return [[
    'module' => 'hr',
    'title' => 'الرئيسية',
    'icon' => 'ki-home',
    'url' => 'hr',
    'roles' => ['Database Officer'],
    'permissions' => ['hr.attendance.view', 'hr.attendance.view-own', 'hr.attendance.view-team'],
    'active_patterns' => ['hr'],
], [
    'module' => 'hr',
    'title' => 'الحضور والانصراف',
    'icon' => 'ki-time',
    'url' => 'hr/attendance',
    'pattern' => 'hr/attendance*',
    'roles' => ['Database Officer'],
    'permissions' => ['hr.attendance.view', 'hr.attendance.view-own', 'hr.attendance.view-team'],
    'active_patterns' => ['hr/attendance*'],
    'exclude_active_patterns' => ['hr/attendance/projects*'],
], [
    'module' => 'hr',
    'title' => 'حضور موظفي المشاريع',
    'icon' => 'ki-people',
    'url' => 'hr/attendance/projects',
    'roles' => ['Database Officer'],
    'permissions' => ['hr.attendance.setup'],
    'active_patterns' => ['hr/attendance/projects*'],
]];
