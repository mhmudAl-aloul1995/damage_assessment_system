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
        ->assertRedirect('damageAssessment');

    $this->followingRedirects()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('phc-sidebar-section', false)
        ->assertSee('phc-sidebar-link', false)
        ->assertDontSee('data-bs-toggle="tooltip"', false)
        ->assertSee(__('menu.damage_assessment.title'), false)
        ->assertSee(__('menu.committee.title'), false);
});

it('shows building survey return requests in the damage assessment sidebar', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect('damageAssessment');

    $this->followingRedirects()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('menu.damage_assessment.building_survey_return_requests'), false)
        ->assertSee('field-engineer/building-survey-return-requests', false);
});

it('shows team leader field engineer assignment in the user management sidebar', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect('damageAssessment');

    $this->followingRedirects()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('menu.user_management.team_leader_field_engineers'), false)
        ->assertSee('admin/team-leader-field-engineers', false);
});

it('groups report links into sidebar categories', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->followingRedirects()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('phc-sidebar-group-label', false)
        ->assertSee(__('menu.reports.area_productivity'), false)
        ->assertSee(__('menu.reports.productivity_items.housing_units'), false)
        ->assertSee(__('menu.reports.productivity_items.buildings'), false)
        ->assertSee(__('menu.reports.groups.operations'), false)
        ->assertSee(__('menu.reports.groups.surveys'), false)
        ->assertSee(__('menu.reports.groups.exports'), false)
        ->assertSee(__('menu.reports.field_engineer'), false)
        ->assertSee(__('menu.reports.export_data'), false);
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
    $sectionTitles = $damageAssessmentModule['sections']->pluck('title')->all();

    expect($sectionTitles[0])->toBe('menu.hud.title')
        ->and($sectionTitles)->toContain('menu.damage_assessment.title')
        ->and(array_search('menu.hud.title', $sectionTitles, true))
        ->toBeLessThan(array_search('menu.damage_assessment.title', $sectionTitles, true));
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
