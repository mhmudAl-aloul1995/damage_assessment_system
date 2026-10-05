<?php

return [
    'damage_assessment' => require __DIR__.'/../app/Modules/DamageAssessment/config/module.php',
    'damage_assessment_borrowers' => require __DIR__.'/../app/Modules/DamageAssessmentBorrowers/config/module.php',
    'heks' => require __DIR__.'/../app/Modules/Heks/config/module.php',
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
