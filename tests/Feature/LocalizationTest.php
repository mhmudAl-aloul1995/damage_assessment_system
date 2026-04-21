<?php

test('login page uses english by default', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('dir="ltr"', false)
        ->assertSee(__('ui.auth.sign_in'), false);
});

test('users can switch locale to arabic and the preference is stored in session', function () {
    $response = $this->from('/login')->post(route('locale.update', 'ar'));

    $response
        ->assertRedirect('/login')
        ->assertSessionHas('locale', 'ar');

    $this->withSession(['locale' => 'ar'])
        ->get('/login')
        ->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee('تسجيل الدخول', false);
});
