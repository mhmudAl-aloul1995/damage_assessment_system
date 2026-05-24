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
    Route::post('/', [BorrowerSurveyController::class, 'store'])
        ->name('damage-assessment-borrowers.store');
    Route::get('/{borrower}', [BorrowerSurveyController::class, 'show'])
        ->name('damage-assessment-borrowers.show');
});
