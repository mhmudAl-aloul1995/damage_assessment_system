<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('shows the sidebar menu for infrastructure team leaders', function () {
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
        ->assertSee(__('menu.damage_assessment.title'), false)
        ->assertSee(__('menu.committee.title'), false);
});
