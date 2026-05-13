<?php

use App\Http\Controllers\Admin\TeamLeaderFieldEngineerController;
use App\Http\Controllers\AssessmentEditHistoryController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Committee\CommitteeDecisionController;
use App\Http\Controllers\Committee\CommitteeMemberController;
use App\Http\Controllers\DamageAssessment\ArcGISController;
use App\Http\Controllers\DamageAssessment\AreaManagerRejectedBuildingsController;
use App\Http\Controllers\DamageAssessment\auditController;
use App\Http\Controllers\DamageAssessment\buildingController;
use App\Http\Controllers\DamageAssessment\damageAssessmentController;
use App\Http\Controllers\DamageAssessment\engineerController;
use App\Http\Controllers\DamageAssessment\ExportDataController;
use App\Http\Controllers\DamageAssessment\housingController;
use App\Http\Controllers\FieldEngineer\BuildingSurveyReturnRequestController;
use App\Http\Controllers\FieldEngineerReportController;
use App\Http\Controllers\InfAuditPublicBuildingController;
use App\Http\Controllers\InfAuditRoadFacilityController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicBuilding\PublicBuildingController;
use App\Http\Controllers\Report\AreaProductivityReportController;
use App\Http\Controllers\Report\BuildingProductivityReportController;
use App\Http\Controllers\Report\DamageStatisticsReportController;
use App\Http\Controllers\Report\reportController;
use App\Http\Controllers\Report\SurveyReportController;
use App\Http\Controllers\RoadFacility\RoadFacilityController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\UserManagement\PermissionController;
use App\Http\Controllers\UserManagement\roleController;
use App\Http\Controllers\UserManagement\userController;
use App\Models\Attendance;
use App\Models\AttendanceImportLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {

    return redirect()->route('login');
});

Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');
/* Route::get('/', action: [damageAssessmentController::class, 'index']);
 */
Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->middleware(['auth', 'verified'])->name('forgot-password');

Route::get('/dashboard', function () {
    return redirect('damageAssessment');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/system-logs', [SystemLogController::class, 'index'])
        ->middleware('role_or_permission:Database Officer|system-logs.view')
        ->name('system.logs');
    Route::get('/system-logs/data', [SystemLogController::class, 'data'])
        ->middleware('role_or_permission:Database Officer|system-logs.view')
        ->name('system.logs.data');

    Route::get('/gitPush', [engineerController::class, 'gitPush'])
        ->middleware('role_or_permission:Database Officer|system.maintenance');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/run-migrations', function () {
        // This runs 'php artisan migrate'
        $exitCode = Artisan::call('migrate', [
            '--force' => true, // Required for running in production environments
        ]);

        return $exitCode === 0 ? 'Migration successful!' : 'Migration failed.';
    })->middleware('role_or_permission:Database Officer|system.maintenance');

    Route::resource('sync', controller: ArcGISController::class);

    Route::get('/pull', function () {
        // Run the git pull command in the project root
        $result = Process::path(base_path())
            ->run('git pull');

        if ($result->successful()) {
            return response()->json(['message' => 'Successfully pulled latest changes']);
        }

        return response()->json(['error' => $result->errorOutput()], 500);
    })->middleware('role_or_permission:Database Officer|system.maintenance');

    Route::get('/push', function () {
        $repo = base_path();
        $safeRepo = str_replace('\\', '/', $repo);

        $commands = [
            'git -c safe.directory="'.$safeRepo.'" add .',
            'git -c safe.directory="'.$safeRepo.'" diff --cached --quiet',
            'git -c safe.directory="'.$safeRepo.'" commit -m "Auto-update: '.now()->toDateTimeString().'"',
            'git -c safe.directory="'.$safeRepo.'" push',
        ];

        $outputs = [];

        foreach ($commands as $index => $command) {
            $result = Process::path($repo)->run($command);

            // diff --cached --quiet Ã™Å Ã˜Â±Ã˜Â¬Ã˜Â¹ 1 Ã˜Â¥Ã˜Â°Ã˜Â§ Ã™ÂÃ™Å Ã™â€¡ Ã˜ÂªÃ˜ÂºÃ™Å Ã™Å Ã˜Â±Ã˜Â§Ã˜Âª
            if ($index === 1) {
                if ($result->exitCode() === 0) {
                    return response()->json([
                        'status' => 'success',
                        'output' => ['No changes to commit.'],
                    ]);
                }

                continue;
            }

            if (! $result->successful()) {
                return response()->json([
                    'status' => 'failed',
                    'command' => $command,
                    'error' => $result->errorOutput() ?: $result->output(),
                    'exit_code' => $result->exitCode(),
                ], 500);
            }

            $outputs[] = $result->output();
        }

        return response()->json([
            'status' => 'success',
            'output' => $outputs,
        ]);
    })->middleware('role_or_permission:Database Officer|system.maintenance');
    /*     Route::get('/deleteUsers', function () {
            user::where('id', '>', '3')->delete();
            AttendanceImportLog::where('id','>','0')->delete();
            Attendance::where('id','>','0')->delete();

        }); */

    Route::prefix('user-management/user')->group(function () {

        Route::get('/', [userController::class, 'index'])->name('users.index');

        Route::get('/show', [userController::class, 'show'])->name('users.show');

        Route::post('/', [userController::class, 'store'])->name('users.store');

        Route::get('/{user}/edit', [userController::class, 'edit'])->name('users.edit');
        Route::put('/{user}', [userController::class, 'update'])->name('users.update');

        Route::delete('/{user}', [userController::class, 'destroy'])->name('users.destroy');
    });
    Route::prefix('user-management')->group(function () {

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');

        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');

        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');

        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::prefix('user-management/permissions')->name('permissions.')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('index');
        Route::get('/data', [PermissionController::class, 'data'])->name('data');
        Route::post('/', [PermissionController::class, 'store'])->name('store');
        Route::get('/{permission}/edit', [PermissionController::class, 'edit'])->name('edit');
        Route::put('/{permission}', [PermissionController::class, 'update'])->name('update');
        Route::delete('/{permission}', [PermissionController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::post('/data', [AttendanceController::class, 'data'])->name('data');
        Route::post('/store', [AttendanceController::class, 'store'])->name('store');
        Route::post('/import', [AttendanceController::class, 'import'])->name('import');
        Route::get('import-progress/{log}', [AttendanceController::class, 'importProgress'])
            ->name('import.progress');
        Route::post('/set-day-present', [AttendanceController::class, 'setDayPresent'])->name('set-day-present');
        Route::post('/set-day-absent', [AttendanceController::class, 'setDayAbsent'])->name('set-day-absent');
        Route::get('dashboard', [AttendanceController::class, 'dashboard'])
            ->name('dashboard');

        Route::get('monthly-report', [AttendanceController::class, 'monthlyReport'])
            ->name('monthly-report');
        Route::get('export-monthly-report', [AttendanceController::class, 'exportMonthlyReport'])
            ->name('export-monthly-report');
    });

    Route::prefix('area-manager-review')->name('area-manager-review.')->group(function () {
        Route::get('/', [AreaManagerRejectedBuildingsController::class, 'index'])->name('index');
        Route::get('/data', [AreaManagerRejectedBuildingsController::class, 'data'])->name('data');
    });

    Route::prefix('admin/team-leader-field-engineers')
        ->name('team-leader-field-engineers.')
        ->group(function () {
            Route::get('/', [TeamLeaderFieldEngineerController::class, 'index'])->name('index');
            Route::post('/', [TeamLeaderFieldEngineerController::class, 'store'])->name('store');
            Route::get('/datatable', [TeamLeaderFieldEngineerController::class, 'datatable'])->name('datatable');
            Route::delete('/{teamLeaderFieldEngineer}', [TeamLeaderFieldEngineerController::class, 'destroy'])->name('destroy');
        });

    Route::prefix('field-engineer/building-survey-return-requests')
        ->name('building-survey-return-requests.')
        ->group(function () {
            Route::get('/', [BuildingSurveyReturnRequestController::class, 'index'])->name('index');
            Route::get('/create', [BuildingSurveyReturnRequestController::class, 'create'])->name('create');
            Route::post('/', [BuildingSurveyReturnRequestController::class, 'store'])->name('store');
            Route::get('/{returnRequest}', [BuildingSurveyReturnRequestController::class, 'show'])->name('show');
            Route::post('/{returnRequest}/team-leader-approve', [BuildingSurveyReturnRequestController::class, 'approveByTeamLeader'])->name('team-leader.approve');
            Route::post('/{returnRequest}/area-manager-approve', [BuildingSurveyReturnRequestController::class, 'approveByAreaManager'])->name('area-manager.approve');
            Route::post('/{returnRequest}/reject', [BuildingSurveyReturnRequestController::class, 'reject'])->name('reject');
        });

    Route::get('/assessment-edit-histories', [AssessmentEditHistoryController::class, 'index'])
        ->name('assessment-edit-histories.index');

    Route::prefix('committee-decisions')->name('committee-decisions.')->group(function () {
        Route::get('/', [CommitteeDecisionController::class, 'index'])->name('index');
        Route::get('/buildings/data', [CommitteeDecisionController::class, 'buildingsData'])->name('buildings.data');
        Route::get('/housing-units/data', [CommitteeDecisionController::class, 'housingUnitsData'])->name('housing-units.data');
        Route::get('/buildings/{building}', [CommitteeDecisionController::class, 'showBuilding'])->name('buildings.show');
        Route::get('/housing-units/{housingUnit}', [CommitteeDecisionController::class, 'showHousingUnit'])->name('housing-units.show');
        Route::put('/{committeeDecision}', [CommitteeDecisionController::class, 'update'])->name('update');
        Route::post('/{committeeDecision}/sign', [CommitteeDecisionController::class, 'sign'])->name('sign');
        Route::post('/{committeeDecision}/retry-arcgis', [CommitteeDecisionController::class, 'retryArcgis'])->name('retry-arcgis');
    });

    Route::prefix('committee-members')->name('committee-members.')->group(function () {
        Route::get('/', [CommitteeMemberController::class, 'index'])->name('index');
        Route::get('/data', [CommitteeMemberController::class, 'data'])->name('data');
        Route::post('/', [CommitteeMemberController::class, 'store'])->name('store');
        Route::put('/{committeeMember}', [CommitteeMemberController::class, 'update'])->name('update');
        Route::delete('/{committeeMember}', [CommitteeMemberController::class, 'destroy'])->name('destroy');
    });

    Route::get('/create_building_data/{token}', [ArcGISController::class, 'create_building_data']);
    Route::get('/create_housing_data/{token}', [ArcGISController::class, 'create_housing_data']);

    Route::get('/sync_housings/{no_day}', [ArcGISController::class, 'sync_housings']);
    Route::get('/sync_buildings/{no_day}', [ArcGISController::class, 'sync_buildings']);

    // building
    Route::resource('building', controller: buildingController::class);
    Route::get('export_building', action: [buildingController::class, 'export_building']);

    // housing
    Route::resource('housing', controller: housingController::class);
    Route::get('/showHousing/{globalid}', action: [housingController::class, 'index']);

    Route::get('/public-buildings', [PublicBuildingController::class, 'index'])->name('public-buildings.index');
    Route::get('/public-buildings/data', [PublicBuildingController::class, 'data'])->name('public-buildings.data');
    Route::get('/public-buildings/export/{format}', [PublicBuildingController::class, 'export'])->name('public-buildings.export');
    Route::get('/public-buildings/{publicBuilding:globalid}', [PublicBuildingController::class, 'show'])->name('public-buildings.show');
    Route::get('/road-facilities', [RoadFacilityController::class, 'index'])->name('road-facilities.index');
    Route::get('/road-facilities/data', [RoadFacilityController::class, 'data'])->name('road-facilities.data');
    Route::get('/road-facilities/export/{format}', [RoadFacilityController::class, 'export'])->name('road-facilities.export');
    Route::get('/road-facilities/{roadFacility:globalid}', [RoadFacilityController::class, 'show'])->name('road-facilities.show');

    Route::prefix('inf-audit')->name('inf-audit.')->group(function () {
        Route::get('/public-buildings', [InfAuditPublicBuildingController::class, 'index'])->name('public-buildings.index');
        Route::get('/public-buildings/data', [InfAuditPublicBuildingController::class, 'data'])->name('public-buildings.data');
        Route::post('/public-buildings/assign', [InfAuditPublicBuildingController::class, 'bulkAssign'])->name('public-buildings.assign');
        Route::get('/public-buildings/{publicBuilding:globalid}', [InfAuditPublicBuildingController::class, 'show'])->name('public-buildings.show');
        Route::post('/public-buildings/{publicBuilding:globalid}/status', [InfAuditPublicBuildingController::class, 'updateStatus'])->name('public-buildings.status');
        Route::post('/public-buildings/{publicBuilding:globalid}/children', [InfAuditPublicBuildingController::class, 'storeChild'])->name('public-buildings.children.store');
        Route::post('/public-buildings/{publicBuilding:globalid}/field-update', [InfAuditPublicBuildingController::class, 'updateField'])->name('public-buildings.field-update');

        Route::get('/roads', [InfAuditRoadFacilityController::class, 'index'])->name('roads.index');
        Route::get('/roads/data', [InfAuditRoadFacilityController::class, 'data'])->name('roads.data');
        Route::post('/roads/assign', [InfAuditRoadFacilityController::class, 'bulkAssign'])->name('roads.assign');
        Route::get('/roads/{road:globalid}', [InfAuditRoadFacilityController::class, 'show'])->name('roads.show');
        Route::post('/roads/{road:globalid}/status', [InfAuditRoadFacilityController::class, 'updateStatus'])->name('roads.status');
        Route::post('/roads/{road:globalid}/children', [InfAuditRoadFacilityController::class, 'storeChild'])->name('roads.children.store');
        Route::post('/roads/{road:globalid}/field-update', [InfAuditRoadFacilityController::class, 'updateField'])->name('roads.field-update');
    });

    Route::prefix('reports')
        ->name('reports.')
        ->group(function () {
            Route::get('/damage-statistics', [DamageStatisticsReportController::class, 'index'])
                ->name('damage-statistics.index');

            Route::get('/damage-statistics/data', [DamageStatisticsReportController::class, 'data'])
                ->name('damage-statistics.data');

            Route::get('/damage-statistics/export', [DamageStatisticsReportController::class, 'export'])
                ->name('damage-statistics.export');
        });
    // engineers
    Route::resource('engineer', controller: engineerController::class);
    Route::get('engineerAssessments/{assignedto}', [engineerController::class, 'engineerAssessments']);
    Route::get('assessment/{globalid}', action: [engineerController::class, 'showAssessment'])->name('assessment.show');
    Route::get('assessment/{globalid}/pdf', action: [engineerController::class, 'exportAssessmentPdf'])->name('assessment.pdf');
    Route::get('/engineers/filter', [EngineerController::class, 'filter'])->name('engineers.filter');
    Route::get('/assessmentAll', [EngineerController::class, 'assessmentAll'])->name('engineers.assessmentAll');
    // Assessment
    Route::get('/damageAssessment/arcgis/options', [damageAssessmentController::class, 'arcgisOptions'])
        ->name('damageAssessment.arcgis.options');
    Route::resource('damageAssessment', controller: damageAssessmentController::class);
    Route::get('/phc/damageAssessment/arcgis/options', [damageAssessmentController::class, 'arcgisOptions'])
        ->name('phc.damageAssessment.arcgis.options');
    Route::get('/showBuildings', action: [damageAssessmentController::class, 'showBuildings']);
    Route::get('/showHousings', action: [damageAssessmentController::class, 'showHousings']);
    Route::get('/housing-units-map', [damageAssessmentController::class, 'housingUnitsMap'])
        ->name('housing-units-map');
    Route::get('/public-buildings-map', [damageAssessmentController::class, 'publicBuildingsMap'])
        ->name('public-buildings-map');
    Route::get('/road-facilities-map', [damageAssessmentController::class, 'roadFacilitiesMap'])
        ->name('road-facilities-map');
    Route::get('/api/get-latest-stats', [damageAssessmentController::class, 'latestStats'])
        ->name('damageAssessment.latest-stats');
    // Reports

    Route::get('reports/productivity', action: [reportController::class, 'productivity']);
    Route::get('/export_productivity', [reportController::class, 'export_productivity'])->name('export_productivity');

    Route::get('/search-buildings', [damageAssessmentController::class, 'search'])->name('buildings.search');
    Route::get('/global-search', [damageAssessmentController::class, 'globalSearch'])->name('global-search');

    Route::get('reports/commulative/export', [ReportController::class, 'exportCommulative'])->name('reports.commulative.export');
    Route::get('reports/commulative', action: [reportController::class, 'commulative'])->name('reports.commulative');
    Route::get('reports/area-productivity/housing-units', [AreaProductivityReportController::class, 'housingUnits'])->name('reports.area-productivity.housing-units');
    Route::get('reports/area-productivity/buildings', [AreaProductivityReportController::class, 'buildings'])->name('reports.area-productivity.buildings');
    Route::get('reports/area-productivity/public-buildings', [AreaProductivityReportController::class, 'publicBuildings'])->name('reports.area-productivity.public-buildings');
    Route::get('reports/area-productivity/road-facilities', [AreaProductivityReportController::class, 'roadFacilities'])->name('reports.area-productivity.road-facilities');
    Route::get('reports/area-productivity/housing-units/export', [AreaProductivityReportController::class, 'exportHousingUnits'])->name('reports.area-productivity.export.housing-units');
    Route::get('reports/area-productivity/buildings/export', [AreaProductivityReportController::class, 'exportBuildings'])->name('reports.area-productivity.export.buildings');
    Route::get('reports/area-productivity/public-buildings/export', [AreaProductivityReportController::class, 'exportPublicBuildings'])->name('reports.area-productivity.export.public-buildings');
    Route::get('reports/area-productivity/road-facilities/export', [AreaProductivityReportController::class, 'exportRoadFacilities'])->name('reports.area-productivity.export.road-facilities');
    Route::get('reports/building-productivity', [BuildingProductivityReportController::class, 'index'])->name('reports.building-productivity.index');
    Route::get('reports/building-productivity/export', [BuildingProductivityReportController::class, 'export'])->name('reports.building-productivity.export');
    Route::get('reports/field-engineer', [FieldEngineerReportController::class, 'index'])->name('reports.field-engineer.index');
    Route::get('reports/field-engineer/stats', [FieldEngineerReportController::class, 'stats'])->name('reports.field-engineer.stats');
    Route::get('reports/field-engineer/buildings', [FieldEngineerReportController::class, 'buildings']);
    Route::get('reports/field-engineer/buildings/data', [FieldEngineerReportController::class, 'buildings'])->name('reports.field-engineer.buildings');
    Route::get('reports/field-engineer/housing-units', [FieldEngineerReportController::class, 'housingUnits']);
    Route::get('reports/field-engineer/housing-units/data', [FieldEngineerReportController::class, 'housingUnits'])->name('reports.field-engineer.housing-units');
    Route::get('reports/field-engineer/edits', [FieldEngineerReportController::class, 'edits']);
    Route::get('reports/field-engineer/edits/data', [FieldEngineerReportController::class, 'edits'])->name('reports.field-engineer.edits');
    Route::get('reports/field-engineer/status-history', [FieldEngineerReportController::class, 'statusHistory']);
    Route::get('reports/field-engineer/status-history/data', [FieldEngineerReportController::class, 'statusHistory'])->name('reports.field-engineer.status-history');
    Route::get('reports/field-engineer/assignments', [FieldEngineerReportController::class, 'assignments']);
    Route::get('reports/field-engineer/assignments/data', [FieldEngineerReportController::class, 'assignments'])->name('reports.field-engineer.assignments');
    Route::get('reports/field-engineer/export/{tab}/{format}', [FieldEngineerReportController::class, 'export'])->name('reports.field-engineer.export');
    Route::get('reports/daily-achievement', [reportController::class, 'dailyAchievement'])->name('reports.daily-achievement');
    Route::get('reports/daily-achievement/export', [reportController::class, 'exportDailyAchievement'])->name('reports.daily-achievement.export');
    Route::get('reports/auditors-daily', [reportController::class, 'auditorsDailyAchievement'])->name('reports.auditors-daily');
    Route::get('reports/lawyers-daily', [reportController::class, 'lawyersDailyAchievement'])->name('reports.lawyers-daily');
    Route::get('reports/hlp-audit', [reportController::class, 'hlpAudit'])->name('reports.hlp-audit');
    Route::get('reports/hlp-audit/export', [reportController::class, 'exportHlpAudit'])->name('reports.hlp-audit.export');
    Route::get('reports/public-buildings', [SurveyReportController::class, 'publicBuildings'])->name('reports.public-buildings');
    Route::get('reports/road-facilities', [SurveyReportController::class, 'roadFacilities'])->name('reports.road-facilities');

    // Ensure this matches your URL: phc/audit
    Route::get('/audit', [auditController::class, 'index'])->name('audit.index');
    Route::get('/audit/export', [auditController::class, 'export'])->name('audit.export');
    Route::get('/audit/dashboard', [auditController::class, 'dashboard'])->name('audit.dashboard');
    Route::post('/assign', [auditController::class, 'assign'])->name('audit.assign');
    Route::get('/auditBuilding', [auditController::class, 'auditBuilding'])->name('audit.auditBuilding');
    Route::get('/engineer-table', [AuditController::class, 'engineerTable']);
    Route::get('/lawyer-table', [AuditController::class, 'lawyerTable']);
    Route::post('/audit/buildings/final-approve/import', [auditController::class, 'importFinalApprove'])
        ->name('audit.building.finalApprove.import');
    // attendence

    // assessmentAudit

    Route::get('showAssessmentAudit/{buildingGlobalid}/{housingGlobalid?}', [auditController::class, 'showAssessmentAudit']);
    Route::post('audit/building/final-approve', [auditController::class, 'finalApproveSelected'])
        ->name('audit.building.finalApprove');
    Route::post('audit/building/undp-final-approve', [auditController::class, 'undpFinalApproveSelected'])
        ->name('audit.building.undpFinalApprove');
    Route::post('/assessment/inline-update', [auditController::class, 'updateInlineAssessment'])
        ->name('assessment.inline.update');
    Route::get('/assessment/inline-history', [auditController::class, 'inlineAssessmentHistory'])
        ->name('assessment.inline.history');

    Route::get('/housing-units-by-building', [auditController::class, 'housingUnitsByBuilding'])
        ->name('housing.units.by.building');

    Route::post('/housing-assessment/set-status', [auditController::class, 'setHousingStatus'])
        ->name('housing.assessment.set.status');
    Route::post('/building/status', [auditController::class, 'setStatus'])
        ->name('building.assessment.set.status');

    Route::get('audit/building-status-history', [auditController::class, 'buildingHistory'])
        ->name('building.status.history');

    Route::get('audit/housing-status-history', [auditController::class, 'housingHistory'])
        ->name('housing.status.history');
    Route::get('assessment/notes/edit-data', [AuditController::class, 'getEditableNote'])
        ->name('assessment.notes.edit.data');

    Route::post('assessment/notes/update', [AuditController::class, 'updateNote'])
        ->name('assessment.notes.update');
    Route::get('audit/building-history', [auditController::class, 'buildingHistory'])
        ->name('audit.building.history');

    Route::post('audit/building-history/delete', [auditController::class, 'deleteHistory'])
        ->name('audit.building.history.delete');

    Route::get('/export-data', [ExportDataController::class, 'index'])->name('export.data.index');
    Route::post('/export-data', [ExportDataController::class, 'export'])->name('export.data.download');
    Route::post('/export-data/objectids/import', [ExportDataController::class, 'importObjectIds'])->name('export.data.objectids.import');
    Route::post('/export-data/objectids/reset', [ExportDataController::class, 'resetImportedObjectIds'])->name('export.data.objectids.reset');

    Route::post('/export/start', [ExportDataController::class, 'export'])->name('export.start');
    Route::get('/export/status/{id}', [ExportDataController::class, 'check'])
        ->name('export.status');
    Route::post('/exports/{id}/cancel', [ExportDataController::class, 'cancel'])
        ->name('exports.cancel');

    Route::post('/exports/start', [ExportDataController::class, 'export']);
    Route::get('/exports/check/{id}', [ExportDataController::class, 'check']);
    Route::post('/exports/{id}/cancel', [ExportDataController::class, 'cancel']);

    Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

        Route::get('/team-leader-field-engineers', [TeamLeaderFieldEngineerController::class, 'index'])
            ->name('team-leader-field-engineers.index');

        Route::get('/team-leader-field-engineers/data', [TeamLeaderFieldEngineerController::class, 'datatable'])
            ->name('team-leader-field-engineers.data');

        Route::get('/team-leader-field-engineers/select/team-leaders', [TeamLeaderFieldEngineerController::class, 'teamLeadersSelect'])
            ->name('team-leader-field-engineers.select.team-leaders');

        Route::get('/team-leader-field-engineers/select/field-engineers', [TeamLeaderFieldEngineerController::class, 'fieldEngineersSelect'])
            ->name('team-leader-field-engineers.select.field-engineers');

        Route::post('/team-leader-field-engineers', [TeamLeaderFieldEngineerController::class, 'store'])
            ->name('team-leader-field-engineers.store');

        Route::delete('/team-leader-field-engineers/{teamLeaderFieldEngineer}', [TeamLeaderFieldEngineerController::class, 'destroy'])
            ->name('team-leader-field-engineers.destroy');

        Route::get('/team-leader-field-engineers/export', [TeamLeaderFieldEngineerController::class, 'export'])
            ->name('team-leader-field-engineers.export');

        Route::get('/team-leader-field-engineers/data', [TeamLeaderFieldEngineerController::class, 'data'])
            ->name('team-leader-field-engineers.data');
    });
    Route::middleware(['auth', 'role_or_permission:Database Officer|login-logs.view'])->group(function () {
        Route::get('/login-logs', [LoginLogController::class, 'index'])
            ->name('login-logs.index');

        Route::get('/login-logs/data', [LoginLogController::class, 'data'])
            ->name('login-logs.data');
    });

    Route::get('/arcgis-count', function () {

        $token = app(\App\Services\ArcgisService::class)->getToken();

        $url = 'https://services2.arcgis.com/VoOot7GfoaREFqQk/arcgis/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/query';

        $response = Http::get($url, [
            'where' => '1=1',
            'returnCountOnly' => 'true',
            'f' => 'json',
            'token' => $token,
        ]);

        return $response->json();
    })->middleware('role_or_permission:Database Officer|system.maintenance');
});

Route::get('/housing-summary', [auditController::class, 'housingSummary'])
    ->name('housing.summary');
use App\Http\Controllers\BuildingImportController;

Route::get('/import-buildings-test', [BuildingImportController::class, 'import']);
use App\Http\Controllers\HousingUnitImportController;

Route::get('/import-housing-units', [HousingUnitImportController::class, 'import']);
require __DIR__.'/auth.php';
