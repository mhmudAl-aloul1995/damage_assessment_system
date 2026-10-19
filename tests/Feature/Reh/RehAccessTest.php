<?php

use App\Models\User;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehScoring;
use App\Modules\Reh\Services\RehSurvey;
use App\Modules\Reh\Services\RehSyncWorker;
use App\Support\Access\PermissionCatalog;
use App\Support\Navigation\Sidebar;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
});

function rehAccessUser(string $role): User
{
    $user = User::factory()->create(['preferred_locale' => 'ar']);
    $user->assignRole($role);

    return $user;
}

function rehAccessScoredUnit(): RehUnit
{
    $unit = RehUnit::factory()->create(['owner_identity' => 'REH-SECRET-IDENTITY']);
    $numbers = [];
    foreach (app(RehSurvey::class)->dictionary()['fields'] as $name => $field) {
        if (in_array($field['type'], ['integer', 'decimal'], true)) {
            $numbers[$name] = 0;
        }
    }
    $unit->update(['source' => [...$unit->source, ...$numbers,
        'owner_identity_number' => 'REH-SECRET-IDENTITY', 'owner_chronic_disease_name' => 'REH-SECRET-HEALTH',
        'owner_age' => 40, 'owner_sex' => 'male', 'household_size' => 1, 'household_males_age18_60' => 1,
        'owner_has_disability' => 'no', 'other_members_have_disability' => 'no',
        'nonfood_assistance_recency' => 'recent', 'shelter_assistance_recency' => 'recent',
        'household_has_stable_income' => 'yes', 'household_protection_risks' => 'debt',
        'breadwinner_male_owner' => 'owner', 'hosts_other_households' => 'no',
        'current_unit_occupancy' => 'owner_family', 'unit_needs_intervention' => 'no',
    ]]);

    return $unit;
}

it('creates the five REH roles with the approved default permissions and no user assignments', function (): void {
    foreach (config('access.role_profiles') as $name => $profile) {
        if ($profile['module'] !== 'reh') {
            continue;
        }
        $role = Role::findByName($name, 'web');
        expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(collect($profile['default_permissions'])->sort()->values()->all())
            ->and($role->users()->count())->toBe(0);
    }
    expect(Role::query()->whereIn('name', array_keys(array_filter(config('access.role_profiles'), fn (array $profile): bool => $profile['module'] === 'reh')))->count())->toBe(5);
});

it('enforces each role for the page every review action synchronization export and access management', function (string $role, array $allowedActions, bool $operatesSync): void {
    $user = rehAccessUser($role);
    $this->actingAs($user)->get(route('reh.index'))->assertOk();
    $this->getJson(route('reh.data'))->assertOk();
    expect(Sidebar::forUser($user)->pluck('key'))->toContain('reh');
    foreach (['claim', 'engineering_approve', 'social_approve', 'return', 'second_assessment', 'quantities', 'select', 'exclude', 'reopen'] as $action) {
        $unit = RehUnit::factory()->create(['engineering_status' => 'approved', 'social_status' => 'approved', 'decision' => $action === 'reopen' ? 'excluded' : 'pending']);
        $response = $this->postJson(route('reh.units.review', $unit), ['action' => $action, 'version' => 1, 'note' => 'تدقيق صلاحيات الموديول', 'quantities' => ['boq_1_1_water_tank_repair_qty' => 1]]);
        if (in_array($action, $allowedActions, true)) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
            expect($unit->fresh()->reviews()->count())->toBe(0);
        }
    }
    Queue::fake();
    if ($operatesSync) {
        $this->mock(RehSyncWorker::class)->shouldReceive('start')->once()->andReturnTrue();
        $this->postJson(route('reh.sync'))->assertAccepted();
        $this->get(route('reh.export'))->assertOk();
    } else {
        $this->postJson(route('reh.sync'))->assertForbidden();
        $this->get(route('reh.export'))->assertForbidden();
    }
    $this->get(route('roles.index'))->assertForbidden();
    $this->get(route('permissions.index'))->assertForbidden();
    $this->get(route('users.access', $user))->assertForbidden();
})->with([
    ['مشاهد REH', [], false],
    ['مدقق هندسي REH', ['claim', 'engineering_approve', 'return', 'second_assessment', 'quantities'], false],
    ['مدقق اجتماعي REH', ['claim', 'social_approve', 'return'], false],
    ['صاحب قرار REH', ['select', 'exclude', 'reopen'], false],
    ['مسؤول موديول REH', ['claim', 'engineering_approve', 'social_approve', 'return', 'second_assessment', 'quantities', 'select', 'exclude', 'reopen'], true],
]);

it('shows final scores priorities and their history without exposing social details identity health or private attachments', function (): void {
    $user = rehAccessUser('صاحب قرار REH');
    $unit = rehAccessScoredUnit();
    $snapshot = app(RehScoring::class)->snapshot($unit);
    $unit->reviews()->create(['action' => 'quantities', 'note' => 'توثيق مراجعة', 'changes' => ['scoring' => ['before' => $snapshot, 'after' => $snapshot]]]);
    $this->actingAs($user)->get(route('reh.index'))->assertOk()->assertSee('id="reh-priority"', false);
    $html = $this->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect($html)->toContain('data-final-score="2"', 'data-priority="low"', 'قبل 2', 'بعد 2')
        ->not->toContain('REH-SECRET-IDENTITY', 'REH-SECRET-HEALTH', 'data-score-panel="social"', 'إعاقة المالك', 'أسرة المالك · نقاط محسومة');
    $html = $this->getJson(route('reh.data', ['tab' => 'units', 'priority' => 'low']))->assertOk()->json('html');
    expect($html)->toContain('data-final-score="2"');
    $this->mock(\App\Modules\Reh\Services\RehArcgisClient::class)->shouldReceive('attachments')->once()->andReturn([
        ['id' => 1, 'name' => 'identity.jpg', 'keywords' => 'ownership_proof_photo', 'contentType' => 'image/jpeg'],
        ['id' => 2, 'name' => 'kitchen.jpg', 'keywords' => 'kitchen_photo', 'contentType' => 'image/jpeg'],
    ]);
    $this->getJson(route('reh.attachments', ['unit', $unit->id]))->assertOk()->assertJsonCount(1, 'attachments')->assertJsonPath('attachments.0.name', 'kitchen.jpg');
});

it('keeps score and sensitive permissions independent including in exports', function (): void {
    $unit = rehAccessScoredUnit();
    $user = rehAccessUser('مشاهد REH');
    $user->givePermissionTo(['reh.scores.view', 'reh.export']);
    $csv = $this->actingAs($user)->get(route('reh.export'))->assertOk()->streamedContent();
    $rows = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));
    expect($rows[1][3])->toBe('محجوب')->and($rows[1][12])->toBe('2')->and($rows[1][13])->toBe('أولوية منخفضة');
    $user->revokePermissionTo('reh.scores.view');
    $user->givePermissionTo('reh.sensitive');
    $html = $this->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect($html)->toContain('REH-SECRET-IDENTITY', 'REH-SECRET-HEALTH', 'data-score-panel="social"')->not->toContain('data-final-score="2"', 'data-priority="low"');
    $this->getJson(route('reh.data', ['priority' => 'low']))->assertForbidden();
    $rows = array_map('str_getcsv', explode("\n", trim(ltrim($this->get(route('reh.export'))->assertOk()->streamedContent(), "\xEF\xBB\xBF"))));
    expect($rows[1][3])->toBe('REH-SECRET-IDENTITY')->and($rows[1][12])->toBe('محجوب')->and($rows[1][13])->toBe('محجوب');
});

it('groups and explains REH permissions and role profiles in Arabic and English', function (string $locale): void {
    $manager = User::factory()->create(['preferred_locale' => $locale]);
    $manager->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($manager)->get(route('roles.index'))->assertOk()->assertSee('id="group-reh"', false)
        ->assertSee(__('access.reh'))->assertSee(__('access.reh_permissions.reh_scores_view.label'))->assertSee(__('access.reh_permissions.reh_scores_view.description'));
    $catalog = app(PermissionCatalog::class);
    expect($catalog->groupKey('reh.scores.view'))->toBe('reh')
        ->and($catalog->groups()->firstWhere('key', 'reh')['permissions'])->toHaveCount(9);
    $role = Role::findByName('صاحب قرار REH', 'web');
    $this->getJson(route('roles.edit', $role))->assertOk()->assertJsonPath('role.profile.description', __('access.reh_roles.decision'));
    Permission::findOrCreate('users.update', 'web');
    $this->putJson(route('roles.update', $role), ['name' => $role->name, 'permissions' => ['reh.view', 'users.update'], 'revision' => $catalog->revision($role->load('permissions'))])->assertUnprocessable()->assertJsonValidationErrors('permissions');
})->with(['ar', 'en']);

it('retains customized grants and revocations when the REH setup is run again', function (): void {
    $role = Role::findByName('مسؤول موديول REH', 'web');
    $role->revokePermissionTo('reh.sync');
    $role = Role::findByName('مدقق اجتماعي REH', 'web');
    $role->revokePermissionTo('reh.scores.view');
    $user = rehAccessUser('مدقق اجتماعي REH');
    $this->seed(RehPermissionsSeeder::class);
    expect(Role::findByName('مسؤول موديول REH', 'web')->hasPermissionTo('reh.sync'))->toBeFalse()
        ->and($user->fresh()->can('reh.scores.view'))->toBeFalse()
        ->and(Role::query()->where('name', 'مدقق اجتماعي REH')->count())->toBe(1);
});

it('preserves existing score access once when upgrading legacy sensitive grants', function (): void {
    Permission::findByName('reh.scores.view', 'web')->delete();
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $legacyRole = Role::findOrCreate('Legacy REH reviewer', 'web');
    $legacyRole->givePermissionTo(['reh.view', 'reh.sensitive']);
    $legacyUser = User::factory()->create();
    $legacyUser->givePermissionTo(['reh.view', 'reh.sensitive']);
    $this->seed(RehPermissionsSeeder::class);
    expect($legacyRole->fresh()->hasPermissionTo('reh.scores.view'))->toBeTrue()
        ->and($legacyUser->fresh()->can('reh.scores.view'))->toBeTrue();
    $legacyUser->revokePermissionTo('reh.scores.view');
    $this->seed(RehPermissionsSeeder::class);
    expect($legacyUser->fresh()->can('reh.scores.view'))->toBeFalse();
});
