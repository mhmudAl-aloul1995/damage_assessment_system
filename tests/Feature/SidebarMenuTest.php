<?php

use App\Models\User;
use App\Support\Navigation\Sidebar;
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

it('shows the sidebar menu for infrastructure Team Leaders', function () {
    $role = Role::findOrCreate('Team Leader -INF', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => '123456',
    ])->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect('damage-assessment/damageAssessment');

    $sectionTitles = Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections']->pluck('title'))
        ->all();

    expect($sectionTitles)
        ->toContain('menu.damage_assessment.title')
        ->toContain('menu.committee.title');
});

it('shows building survey return requests in the damage assessment sidebar', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect('damage-assessment/damageAssessment');

    $damageAssessmentModule = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment');

    expect($damageAssessmentModule['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->toContain('damage-assessment/field-engineer/building-survey-return-requests');
});

it('shows team leader field engineer assignment in the user management sidebar', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect('damage-assessment/damageAssessment');

    $administrationModule = Sidebar::forUser($user)->firstWhere('key', 'administration');

    expect($administrationModule['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->toContain('admin/team-leader-field-engineers');
});

it('groups report links into sidebar categories', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $damageAssessmentModule = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment');
    $reportsSection = $damageAssessmentModule['sections']->firstWhere('title', 'menu.reports.title');
    $reportGroupTitles = $reportsSection['items']->pluck('title')->all();

    expect($reportGroupTitles)
        ->toContain('menu.reports.area_productivity')
        ->toContain('menu.reports.groups.operations')
        ->toContain('menu.reports.groups.auditing')
        ->toContain('menu.reports.groups.surveys')
        ->toContain('menu.reports.groups.exports');

    $auditingGroup = $reportsSection['items']->firstWhere('title', 'menu.reports.groups.auditing');

    expect($auditingGroup['children'])
        ->pluck('url')
        ->toContain('damage-assessment/reports/engineer-audit');
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
        ->toContain('menu.hud.title', 'menu.damage_assessment.title', 'menu.reports.title', 'menu.audit.title');

    expect($administrationModule['sections']->pluck('title')->all())
        ->toContain('menu.user_management.title');
});

it('places hud above damage assessment for non auditor sidebar roles', function () {
    $role = Role::findOrCreate('Area Manager', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $damageAssessmentModule = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment');
    $hudSection = $damageAssessmentModule['sections']->firstWhere('title', 'menu.hud.title');
    $sectionTitles = $damageAssessmentModule['sections']->pluck('title')->all();

    expect($sectionTitles[0])->toBe('menu.hud.title')
        ->and($sectionTitles)->toContain('menu.damage_assessment.title')
        ->and(array_search('menu.hud.title', $sectionTitles, true))
        ->toBeLessThan(array_search('menu.damage_assessment.title', $sectionTitles, true))
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

it('temporarily shows the audit home sidebar link for selected users only', function () {
    $role = Role::findOrCreate('QC/QA Engineer', 'web');

    $exceptedUser = User::factory()->create([
        'name' => 'ياسمين ماهر مصطفى ابومدللة',
    ]);
    $exceptedUser->assignRole($role);

    $regularUser = User::factory()->create([
        'name' => 'Regular QC Engineer',
    ]);
    $regularUser->assignRole($role);

    $exceptedUrls = Sidebar::forUser($exceptedUser)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->all();

    $regularUrls = Sidebar::forUser($regularUser)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->all();

    expect($exceptedUrls)
        ->toContain('damage-assessment/audit')
        ->and($regularUrls)
        ->not->toContain('damage-assessment/audit');
});
