<?php

use App\Modules\Heks\Http\Controllers\HeksController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [HeksController::class, 'dashboard'])->name('heks.dashboard');
    Route::get('/imports', [HeksController::class, 'imports'])->name('heks.imports');
    Route::post('/imports/preview', [HeksController::class, 'preview'])->name('heks.imports.preview');
    Route::post('/imports', [HeksController::class, 'import'])->name('heks.imports.store');
    Route::get('/beneficiaries', [HeksController::class, 'beneficiaries'])->name('heks.beneficiaries');
    Route::get('/beneficiaries/{beneficiary}/edit', [HeksController::class, 'edit'])->name('heks.beneficiaries.edit');
    Route::put('/beneficiaries/{beneficiary}', [HeksController::class, 'update'])->name('heks.beneficiaries.update');
    Route::post('/beneficiaries/{beneficiary}/scores', [HeksController::class, 'storeScore'])->name('heks.beneficiaries.scores.store');
    Route::get('/beneficiaries/{beneficiary}/pricing', [HeksController::class, 'pricing'])->name('heks.beneficiaries.pricing');
    Route::put('/beneficiaries/{beneficiary}/pricing', [HeksController::class, 'updateBoqPricing'])->name('heks.beneficiaries.pricing.update');
    Route::post('/beneficiaries/{beneficiary}/boq-items/import', [HeksController::class, 'importBoqItems'])->name('heks.beneficiaries.boq-items.import');
    Route::post('/beneficiaries/{beneficiary}/boq-items', [HeksController::class, 'storeBoqItem'])->name('heks.beneficiaries.boq-items.store');
    Route::put('/boq-items/{boqItem}', [HeksController::class, 'updateBoqItem'])->name('heks.boq-items.update');
    Route::delete('/boq-items/{boqItem}', [HeksController::class, 'destroyBoqItem'])->name('heks.boq-items.destroy');
    Route::get('/labels', [HeksController::class, 'labels'])->name('heks.labels');
    Route::put('/labels/{label}', [HeksController::class, 'updateLabel'])->name('heks.labels.update');
    Route::get('/follow-ups', [HeksController::class, 'followUps'])->name('heks.follow-ups');
    Route::get('/follow-ups/{followUp}/boq', [HeksController::class, 'followUpBoq'])->name('heks.follow-ups.boq');
    Route::put('/follow-ups/{followUp}', [HeksController::class, 'updateFollowUp'])->name('heks.follow-ups.update');
    Route::get('/scores', [HeksController::class, 'scores'])->name('heks.scores');
    Route::put('/scores/{score}', [HeksController::class, 'updateScore'])->name('heks.scores.update');
    Route::get('/quality', [HeksController::class, 'quality'])->name('heks.quality');
});
