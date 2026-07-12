<?php

use App\Modules\DamageAssessmentBorrowers\Http\Controllers\BorrowerSurveyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [BorrowerSurveyController::class, 'index'])
        ->name('damage-assessment-borrowers.index');
    Route::get('/create', [BorrowerSurveyController::class, 'create'])
        ->name('damage-assessment-borrowers.create');
    Route::get('/data', [BorrowerSurveyController::class, 'data'])
        ->name('damage-assessment-borrowers.data');
    Route::get('/export', [BorrowerSurveyController::class, 'export'])
        ->name('damage-assessment-borrowers.export');
    Route::post('/', [BorrowerSurveyController::class, 'store'])
        ->name('damage-assessment-borrowers.store');
    Route::post('/import', [BorrowerSurveyController::class, 'import'])
        ->name('damage-assessment-borrowers.import');
    Route::post('/import/preview', [BorrowerSurveyController::class, 'previewImport'])
        ->name('damage-assessment-borrowers.import.preview');
    Route::put('/exchange-rate', [BorrowerSurveyController::class, 'updateGlobalExchangeRate'])
        ->name('damage-assessment-borrowers.exchange-rate.update');
    Route::get('/{borrower}/pricing', [BorrowerSurveyController::class, 'pricing'])
        ->name('damage-assessment-borrowers.pricing');
    Route::put('/{borrower}/pricing', [BorrowerSurveyController::class, 'updatePricing'])
        ->name('damage-assessment-borrowers.pricing.update');
    Route::get('/{borrower}/attachments/{attachment}', [BorrowerSurveyController::class, 'attachment'])
        ->name('damage-assessment-borrowers.attachments.show');
    Route::get('/{borrower}', [BorrowerSurveyController::class, 'show'])
        ->name('damage-assessment-borrowers.show');
});
