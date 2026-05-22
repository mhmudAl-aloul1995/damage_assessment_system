<?php

use App\Models\User;

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
        ->assertDontSee('http://localhost/login', false)
        ->assertDontSee('http://localhost/dashboard', false);
});

test('login screen preserves configured subdirectory path', function () {
    config(['app.url' => 'http://localhost/phc']);

    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('action="/phc/login"', false)
        ->assertSee('data-kt-redirect-url="/phc/dashboard"', false)
        ->assertDontSee('action="/login"', false)
        ->assertDontSee('http://localhost/login', false);
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

test('duplicated phc path redirects to the normalized app path', function () {
    config(['app.url' => 'http://localhost/phc']);

    $this->get('/phc/phc/login')
        ->assertOk()
        ->assertSee('action="/phc/login"', false);
});

test('configured subdirectory login path renders the login screen', function () {
    config(['app.url' => 'http://localhost/phc']);

    $this->get('/phc/login')
        ->assertOk()
        ->assertSee('action="/phc/login"', false);
});

test('login screen can be rendered with an existing session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => '123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');
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
    $response->assertRedirect('/dashboard');
});

test('login redirects to configured subdirectory dashboard', function () {
    config(['app.url' => 'http://localhost/phc']);

    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => '123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/phc/dashboard');
});

test('users can authenticate from the configured subdirectory login path', function () {
    config(['app.url' => 'http://localhost/phc']);

    $user = User::factory()->create();

    $response = $this->post('/phc/login', [
        'email' => $user->email,
        'password' => '123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/phc/dashboard');
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
