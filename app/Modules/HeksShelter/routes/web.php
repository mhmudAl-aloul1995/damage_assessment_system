<?php

use App\Modules\HeksShelter\Http\Controllers\ShelterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:heks3.view'])->name('heks3.')->group(function (): void {
    Route::middleware('can:heks3.settings')->group(function (): void {
        Route::get('/settings', [\App\Modules\HeksShelter\Http\Controllers\SurveySettingsController::class, 'index'])->name('settings');
        Route::post('/settings/draft', [\App\Modules\HeksShelter\Http\Controllers\SurveySettingsController::class, 'draft'])->name('settings.draft');
        Route::post('/settings/questions', [\App\Modules\HeksShelter\Http\Controllers\SurveySettingsController::class, 'save'])->name('settings.save');
        Route::post('/settings/publish', [\App\Modules\HeksShelter\Http\Controllers\SurveySettingsController::class, 'publish'])->name('settings.publish');
        Route::post('/settings/sections', [\App\Modules\HeksShelter\Http\Controllers\SurveySettingsController::class, 'section'])->name('settings.section');
    });
    Route::get('/', [ShelterController::class, 'index'])->name('index');
    Route::get('/export', [ShelterController::class, 'export'])->middleware('can:heks3.export')->name('export');
    Route::get('/cases/{case}', [ShelterController::class, 'show'])->name('show');
    Route::get('/cases/{case}/pdf', [ShelterController::class, 'pdf'])->middleware('can:heks3.export')->name('pdf');
    Route::post('/cases/{case}/actions', [ShelterController::class, 'act'])->name('act');
    Route::get('/cases/{case}/attachments/{attachment}', [ShelterController::class, 'attachment'])->name('attachment');
});
