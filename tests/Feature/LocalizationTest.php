<?php

use App\Models\User;
use App\Services\ArcgisService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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

test('damage assessment dashboard does not show mixed english summary labels in arabic locale', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    $user = User::factory()->create([
        'preferred_locale' => 'ar',
    ]);

    $this->mock(ArcgisService::class, function ($mock): void {
        $mock->shouldReceive('getToken')->andReturn('fake-token');
    });

    $this->actingAs($user)
        ->withSession(['locale' => 'ar'])
        ->get('/damageAssessment')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('ui.damage_dashboard.public_buildings'), false)
        ->assertSee(__('ui.damage_dashboard.road_facilities'), false)
        ->assertDontSee('Total Public Buildings', false)
        ->assertDontSee('Total Road Facilities', false)
        ->assertDontSee('Public Buildings Map', false)
        ->assertDontSee('Road Facilities Map', false)
        ->assertDontSee('Search public buildings', false)
        ->assertDontSee('Search road facilities', false);
});
