<?php

use App\Modules\HR\Http\Controllers\NativeAttendanceController;
use App\Modules\HR\Http\Middleware\AuditNativeAttendanceFailure;
use App\Modules\HR\Http\Middleware\EnsureAttendanceWorkflowStorage;
use App\Modules\HR\Http\Middleware\EnsureHrToken;
use App\Modules\HR\Http\Middleware\EnsureSecureHrApi;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hr')->name('api.v1.hr.')->middleware([AuditNativeAttendanceFailure::class, EnsureSecureHrApi::class, EnsureAttendanceWorkflowStorage::class])->group(function (): void {
    Route::post('auth/login', [NativeAttendanceController::class, 'login'])->middleware('throttle:5,1,hr-login')->name('login');
    Route::middleware(['auth:sanctum', EnsureHrToken::class, 'throttle:60,1,hr-api'])->group(function (): void {
        Route::get('me', [NativeAttendanceController::class, 'me'])->name('me');
        Route::get('attendance/today', [NativeAttendanceController::class, 'today'])->name('today');
        Route::get('attendance/history', [NativeAttendanceController::class, 'history'])->name('history');
        Route::post('devices/challenge', [NativeAttendanceController::class, 'registrationChallenge'])->middleware('throttle:10,1,hr-actions')->name('devices.challenge');
        Route::post('devices/register', [NativeAttendanceController::class, 'register'])->middleware('throttle:10,1,hr-actions')->name('devices.register');
        Route::get('devices/status', [NativeAttendanceController::class, 'devices'])->name('devices.status');
        Route::post('devices/{credential}/revoke', [NativeAttendanceController::class, 'revoke'])->whereNumber('credential')->name('devices.revoke');
        Route::post('attendance/challenge', [NativeAttendanceController::class, 'challenge'])->middleware('throttle:10,1,hr-actions')->name('challenge');
        Route::post('attendance/check-in', [NativeAttendanceController::class, 'checkIn'])->middleware('throttle:10,1,hr-actions')->name('check-in');
        Route::post('attendance/check-out', [NativeAttendanceController::class, 'checkOut'])->middleware('throttle:10,1,hr-actions')->name('check-out');
        Route::post('auth/logout', [NativeAttendanceController::class, 'logout'])->name('logout');
    });
});
