<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

it('issues an expiring read token with minimal account data', function (): void {
    $user = User::factory()->create();
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => '123456', 'device_name' => 'iPhone',
    ])->assertOk()->assertJsonPath('token_type', 'Bearer')
        ->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.id_no');

    expect($user->tokens()->first()->abilities)->toBe(['mobile:read']);
    expect($user->tokens()->first()->expires_at)->not->toBeNull();
    $this->withToken($response->json('access_token'))->getJson('/api/v1/me')
        ->assertOk()->assertJsonPath('data.id', $user->id);
});

it('rejects bad passwords and inactive accounts', function (bool $active, string $password): void {
    $user = User::factory()->create(['is_active' => $active]);
    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => $password, 'device_name' => 'Android',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
    expect($user->tokens()->count())->toBe(0);
})->with([[true, 'incorrect'], [false, '123456']]);

it('validates input and throttles login attempts', function (): void {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable();
    }
    $this->postJson('/api/v1/auth/login', [])->assertTooManyRequests();
});

it('requires authentication', function (string $path): void {
    $this->getJson('/api/v1/'.$path)->assertUnauthorized();
})->with(['me', 'modules']);

it('returns JSON errors even without an accept header', function (): void {
    $this->get('/api/v1/modules')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
});

it('returns the actual permitted module catalog without internal configuration', function (): void {
    $user = User::factory()->create();
    $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Database Officer', 'web'));
    $expected = \App\Support\Navigation\Sidebar::forUser($user)->pluck('key')->all();
    expect($expected)->toContain('heks');
    $response = $this->withToken($user->createToken('test', ['mobile:read'])->plainTextToken)
        ->getJson('/api/v1/modules')->assertOk()->assertJsonMissingPath('data.0.provider');
    expect(collect($response->json('data'))->pluck('key')->all())->toBe($expected);
});

it('denies inactive users and unsuitable tokens', function (bool $active, array $abilities): void {
    $user = User::factory()->create(['is_active' => $active]);
    $this->withToken($user->createToken('test', $abilities)->plainTextToken)
        ->getJson('/api/v1/modules')->assertForbidden();
})->with([[false, ['mobile:read']], [true, ['other:read']]]);

it('rejects expired tokens', function (): void {
    $user = User::factory()->create();
    $this->withToken($user->createToken('expired', ['mobile:read'], now()->subMinute())->plainTextToken)
        ->getJson('/api/v1/me')->assertUnauthorized();
});

it('revokes only the current device token', function (): void {
    $user = User::factory()->create();
    $current = $user->createToken('current', ['mobile:read']);
    $other = $user->createToken('other', ['mobile:read']);
    $this->withToken($current->plainTextToken)->postJson('/api/v1/auth/logout')->assertNoContent();
    expect($user->tokens()->pluck('id')->all())->toBe([$other->accessToken->id]);
});

it('discovers enabled modules according to permissions including future modules', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('mobile-example.view', 'web'));
    $modules = [];
    $sections = [];
    foreach (['damage_assessment', 'damage_assessment_borrowers', 'heks', 'administration', 'future_module'] as $key) {
        $modules[$key] = ['title' => $key, 'enabled' => true, 'order' => 1];
        $sections[] = ['module' => $key, 'title' => $key, 'url' => $key, 'permissions' => ['mobile-example.view']];
    }
    $modules['hidden'] = ['title' => 'hidden', 'enabled' => true];
    $sections[] = ['module' => 'hidden', 'title' => 'hidden', 'url' => 'hidden', 'permissions' => ['hidden.view']];
    $modules['disabled'] = ['title' => 'disabled', 'enabled' => false];
    $sections[] = ['module' => 'disabled', 'title' => 'disabled', 'url' => 'disabled', 'permissions' => ['mobile-example.view']];
    config(['modules' => $modules, 'sidebar' => $sections]);
    $this->withToken($user->createToken('test', ['mobile:read'])->plainTextToken)
        ->getJson('/api/v1/modules')->assertOk()->assertJsonCount(5, 'data')
        ->assertJsonMissing(['key' => 'hidden'])->assertJsonMissing(['key' => 'disabled'])
        ->assertJsonMissingPath('data.0.provider');
});
