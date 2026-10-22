<?php

use App\Modules\HeksShelter\Http\Controllers\ShelterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:heks3.view'])->name('heks3.')->group(function (): void {
    Route::get('/', [ShelterController::class, 'index'])->name('index');
    Route::get('/export', [ShelterController::class, 'export'])->middleware('can:heks3.export')->name('export');
    Route::get('/cases/{case}', [ShelterController::class, 'show'])->name('show');
    Route::get('/cases/{case}/pdf', [ShelterController::class, 'pdf'])->middleware('can:heks3.export')->name('pdf');
    Route::post('/cases/{case}/actions', [ShelterController::class, 'act'])->name('act');
    Route::get('/cases/{case}/attachments/{attachment}', [ShelterController::class, 'attachment'])->name('attachment');
});
