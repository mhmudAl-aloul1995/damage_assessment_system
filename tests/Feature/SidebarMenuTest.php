<?php

use App\Models\User;
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
        ->assertSee('data-bs-toggle="tooltip"', false)
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
        ->assertSee(__('menu.reports.groups.productivity'), false)
        ->assertSee(__('menu.reports.groups.operations'), false)
        ->assertSee(__('menu.reports.groups.surveys'), false)
        ->assertSee(__('menu.reports.groups.exports'), false)
        ->assertSee(__('menu.reports.field_engineer'), false)
        ->assertSee(__('menu.reports.export_data'), false);
});
