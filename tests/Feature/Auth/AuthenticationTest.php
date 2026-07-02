<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['app.url' => 'http://localhost']);
});

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertSee('action="/login"', false);
});

test('login screen uses relative submit and redirect urls', function () {
    config(['app.url' => 'http://localhost']);

    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('action="/login"', false)
        ->assertSee('data-kt-redirect-url="/dashboard"', false)
        ->assertDontSee('action="http://localhost/login"', false)
        ->assertDontSee('data-kt-redirect-url="http://localhost/dashboard"', false);
});

test('login screen preserves configured subdirectory path', function () {
    config(['app.url' => 'http://localhost/phc']);

    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('action="/phc/login"', false)
        ->assertSee('data-kt-redirect-url="/phc/dashboard"', false)
        ->assertDontSee('action="/login"', false)
        ->assertDontSee('action="http://localhost/login"', false);
});

test('login screen does not duplicate configured subdirectory path', function () {
    config(['app.url' => 'http://localhost/phc']);

    expect(app_path_url('/phc/login'))->toBe('/phc/login')
        ->and(app_path_url('/phc/phc/login'))->toBe('/phc/login')
        ->and(app_path_url('/phc/phc/dashboard'))->toBe('/phc/dashboard');
});

test('login path helper does not duplicate when app url already contains duplicate subdirectory', function () {
    config(['app.url' => 'http://localhost/phc/phc']);

    expect(app_path_url('/login'))->toBe('/phc/login')
        ->and(app_path_url('/phc/phc/login'))->toBe('/phc/login');
});

test('login path helper does not duplicate server subdirectory path', function () {
    config(['app.url' => 'http://213.6.135.115/damage_assessment_system']);

    expect(app_path_url('/login'))->toBe('/damage_assessment_system/login')
        ->and(app_path_url('/damage_assessment_system/login'))->toBe('/damage_assessment_system/login')
        ->and(app_path_url('/damage_assessment_system/damage_assessment_system/login'))->toBe('/damage_assessment_system/login');
});

test('duplicated phc path redirects to the normalized app path', function () {
    config(['app.url' => 'http://localhost']);

    $this->get('/phc/phc/login')
        ->assertStatus(302);
});

test('configured subdirectory login path renders the login screen', function () {
    config(['app.url' => 'http://localhost/phc']);

    $this->followingRedirects()
        ->get('/phc/login')
        ->assertOk()
        ->assertSee('action="/phc/login"', false);
});

test('server subdirectory login path renders the login screen', function () {
    config(['app.url' => 'http://213.6.135.115/damage_assessment_system']);

    $this->get('/damage_assessment_system/login')
        ->assertOk()
        ->assertSee('action="/damage_assessment_system/login"', false);
});

test('duplicated server subdirectory path redirects to the normalized app path', function () {
    config(['app.url' => 'http://213.6.135.115/damage_assessment_system']);

    $this->get('/damage_assessment_system/damage_assessment_system/login')
        ->assertOk()
        ->assertSee('action="/damage_assessment_system/login"', false);
});

test('authenticated users are redirected away from the login screen', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/login');

    $response->assertRedirect('/dashboard');
});

test('authenticated users are redirected away from the server subdirectory login screen', function () {
    config(['app.url' => 'http://213.6.135.115/damage_assessment_system']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/damage_assessment_system/login');

    $response->assertRedirect('/damage_assessment_system/dashboard');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => '123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/damage-assessment/damageAssessment');
});

test('field engineers are redirected to their audit page after login', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => '123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/damage-assessment/field-engineer-audit');
});

test('borrowers project officers with only that role are redirected to borrowers page after login', function () {
    $role = Role::findOrCreate('Project Officer - Borrowers', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => '123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/damage-assessment-borrowers');
});

test('borrowers project officers with additional roles use the regular login redirect', function () {
    $borrowersRole = Role::findOrCreate('Project Officer - Borrowers', 'web');
    $databaseOfficerRole = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($borrowersRole, $databaseOfficerRole);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => '123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/damage-assessment/damageAssessment');
});

test('dashboard redirects field engineers to their audit page', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/damage-assessment/field-engineer-audit');
});

test('damage assessment dashboard redirects field engineers to their audit page', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/damage-assessment/damageAssessment')
        ->assertRedirect('/damage-assessment/field-engineer-audit');
});

test('login ignores stale localhost intended urls', function () {
    $user = User::factory()->create();

    $response = $this
        ->withSession(['url.intended' => 'http://localhost/login'])
        ->post('/login', [
            'email' => $user->email,
            'password' => '123456',
        ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/damage-assessment/damageAssessment');
});

test('login redirects to configured subdirectory damage assessment dashboard', function () {
    config(['app.url' => 'http://localhost/phc']);

    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => '123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/phc/damage-assessment/damageAssessment');
});

test('configured subdirectory login path submits to the configured login route', function () {
    config(['app.url' => 'http://localhost/phc']);

    $this->followingRedirects()
        ->get('/phc/login')
        ->assertOk()
        ->assertSee('action="/phc/login"', false);
});

test('users can authenticate from the server subdirectory login path', function () {
    config(['app.url' => 'http://213.6.135.115/damage_assessment_system']);
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    $user = User::factory()->create();

    $response = $this->post('/damage_assessment_system/login', [
        'email' => $user->email,
        'password' => '123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/damage_assessment_system/damage-assessment/damageAssessment');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this
        ->from('/login')
        ->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

    $this->assertGuest();
    $response->assertRedirect('/login');
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
