<?php

namespace App\Providers;

use App\services\Messaging\MessagingProvider;
use App\services\Messaging\TelegramMessagingProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MessagingProvider::class, TelegramMessagingProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
