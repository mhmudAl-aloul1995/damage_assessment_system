<?php

return [
    'damage_assessment' => [
        'title' => 'menu.modules.damage_assessment',
        'short_title' => 'menu.damage_assessment.title',
        'description' => 'menu.module_switcher.descriptions.damage_assessment',
        'icon' => 'ki-map',
        'active_patterns' => ['damage-assessment', 'damage-assessment/*', 'Attendance/*'],
        'central' => false,
        'enabled' => true,
        'order' => 10,
    ],
    'damage_assessment_borrowers' => [
        'title' => 'مقترضو بنك التنمية الإسلامي',
        'short_title' => 'menu.module_switcher.borrowers',
        'description' => 'menu.module_switcher.descriptions.borrowers',
        'icon' => 'ki-profile-user',
        'active_patterns' => ['damage-assessment-borrowers', 'damage-assessment-borrowers/*'],
        'central' => false,
        'enabled' => true,
        'order' => 20,
    ],
    'heks' => [
        'title' => 'المساعدة النقدية لإصلاح المأوى الطارئ (HEKS)',
        'short_title' => 'menu.module_switcher.heks',
        'description' => 'menu.module_switcher.descriptions.heks',
        'icon' => 'ki-home-2',
        'active_patterns' => ['heks', 'heks/*'],
        'central' => false,
        'enabled' => true,
        'order' => 30,
    ],
    'administration' => [
        'title' => 'menu.modules.administration',
        'description' => 'menu.module_switcher.descriptions.administration',
        'icon' => 'ki-setting-2',
        'active_patterns' => ['user-management/*', 'admin/*', 'login-logs*', 'user-activity-logs*'],
        'central' => true,
        'enabled' => true,
        'order' => 90,
    ],
];
