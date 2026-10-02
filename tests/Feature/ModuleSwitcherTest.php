<?php

use App\Models\User;
use App\Support\Navigation\Sidebar;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::findOrCreate('Database Officer', 'web'));
});

it('selects the module from a nested page URL', function (string $path, string $moduleKey) {
    app()->instance('request', Request::create($path));

    expect(Sidebar::currentModule(Sidebar::forUser($this->user))['key'])->toBe($moduleKey);
})->with([
    'sector report' => ['/damage-assessment/reports/area-productivity/buildings', 'damage_assessment'],
    'shared decisions' => ['/damage-assessment/committee-decisions?sector=housing-units', 'damage_assessment'],
    'borrower record' => ['/damage-assessment-borrowers/10/edit', 'damage_assessment_borrowers'],
    'heks beneficiary' => ['/heks/beneficiaries/10', 'heks'],
    'user permissions' => ['/user-management/permissions', 'administration'],
    'administration tool' => ['/admin/dashboard-cards', 'administration'],
]);

it('opens each module on a destination visible to the user', function () {
    $modules = Sidebar::forUser($this->user)->keyBy('key');

    expect($modules['damage_assessment']['url'])->toBe('damage-assessment/damageAssessment')
        ->and($modules['damage_assessment_borrowers']['url'])->toBe('damage-assessment-borrowers')
        ->and($modules['heks']['url'])->toBe('heks')
        ->and($modules['administration']['url'])->toBe('user-management/user');

    $specialist = User::factory()->create();
    $specialist->assignRole(Role::findOrCreate('Inf - QC/QA Engineer', 'web'));

    expect(Sidebar::forUser($specialist)->first()['url'])->toBe('damage-assessment/inf-audit/public-buildings');
});

it('offers only enabled modules the user can access', function () {
    config(['modules.heks.enabled' => false]);
    $borrowerOfficer = User::factory()->create();
    $borrowerOfficer->assignRole(Role::findOrCreate('Project Officer - Borrowers', 'web'));

    expect(Sidebar::forUser($this->user)->pluck('key')->all())->not->toContain('heks')
        ->and(Sidebar::forUser($borrowerOfficer)->pluck('key')->all())->toBe(['damage_assessment_borrowers']);
});

it('falls back to the first available module on pages without a module', function () {
    app()->instance('request', Request::create('/dashboard'));

    expect(Sidebar::currentModule(Sidebar::forUser($this->user))['key'])->toBe('damage_assessment')
        ->and(Sidebar::currentModule(collect()))->toBeNull();
});

it('shows module destinations and identifies the current workspace in the picker', function () {
    app()->instance('request', Request::create('/heks/beneficiaries/10'));
    $modules = Sidebar::forUser($this->user);

    $this->view('layouts.partials.module-switcher', [
        'sidebarModules' => $modules,
        'currentSidebarModule' => Sidebar::currentModule($modules),
    ])->assertSee('aria-expanded="false"', false)
        ->assertSee('aria-controls="phc_module_options"', false)
        ->assertSee('data-module-option="damage_assessment"', false)
        ->assertSee('data-module-option="administration"', false)
        ->assertSee('href="'.url('heks').'"', false)
        ->assertSee('aria-current="true"', false);
});

it('shows just the current module sections in the sidebar', function () {
    app()->instance('request', Request::create('/damage-assessment/sectors/buildings'));

    $this->view('layouts.partials.sidebar-module-menu', [
        'currentSidebarModule' => Sidebar::currentModule(Sidebar::forUser($this->user)),
    ])->assertSee(url('damage-assessment/sectors/buildings'))
        ->assertSee(url('damage-assessment/sectors/housing-units'))
        ->assertDontSee(url('user-management/user'))
        ->assertDontSee('href="'.url('heks').'"', false);

    app()->instance('request', Request::create('/user-management/roles'));

    $this->view('layouts.partials.sidebar-module-menu', [
        'currentSidebarModule' => Sidebar::currentModule(Sidebar::forUser($this->user)),
    ])->assertSee(url('user-management/user'))
        ->assertDontSee(url('damage-assessment/sectors/buildings'));
});

it('does not offer a switch when only one module is available', function () {
    $borrowerOfficer = User::factory()->create();
    $borrowerOfficer->assignRole(Role::findOrCreate('Project Officer - Borrowers', 'web'));
    $modules = Sidebar::forUser($borrowerOfficer);

    $this->view('layouts.partials.module-switcher', [
        'sidebarModules' => $modules,
        'currentSidebarModule' => Sidebar::currentModule($modules),
    ])->assertSee('disabled', false)
        ->assertDontSee('data-bs-toggle="dropdown"', false)
        ->assertDontSee('data-module-option=', false);
});

it('renders the workspace switcher and isolated module navigation in the application layout', function () {
    $response = $this->actingAs($this->user)->get(route('heks.dashboard'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($document);
    $sidebarLinks = $xpath->query('//*[@id="kt_app_sidebar_menu"]//a');

    expect($xpath->query('//*[@id="phc_module_trigger"]'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-module-option]'))->toHaveCount(4)
        ->and($sidebarLinks)->toHaveCount(1)
        ->and($sidebarLinks->item(0)->getAttribute('href'))->toBe(url('heks'));
});
