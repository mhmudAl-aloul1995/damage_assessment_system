<?php

return [
    'provider' => App\Modules\HeksShelter\Providers\HeksShelterServiceProvider::class,
    'title' => 'المساعدة النقدية لإصلاح المأوى الطارئ — HEKS',
    'short_title' => 'HEKS · المرحلة الثالثة',
    'description' => 'استبيانات الموبايل والتدقيق والمنح ومتابعة إصلاح المأوى',
    'icon' => 'ki-home-2',
    'active_patterns' => ['heks-shelter', 'heks-shelter/*'],
    'central' => false, 'enabled' => true, 'order' => 29,
];
