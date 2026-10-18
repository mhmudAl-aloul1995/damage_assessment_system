<?php

use App\Modules\HR\Http\Controllers\AttendanceController;
use App\Modules\HR\Http\Controllers\AttendanceProjectController;
use App\Modules\HR\Http\Controllers\AttendanceReportController;
use App\Modules\HR\Http\Controllers\AttendanceWorkflowController;
use App\Modules\HR\Http\Controllers\ContractController;
use App\Modules\HR\Http\Controllers\DeviceController;
use App\Modules\HR\Http\Controllers\EmployeeController;
use App\Modules\HR\Http\Controllers\OrganizationController;
use App\Modules\HR\Http\Middleware\EnsureAttendanceWorkflowStorage;
use App\Modules\HR\Http\Middleware\EnsureContractStorage;
use App\Modules\HR\Http\Middleware\EnsureEmployeeStorage;
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

Route::middleware([RequireHrHttps::class, 'auth', EnsureEmployeeStorage::class, EnsureContractStorage::class])->name('hr.contracts.')->group(function (): void {
    Route::get('/contracts', [ContractController::class, 'index'])->name('index');
    Route::post('/contracts', [ContractController::class, 'store'])->name('store');
    Route::get('/contracts/export', [ContractController::class, 'export'])->name('export');
    Route::get('/contracts/types', [ContractController::class, 'types'])->name('types');
    Route::post('/contracts/types', [ContractController::class, 'storeType'])->name('types.store');
    Route::put('/contracts/types/{type}', [ContractController::class, 'updateType'])->name('types.update');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->whereNumber('contract')->name('show');
    Route::post('/contracts/{contract}/revisions', [ContractController::class, 'propose'])->whereNumber('contract')->name('propose');
    Route::post('/contract-revisions/{revision}/decision', [ContractController::class, 'decide'])->name('decide');
});

Route::middleware([RequireHrHttps::class, 'auth', EnsureEmployeeStorage::class])->group(function (): void {
    Route::get('/organization-chart', [OrganizationController::class, 'chart'])->name('hr.organization.chart');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('hr.employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('hr.employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('hr.employees.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->name('hr.employees.show');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee')->name('hr.employees.update');
    Route::post('/employees/{employee}/assignments', [EmployeeController::class, 'assign'])->name('hr.employees.assignments.store');
    Route::post('/employee-assignments/{assignment}/end', [EmployeeController::class, 'endAssignment'])->name('hr.employees.assignments.end');
    Route::post('/employees/{employee}/notes', [EmployeeController::class, 'note'])->name('hr.employees.notes.store');
    Route::post('/employees/{employee}/archive', [EmployeeController::class, 'archive'])->name('hr.employees.archive');
    Route::post('/employees/{employee}/documents', [EmployeeController::class, 'upload'])->name('hr.employees.documents.store');
    Route::get('/employee-documents/{document}/download', [EmployeeController::class, 'download'])->name('hr.employees.documents.download');
    Route::post('/employee-documents/{document}/withdraw', [EmployeeController::class, 'withdraw'])->name('hr.employees.documents.withdraw');
    Route::get('/organization/{kind}', [OrganizationController::class, 'index'])->whereIn('kind', array_keys(\App\Modules\HR\Services\OrganizationCatalog::MODELS))->name('hr.organization.index');
    Route::post('/organization/{kind}', [OrganizationController::class, 'store'])->whereIn('kind', array_keys(\App\Modules\HR\Services\OrganizationCatalog::MODELS))->name('hr.organization.store');
    Route::put('/organization/{kind}/{record}', [OrganizationController::class, 'update'])->whereIn('kind', array_keys(\App\Modules\HR\Services\OrganizationCatalog::MODELS))->whereNumber('record')->name('hr.organization.update');
    Route::post('/department-scopes', [OrganizationController::class, 'scope'])->name('hr.organization.scopes.store');
    Route::post('/department-scopes/{scope}/end', [OrganizationController::class, 'endScope'])->name('hr.organization.scopes.end');
});
