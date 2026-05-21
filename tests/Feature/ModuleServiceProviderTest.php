<?php

use App\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

it('registers the module service provider', function () {
    $providers = require base_path('bootstrap/providers.php');

    expect($providers)->toContain(ModuleServiceProvider::class);
});

it('loads module routes and view namespaces', function () {
    expect(Route::has('damageAssessment.index'))->toBeTrue()
        ->and(View::getFinder()->getHints())->toHaveKeys([
            'damage-assessment',
            'damage-assessment-borrowers',
        ])
        ->and(view()->exists('damage-assessment::dashboard.damageAssessment'))->toBeTrue();
});

it('loads module web routes with session middleware', function () {
    $route = Route::getRoutes()->getByName('damageAssessment.index');

    expect($route->uri())->toBe('damage-assessment/damageAssessment')
        ->and($route->gatherMiddleware())
        ->toContain('web')
        ->toContain('auth');
});

it('redirects legacy damage assessment urls to the module prefix', function (string $legacyUrl, string $expectedUrl) {
    $this->get($legacyUrl)->assertRedirect($expectedUrl);
})->with([
    'dashboard' => ['/damageAssessment?period=day', '/damage-assessment/damageAssessment?period=day'],
    'building show' => ['/building/123', '/damage-assessment/building/123'],
    'audit page' => ['/showAssessmentAudit/building-global-id/unit-global-id', '/damage-assessment/showAssessmentAudit/building-global-id/unit-global-id'],
    'reports' => ['/reports/field-engineer?tab=buildings', '/damage-assessment/reports/field-engineer?tab=buildings'],
]);
