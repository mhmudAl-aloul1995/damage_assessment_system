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
], [
    'module' => 'hr', 'title' => 'الموظفون والتاريخ الوظيفي', 'icon' => 'ki-people', 'url' => 'hr/employees',
    'permissions' => ['hr.employees.view', 'hr.employees.view-own', 'hr.employees.view-team'],
    'active_patterns' => ['hr/employees*', 'hr/employee-assignments*', 'hr/employee-documents*'],
], [
    'module' => 'hr', 'title' => 'الهيكل التنظيمي', 'icon' => 'ki-chart', 'url' => 'hr/organization/departments',
    'permissions' => ['hr.organization.manage'], 'active_patterns' => ['hr/organization*', 'hr/department-scopes*'],
], [
    'module' => 'hr', 'title' => 'العقود وتقارير الانتهاء', 'icon' => 'ki-document', 'url' => 'hr/contracts',
    'permissions' => ['hr.contracts.view', 'hr.contracts.view-own', 'hr.contracts.view-team'],
    'active_patterns' => ['hr/contracts*', 'hr/contract-revisions*'],
]];
