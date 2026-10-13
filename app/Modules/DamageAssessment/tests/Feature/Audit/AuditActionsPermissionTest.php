<?php

use App\Models\Building;
use App\Models\User;
use App\services\ArcgisAuditedRestoreService;
use App\services\ArcgisService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->building = Building::query()->create([
        'objectid' => 7901,
        'globalid' => 'audit-actions-building',
        'building_name' => 'Audit Actions Building',
        'field_status' => 'Not_Completed',
    ]);
});

it('hides the entire actions menu without its permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('audit.view', 'web'));

    $this->actingAs($user)
        ->getJson(route('audit.index', ['field_status' => 'Not_Completed']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonPath('data.0.globalid', $this->building->globalid)
        ->assertJsonPath('data.0.actions', '');
});

it('shows the entire actions menu to authorized users', function (string $access): void {
    $user = User::factory()->create();

    if ($access === 'direct') {
        $user->givePermissionTo('audit.actions');
    } elseif ($access === 'role') {
        $role = Role::findOrCreate('Audit Actions Operator', 'web');
        $role->givePermissionTo('audit.actions');
        $user->assignRole($role);
    } else {
        $user->assignRole(Role::findOrCreate('Database Officer', 'web'));
    }

    $response = $this->actingAs($user)
        ->getJson(route('audit.index', ['field_status' => 'Not_Completed']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk();

    expect($response->json('data.0.actions'))
        ->toContain('audit-actions-wrapper')
        ->toContain('/showAssessmentAudit/'.$this->building->globalid)
        ->toContain('btn-building-attachments')
        ->toContain('btn-show-history')
        ->toContain('btn-complete-building-field-status')
        ->toContain('btn-restore-audited-to-normal')
        ->toContain(route('building-deletions.create', ['building_globalid' => $this->building->globalid]));
})->with(['direct', 'role', 'database officer']);

it('blocks direct audit action requests without permission', function (string $routeName): void {
    Http::fake();
    $this->mock(ArcgisService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('updateBuildingFieldStatus');
    });
    $this->mock(ArcgisAuditedRestoreService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('restoreBuilding');
    });

    $this->actingAs(User::factory()->create())
        ->postJson(route($routeName, $this->building->globalid))
        ->assertForbidden();

    expect($this->building->refresh()->field_status)->toBe('Not_Completed');
    Http::assertNothingSent();
})->with([
    'audit.building.field-status.completed',
    'audit.building.restore-audited-to-normal',
]);

it('allows authorized users to execute audit actions', function (string $access): void {
    $user = User::factory()->create();

    if ($access === 'direct') {
        $user->givePermissionTo('audit.actions');
    } elseif ($access === 'role') {
        $role = Role::findOrCreate('Audit Actions Operator', 'web');
        $role->givePermissionTo('audit.actions');
        $user->assignRole($role);
    } else {
        $user->assignRole(Role::findOrCreate('Database Officer', 'web'));
    }

    $this->mock(ArcgisService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('updateBuildingFieldStatus')
            ->once()
            ->with(7901, 'COMPLETED')
            ->andReturn(['success' => true]);
    });
    $this->mock(ArcgisAuditedRestoreService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('restoreBuilding')
            ->once()
            ->withArgs(fn (Building $building): bool => $building->is($this->building))
            ->andReturn(['buildings' => 1]);
    });

    $this->actingAs($user)
        ->postJson(route('audit.building.field-status.completed', $this->building->globalid))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($this->building->refresh()->field_status)->toBe('COMPLETED');

    $this->postJson(route('audit.building.restore-audited-to-normal', $this->building->globalid))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('summary.buildings', 1);
})->with(['direct', 'role', 'database officer']);

it('lists the audit actions permission in user and role management', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole(Role::findOrCreate('Database Officer', 'web'));

    foreach (['users.index', 'roles.index'] as $routeName) {
        $this->actingAs($manager)
            ->get(route($routeName))
            ->assertOk()
            ->assertSee('value="audit.actions"', false)
            ->assertSee(__('ui.permissions.audit_actions'), false);
    }
});

it('completes the survey using the configured ArcGIS building layer', function (string $layerUrl): void {
    config(['services.arcgis.buildings_url' => $layerUrl]);
    Cache::put('arcgis_token', 'test-arcgis-token');
    Http::preventStrayRequests();
    Http::fake([
        'https://example.com/FeatureServer/7/updateFeatures' => Http::response([
            'updateResults' => [['objectId' => 7901, 'success' => true]],
        ]),
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('audit.actions');

    $this->actingAs($user)
        ->postJson(route('audit.building.field-status.completed', $this->building->globalid))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('field_status', 'COMPLETED');

    expect($this->building->refresh()->field_status)->toBe('COMPLETED');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://example.com/FeatureServer/7/updateFeatures'
            && $request['token'] === 'test-arcgis-token'
            && $request['f'] === 'json'
            && json_decode($request['features'], true) === [
                ['attributes' => ['objectid' => 7901, 'field_status' => 'COMPLETED']],
            ];
    });
    Http::assertSentCount(1);
})->with([
    'https://example.com/FeatureServer/7',
    'https://example.com/FeatureServer/7/',
]);

it('keeps the survey incomplete and explains ArcGIS update failures', function (array|string $body, int $status, string $expectedMessage): void {
    config(['services.arcgis.buildings_url' => 'https://example.com/FeatureServer/7']);
    Cache::put('arcgis_token', 'test-arcgis-token');
    Http::preventStrayRequests();
    Http::fake([
        'https://example.com/FeatureServer/7/updateFeatures' => Http::response($body, $status),
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('audit.actions');

    $this->actingAs($user)
        ->postJson(route('audit.building.field-status.completed', $this->building->globalid))
        ->assertStatus(502)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __($expectedMessage));

    expect($this->building->refresh()->field_status)->toBe('Not_Completed');
    Http::assertSentCount(1);
})->with([
    'unsupported update with blank message' => [
        ['error' => ['code' => 400, 'message' => '', 'details' => ['This operation is not supported.']]],
        200,
        'ui.audit.arcgis_update_not_supported',
    ],
    'provider error details' => [
        ['error' => ['code' => 403, 'message' => '', 'details' => ['Update access denied.']]],
        403,
        'Update access denied.',
    ],
    'individual feature failure' => [
        ['updateResults' => [['success' => false, 'error' => ['description' => 'Invalid field status.']]]],
        200,
        'Invalid field status.',
    ],
    'non-json provider error' => ['<html>Bad gateway</html>', 502, 'ui.audit.field_status_update_failed'],
    'missing update result' => [[], 200, 'ui.audit.field_status_update_failed'],
]);

it('grants and revokes the actions menu through user management', function (): void {
    $manager = User::factory()->create();
    $manager->givePermissionTo(Permission::findOrCreate('users.update', 'web'));
    $role = Role::findOrCreate('Audit Actions Operator', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    foreach ([['audit.actions'], []] as $permissions) {
        $this->actingAs($manager)
            ->putJson(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '0599000000',
                'roles' => [$role->name],
                'permissions' => $permissions,
            ])
            ->assertOk();

        $user->refresh();

        expect($user->hasDirectPermission('audit.actions'))->toBe($permissions !== []);

        $response = $this->actingAs($user)
            ->getJson(route('audit.index', ['field_status' => 'Not_Completed']), [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk();

        expect($response->json('data.0.actions') !== '')->toBe($permissions !== []);
    }

    $this->postJson(route('audit.building.field-status.completed', $this->building->globalid))
        ->assertForbidden();
    $this->postJson(route('audit.building.restore-audited-to-normal', $this->building->globalid))
        ->assertForbidden();
});
