<?php

use App\Http\Controllers\Api\KoboRestSubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('/kobo/{service}', KoboRestSubmissionController::class)
    ->where('service', '[A-Za-z0-9_-]+')
    ->name('api.kobo.submissions.store');
