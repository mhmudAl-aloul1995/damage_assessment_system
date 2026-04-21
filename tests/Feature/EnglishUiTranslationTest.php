<?php

use App\Models\User;
use App\Services\ArcgisService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('shows translated english strings on the main dashboard and audit dashboard', function () {
    $user = User::factory()->create();

    $this->app->instance(ArcgisService::class, new class extends ArcgisService
    {
        public function getToken(): string
        {
            return 'fake-token';
        }
    });

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get('/damageAssessment')
        ->assertOk()
        ->assertSee('Buildings Status Summary')
        ->assertSee('Housing Units')
        ->assertSee('Aerial Map');

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('audit.dashboard'))
        ->assertOk()
        ->assertSee('Audit Dashboard')
        ->assertSee('Engineering Audit')
        ->assertSee('Legal Audit');
});
