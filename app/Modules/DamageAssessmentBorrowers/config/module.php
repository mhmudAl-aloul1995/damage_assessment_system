<?php

return [
    'provider' => App\Modules\DamageAssessmentBorrowers\Providers\DamageAssessmentBorrowersServiceProvider::class,
    'title' => 'مقترضو بنك التنمية الإسلامي',
    'short_title' => 'menu.module_switcher.borrowers',
    'description' => 'menu.module_switcher.descriptions.borrowers',
    'icon' => 'ki-profile-user',
    'active_patterns' => ['damage-assessment-borrowers', 'damage-assessment-borrowers/*'],
    'central' => false,
    'enabled' => true,
    'order' => 20,
];
