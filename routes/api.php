<?php

use App\Http\Controllers\Api\KoboRestSubmissionController;
use App\Http\Controllers\Api\V1\DamageInquiryController;
use App\Http\Controllers\Api\V1\MobileAccessController;
use App\Http\Controllers\Api\V1\MobilePortalController;
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
        Route::get('portal/modules', [MobilePortalController::class, 'catalog'])->name('portal.modules');
        Route::get('portal/{module}/records', [MobilePortalController::class, 'records'])->whereIn('module', ['damage_assessment_borrowers', 'heks', 'administration'])->name('portal.records');
        Route::get('portal/{module}/records/{record}', [MobilePortalController::class, 'show'])->whereIn('module', ['damage_assessment_borrowers', 'heks', 'administration'])->whereNumber('record')->name('portal.show');
        Route::get('forms', [MobilePortalController::class, 'forms'])->name('forms.index');
        Route::get('forms/{form}/schema', [MobilePortalController::class, 'schema'])->where('form', '[a-z-]+')->name('forms.schema');
        Route::get('forms/{form}/records', [MobilePortalController::class, 'formRecords'])->where('form', '[a-z-]+')->name('forms.records');
        Route::post('forms/{form}/submissions', [MobilePortalController::class, 'submit'])->where('form', '[a-z-]+')->middleware('throttle:mobile-form-submit')->name('forms.submit');
        Route::post('forms/{form}/attachments', [MobilePortalController::class, 'upload'])->where('form', '[a-z-]+')->middleware('throttle:mobile-form-upload')->name('forms.upload');
        Route::post('forms/{form}/attachments/{attachment}/discard', [MobilePortalController::class, 'discard'])->where('form', '[a-z-]+')->whereNumber('attachment')->middleware('throttle:mobile-form-discard')->name('forms.discard');
        Route::get('submissions', [MobilePortalController::class, 'submissions'])->name('submissions.index');
        Route::get('submissions/{uuid}', [MobilePortalController::class, 'receipt'])->whereUuid('uuid')->name('submissions.show');
        Route::get('form-attachments/{attachment}', [MobilePortalController::class, 'attachment'])->whereNumber('attachment')->name('forms.attachment');
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
