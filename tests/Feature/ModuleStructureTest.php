<?php

use App\Imports\AttendanceMultiSheetImport;
use App\Imports\AttendanceSheetImport;
use App\Jobs\SyncHeksKoboSubmission;
use App\Models\AttendanceImportLog;
use App\Models\Building;
use App\Models\CommitteeDecision;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Support\ArtisanCommandCatalog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

it('boots each module from its own provider and resources', function (string $key, string $directory, string $view): void {
    $provider = app()->getProvider(config('modules.'.$key.'.provider'));

    expect($provider)->not->toBeNull()
        ->and(str_replace('\\', '/', $provider->modulePath()))->toBe(str_replace('\\', '/', app_path('Modules/'.$directory)))
        ->and(view()->exists($view))->toBeTrue();

    $viewPath = view()->getFinder()->find($view);
    expect(str_replace('\\', '/', $viewPath))->toContain('/'.$directory.'/resources/views/');
    expect(file_get_contents($viewPath))->toContain("@extends('layouts.app')");
})->with([
    ['damage_assessment', 'DamageAssessment', 'damage-assessment::dashboard.damageAssessment'],
    ['damage_assessment_borrowers', 'DamageAssessmentBorrowers', 'damage-assessment-borrowers::index'],
    ['heks', 'Heks', 'heks::dashboard'],
]);

it('loads all module migration paths without duplicate migration names', function (): void {
    $paths = app('migrator')->paths();

    foreach (['DamageAssessment', 'DamageAssessmentBorrowers', 'Heks'] as $module) {
        expect(array_map(fn (string $path): string => str_replace('\\', '/', $path), $paths))
            ->toContain(str_replace('\\', '/', app_path('Modules/'.$module.'/database/migrations')));
    }

    $files = collect([database_path('migrations'), ...$paths])
        ->flatMap(fn (string $path): array => glob($path.'/*.php') ?: []);

    expect($files->map(fn (string $file): string => basename($file))->duplicates())->toBeEmpty()
        ->and(Schema::hasTable('buildings'))->toBeTrue()
        ->and(Schema::hasTable('damage_assessment_borrowers'))->toBeTrue()
        ->and(Schema::hasTable('heks_beneficiaries'))->toBeTrue();
});

it('preserves model identities and legacy factory discovery after moving files', function (): void {
    foreach ([
        Building::class => 'DamageAssessment',
        CommitteeDecision::class => 'DamageAssessment',
        DamageAssessmentBorrower::class => 'DamageAssessmentBorrowers',
        HeksBeneficiary::class => 'Heks',
    ] as $class => $module) {
        expect(str_replace('\\', '/', (new ReflectionClass($class))->getFileName()))
            ->toContain('/Modules/'.$module.'/app/Models/');
        expect((new $class)->getMorphClass())->toBe($class);
    }

    expect(CommitteeDecision::factory()->make())->toBeInstanceOf(CommitteeDecision::class);
});

it('can restore a HEKS job serialized using its original class name', function (): void {
    $payload = 'O:31:"App\\Jobs\\SyncHeksKoboSubmission":1:{s:12:"submissionId";i:123;}';
    $job = unserialize($payload);

    expect($job)->toBeInstanceOf(SyncHeksKoboSubmission::class)
        ->and($job->submissionId)->toBe(123);
});

it('registers moved commands and keeps them visible in the administration catalog', function (string $command, string $module): void {
    expect(Artisan::all())->toHaveKey($command);

    $entry = app(ArtisanCommandCatalog::class)->find($command);

    expect($entry)->not->toBeNull()
        ->and($entry['file'])->toContain('/Modules/'.$module.'/app/Console/Commands/');
})->with([
    ['sync:arcgis-layers', 'DamageAssessment'],
    ['borrowers:import', 'DamageAssessmentBorrowers'],
    ['heks:kobo-sync', 'Heks'],
]);

it('keeps module webhook URLs and their API middleware', function (string $name, string $uri): void {
    $route = Route::getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe($uri)
        ->and($route->gatherMiddleware())->toContain('api')->not->toContain('web');
})->with([
    ['api.heks.kobo-webhook.store', 'api/heks/kobo-webhook/{service}'],
    ['api.arcgis.csos.webhook', 'api/arcgis/csos/webhook'],
    ['api.kobo.submissions.store', 'api/kobo/{service}'],
]);

it('loads module configuration and merges navigation into the shared sidebar', function (): void {
    expect(config('heks_kobo.services.heks_main.normalized_handler'))->toBe('main');

    $sidebar = collect(config('sidebar'));
    foreach (['damage_assessment', 'damage_assessment_borrowers', 'heks', 'administration'] as $module) {
        expect($sidebar->where('module', $module))->not->toBeEmpty();
    }

    expect(view()->exists('layouts.partials.sidebar'))->toBeTrue();
});

it('keeps the two attendance import implementations independently loadable', function (): void {
    $log = new AttendanceImportLog;
    $singleSheet = new AttendanceSheetImport($log, '2026-10');
    $multipleSheets = (new AttendanceMultiSheetImport($log))->sheets();

    expect($singleSheet->title())->toBe('2026-10')
        ->and($multipleSheets['*'])->toBeInstanceOf(App\Imports\AttendanceMultiSheetRowImport::class);
});

it('loads assessment helpers from the module using their existing class names', function (): void {
    foreach ([
        App\Enums\BuildingDeletionStatus::class,
        App\Support\Audit\RestrictedLawyerAuditAccess::class,
        App\Support\Exports\ExportDataColumns::class,
        App\Support\Exports\CommitteeReviewArchiveFilter::class,
        App\Support\CsoSurveyDamageStatusNormalizer::class,
        App\Support\CsoDamageStatusMapper::class,
        App\Support\Forms\CsoSurveyLayout::class,
        App\Support\Forms\PublicBuildingSurveyLayout::class,
        App\Support\Forms\RoadFacilitySurveyLayout::class,
        App\Support\Forms\StaticSurveyLayout::class,
    ] as $class) {
        expect(str_replace('\\', '/', (new ReflectionClass($class))->getFileName()))
            ->toContain('/Modules/DamageAssessment/app/');
    }

    expect(App\Support\Forms\CsoSurveyLayout::choices())->not->toBeEmpty();
});

it('keeps module routes available when configuration and routes are cached', function (): void {
    $cachePrefix = 'bootstrap/cache/phc-module-'.bin2hex(random_bytes(8));
    $environment = [
        'APP_ENV' => 'testing',
        'APP_CONFIG_CACHE' => $cachePrefix.'-config.php',
        'APP_ROUTES_CACHE' => $cachePrefix.'-routes.php',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'CACHE_STORE' => 'array',
    ];

    $run = function (array $arguments) use ($environment): string {
        $process = new Process([PHP_BINARY, 'artisan', ...$arguments, '--no-interaction'], base_path(), $environment);
        $process->setTimeout(90);
        $process->mustRun();

        return $process->getOutput();
    };

    try {
        $uncached = json_decode($run(['route:list', '--json']), true, flags: JSON_THROW_ON_ERROR);
        $run(['config:cache']);
        $run(['route:cache']);
        $cached = json_decode($run(['route:list', '--json']), true, flags: JSON_THROW_ON_ERROR);

        expect($cached)->toHaveCount(count($uncached));

        foreach (['damageAssessment.index', 'damage-assessment-borrowers.index', 'heks.dashboard', 'api.heks.kobo-webhook.store', 'api.arcgis.csos.webhook'] as $name) {
            expect(collect($cached)->firstWhere('name', $name))
                ->toBe(collect($uncached)->firstWhere('name', $name));
        }

        $configuration = require base_path($environment['APP_CONFIG_CACHE']);
        expect($configuration['heks_kobo']['services']['heks_main']['normalized_handler'])->toBe('main');
    } finally {
        foreach (['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE'] as $key) {
            if (is_file(base_path($environment[$key]))) {
                unlink(base_path($environment[$key]));
            }
        }
    }
});
