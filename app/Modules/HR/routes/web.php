<?php

use App\Modules\HR\Http\Controllers\AttendanceController;
use App\Modules\HR\Http\Middleware\RequireHrHttps;
use Illuminate\Support\Facades\Route;

Route::middleware([RequireHrHttps::class, 'auth'])->name('hr.attendance.')->group(function (): void {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('index');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('export');
    Route::get('/attendance/setup', [AttendanceController::class, 'setup'])->name('setup');
    Route::post('/attendance/employees', [AttendanceController::class, 'employee'])->name('employees.store');
    Route::post('/attendance/schedules', [AttendanceController::class, 'schedule'])->name('schedules.store');
    Route::post('/attendance/assignments', [AttendanceController::class, 'assignment'])->name('assignments.store');
});
