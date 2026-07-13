<?php

return [
    'queue' => env('HEKS_KOBO_QUEUE', 'heks'),

    'services' => [
        'heks_main' => [
            'aliases' => ['heks-main'],
            'wide_table' => 'heks_main_kobo_records',
            'normalized_handler' => 'main',
            'allow_create_beneficiary' => true,
        ],
        'heks_followup' => [
            'aliases' => ['heks-followups', 'heks-followup'],
            'wide_table' => 'heks_followups_kobo_records',
            'normalized_handler' => 'followup',
            'allow_create_beneficiary' => false,
        ],
        'heks_boq' => [
            'aliases' => ['heks-boq'],
            'wide_table' => 'heks_boq_kobo_records',
            'normalized_handler' => 'boq',
            'allow_create_beneficiary' => false,
        ],
        'heks_followup_boq' => [
            'aliases' => ['heks-followup-boq'],
            'wide_table' => 'heks_followup_boq_kobo_records',
            'normalized_handler' => 'followup_boq',
            'allow_create_beneficiary' => false,
        ],
    ],

    'allow_clear_fields' => [
        'social_notes',
        'engineer_notes',
        'recommendations',
        'other_condition',
        'engineer_recommendations',
    ],
];
