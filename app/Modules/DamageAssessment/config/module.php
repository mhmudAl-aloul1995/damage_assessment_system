<?php

return [
    'provider' => App\Modules\DamageAssessment\Providers\DamageAssessmentServiceProvider::class,
    'title' => 'menu.modules.damage_assessment',
    'short_title' => 'menu.damage_assessment.title',
    'description' => 'menu.module_switcher.descriptions.damage_assessment',
    'icon' => 'ki-map',
    'active_patterns' => ['damage-assessment', 'damage-assessment/*', 'Attendance/*'],
    'central' => false,
    'enabled' => true,
    'order' => 10,
];
