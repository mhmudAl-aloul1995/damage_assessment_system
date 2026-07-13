<?php

return [
    'queue' => env('HEKS_KOBO_QUEUE', 'heks'),

    'services' => [
        'heks_main' => [
            'aliases' => ['heks-main'],
            'asset_uid' => env('HEKS_MAIN_KOBO_ASSET'),
            'wide_table' => 'heks_main_kobo_records',
            'normalized_handler' => 'main',
            'allow_create_beneficiary' => true,
        ],
        'heks_followup' => [
            'aliases' => ['heks-followups', 'heks-followup'],
            'asset_uid' => env('HEKS_FOLLOWUP_KOBO_ASSET'),
            'wide_table' => 'heks_followups_kobo_records',
            'normalized_handler' => 'followup',
            'allow_create_beneficiary' => false,
        ],
        'heks_boq' => [
            'aliases' => ['heks-boq'],
            'asset_uid' => env('HEKS_BOQ_KOBO_ASSET'),
            'wide_table' => 'heks_boq_kobo_records',
            'normalized_handler' => 'boq',
            'allow_create_beneficiary' => false,
        ],
        'heks_followup_boq' => [
            'aliases' => ['heks-followup-boq'],
            'asset_uid' => env('HEKS_FOLLOWUP_BOQ_KOBO_ASSET'),
            'wide_table' => 'heks_followup_boq_kobo_records',
            'normalized_handler' => 'followup_boq',
            'allow_create_beneficiary' => false,
        ],
    ],

    'section_labels' => [
        'identification' => [
            'title' => 'معلومات التعريف',
            'description' => 'رقم الطلب، المهندس، تاريخ الزيارة، الإحداثيات، وبيانات المقابلة.',
        ],
        'family_info' => [
            'title' => 'تقييم الهشاشة الاجتماعية',
            'description' => 'بيانات الأسرة، رب الأسرة، العمر، الجنس، الإعاقة، الأمراض المزمنة، والنزوح.',
        ],
        'housing_info' => [
            'title' => 'معلومات الوحدة السكنية',
            'description' => 'نوع الوحدة السكنية، الإشغال، المساحة، وبيانات السكن الأساسية.',
        ],
        'technical_assessment' => [
            'title' => 'تقييم حالة المأوى',
            'description' => 'الأضرار، الغرف، الأبواب، النوافذ، المياه، المطبخ، الحمام، والإنارة.',
        ],
        'social_assessment' => [
            'title' => 'تقييم الظروف المعيشية للأسرة',
            'description' => 'الدخل، الاعتماد على المساعدات، الاحتياجات الأساسية، والخصوصية.',
        ],
        'recommendations' => [
            'title' => 'توصيات نهائية',
            'description' => 'حالة التدخل، المستندات، الملاحظات، والتوصيات النهائية.',
        ],
        'documents' => [
            'title' => 'المستندات',
            'description' => 'أوراق الملكية، العقود، المستندات الثبوتية، والحسابات البنكية.',
        ],
        'photos' => [
            'title' => 'صور الوحدة السكنية',
            'description' => 'صور المبنى والوحدة السكنية من الداخل والخارج.',
        ],
        'meta' => [
            'title' => 'بيانات النظام',
            'description' => 'معرفات وسجلات Kobo الفنية الخاصة بالإرسال.',
        ],
        'group_vb7yr42' => [
            'title' => 'معلومات الحماية',
            'description' => 'سلامة الوحدة، المخاطر، الإحالات، وسهولة الوصول الآمن.',
        ],
        'group_rs8tf50' => [
            'title' => 'معلومات التكوين الأسري',
            'description' => 'توزيع أفراد الأسرة حسب العمر، الجنس، الإعاقة، الأمراض، والإصابات.',
        ],
        'group_8m8lj61' => [
            'title' => 'تقييم الظروف المعيشية للأسرة',
            'description' => 'الدخل، العمل، المساعدات الغذائية، الاحتياجات الأساسية، والخصوصية.',
        ],
        'group_fj7vq52' => [
            'title' => 'تقييم حالة المأوى',
            'description' => 'حالة الضرر، السقف، الجدران، الغرف، الأبواب، المياه، المطبخ، والحمام.',
        ],
        'group_ng8dw05' => [
            'title' => 'المستندات والتوصيات',
            'description' => 'الأوراق الثبوتية، التوصيات النهائية، وملاحظات الفريق.',
        ],
        'group_un2xy00' => [
            'title' => 'المستندات',
            'description' => 'صور المستندات المتوفرة للمستفيد.',
        ],
        'group_lm1ok19' => [
            'title' => 'صور الوحدة السكنية',
            'description' => 'صور الوحدة السكنية والمبنى المرفوعة من Kobo.',
        ],
    ],

    'section_order' => [
        'identification' => 10,
        'beneficiary' => 10,
        'family_info' => 20,
        'social' => 20,
        'group_vb7yr42' => 30,
        'protection' => 30,
        'group_rs8tf50' => 40,
        'housing_info' => 50,
        'shelter' => 50,
        'group_8m8lj61' => 60,
        'social_assessment' => 60,
        'group_fj7vq52' => 70,
        'technical_assessment' => 70,
        'documents' => 80,
        'group_un2xy00' => 80,
        'group_lm1ok19' => 90,
        'photos' => 90,
        'group_ng8dw05' => 100,
        'recommendations' => 100,
        'management' => 100,
        'meta' => 110,
        'other' => 500,
    ],

    'allow_clear_fields' => [
        'social_notes',
        'engineer_notes',
        'recommendations',
        'other_condition',
        'engineer_recommendations',
    ],
];
