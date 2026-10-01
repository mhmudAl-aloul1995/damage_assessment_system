<?php

use App\Models\User;
use App\Support\Navigation\SectorNavigation;
use App\Support\Navigation\Sidebar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Cache::put('arcgis_token', 'fake-arcgis-token', 3000);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-arcgis-token',
        ]),
        'https://services2.arcgis.com/*' => Http::response([
            'features' => [],
            'exceededTransferLimit' => false,
        ]),
    ]);
});

function sidebarUrlsFor(User $user): array
{
    return Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['is_direct'] ?? false ? [$section] : $section['items'])
        ->flatMap(fn (array $item) => $item['children'] ?? [$item])
        ->pluck('url')
        ->filter()
        ->all();
}

it('shows the sidebar menu for infrastructure Team Leaders', function () {
    $role = Role::findOrCreate('Team Leader -INF', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $sectionTitles = Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections']->pluck('title'))
        ->all();

    expect($sectionTitles)
        ->toContain('menu.damage_assessment.public_buildings')
        ->toContain('menu.damage_assessment.road_facilities')
        ->toContain('menu.damage_assessment.operations')
        ->not->toContain('menu.committee.title');
});

it('shows building survey return requests in the damage assessment sidebar', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(sidebarUrlsFor($user))->toContain('damage-assessment/field-engineer/building-survey-return-requests');
});

it('shows team leader field engineer assignment in the user management sidebar', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $administrationModule = Sidebar::forUser($user)->firstWhere('key', 'administration');

    expect($administrationModule['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->toContain('admin/team-leader-field-engineers');
});

it('removes the standalone reports section from the sidebar', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $damageAssessmentModule = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment');

    expect($damageAssessmentModule['sections']->pluck('title')->all())
        ->not->toContain('menu.reports.title')
        ->and(sidebarUrlsFor($user))
        ->not->toContain('damage-assessment/reports/productivity')
        ->not->toContain('damage-assessment/export-data');
});

it('shows missing citizen identities sidebar link to auditing supervisor and project officer', function (string $roleName) {
    $role = Role::findOrCreate($roleName, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $urls = sidebarUrlsFor($user);

    expect($urls)->toContain('damage-assessment/reports/missing-citizen-identities');
})->with([
    'auditing supervisor' => 'Auditing Supervisor',
    'project officer' => 'Project Officer',
]);

it('removes infrastructure audit links from the standalone sidebar', function () {
    $role = Role::findOrCreate('Project Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $urls = sidebarUrlsFor($user);

    expect($urls)
        ->not->toContain('damage-assessment/inf-audit/public-buildings')
        ->not->toContain('damage-assessment/inf-audit/roads')
        ->toContain('damage-assessment/public-buildings')
        ->toContain('damage-assessment/road-facilities');
});

it('shows all cso links to cso officers', function () {
    $role = Role::findOrCreate('CSO Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $urls = sidebarUrlsFor($user);

    expect($urls)
        ->toContain('damage-assessment/cso-surveys')
        ->not->toContain('damage-assessment/inf-audit/cso')
        ->not->toContain('damage-assessment/reports/area-productivity/cso-surveys')
        ->not->toContain('damage-assessment/cso-surveys/export-data')
        ->not->toContain('damage-assessment/building-deletions')
        ->not->toContain('damage-assessment/public-buildings')
        ->not->toContain('damage-assessment/road-facilities');
});

it('groups visible sidebar sections by module', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $modules = Sidebar::forUser($user);

    expect($modules->pluck('key')->all())->toContain('damage_assessment', 'administration');

    $damageAssessmentModule = $modules->firstWhere('key', 'damage_assessment');
    $administrationModule = $modules->firstWhere('key', 'administration');

    expect($damageAssessmentModule['sections']->pluck('title')->all())
        ->toContain(
            'menu.damage_assessment.dashboard',
            'menu.hud.title',
            'menu.damage_assessment.buildings',
            'menu.damage_assessment.housing_units',
            'menu.damage_assessment.public_buildings',
            'menu.damage_assessment.road_facilities',
            'menu.damage_assessment.cso_surveys',
            'menu.damage_assessment.operations',
        )
        ->not->toContain(
            'menu.damage_assessment.monitoring',
            'menu.audit.title',
            'menu.committee.title',
        );

    expect($administrationModule['sections']->pluck('title')->all())
        ->toContain('menu.user_management.title');
});

it('orders damage assessment sidebar sections by sector first', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $sectionTitles = Sidebar::forUser($user)
        ->firstWhere('key', 'damage_assessment')['sections']
        ->pluck('title')
        ->all();

    expect($sectionTitles)->toMatchArray([
        'menu.damage_assessment.dashboard',
        'menu.hud.title',
        'menu.damage_assessment.buildings',
        'menu.damage_assessment.housing_units',
        'menu.damage_assessment.public_buildings',
        'menu.damage_assessment.road_facilities',
        'menu.damage_assessment.cso_surveys',
        'menu.damage_assessment.operations',
        'menu.attendance.title',
    ]);
});

it('groups damage assessment navigation into clear ux sections', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $navigationGroups = Sidebar::forUser($user)
        ->firstWhere('key', 'damage_assessment')['sections']
        ->pluck('navigation_group')
        ->unique()
        ->values()
        ->all();

    expect($navigationGroups)->toBe([
        'menu.navigation_groups.overview',
        'menu.navigation_groups.sectors',
        'menu.navigation_groups.operations',
    ]);
});

it('exposes each damage assessment sector as a direct destination', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $sectorSections = Sidebar::forUser($user)
        ->firstWhere('key', 'damage_assessment')['sections']
        ->where('navigation_group', 'menu.navigation_groups.sectors')
        ->values();

    expect($sectorSections->pluck('title')->all())->toBe([
        'menu.damage_assessment.buildings',
        'menu.damage_assessment.housing_units',
        'menu.damage_assessment.public_buildings',
        'menu.damage_assessment.road_facilities',
        'menu.damage_assessment.cso_surveys',
    ])->and($sectorSections->every(fn (array $section): bool => $section['is_direct']))->toBeTrue();
});

it('keeps the standalone reports section hidden on sector export pages', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    app()->instance('request', Request::create('/damage-assessment/public-buildings/export-data'));

    $sections = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment')['sections'];

    expect($sections->firstWhere('title', 'menu.reports.title'))->toBeNull();
});

it('highlights the selected sector on shared review and decision pages', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $request = Request::create('/damage-assessment/committee-decisions?sector=housing-units');
    $route = app('router')->getRoutes()->getByName('committee-decisions.index');
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    $sections = Sidebar::forUser($user)
        ->firstWhere('key', 'damage_assessment')['sections']
        ->keyBy('sector');

    expect($sections['housing-units']['is_active'])->toBeTrue()
        ->and($sections['buildings']['is_active'])->toBeFalse();
});

it('removes standalone monitoring review and committee sections from the sidebar', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $sections = Sidebar::forUser($user)
        ->firstWhere('key', 'damage_assessment')['sections'];

    expect($sections->pluck('title')->all())
        ->not->toContain('menu.damage_assessment.monitoring')
        ->not->toContain('menu.audit.title')
        ->not->toContain('menu.committee.title');
});

it('opens sector links on the first tool available to specialist roles', function () {
    $mopwhRole = Role::findOrCreate('MOPWH', 'web');
    $fieldEngineerRole = Role::findOrCreate('Field Engineer', 'web');
    $infrastructureAuditorRole = Role::findOrCreate('Inf - QC/QA Engineer', 'web');
    $mopwh = User::factory()->create();
    $fieldEngineer = User::factory()->create();
    $infrastructureAuditor = User::factory()->create();
    $mopwh->assignRole($mopwhRole);
    $fieldEngineer->assignRole($fieldEngineerRole);
    $infrastructureAuditor->assignRole($infrastructureAuditorRole);

    expect(sidebarUrlsFor($mopwh))
        ->toContain('damage-assessment/damageAssessment')
        ->and(sidebarUrlsFor($fieldEngineer))
        ->toContain('damage-assessment/field-engineer-audit?sector=buildings')
        ->and(sidebarUrlsFor($infrastructureAuditor))
        ->toContain('damage-assessment/inf-audit/public-buildings');
});

it('places the main page and hud above damage assessment sectors', function () {
    $role = Role::findOrCreate('Area Manager', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $damageAssessmentModule = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment');
    $mainSection = $damageAssessmentModule['sections']->firstWhere('title', 'menu.damage_assessment.dashboard');
    $hudSection = $damageAssessmentModule['sections']->firstWhere('title', 'menu.hud.title');
    $sectionTitles = $damageAssessmentModule['sections']->pluck('title')->all();

    expect($sectionTitles[0])->toBe('menu.damage_assessment.dashboard')
        ->and($sectionTitles[1])->toBe('menu.hud.title')
        ->and($sectionTitles)->toContain('menu.damage_assessment.buildings')
        ->and($mainSection['is_direct'])->toBeTrue()
        ->and($mainSection['url'])->toBe('damage-assessment/damageAssessment')
        ->and(array_search('menu.hud.title', $sectionTitles, true))
        ->toBeLessThan(array_search('menu.damage_assessment.buildings', $sectionTitles, true))
        ->and($hudSection['is_direct'])->toBeTrue()
        ->and($hudSection['variant'])->toBe('hud')
        ->and($hudSection['url'])->toBe('damage-assessment/damageAssessment/hud')
        ->and($hudSection['items'])->toBeEmpty();
});

it('hides hud from auditors and field engineers', function (string $roleName) {
    $role = Role::findOrCreate($roleName, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $sectionTitles = Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections']->pluck('title'))
        ->all();

    expect($sectionTitles)->not->toContain('menu.hud.title');
})->with([
    'legal auditor' => 'Legal Auditor',
    'quality auditor' => 'QC/QA Engineer',
    'auditing supervisor' => 'Auditing Supervisor',
    'infrastructure auditor' => 'Inf - QC/QA Engineer',
    'field engineer' => 'Field Engineer',
]);

it('temporarily shows the audit home sector item for selected users only', function () {
    $role = Role::findOrCreate('QC/QA Engineer', 'web');

    $exceptedUser = User::factory()->create([
        'name' => 'ياسمين ماهر مصطفى ابومدللة',
    ]);
    $exceptedUser->assignRole($role);

    $identityExceptedUsers = collect([
        '800409062',
        '400940623',
        '400591194',
        '404581993',
        '456901503',
        '400662938',
        '404030421',
        '403746530',
        '406966812',
    ])
        ->map(function (string $idNumber) use ($role): User {
            $user = User::factory()->create(['id_no' => $idNumber]);
            $user->assignRole($role);

            return $user;
        });

    $regularUser = User::factory()->create([
        'name' => 'Regular QC Engineer',
    ]);
    $regularUser->assignRole($role);

    $auditUrlsFor = fn (User $user): array => collect(SectorNavigation::forUser('buildings', $user))
        ->firstWhere('key', 'audit')['items'];
    $exceptedUrls = collect($auditUrlsFor($exceptedUser))->pluck('url')->all();
    $regularUrls = collect($auditUrlsFor($regularUser))->pluck('url')->all();

    expect(collect($exceptedUrls)->contains(fn (string $url): bool => str_contains($url, '/damage-assessment/audit?')))->toBeTrue()
        ->and(collect($regularUrls)->contains(fn (string $url): bool => str_contains($url, '/damage-assessment/audit?')))->toBeFalse();

    $identityExceptedUsers->each(function (User $identityExceptedUser) use ($auditUrlsFor): void {
        $identityExceptedUrls = collect($auditUrlsFor($identityExceptedUser))->pluck('url');

        expect($identityExceptedUrls->contains(fn (string $url): bool => str_contains($url, '/damage-assessment/audit?')))->toBeTrue();
    });
});

it('shows the read only audit home item for team leaders', function () {
    $role = Role::findOrCreate('Team Leader', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $urls = collect(SectorNavigation::forUser('buildings', $user))
        ->firstWhere('key', 'audit')['items'];

    expect(collect($urls)->pluck('title')->all())
        ->toContain('menu.sector_navigation.audit_items.overview');
});

it('does not duplicate productivity reports in the sidebar for team leaders', function () {
    $role = Role::findOrCreate('Team Leader', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $urls = sidebarUrlsFor($user);

    expect($urls)->not->toContain('damage-assessment/reports/productivity');
});
