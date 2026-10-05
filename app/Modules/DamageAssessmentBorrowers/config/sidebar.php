<?php

return [
    [
        'module' => 'damage_assessment_borrowers',
        'title' => 'استبيان المقترضين',
        'icon' => 'ki-profile-user',
        'url' => 'damage-assessment-borrowers',
        'pattern' => 'damage-assessment-borrowers*',
        'roles' => ['Database Officer', 'Project Officer - Borrowers'],
        'active_patterns' => [
            'damage-assessment-borrowers*',
        ],
    ],
];
