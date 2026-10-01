<?php

use App\Models\User;
use App\Support\Navigation\SectorNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;

it('builds sector workspaces around monitoring review decisions reports and exports', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(collect(SectorNavigation::forUser('buildings', $user))->pluck('key')->all())->toBe([
        'records',
        'monitoring',
        'audit',
        'decisions',
        'reports',
        'export',
    ])->and(collect(SectorNavigation::forUser('public-buildings', $user))->pluck('key')->all())->toBe([
        'records',
        'audit',
        'reports',
        'export',
    ]);
});

it('only shows sector tools available to the user role', function () {
    $role = Role::findOrCreate('Team Leader', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(collect(SectorNavigation::forUser('buildings', $user))->pluck('key')->all())->toBe([
        'records',
        'monitoring',
        'audit',
        'decisions',
        'reports',
        'export',
    ]);
});

it('distributes monitoring review and committee links into damage sector tabs', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $tabs = collect(SectorNavigation::forUser('buildings', $user))->keyBy('key');
    $pathsFor = fn (string $key): array => collect($tabs[$key]['items'])
        ->pluck('url')
        ->map(fn (string $url): string => (string) parse_url($url, PHP_URL_PATH))
        ->all();

    expect($pathsFor('monitoring'))
        ->toContain((string) parse_url(route('damageAssessment.index'), PHP_URL_PATH))
        ->toContain((string) parse_url(route('audit.dashboard'), PHP_URL_PATH))
        ->and($pathsFor('audit'))
        ->toContain((string) parse_url(route('audit.index'), PHP_URL_PATH))
        ->toContain((string) parse_url(route('audit.auditBuilding'), PHP_URL_PATH))
        ->toContain((string) parse_url(route('audit.fieldEngineer'), PHP_URL_PATH))
        ->toContain((string) parse_url(route('area-manager-review.index'), PHP_URL_PATH))
        ->and($pathsFor('decisions'))
        ->toContain((string) parse_url(route('committee-decisions.index'), PHP_URL_PATH))
        ->toContain((string) parse_url(route('committee-decisions.higher-committee-reassessments.index'), PHP_URL_PATH))
        ->toContain((string) parse_url(route('committee-members.index'), PHP_URL_PATH))
        ->toContain((string) parse_url(route('committee-archive.index'), PHP_URL_PATH));
});

it('shows legal unit review only to authorized housing sector users', function () {
    $legalAuditorRole = Role::findOrCreate('Legal Auditor', 'web');
    $auditingSupervisorRole = Role::findOrCreate('Auditing Supervisor', 'web');
    $legalAuditor = User::factory()->create([
        'name' => \App\Support\Audit\RestrictedLawyerAuditAccess::ALAA_KATOU,
    ]);
    $legalAuditor->assignRole($legalAuditorRole);
    $auditingSupervisor = User::factory()->create();
    $auditingSupervisor->assignRole($auditingSupervisorRole);

    $auditTitlesFor = fn (User $user): array => collect(SectorNavigation::forUser('housing-units', $user))
        ->firstWhere('key', 'audit')['items'];

    expect(collect($auditTitlesFor($legalAuditor))->pluck('title')->all())
        ->toContain('menu.sector_navigation.audit_items.legal_units')
        ->and(collect($auditTitlesFor($auditingSupervisor))->pluck('title')->all())
        ->not->toContain('menu.sector_navigation.audit_items.legal_units');
});

it('preserves report visibility rules while moving reports into sectors', function () {
    $role = Role::findOrCreate('Auditing Supervisor', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $reportTitles = collect(SectorNavigation::forUser('buildings', $user))
        ->firstWhere('key', 'reports')['items'];

    expect(collect($reportTitles)->pluck('title')->all())
        ->not->toContain('menu.sector_navigation.report_items.area_productivity')
        ->toContain('menu.sector_navigation.report_items.field_engineer')
        ->toContain('menu.sector_navigation.report_items.daily_audit')
        ->toContain('menu.sector_navigation.report_items.engineer_audit');
});

it('distributes every report into its related sector report tab', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $sectorReports = collect([
        'buildings',
        'housing-units',
        'public-buildings',
        'road-facilities',
        'cso-surveys',
    ])->flatMap(function (string $sector) use ($user): array {
        $reportsTab = collect(SectorNavigation::forUser($sector, $user))->firstWhere('key', 'reports');

        return collect($reportsTab['items'])
            ->pluck('url')
            ->map(fn (string $url): string => (string) parse_url($url, PHP_URL_PATH))
            ->all();
    })->unique()->values()->all();

    $expectedReports = collect([
        'reports.area-productivity.buildings',
        'reports.area-productivity.housing-units',
        'reports.area-productivity.public-buildings',
        'reports.area-productivity.road-facilities',
        'reports.area-productivity.cso-surveys',
        'reports.building-productivity.index',
        'reports.productivity',
        'reports.field-engineer.index',
        'reports.daily-achievement',
        'reports.hlp-audit',
        'reports.engineer-audit',
        'reports.public-buildings',
        'reports.road-facilities',
    ])->map(fn (string $routeName): string => (string) parse_url(route($routeName), PHP_URL_PATH))->all();

    expect($sectorReports)->toContain(...$expectedReports);
});

it('preserves the selected sector on shared audit and export pages', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $tabs = collect(SectorNavigation::forUser('housing-units', $user))->keyBy('key');

    expect($tabs['audit']['url'])->toContain('sector=housing-units')
        ->and($tabs['export']['url'])->toContain('sector=housing-units');
});

it('renders an accessible sector navigation with localized labels', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);
    app()->setLocale('ar');

    $html = Blade::render("@include('damage-assessment::components.sector-navigation', ['sector' => 'road-facilities'])");

    expect($html)
        ->toContain('مساحة قطاع الطرق')
        ->toContain('التنقل داخل قطاع الطرق')
        ->toContain('السجلات')
        ->toContain('التدقيق')
        ->toContain('التقارير')
        ->toContain('إنتاجية المناطق')
        ->toContain('تصدير البيانات');
});

it('marks the current sector tab as active', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $request = Request::create('/damage-assessment/road-facilities');
    $route = app('router')->getRoutes()->getByName('road-facilities.index');
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    $tabs = collect(SectorNavigation::forUser('road-facilities', $user))->keyBy('key');

    expect($tabs['records']['is_active'])->toBeTrue()
        ->and($tabs['audit']['is_active'])->toBeFalse()
        ->and(SectorNavigation::sectorForCurrentRoute())->toBe('road-facilities');
});
