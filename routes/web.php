<?php

use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Committee\CommitteeDecisionController;
use App\Http\Controllers\Committee\CommitteeMemberController;
use App\Http\Controllers\Committee\TelegramBroadcastController;
use App\Http\Controllers\Committee\TelegramDestinationController;
use App\Http\Controllers\Committee\TelegramDiscoveredChatController;
use App\Http\Controllers\Committee\TelegramSettingsController;
use App\Http\Controllers\Committee\TelegramWebhookController;
use App\Http\Controllers\DamageAssessment\ArcGISController;
use App\Http\Controllers\DamageAssessment\AreaManagerRejectedBuildingsController;
use App\Http\Controllers\DamageAssessment\auditController;
use App\Http\Controllers\DamageAssessment\buildingController;
use App\Http\Controllers\DamageAssessment\damageAssessmentController;
use App\Http\Controllers\DamageAssessment\engineerController;
use App\Http\Controllers\DamageAssessment\ExportDataController;
use App\Http\Controllers\DamageAssessment\housingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicBuilding\PublicBuildingController;
use App\Http\Controllers\Report\AreaProductivityReportController;
use App\Http\Controllers\Report\reportController;
use App\Http\Controllers\Report\SurveyReportController;
use App\Http\Controllers\RoadFacility\RoadFacilityController;
use App\Http\Controllers\UserManagement\PermissionController;
use App\Http\Controllers\UserManagement\roleController;
use App\Http\Controllers\UserManagement\userController;
use App\Models\Attendance;
use App\Models\AttendanceImportLog;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
    Route::get('/gitPush', [engineerController::class, 'gitPush']);
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/run-migrations', function () {
        // This runs 'php artisan migrate'
        $exitCode = Artisan::call('migrate', [
            '--force' => true, // Required for running in production environments
        ]);

        return $exitCode === 0 ? 'Migration successful!' : 'Migration failed.';
    });

    Route::resource('sync', controller: ArcGISController::class);

    Route::get('/pull', function () {
        // Run the git pull command in the project root
        $result = Process::path(base_path())
            ->run('git pull');

        if ($result->successful()) {
            return response()->json(['message' => 'Successfully pulled latest changes']);
        }

        return response()->json(['error' => $result->errorOutput()], 500);
    });

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
    });
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
        Route::post('/{user}/telegram-link', [userController::class, 'telegramLink'])->name('users.telegram-link');

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

    Route::prefix('committee-decisions')->name('committee-decisions.')->group(function () {
        Route::get('/', [CommitteeDecisionController::class, 'index'])->name('index');
        Route::get('/buildings/data', [CommitteeDecisionController::class, 'buildingsData'])->name('buildings.data');
        Route::get('/housing-units/data', [CommitteeDecisionController::class, 'housingUnitsData'])->name('housing-units.data');
        Route::get('/buildings/{building}', [CommitteeDecisionController::class, 'showBuilding'])->name('buildings.show');
        Route::get('/housing-units/{housingUnit}', [CommitteeDecisionController::class, 'showHousingUnit'])->name('housing-units.show');
        Route::put('/{committeeDecision}', [CommitteeDecisionController::class, 'update'])->name('update');
        Route::post('/{committeeDecision}/sign', [CommitteeDecisionController::class, 'sign'])->name('sign');
        Route::post('/{committeeDecision}/retry-telegram', [CommitteeDecisionController::class, 'retryTelegram'])->name('retry-telegram');
    });

    Route::prefix('committee-members')->name('committee-members.')->group(function () {
        Route::get('/', [CommitteeMemberController::class, 'index'])->name('index');
        Route::get('/data', [CommitteeMemberController::class, 'data'])->name('data');
        Route::post('/', [CommitteeMemberController::class, 'store'])->name('store');
        Route::put('/{committeeMember}', [CommitteeMemberController::class, 'update'])->name('update');
        Route::delete('/{committeeMember}', [CommitteeMemberController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('telegram-integrations')->name('telegram-integrations.')->group(function () {
        Route::get('/', fn () => redirect()->route('telegram.destinations.index'))->name('index');
        Route::get('/data', [TelegramDestinationController::class, 'data'])->name('data');
        Route::post('/', [TelegramDestinationController::class, 'store'])->name('store');
        Route::post('/{telegramDestination}/refresh', [TelegramDestinationController::class, 'refresh'])->name('refresh');
        Route::post('/{telegramDestination}/disable', [TelegramDestinationController::class, 'disable'])->name('disable');
        Route::delete('/{telegramDestination}', [TelegramDestinationController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('telegram/settings')->name('telegram.settings.')->group(function () {
        Route::get('/', [TelegramSettingsController::class, 'index'])->name('index');
        Route::put('/', [TelegramSettingsController::class, 'update'])->name('update');
    });

    Route::prefix('telegram/destinations')->name('telegram.destinations.')->group(function () {
        Route::get('/', [TelegramDestinationController::class, 'index'])->name('index');
        Route::get('/data', [TelegramDestinationController::class, 'data'])->name('data');
        Route::post('/', [TelegramDestinationController::class, 'store'])->name('store');
        Route::get('/{telegramDestination}', [TelegramDestinationController::class, 'show'])->name('show');
        Route::put('/{telegramDestination}/preferences', [TelegramDestinationController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('/{telegramDestination}/regenerate-link', [TelegramDestinationController::class, 'regenerateLink'])->name('regenerate-link');
        Route::post('/{telegramDestination}/refresh', [TelegramDestinationController::class, 'refresh'])->name('refresh');
        Route::post('/{telegramDestination}/unlink', [TelegramDestinationController::class, 'unlink'])->name('unlink');
        Route::post('/{telegramDestination}/disable', [TelegramDestinationController::class, 'disable'])->name('disable');
        Route::delete('/{telegramDestination}', [TelegramDestinationController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('telegram/discovered-chats')->name('telegram.discovered.')->group(function () {
        Route::get('/', [TelegramDiscoveredChatController::class, 'index'])->name('index');
        Route::get('/data', [TelegramDiscoveredChatController::class, 'data'])->name('data');
        Route::post('/{telegramDiscoveredChat}/promote', [TelegramDiscoveredChatController::class, 'promote'])->name('promote');
    });

    Route::prefix('telegram/broadcasts')->name('telegram.broadcasts.')->group(function () {
        Route::get('/', [TelegramBroadcastController::class, 'index'])->name('index');
        Route::get('/data', [TelegramBroadcastController::class, 'data'])->name('data');
        Route::post('/', [TelegramBroadcastController::class, 'store'])->name('store');
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
    Route::get('/public-buildings/{publicBuilding}', [PublicBuildingController::class, 'show'])->name('public-buildings.show');
    Route::get('/road-facilities', [RoadFacilityController::class, 'index'])->name('road-facilities.index');
    Route::get('/road-facilities/data', [RoadFacilityController::class, 'data'])->name('road-facilities.data');
    Route::get('/road-facilities/export/{format}', [RoadFacilityController::class, 'export'])->name('road-facilities.export');
    Route::get('/road-facilities/{roadFacility}', [RoadFacilityController::class, 'show'])->name('road-facilities.show');

    // engineers
    Route::resource('engineer', controller: engineerController::class);
    Route::get('engineerAssessments/{assignedto}', [engineerController::class, 'engineerAssessments']);
    Route::get('assessment/{globalid}', action: [engineerController::class, 'showAssessment'])->name('assessment.show');
    Route::get('assessment/{globalid}/pdf', action: [engineerController::class, 'exportAssessmentPdf'])->name('assessment.pdf');
    Route::get('/engineers/filter', [EngineerController::class, 'filter'])->name('engineers.filter');
    Route::get('/assessmentAll', [EngineerController::class, 'assessmentAll'])->name('engineers.assessmentAll');
    // Assessment
    Route::resource('damageAssessment', controller: damageAssessmentController::class);
    Route::get('/showBuildings', action: [damageAssessmentController::class, 'showBuildings']);
    Route::get('/showHousings', action: [damageAssessmentController::class, 'showHousings']);
    Route::get('/housing-units-map', [damageAssessmentController::class, 'housingUnitsMap'])
        ->name('housing-units-map');
    Route::get('/public-buildings-map', [damageAssessmentController::class, 'publicBuildingsMap'])
        ->name('public-buildings-map');
    Route::get('/road-facilities-map', [damageAssessmentController::class, 'roadFacilitiesMap'])
        ->name('road-facilities-map');
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
    Route::get('reports/daily-achievement', [reportController::class, 'dailyAchievement'])->name('reports.daily-achievement');
    Route::get('reports/auditors-daily', [reportController::class, 'auditorsDailyAchievement'])->name('reports.auditors-daily');
    Route::get('reports/lawyers-daily', [reportController::class, 'lawyersDailyAchievement'])->name('reports.lawyers-daily');
    Route::get('reports/public-buildings', [SurveyReportController::class, 'publicBuildings'])->name('reports.public-buildings');
    Route::get('reports/road-facilities', [SurveyReportController::class, 'roadFacilities'])->name('reports.road-facilities');

    // Ensure this matches your URL: phc/audit
    Route::get('/audit', [auditController::class, 'index'])->name('audit.index');
    Route::get('/audit/dashboard', [auditController::class, 'dashboard'])->name('audit.dashboard');
    Route::post('/assign', [auditController::class, 'assign'])->name('audit.assign');
    Route::get('/auditBuilding', [auditController::class, 'auditBuilding'])->name('audit.auditBuilding');
    Route::get('/engineer-table', [AuditController::class, 'engineerTable']);
    Route::get('/lawyer-table', [AuditController::class, 'lawyerTable']);

    // attendence

    // assessmentAudit

    Route::get('showAssessmentAudit/{buildingGlobalid}/{housingGlobalid?}', [auditController::class, 'showAssessmentAudit']);
    Route::post('audit/building/final-approve', [auditController::class, 'finalApproveSelected'])
        ->name('audit.building.finalApprove');
    Route::post('/assessment/inline-update', [auditController::class, 'updateInlineAssessment'])
        ->name('assessment.inline.update');

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

    Route::post('/export/start', [ExportDataController::class, 'export'])->name('export.start');
    Route::get('/export/status/{id}', [ExportDataController::class, 'check'])
        ->name('export.status');
    Route::post('/exports/{id}/cancel', [ExportDataController::class, 'cancel'])
        ->name('exports.cancel');

    Route::post('/exports/start', [ExportDataController::class, 'export']);
    Route::get('/exports/check/{id}', [ExportDataController::class, 'check']);
    Route::post('/exports/{id}/cancel', [ExportDataController::class, 'cancel']);

});

Route::post('/api/telegram/webhook/{secret}', [TelegramWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('telegram.webhook');
Route::post('/telegram/webhook/{secret}', [TelegramWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class]);
use Spatie\Browsershot\Browsershot;

Route::get('/debug-pdf', function () {

    $pdfPath = storage_path('app/public/debug.pdf');

    Browsershot::html('
        <html lang="ar" dir="rtl">
        <head><meta charset="UTF-8"></head>
        <body>
            <h1>Ã™â€ Ã˜Â¬Ã˜Â­ Ã˜Â§Ã™â€žÃ˜ÂªÃ˜ÂµÃ˜Â¯Ã™Å Ã˜Â± Ã¢Å“â€¦</h1>
            <p>Browsershot Ã™Å Ã˜Â¹Ã™â€¦Ã™â€ž Ã˜Â¨Ã˜Â§Ã˜Â³Ã˜ÂªÃ˜Â®Ã˜Â¯Ã˜Â§Ã™â€¦ Edge</p>
        </body>
        </html>
    ')
        ->setNodeBinary('C:\\Program Files\\nodejs\\node.exe')
        ->setNpmBinary('C:\\Program Files\\nodejs\\npm.cmd')
        ->setNodeModulePath(base_path('node_modules'))
        ->setChromePath('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe')
        ->format('A4')
        ->showBackground()
        ->save($pdfPath);

    return response()->download($pdfPath);
});
require __DIR__.'/auth.php';
