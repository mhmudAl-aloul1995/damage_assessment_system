<?php

use App\Models\User;
use App\Support\Audit\RestrictedLawyerAuditAccess;
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

it('shows missing citizen identities sidebar link to auditing supervisor and project officer', function (string $roleName) {
    $role = Role::findOrCreate($roleName, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $urls = Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->all();

    expect($urls)->toContain('damage-assessment/reports/missing-citizen-identities');
})->with([
    'auditing supervisor' => 'Auditing Supervisor',
    'project officer' => 'Project Officer',
]);

it('shows infrastructure audit links to project officers', function () {
    $role = Role::findOrCreate('Project Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $urls = Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->all();

    expect($urls)
        ->toContain('damage-assessment/inf-audit/public-buildings')
        ->toContain('damage-assessment/inf-audit/roads');
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

it('shows higher committee reassessments in the committee sidebar', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $committeeSection = Sidebar::forUser($user)
        ->firstWhere('key', 'damage_assessment')['sections']
        ->firstWhere('title', 'menu.committee.title');

    expect($committeeSection['items'])
        ->pluck('url')
        ->toContain('damage-assessment/committee-decisions/higher-committee-reassessments');
});

it('shows lawyer legal audit assignments only to authorized sidebar users', function () {
    $databaseOfficerRole = Role::findOrCreate('Database Officer', 'web');
    $legalAuditorRole = Role::findOrCreate('Legal Auditor', 'web');
    $auditingSupervisorRole = Role::findOrCreate('Auditing Supervisor', 'web');
    $auditReviewerRole = Role::findOrCreate('Audit Reviewer', 'web');

    $databaseOfficer = User::factory()->create();
    $databaseOfficer->assignRole($databaseOfficerRole);

    $lawyer = User::factory()->create([
        'name' => RestrictedLawyerAuditAccess::ALAA_KATOU,
    ]);
    $lawyer->assignRole($legalAuditorRole);

    $auditingSupervisor = User::factory()->create();
    $auditingSupervisor->assignRole($auditingSupervisorRole);

    $auditReviewer = User::factory()->create();
    $auditReviewer->assignRole($auditReviewerRole);

    $sidebarUrlsFor = fn (User $user): array => Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->all();

    expect($sidebarUrlsFor($databaseOfficer))
        ->toContain('damage-assessment/audit/lawyer-assignments')
        ->and($sidebarUrlsFor($lawyer))
        ->toContain('damage-assessment/audit/lawyer-assignments')
        ->and($sidebarUrlsFor($auditingSupervisor))
        ->not->toContain('damage-assessment/audit/lawyer-assignments')
        ->and($sidebarUrlsFor($auditReviewer))
        ->not->toContain('damage-assessment/audit/lawyer-assignments');
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

    $identityExceptedUsers->each(function (User $identityExceptedUser): void {
        $identityExceptedUrls = Sidebar::forUser($identityExceptedUser)
            ->flatMap(fn (array $module) => $module['sections'])
            ->flatMap(fn (array $section) => $section['items'])
            ->pluck('url')
            ->all();

        expect($identityExceptedUrls)->toContain('damage-assessment/audit');
    });
});

it('shows the read only audit home link for team leaders', function () {
    $role = Role::findOrCreate('Team Leader', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $urls = Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->all();

    expect($urls)->toContain('damage-assessment/audit');
});

it('shows productivity report link for team leaders', function () {
    $role = Role::findOrCreate('Team Leader', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $urls = Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->flatMap(fn (array $item) => $item['children'] ?? [$item])
        ->pluck('url')
        ->all();

    expect($urls)->toContain('damage-assessment/reports/productivity');
});
