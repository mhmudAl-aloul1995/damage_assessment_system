<?php

use App\Http\Controllers\Admin\TeamLeaderFieldEngineerController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\UserManagement\PermissionController;
use App\Http\Controllers\UserManagement\roleController;
use App\Http\Controllers\UserManagement\userController;
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

    Route::prefix('admin/team-leader-field-engineers')
        ->name('team-leader-field-engineers.')
        ->group(function () {
            Route::get('/', [TeamLeaderFieldEngineerController::class, 'index'])->name('index');
            Route::post('/', [TeamLeaderFieldEngineerController::class, 'store'])->name('store');
            Route::get('/datatable', [TeamLeaderFieldEngineerController::class, 'datatable'])->name('datatable');
            Route::delete('/{teamLeaderFieldEngineer}', [TeamLeaderFieldEngineerController::class, 'destroy'])->name('destroy');
        });

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

});

require __DIR__.'/modules/damage-assessment.php';
require __DIR__.'/auth.php';
