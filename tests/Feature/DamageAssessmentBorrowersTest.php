<?php

use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerRiskAnalysisService;
use App\Support\Navigation\Sidebar;
use Spatie\Permission\Models\Role;

it('allows field engineers to open the borrowers survey page', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.index'))
        ->assertOk()
        ->assertSee('borrowerSurveyForm', false)
        ->assertSee('data-offline-sync="true"', false)
        ->assertSee('window.phcOfflineSync.queue', false)
        ->assertSee('استبيان المقترضين', false);
});

it('wires borrowers surveys into pwa offline caching and sync', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);
    $serviceWorker = file_get_contents(public_path('sw.js'));
    $backgroundSync = file_get_contents(public_path('background-sync.js'));
    $view = file_get_contents(base_path('app/Modules/DamageAssessmentBorrowers/views/index.blade.php'));

    expect(collect($manifest['shortcuts'] ?? [])->pluck('url'))->toContain('/damage-assessment-borrowers')
        ->and($serviceWorker)->toContain('PHC_CACHE_URLS')
        ->and($serviceWorker)->toContain("const CACHE_NAME = 'phc-pwa-v5'")
        ->and($serviceWorker)->toContain('APP_SCOPE_URL')
        ->and($serviceWorker)->toContain('cache.put(requestUrl.pathname, copy)')
        ->and($serviceWorker)->toContain('PHC_OFFLINE_SYNC_COMPLETE')
        ->and($backgroundSync)->toContain('cacheCurrentPage')
        ->and($backgroundSync)->toContain('PHC_PWA_URLS')
        ->and($view)->toContain('borrowersPendingRowsKey')
        ->and($view)->toContain('window.phcOfflineSync?.registerSync?.()')
        ->and($view)->toContain('damage-assessment-borrowers-page')
        ->and($view)->toContain('borrowersMobileList')
        ->and($view)->toContain('borrower-mobile-card');
});

it('serves pwa resources within the configured deployment path', function () {
    config(['app.url' => 'http://localhost/damage_assessment_system']);

    $this->get('/damage_assessment_system/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('scope', '/damage_assessment_system/')
        ->assertJsonPath('start_url', '/damage_assessment_system/login')
        ->assertJsonPath('shortcuts.0.url', '/damage_assessment_system/damage-assessment-borrowers')
        ->assertJsonPath('icons.5.src', '/damage_assessment_system/icon-192x192.png');

    $this->get('/damage_assessment_system/sw.js')
        ->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/damage_assessment_system/')
        ->assertSee('phc-pwa-v5');

    $this->get('/damage_assessment_system/icon-192x192.png')
        ->assertOk();
});

it('stores borrower surveys through ajax and returns risk analysis', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($user)
        ->postJson(route('damage-assessment-borrowers.store'), [
            'borrower_name' => 'Ahmad Saleh',
            'borrower_id_number' => '900000001',
            'family_members_count' => 7,
            'employment_status' => 'not_working',
            'is_borrower_alive' => false,
            'vulnerability_types' => ['disabled', 'elderly'],
            'guarantors_count' => 2,
            'guarantors_alive_status' => 'no',
            'guarantors_employment_statuses' => ['lost_job'],
            'displacement_status' => 'displaced',
            'displaced_to_governorate' => 'gaza',
            'loan_unit_occupancy_status' => 'none_due_damage',
            'loan_unit_damage_status' => 'destroyed',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('analysis.risk_level', 'critical');

    expect(DamageAssessmentBorrower::query()->where('borrower_id_number', '900000001')->exists())->toBeTrue();
});

it('lists borrower surveys as json rows', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    DamageAssessmentBorrower::query()->create([
        'submitted_by' => $user->id,
        'borrower_name' => 'Mona Borrower',
        'borrower_id_number' => '800000001',
        'is_borrower_alive' => true,
        'risk_level' => 'medium',
        'risk_score' => 33,
    ]);

    $this->actingAs($user)
        ->getJson(route('damage-assessment-borrowers.data', ['q' => 'Mona']))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.0.borrower_name', 'Mona Borrower');
});

it('adds borrowers to the sidebar for field engineers', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $module = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment_borrowers');

    expect($module)->not->toBeNull()
        ->and($module['sections']->first()['url'])->toBe('damage-assessment-borrowers');
});

it('calculates borrower risk levels', function () {
    $analysis = app(BorrowerRiskAnalysisService::class)->analyze([
        'is_borrower_alive' => true,
        'employment_status' => 'working',
        'guarantors_alive_status' => 'yes',
        'guarantors_employment_statuses' => ['all_working'],
        'displacement_status' => 'resident',
        'loan_unit_damage_status' => 'minor',
    ]);

    expect($analysis['risk_level'])->toBe('low');
});
