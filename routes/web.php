<?php

use App\Http\Controllers\Admin\LocalDatabaseImportController;
use App\Http\Controllers\Admin\TeamLeaderFieldEngineerController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\UserManagement\PermissionController;
use App\Http\Controllers\UserManagement\roleController;
use App\Http\Controllers\UserManagement\userController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpFoundation\Response;

$configuredBasePath = trim(app_deduplicated_path((string) parse_url((string) config('app.url'), PHP_URL_PATH)), '/');
$supportedBasePaths = array_values(array_unique(array_filter([
    $configuredBasePath,
    'phc',
    'damage_assessment_system',
])));

foreach ($supportedBasePaths as $supportedBasePath) {
    Route::any($supportedBasePath.'/{path?}', function (Request $request, ?string $path = null) use ($supportedBasePath): Response {
        $targetPath = app_deduplicated_path('/'.ltrim($path ?? '', '/'));
        $requestPath = '/'.trim($request->path(), '/');
        $duplicatedPrefix = '/'.$supportedBasePath.'/'.$supportedBasePath;

        if ($requestPath === $duplicatedPrefix || str_starts_with($requestPath, $duplicatedPrefix.'/')) {
            while ($targetPath === '/'.$supportedBasePath || str_starts_with($targetPath, '/'.$supportedBasePath.'/')) {
                $targetPath = substr($targetPath, strlen('/'.$supportedBasePath)) ?: '/';
            }

            $queryString = $request->getQueryString();

            return new SymfonyRedirectResponse(app_path_url($targetPath).($queryString !== null ? '?'.$queryString : ''));
        }

        $server = array_replace($request->server->all(), [
            'REQUEST_URI' => $targetPath.($request->getQueryString() !== null ? '?'.$request->getQueryString() : ''),
            'PATH_INFO' => $targetPath,
        ]);

        $forwardedRequest = Request::create(
            $server['REQUEST_URI'],
            $request->method(),
            $request->request->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent()
        );

        if ($request->hasSession()) {
            $forwardedRequest->setLaravelSession($request->session());
        }

        $app = app();
        $originalRequest = $app['request'];

        $app->instance('request', $forwardedRequest);

        try {
            return app('router')->dispatch($forwardedRequest);
        } finally {
            $app->instance('request', $originalRequest);
        }
    })->where('path', '.*');
}

Route::get('/', function () {

    return redirect()->to(app_route('login'));
});

Route::get('/clear-session', function () {
    auth()->guard('web')->logout();

    session()->invalidate();
    session()->regenerateToken();

    return redirect()
        ->to(app_route('login'))
        ->withCookie(cookie()->forget('laravel-session', '/'))
        ->withCookie(cookie()->forget('laravel-session', '/phc'))
        ->withCookie(cookie()->forget('phc_session', '/'))
        ->withCookie(cookie()->forget('phc_session', '/phc'))
        ->withCookie(cookie()->forget('XSRF-TOKEN', '/'))
        ->withCookie(cookie()->forget('XSRF-TOKEN', '/phc'));
})->name('session.clear');

if (app()->environment('testing')) {
    Route::get('/testing/legacy-login-redirect', fn (): RedirectResponse => redirect()->away('/login.php'));
    Route::get('/testing/duplicated-base-redirect', fn (): RedirectResponse => redirect()->away('/damage_assessment_system/damage_assessment_system/damage-assessment/damageAssessment'));
}

$legacyDamageAssessmentPrefixes = [
    'damageAssessment' => 'damage-assessment/damageAssessment',
    'assessmentAll' => 'damage-assessment/assessmentAll',
    'assessment' => 'damage-assessment/assessment',
    'showAssessmentAudit' => 'damage-assessment/showAssessmentAudit',
    'showAassessmentAudit' => 'damage-assessment/showAassessmentAudit',
    'showBuildings' => 'damage-assessment/showBuildings',
    'showHousings' => 'damage-assessment/showHousings',
    'showHousing' => 'damage-assessment/showHousing',
    'building' => 'damage-assessment/building',
    'housing' => 'damage-assessment/housing',
    'public-buildings' => 'damage-assessment/public-buildings',
    'road-facilities' => 'damage-assessment/road-facilities',
    'housing-units-map' => 'damage-assessment/housing-units-map',
    'public-buildings-map' => 'damage-assessment/public-buildings-map',
    'road-facilities-map' => 'damage-assessment/road-facilities-map',
    'audit' => 'damage-assessment/audit',
    'auditBuilding' => 'damage-assessment/auditBuilding',
    'area-manager-review' => 'damage-assessment/area-manager-review',
    'inf-audit' => 'damage-assessment/inf-audit',
    'attendance' => 'damage-assessment/attendance',
    'committee-decisions' => 'damage-assessment/committee-decisions',
    'committee-members' => 'damage-assessment/committee-members',
    'engineer' => 'damage-assessment/engineer',
    'engineerAssessments' => 'damage-assessment/engineerAssessments',
    'engineers' => 'damage-assessment/engineers',
    'field-engineer' => 'damage-assessment/field-engineer',
    'reports' => 'damage-assessment/reports',
    'export-data' => 'damage-assessment/export-data',
    'exports' => 'damage-assessment/exports',
    'export' => 'damage-assessment/export',
    'export_building' => 'damage-assessment/export_building',
    'export_housings' => 'damage-assessment/export_housings',
    'export_productivity' => 'damage-assessment/export_productivity',
    'sync' => 'damage-assessment/sync',
    'search-buildings' => 'damage-assessment/search-buildings',
    'global-search' => 'damage-assessment/global-search',
];

foreach ($legacyDamageAssessmentPrefixes as $legacyPrefix => $modulePrefix) {
    Route::get($legacyPrefix.'/{path?}', function (?string $path = null) use ($modulePrefix): RedirectResponse {
        $target = $modulePrefix.($path !== null ? '/'.$path : '');
        $query = request()->getQueryString();

        return redirect()->to($query !== null ? $target.'?'.$query : $target);
    })->where('path', '.*');
}

Route::get('/manifest.webmanifest', function (): Response {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true) ?: [];

    $manifest['id'] = app_path_url('/');
    $manifest['start_url'] = app_path_url('/login');
    $manifest['scope'] = app_path_url('/');

    if (isset($manifest['icons'])) {
        foreach ($manifest['icons'] as &$icon) {
            if (isset($icon['src'])) {
                $icon['src'] = app_path_url($icon['src']);
            }
        }
        unset($icon);
    }

    if (isset($manifest['shortcuts'])) {
        foreach ($manifest['shortcuts'] as &$shortcut) {
            if (isset($shortcut['url'])) {
                $shortcut['url'] = app_path_url($shortcut['url']);
            }

            if (isset($shortcut['icons'])) {
                foreach ($shortcut['icons'] as &$icon) {
                    if (isset($icon['src'])) {
                        $icon['src'] = app_path_url($icon['src']);
                    }
                }
                unset($icon);
            }
        }
        unset($shortcut);
    }

    return response()
        ->json($manifest)
        ->header('Content-Type', 'application/manifest+json');
})->name('pwa.manifest');

Route::get('/{pwaIcon}', function (string $pwaIcon): Response {
    return response()->file(public_path($pwaIcon), [
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('pwaIcon', 'icon-(72x72|96x96|128x128|144x144|152x152|192x192|384x384|512x512)\.png')
    ->name('pwa.icon');

Route::get('/sw.js', function (): Response {
    return response((string) file_get_contents(public_path('sw.js')), 200, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Service-Worker-Allowed' => app_path_url('/'),
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->name('pwa.service-worker');

Route::get('/background-sync.js', function (): Response {
    return response((string) file_get_contents(public_path('background-sync.js')), 200, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->name('pwa.background-sync');

Route::get('/pwa-install.js', function (): Response {
    return response((string) file_get_contents(public_path('pwa-install.js')), 200, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->name('pwa.install-script');

Route::get('/offline.html', function (): Response {
    return response((string) file_get_contents(public_path('offline.html')), 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-cache',
    ]);
})->name('pwa.offline');

Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');
/* Route::get('/', action: [damageAssessmentController::class, 'index']);
 */
Route::get('/dashboard', function () {
    return redirect()->to(app_route('damageAssessment.index'));
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
        $serverPullUrl = config('app.server_pull_url');

        $addResult = Process::path($repo)
            ->run(['git', '-c', 'safe.directory='.$safeRepo, 'add', '.']);

        if (! $addResult->successful()) {
            return response()->json([
                'status' => 'failed',
                'command' => 'git add .',
                'error' => $addResult->errorOutput() ?: $addResult->output(),
                'exit_code' => $addResult->exitCode(),
            ], 500);
        }

        $diffResult = Process::path($repo)
            ->run(['git', '-c', 'safe.directory='.$safeRepo, 'diff', '--cached', '--quiet']);

        if ($diffResult->exitCode() === 1) {
            $commitResult = Process::path($repo)
                ->run(['git', '-c', 'safe.directory='.$safeRepo, 'commit', '-m', 'Auto-update: '.now()->toDateTimeString()]);

            if (! $commitResult->successful()) {
                return response()->json([
                    'status' => 'failed',
                    'command' => 'git commit',
                    'error' => $commitResult->errorOutput() ?: $commitResult->output(),
                    'exit_code' => $commitResult->exitCode(),
                ], 500);
            }

            $pushResult = Process::path($repo)
                ->run(['git', '-c', 'safe.directory='.$safeRepo, 'push']);

            if (! $pushResult->successful()) {
                return response()->json([
                    'status' => 'failed',
                    'command' => 'git push',
                    'error' => $pushResult->errorOutput() ?: $pushResult->output(),
                    'exit_code' => $pushResult->exitCode(),
                ], 500);
            }
        } elseif ($diffResult->exitCode() !== 0) {
            return response()->json([
                'status' => 'failed',
                'command' => 'git diff --cached --quiet',
                'error' => $diffResult->errorOutput() ?: $diffResult->output(),
                'exit_code' => $diffResult->exitCode(),
            ], 500);
        }

        return redirect()->away($serverPullUrl);

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
        Route::get('/local-database-import', [LocalDatabaseImportController::class, 'index'])
            ->middleware('role_or_permission:Database Officer|system.maintenance')
            ->name('local-database-import.index');

        Route::post('/local-database-import', [LocalDatabaseImportController::class, 'store'])
            ->middleware('role_or_permission:Database Officer|system.maintenance')
            ->name('local-database-import.store');

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

require __DIR__.'/auth.php';
