<?php

return [[
    'module' => 'hr',
    'title' => 'الحضور والانصراف',
    'icon' => 'ki-time',
    'url' => 'hr/attendance',
    'pattern' => 'hr*',
    'roles' => ['Database Officer'],
    'permissions' => ['hr.attendance.view', 'hr.attendance.view-own'],
    'active_patterns' => ['hr*'],
]];
