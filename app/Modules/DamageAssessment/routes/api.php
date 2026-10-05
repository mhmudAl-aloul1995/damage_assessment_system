<?php

use App\Http\Controllers\Api\ArcgisCsoWebhookController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/arcgis/csos/webhook', ArcgisCsoWebhookController::class)
    ->name('api.arcgis.csos.webhook');
