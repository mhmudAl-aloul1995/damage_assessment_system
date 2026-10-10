<?php

use App\Http\Controllers\Api\KoboRestSubmissionController;
use App\Http\Controllers\Api\V1\DamageInquiryController;
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
        Route::get('damage-assessment/sectors', [DamageInquiryController::class, 'sectors'])->name('damage.sectors');
        Route::get('damage-assessment/citizens', [DamageInquiryController::class, 'citizens'])->middleware('throttle:20,1')->name('damage.citizens');
        Route::prefix('damage-assessment/{sector}')->whereIn('sector', DamageInquiryController::SECTORS)->name('damage.')->group(function (): void {
            Route::get('/', [DamageInquiryController::class, 'records'])->name('records');
            Route::get('filters', [DamageInquiryController::class, 'filters'])->name('filters');
            Route::get('map', [DamageInquiryController::class, 'map'])->name('map');
            Route::get('stats', [DamageInquiryController::class, 'stats'])->name('stats');
            Route::get('{record}', [DamageInquiryController::class, 'show'])->whereNumber('record')->name('show');
            Route::get('{record}/history', [DamageInquiryController::class, 'history'])->whereNumber('record')->name('history');
            Route::get('{record}/attachments', [DamageInquiryController::class, 'attachments'])->whereNumber('record')->name('attachments');
            Route::get('{record}/attachments/{attachment}', [DamageInquiryController::class, 'attachment'])->whereNumber(['record', 'attachment'])->name('attachment');
        });
        Route::post('auth/logout', [MobileAccessController::class, 'logout'])->name('logout');
    });
});
