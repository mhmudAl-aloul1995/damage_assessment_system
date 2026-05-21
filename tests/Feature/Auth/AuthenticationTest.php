<?php

use App\Models\User;

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
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
