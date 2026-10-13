<?php

use App\Modules\HR\Http\Controllers\AttendanceController;
use App\Modules\HR\Http\Controllers\AttendanceWorkflowController;
use App\Modules\HR\Http\Middleware\EnsureAttendanceWorkflowStorage;
use App\Modules\HR\Http\Middleware\RequireHrHttps;
use Illuminate\Support\Facades\Route;

Route::middleware([RequireHrHttps::class, 'auth', EnsureAttendanceWorkflowStorage::class])->name('hr.attendance.')->group(function (): void {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('index');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('export');
    Route::get('/attendance/setup', [AttendanceController::class, 'setup'])->name('setup');
    Route::post('/attendance/employees', [AttendanceController::class, 'employee'])->name('employees.store');
    Route::post('/attendance/schedules', [AttendanceController::class, 'schedule'])->name('schedules.store');
    Route::post('/attendance/assignments', [AttendanceController::class, 'assignment'])->name('assignments.store');
    Route::post('/attendance/assignments/{assignment}/retire', [AttendanceController::class, 'retireAssignment'])->name('assignments.retire');
    Route::get('/attendance/calendar', [AttendanceWorkflowController::class, 'calendar'])->name('calendar');
    Route::post('/attendance/holidays', [AttendanceWorkflowController::class, 'holiday'])->name('holidays.store');
    Route::post('/attendance/holidays/{holiday}/cancel', [AttendanceWorkflowController::class, 'cancelHoliday'])->name('holidays.cancel');
    Route::post('/attendance/schedule-exceptions', [AttendanceWorkflowController::class, 'scheduleException'])->name('schedule-exceptions.store');
    Route::get('/attendance/corrections', [AttendanceWorkflowController::class, 'corrections'])->name('corrections');
    Route::get('/attendance/employees/{employee}/corrections/create', [AttendanceWorkflowController::class, 'createCorrection'])->name('corrections.create');
    Route::post('/attendance/employees/{employee}/corrections', [AttendanceWorkflowController::class, 'submitCorrection'])->name('corrections.store');
    Route::post('/attendance/corrections/{adjustment}/decision', [AttendanceWorkflowController::class, 'decideCorrection'])->name('corrections.decide');
    Route::get('/attendance/exceptions', [AttendanceWorkflowController::class, 'exceptions'])->name('exceptions');
    Route::post('/attendance/reconcile', [AttendanceWorkflowController::class, 'reconcile'])->name('reconcile');
    Route::get('/attendance/audit', [AttendanceWorkflowController::class, 'audit'])->name('audit');
});
