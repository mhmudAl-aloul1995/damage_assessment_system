<?php

use App\Http\Controllers\auditContoller;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserManagement\userController;
use App\Http\Controllers\UserManagement\roleController;
use App\Http\Controllers\UserManagement\PermissionController;
use App\Http\Controllers\DamageAssessment\ArcGISController;
use App\Http\Controllers\DamageAssessment\buildingController;
use App\Http\Controllers\DamageAssessment\housingController;
use App\Http\Controllers\DamageAssessment\engineerController;
use App\Http\Controllers\DamageAssessment\damageAssessmentController;
use App\Http\Controllers\Report\reportController;
use Carbon\Carbon;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\DamageAssessment\auditController;
use Illuminate\Support\Facades\Process;

Route::get('/', function () {

    return redirect()->route('login');
});
/* Route::get('/', action: [damageAssessmentController::class, 'index']);
 */
Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->middleware(['auth', 'verified'])->name('forgot-password');

Route::get('/dashboard', function () {
    return redirect('damageAssessment');
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    Route::resource('sync', controller: ArcGISController::class);


  /*   Route::get('/pull', function () {
        // Run the git pull command in the project root
        $result = Process::path(base_path())
            ->run('git pull');

        if ($result->successful()) {
            return response()->json(['message' => 'Successfully pulled latest changes']);
        }

        return response()->json(['error' => $result->errorOutput()], 500);
    });
 */



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



    //engineers
    Route::resource(name: 'engineer', controller: engineerController::class);
    Route::get('engineerAssessments/{assignedto}', [engineerController::class, 'engineerAssessments']);
    Route::get(uri: 'assessment/{globalid}', action: [engineerController::class, 'showAssessment']);
    Route::get('/engineers/filter', [EngineerController::class, 'filter'])->name('engineers.filter');
    //Assessment
    Route::resource(name: 'damageAssessment', controller: damageAssessmentController::class);
    Route::get('/showBuildings', action: [damageAssessmentController::class, 'showBuildings']);
    Route::get('/showHousings', action: [damageAssessmentController::class, 'showHousings']);

    // Reports

    Route::get('reports/productivity', action: [reportController::class, 'productivity']);
    Route::get('/export_productivity', [reportController::class, 'export_productivity'])->name('export_productivity');

    Route::get('/search-buildings', [damageAssessmentController::class, 'search'])->name('buildings.search');

    Route::get('reports/commulative/export', [ReportController::class, 'exportCommulative'])->name('reports.commulative.export');
    Route::get('reports/commulative', action: [reportController::class, 'commulative'])->name('reports.commulative');



    // Ensure this matches your URL: phc/audit
    Route::get('/audit', [auditController::class, 'index'])->name('audit.index');
    Route::post('/assign', [auditController::class, 'assign'])->name('audit.assign');
    Route::get('/auditBuilding', [auditController::class, 'auditBuilding'])->name('audit.auditBuilding');
    Route::get('/engineer-table', [AuditController::class, 'engineerTable']);
    Route::get('/lawyer-table', [AuditController::class, 'lawyerTable']);


    // assessmentAudit

    Route::get('showAssessmentAudit/{globalid}', action: [auditController::class, 'showAssessmentAudit']);
    Route::post('/assessment/inline-update', [auditController::class, 'updateInlineAssessment'])
        ->name('assessment.inline.update');
});

require __DIR__ . '/auth.php';
