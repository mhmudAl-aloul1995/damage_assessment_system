<?php

use App\Modules\Reh\Http\Controllers\RehController;
use App\Modules\Reh\Http\Controllers\RehPricingController;
use App\Modules\Reh\Http\Controllers\RehUnitMaintenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:reh.view'])->name('reh.')->group(function (): void {
    Route::middleware('role:Database Officer')->prefix('unit-maintenance')->name('maintenance.')->group(function (): void {
        Route::get('/', [RehUnitMaintenanceController::class, 'index'])->name('index');
        Route::post('/bulk/preview', [RehUnitMaintenanceController::class, 'previewBulk'])->name('bulk.preview');
        Route::post('/bulk', [RehUnitMaintenanceController::class, 'destroyBulk'])->name('bulk.destroy');
        Route::get('/bulk/{log}', [RehUnitMaintenanceController::class, 'bulkStatus'])->name('bulk.status');
        Route::post('/bulk/{log}/start', [RehUnitMaintenanceController::class, 'startBulkWorker'])->name('bulk.start');
        Route::delete('/units/{unit}', [RehUnitMaintenanceController::class, 'destroy'])->name('destroy');
        Route::post('/logs/{log}/verify', [RehUnitMaintenanceController::class, 'verify'])->name('verify');
    });
    Route::middleware('can:reh.pricing.view')->prefix('pricing')->name('pricing.')->group(function (): void {
        Route::get('/', [RehPricingController::class, 'index'])->name('index');
        Route::post('/preview', [RehPricingController::class, 'preview'])->name('preview');
        Route::post('/books', [RehPricingController::class, 'store'])->name('store');
        Route::get('/items/{item}', [RehPricingController::class, 'item'])->name('item');
        Route::post('/items/{item}', [RehPricingController::class, 'update'])->name('item.update');
        Route::post('/books/{book}/approve', [RehPricingController::class, 'approve'])->name('approve');
        Route::post('/books/{book}/settings', [RehPricingController::class, 'updateBook'])->name('book.update');
        Route::get('/books/{book}/impact', [RehPricingController::class, 'impact'])->name('impact');
        Route::post('/books/{book}/revise', [RehPricingController::class, 'revise'])->name('revise');
        Route::get('/units/{unit}', [RehPricingController::class, 'unit'])->name('unit');
        Route::post('/units/{unit}/approve', [RehPricingController::class, 'approveEstimate'])->name('estimate.approve');
    });
    Route::get('/', [RehController::class, 'index'])->name('index');
    Route::get('/data', [RehController::class, 'data'])->name('data');
    Route::get('/units/{unit}', [RehController::class, 'unit'])->name('units.show');
    Route::get('/units/{unit}/pdf', [RehController::class, 'unitPdf'])->middleware('can:reh.export')->name('units.pdf');
    Route::post('/units/{unit}/reviews', [RehController::class, 'review'])->name('units.review');
    Route::get('/buildings/{building}', [RehController::class, 'building'])->name('buildings.show');
    Route::post('/audit/{kind}/{id}', [RehController::class, 'audit'])->whereIn('kind', ['building', 'unit'])->whereNumber('id')->name('audit.save');
    Route::post('/audit-logs/{log}/verify', [RehController::class, 'reconcileAudit'])->whereNumber('log')->name('audit.verify');
    Route::get('/attachments/{kind}/{id}', [RehController::class, 'attachments'])->whereIn('kind', ['building', 'unit'])->whereNumber('id')->name('attachments');
    Route::get('/attachments/{kind}/{id}/{attachment}', [RehController::class, 'attachment'])->whereIn('kind', ['building', 'unit'])->whereNumber(['id', 'attachment'])->name('attachment');
    Route::post('/sync', [RehController::class, 'sync'])->middleware('can:reh.sync')->name('sync');
    Route::get('/exports', [RehController::class, 'exports'])->middleware('can:reh.export')->name('exports');
    Route::get('/budget/excel', [RehController::class, 'budgetExcel'])->middleware(['can:reh.export', 'can:reh.pricing.view'])->name('budget.excel');
    Route::get('/budget/pdf', [RehController::class, 'budgetPdf'])->middleware(['can:reh.export', 'can:reh.pricing.view'])->name('budget.pdf');
    Route::get('/exports/data', [RehController::class, 'exportData'])->middleware('can:reh.export')->name('exports.data');
    Route::get('/export/excel', [RehController::class, 'exportExcel'])->middleware('can:reh.export')->name('export.excel');
    Route::get('/export', [RehController::class, 'export'])->middleware('can:reh.export')->name('export');
    Route::get('/export/pdf', [RehController::class, 'exportPdf'])->middleware('can:reh.export')->name('export.pdf');
});
