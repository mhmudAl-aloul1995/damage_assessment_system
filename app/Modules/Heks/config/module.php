<?php

return [
    'provider' => App\Modules\Heks\Providers\HeksServiceProvider::class,
    'title' => 'المساعدة النقدية لإصلاح المأوى الطارئ (HEKS)',
    'short_title' => 'menu.module_switcher.heks',
    'description' => 'menu.module_switcher.descriptions.heks',
    'icon' => 'ki-home-2',
    'active_patterns' => ['heks', 'heks/*'],
    'central' => false,
    'enabled' => true,
    'order' => 30,
];
