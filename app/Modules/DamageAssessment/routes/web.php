<?php

use App\Modules\DamageAssessment\Http\Controllers\Attendance\AttendanceController;
use App\Modules\DamageAssessment\Http\Controllers\Audit\AreaManagerRejectedBuildingsController;
use App\Modules\DamageAssessment\Http\Controllers\Audit\AssessmentEditHistoryController;
use App\Modules\DamageAssessment\Http\Controllers\Audit\AssessmentInlineEditController;
use App\Modules\DamageAssessment\Http\Controllers\Audit\auditController;
use App\Modules\DamageAssessment\Http\Controllers\Audit\AuditDashboardController;
use App\Modules\DamageAssessment\Http\Controllers\Audit\AuditExportController;
use App\Modules\DamageAssessment\Http\Controllers\Audit\AuditStatusHistoryController;
use App\Modules\DamageAssessment\Http\Controllers\Committee\CommitteeDecisionController;
use App\Modules\DamageAssessment\Http\Controllers\Committee\CommitteeMemberController;
use App\Modules\DamageAssessment\Http\Controllers\Dashboard\DamageAssessmentController;
use App\Modules\DamageAssessment\Http\Controllers\Exports\ExportDataController;
use App\Modules\DamageAssessment\Http\Controllers\FieldOperations\BuildingSurveyReturnRequestController;
use App\Modules\DamageAssessment\Http\Controllers\FieldOperations\EngineerController;
use App\Modules\DamageAssessment\Http\Controllers\Imports\BuildingImportController;
use App\Modules\DamageAssessment\Http\Controllers\Imports\HousingUnitImportController;
use App\Modules\DamageAssessment\Http\Controllers\InfrastructureAudit\InfAuditPublicBuildingController;
use App\Modules\DamageAssessment\Http\Controllers\InfrastructureAudit\InfAuditRoadFacilityController;
use App\Modules\DamageAssessment\Http\Controllers\Integrations\ArcGISController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\AreaProductivityReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\BuildingProductivityReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\DailyAchievementReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\DamageStatisticsReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\EngineerAuditReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\FieldEngineerReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\HlpAuditReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\IndasPdfReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\phcPdfReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\ReportController;
use App\Modules\DamageAssessment\Http\Controllers\Reports\SurveyReportController;
use App\Modules\DamageAssessment\Http\Controllers\Surveys\Buildings\BuildingController;
use App\Modules\DamageAssessment\Http\Controllers\Surveys\HousingUnits\HousingUnitController;
use App\Modules\DamageAssessment\Http\Controllers\Surveys\PublicBuildings\PublicBuildingController;
use App\Modules\DamageAssessment\Http\Controllers\Surveys\RoadFacilities\RoadFacilityController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/gitPush', [EngineerController::class, 'gitPush'])
        ->middleware('role_or_permission:Database Officer|system.maintenance');

    Route::resource('sync', controller: ArcGISController::class);

    Route::get('/reports/phc', [phcPdfReportController::class, 'index'])
        ->name('damage-assessment.reports.phc');

    Route::get('/reports/phc/export', [phcPdfReportController::class, 'export'])
        ->name('damage-assessment.reports.phc.export');

    Route::get('/reports/indas', [IndasPdfReportController::class, 'index'])
        ->name('damage-assessment.reports.indas');

    Route::get('/reports/indas/export', [IndasPdfReportController::class, 'export'])
        ->name('damage-assessment.reports.indas.export');
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
    Route::resource('building', controller: BuildingController::class);
    Route::get('export_building', action: [BuildingController::class, 'export_building']);

    // housing
    Route::resource('housing', controller: HousingUnitController::class);
    Route::get('/showHousing/{globalid}', action: [HousingUnitController::class, 'index']);

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
    Route::resource('engineer', controller: EngineerController::class);
    Route::get('engineerAssessments/{assignedto}', [EngineerController::class, 'engineerAssessments']);
    Route::get('assessment/{globalid}', action: [EngineerController::class, 'showAssessment'])->name('assessment.show');
    Route::get('assessment/{globalid}/pdf', action: [EngineerController::class, 'exportAssessmentPdf'])->name('assessment.pdf');
    Route::get('/engineers/filter', [EngineerController::class, 'filter'])->name('engineers.filter');
    Route::get('/assessmentAll', [EngineerController::class, 'assessmentAll'])->name('engineers.assessmentAll');
    // Assessment
    Route::get('/damageAssessment/arcgis/options', [DamageAssessmentController::class, 'arcgisOptions'])
        ->name('damageAssessment.arcgis.options');
    Route::get('/damageAssessment/hud', [DamageAssessmentController::class, 'hud'])
        ->name('damageAssessment.hud');
    Route::get('/damageAssessment/hud/stats', [DamageAssessmentController::class, 'hudStats'])
        ->name('damageAssessment.hud.stats');
    Route::get('/damageAssessment/hud/building-units', [DamageAssessmentController::class, 'hudBuildingUnits'])
        ->name('damageAssessment.hud.building-units');
    Route::resource('damageAssessment', controller: DamageAssessmentController::class);
    Route::get('/showBuildings', action: [DamageAssessmentController::class, 'showBuildings']);
    Route::get('/showHousings', action: [DamageAssessmentController::class, 'showHousings']);
    Route::get('/housing-units-map', [DamageAssessmentController::class, 'housingUnitsMap'])
        ->name('housing-units-map');
    Route::get('/public-buildings-map', [DamageAssessmentController::class, 'publicBuildingsMap'])
        ->name('public-buildings-map');
    Route::get('/road-facilities-map', [DamageAssessmentController::class, 'roadFacilitiesMap'])
        ->name('road-facilities-map');
    Route::get('/api/get-latest-stats', [DamageAssessmentController::class, 'latestStats'])
        ->name('damageAssessment.latest-stats');
    // Reports

    Route::get('reports/productivity', action: [ReportController::class, 'productivity']);
    Route::get('/export_productivity', [ReportController::class, 'export_productivity'])->name('export_productivity');

    Route::get('/search-buildings', [DamageAssessmentController::class, 'search'])->name('buildings.search');
    Route::get('/global-search', [DamageAssessmentController::class, 'globalSearch'])->name('global-search');

    Route::get('reports/commulative/export', [ReportController::class, 'exportCommulative'])->name('reports.commulative.export');
    Route::get('reports/commulative', action: [ReportController::class, 'commulative'])->name('reports.commulative');
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
    Route::get('reports/daily-achievement', [DailyAchievementReportController::class, 'dailyAchievement'])->name('reports.daily-achievement');
    Route::get('reports/daily-achievement/export', [DailyAchievementReportController::class, 'exportDailyAchievement'])->name('reports.daily-achievement.export');
    Route::get('reports/auditors-daily', [DailyAchievementReportController::class, 'auditorsDailyAchievement'])->name('reports.auditors-daily');
    Route::get('reports/lawyers-daily', [DailyAchievementReportController::class, 'lawyersDailyAchievement'])->name('reports.lawyers-daily');
    Route::get('reports/hlp-audit', [HlpAuditReportController::class, 'index'])->name('reports.hlp-audit');
    Route::get('reports/hlp-audit/export', [HlpAuditReportController::class, 'export'])->name('reports.hlp-audit.export');
    Route::get('reports/engineer-audit', [EngineerAuditReportController::class, 'index'])->name('reports.engineer-audit');
    Route::get('reports/engineer-audit/export', [EngineerAuditReportController::class, 'export'])->name('reports.engineer-audit.export');
    Route::get('reports/public-buildings', [SurveyReportController::class, 'publicBuildings'])->name('reports.public-buildings');
    Route::get('reports/road-facilities', [SurveyReportController::class, 'roadFacilities'])->name('reports.road-facilities');

    // Ensure this matches your URL: phc/audit
    Route::get('/audit', [auditController::class, 'index'])->name('audit.index');
    Route::get('/field-engineer-audit', [auditController::class, 'fieldEngineerAudit'])->name('audit.fieldEngineer');
    Route::get('/audit/export', AuditExportController::class)->name('audit.export');
    Route::get('/audit/dashboard', AuditDashboardController::class)->name('audit.dashboard');
    Route::get('/audit/buildings/{building:globalid}/attachments', [auditController::class, 'buildingAttachments'])
        ->name('audit.building.attachments.index');
    Route::post('/audit/buildings/{building:globalid}/attachments', [auditController::class, 'storeBuildingAttachment'])
        ->name('audit.building.attachments.store');
    Route::post('/audit/buildings/{building:globalid}/attachments/{attachmentId}/replace', [auditController::class, 'replaceBuildingAttachment'])
        ->name('audit.building.attachments.replace');
    Route::delete('/audit/buildings/{building:globalid}/attachments/{attachmentId}', [auditController::class, 'destroyBuildingAttachment'])
        ->name('audit.building.attachments.destroy');
    Route::post('/assign', [auditController::class, 'assign'])->name('audit.assign');
    Route::get('/auditBuilding', [auditController::class, 'auditBuilding'])->name('audit.auditBuilding');
    Route::get('/engineer-table', [AuditController::class, 'engineerTable']);
    Route::get('/lawyer-table', [AuditController::class, 'lawyerTable']);
    Route::post('/audit/buildings/final-approve/import', [auditController::class, 'importFinalApprove'])
        ->name('audit.building.finalApprove.import');
    Route::post('/audit/buildings/legal-challenge', [auditController::class, 'updateBuildingLegalChallenge'])
        ->name('audit.building.legalChallenge');
    // attendence

    // assessmentAudit

    Route::get('showAssessmentAudit/{buildingGlobalid}/{housingGlobalid?}', [auditController::class, 'showAssessmentAudit']);
    Route::post('audit/building/final-approve', [auditController::class, 'finalApproveSelected'])
        ->name('audit.building.finalApprove');
    Route::post('audit/building/undp-final-approve', [auditController::class, 'undpFinalApproveSelected'])
        ->name('audit.building.undpFinalApprove');
    Route::post('/assessment/inline-update', [AssessmentInlineEditController::class, 'update'])
        ->name('assessment.inline.update');
    Route::get('/assessment/inline-history', [AssessmentInlineEditController::class, 'history'])
        ->name('assessment.inline.history');

    Route::get('/housing-units-by-building', [auditController::class, 'housingUnitsByBuilding'])
        ->name('housing.units.by.building');

    Route::post('/housing-assessment/set-status', [auditController::class, 'setHousingStatus'])
        ->name('housing.assessment.set.status');
    Route::post('/housing-assessment/legal-challenge', [auditController::class, 'updateHousingLegalChallenge'])
        ->name('housing.assessment.legalChallenge');
    Route::post('/building/status', [auditController::class, 'setStatus'])
        ->name('building.assessment.set.status');

    Route::get('audit/building-status-history', [AuditStatusHistoryController::class, 'buildingHistory'])
        ->name('building.status.history');

    Route::get('audit/housing-status-history', [AuditStatusHistoryController::class, 'housingHistory'])
        ->name('housing.status.history');
    Route::post('assessment/notes/update', [AuditStatusHistoryController::class, 'updateNote'])
        ->name('assessment.notes.update');
    Route::get('audit/building-history', [AuditStatusHistoryController::class, 'buildingHistory'])
        ->name('audit.building.history');

    Route::post('audit/building-history/delete', [AuditStatusHistoryController::class, 'deleteHistory'])
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
Route::get('/import-buildings-test', [BuildingImportController::class, 'import']);
Route::get('/import-housing-units', [HousingUnitImportController::class, 'import']);
