<?php

use App\Modules\HR\Http\Controllers\AttendanceController;
use App\Modules\HR\Http\Controllers\AttendanceProjectController;
use App\Modules\HR\Http\Controllers\AttendanceReportController;
use App\Modules\HR\Http\Controllers\AttendanceWorkflowController;
use App\Modules\HR\Http\Controllers\DeviceController;
use App\Modules\HR\Http\Middleware\EnsureAttendanceWorkflowStorage;
use App\Modules\HR\Http\Middleware\RequireHrHttps;
use Illuminate\Support\Facades\Route;

Route::get('/', [AttendanceController::class, 'home'])
    ->middleware([RequireHrHttps::class, 'auth'])->name('hr.home');

Route::middleware([RequireHrHttps::class, 'auth', EnsureAttendanceWorkflowStorage::class])->name('hr.attendance.')->group(function (): void {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('index');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('export');
    Route::get('/attendance/setup', [AttendanceController::class, 'setup'])->name('setup');
    Route::get('/attendance/projects', [AttendanceProjectController::class, 'index'])->name('projects');
    Route::post('/attendance/projects', [AttendanceProjectController::class, 'store'])->name('projects.store');
    Route::post('/attendance/projects/employees', [AttendanceController::class, 'employee'])->name('projects.employees.store');
    Route::post('/attendance/projects/assignments', [AttendanceController::class, 'assignment'])->name('projects.assignments.store');
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
    Route::get('/attendance/reports', [AttendanceReportController::class, 'index'])->name('reports');
    Route::post('/attendance/reports', [AttendanceReportController::class, 'queue'])->middleware('throttle:5,1')->name('reports.queue');
    Route::get('/attendance/reports/{export}/download', [AttendanceReportController::class, 'download'])->whereUuid('export')->name('reports.download');
    Route::get('/attendance/security-report', [AttendanceReportController::class, 'security'])->name('security-report');
    Route::get('/attendance/devices', [DeviceController::class, 'index'])->name('devices');
    Route::post('/attendance/devices/{credential}/decision', [DeviceController::class, 'decide'])->name('devices.decide');
});
