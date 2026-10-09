<?php

use App\Http\Controllers\Api\KoboRestSubmissionController;
use App\Http\Controllers\Api\V1\MobileAccessController;
use App\Http\Middleware\EnsureMobileAccess;
use Illuminate\Support\Facades\Route;

Route::post('/kobo/{service}', KoboRestSubmissionController::class)
    ->where('service', '[A-Za-z0-9_-]+')
    ->name('api.kobo.submissions.store');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [MobileAccessController::class, 'login'])
        ->middleware('throttle:5,1')->name('login');

    Route::middleware(['auth:sanctum', EnsureMobileAccess::class, 'throttle:60,1'])->group(function (): void {
        Route::get('me', [MobileAccessController::class, 'me'])->name('me');
        Route::get('modules', [MobileAccessController::class, 'modules'])->name('modules');
        Route::post('auth/logout', [MobileAccessController::class, 'logout'])->name('logout');
    });
});
