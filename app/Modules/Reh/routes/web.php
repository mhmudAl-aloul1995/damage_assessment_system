<?php

use App\Modules\Reh\Http\Controllers\RehController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:reh.view'])->name('reh.')->group(function (): void {
    Route::get('/', [RehController::class, 'index'])->name('index');
    Route::get('/data', [RehController::class, 'data'])->name('data');
    Route::get('/units/{unit}', [RehController::class, 'unit'])->name('units.show');
    Route::post('/units/{unit}/reviews', [RehController::class, 'review'])->name('units.review');
    Route::get('/buildings/{building}', [RehController::class, 'building'])->name('buildings.show');
    Route::post('/audit/{kind}/{id}', [RehController::class, 'audit'])->whereIn('kind', ['building', 'unit'])->whereNumber('id')->name('audit.save');
    Route::post('/audit-logs/{log}/verify', [RehController::class, 'reconcileAudit'])->whereNumber('log')->name('audit.verify');
    Route::get('/attachments/{kind}/{id}', [RehController::class, 'attachments'])->whereIn('kind', ['building', 'unit'])->whereNumber('id')->name('attachments');
    Route::get('/attachments/{kind}/{id}/{attachment}', [RehController::class, 'attachment'])->whereIn('kind', ['building', 'unit'])->whereNumber(['id', 'attachment'])->name('attachment');
    Route::post('/sync', [RehController::class, 'sync'])->middleware('can:reh.sync')->name('sync');
    Route::get('/export', [RehController::class, 'export'])->middleware('can:reh.export')->name('export');
});
