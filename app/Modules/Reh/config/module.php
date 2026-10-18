<?php

return [
    'provider' => App\Modules\Reh\Providers\RehServiceProvider::class,
    'title' => 'تأهيل المساكن — REH',
    'short_title' => 'REH · المرحلة الثانية',
    'description' => 'التقييم والتدقيق وكميات أعمال تأهيل المساكن',
    'icon' => 'ki-home-2',
    'active_patterns' => ['reh', 'reh/*'],
    'central' => false,
    'enabled' => true,
    'order' => 35,
];
