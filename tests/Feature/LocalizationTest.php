<?php

use App\Models\User;

test('login page uses english by default', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('dir="ltr"', false)
        ->assertSee(__('ui.auth.sign_in'), false);
});

test('guests can switch locale to arabic and the preference is stored in session and cookie', function () {
    $response = $this->from('/login')->post(route('locale.update', 'ar'));

    $response
        ->assertRedirect('/login')
        ->assertSessionHas('locale', 'ar')
        ->assertCookie('preferred_locale', 'ar');

    $this->withSession(['locale' => 'ar'])
        ->get('/login')
        ->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('ui.auth.sign_in'), false);
});

test('authenticated users persist locale preference in the database', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'en',
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/login')
        ->post(route('locale.update', 'ar'));

    $response
        ->assertRedirect('/login')
        ->assertSessionHas('locale', 'ar')
        ->assertCookie('preferred_locale', 'ar');

    expect($user->fresh()->preferred_locale)->toBe('ar');
});
