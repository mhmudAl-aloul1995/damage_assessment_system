<?php

return [
    'provider' => App\Modules\HR\Providers\HRServiceProvider::class,
    'title' => 'الموارد البشرية',
    'description' => 'الحضور والموظفون والهيكل الإداري',
    'icon' => 'ki-people',
    'active_patterns' => ['hr', 'hr/*'],
    'central' => false,
    'enabled' => true,
    'order' => 40,
];
