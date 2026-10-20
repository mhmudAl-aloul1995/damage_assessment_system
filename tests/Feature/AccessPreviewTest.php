<?php

use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\Access\AccessPreview;
use App\Support\Phase\PhaseContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->officer = User::factory()->create(['preferred_locale' => 'en']);
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->target = User::factory()->create([
        'preferred_locale' => 'en',
        'region' => 'North',
        'allowed_phase_numbers' => [2],
        'default_phase_number' => 2,
    ]);

    Route::middleware(['web', 'auth'])->get('/preview-test/context', function (Request $request) {
        return response()->json([
            'user_id' => $request->user()->id,
            'auth_id' => Auth::id(),
            'roles' => $request->user()->getRoleNames(),
            'permissions' => $request->user()->getAllPermissions()->pluck('name')->sort()->values(),
            'region' => $request->user()->region,
            'phase' => app(PhaseContext::class)->selected(),
            'can_create_roles' => $request->user()->can('roles.create'),
        ]);
    });
});

it('only allows database officers to open and start a preview', function (): void {
    $this->get(route('access-preview.create'))->assertRedirect();
    $this->post(route('access-preview.store'), ['mode' => 'user', 'user_id' => $this->target->id])->assertRedirect();
    $this->actingAs($this->target)->get(route('access-preview.create'))->assertForbidden();
    $this->post(route('access-preview.store'), ['mode' => 'user', 'user_id' => $this->officer->id])->assertForbidden();

    $this->target->givePermissionTo(Permission::findOrCreate('users.update', 'web'));
    $this->get(route('access-preview.create'))->assertForbidden();
    $this->post(route('access-preview.store'), ['mode' => 'user', 'user_id' => $this->officer->id])->assertForbidden();

    $this->actingAs($this->officer)->get(route('access-preview.create'))->assertOk()
        ->assertSee($this->target->email)->assertSee(__('access_preview.live_notice'));
});

it('uses the target identity permissions and phase without changing the login or remember token', function (): void {
    $this->target->assignRole(Role::findOrCreate('Field Engineer', 'web'));
    $this->target->givePermissionTo(Permission::findOrCreate('users.view', 'web'));
    $rememberToken = $this->target->remember_token;

    $this->actingAs($this->officer)->withSession([PhaseContext::SESSION_KEY => 1])
        ->post(route('access-preview.store'), ['mode' => 'user', 'user_id' => $this->target->id])
        ->assertRedirectToRoute('access-preview.show')
        ->assertSessionHas('access_preview.actor_id', $this->officer->id)
        ->assertSessionMissing(PhaseContext::SESSION_KEY);

    $this->get('/preview-test/context')->assertOk()
        ->assertJsonPath('user_id', $this->target->id)
        ->assertJsonPath('auth_id', $this->target->id)
        ->assertJsonPath('roles', ['Field Engineer'])
        ->assertJsonPath('permissions', ['users.view'])
        ->assertJsonPath('region', 'North')
        ->assertJsonPath('phase', 2);
    $this->assertAuthenticatedAs($this->officer);
    expect($this->target->fresh()->remember_token)->toBe($rememberToken);

    $this->get(route('access-preview.show'))->assertOk()->assertSee('access-preview-banner')
        ->assertSee($this->target->name)->assertSee(__('access_preview.stop'));

    $this->post(route('access-preview.destroy'))->assertRedirectToRoute('users.index')
        ->assertSessionMissing(AccessPreview::SESSION_KEY)
        ->assertSessionHas(PhaseContext::SESSION_KEY, 1);
    $this->get('/preview-test/context')->assertJsonPath('user_id', $this->officer->id);

    $events = UserActivityLog::query()->whereNotNull('metadata->event')->get();
    expect($events->pluck('metadata.event')->all())->toBe(['started', 'stopped'])
        ->and($events->first()->metadata['access_preview']['user_id'])->toBe($this->target->id)
        ->and($events->last()->metadata['access_preview']['id'])->toBe($events->first()->metadata['access_preview']['id']);
});

it('restricts role previews to the selected role and preserves saved assignments', function (): void {
    $selectedRole = Role::findOrCreate('Preview editor', 'web');
    $selectedRole->givePermissionTo(Permission::findOrCreate('roles.create', 'web'));
    $this->officer->givePermissionTo(Permission::findOrCreate('users.delete', 'web'));

    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'role', 'user_id' => $this->officer->id, 'role_id' => $selectedRole->id,
    ])->assertRedirect();

    $this->get('/preview-test/context')->assertOk()
        ->assertJsonPath('roles', ['Preview editor'])
        ->assertJsonPath('permissions', ['roles.create'])
        ->assertJsonPath('can_create_roles', true);
    $this->get(route('users.index'))->assertForbidden()->assertSee(__('access_preview.stop'));
    $this->postJson(route('roles.store'), ['name' => 'Created during preview', 'permissions' => []])->assertOk();
    $this->assertDatabaseHas('roles', ['name' => 'Created during preview']);

    expect($this->officer->fresh()->getRoleNames()->all())->toBe(['Database Officer'])
        ->and($this->officer->fresh()->getDirectPermissions()->pluck('name')->all())->toBe(['users.delete']);

    $this->post(route('access-preview.destroy'))->assertRedirect();
    $this->get('/preview-test/context')->assertJsonPath('roles', ['Database Officer']);
});

it('performs real edits as the target and records the original officer', function (): void {
    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'user', 'user_id' => $this->target->id,
    ])->assertRedirect();

    $this->putJson(route('profile.update'), ['name' => 'Updated during preview', 'email' => $this->target->email])->assertOk();
    expect($this->target->fresh()->name)->toBe('Updated during preview')
        ->and($this->officer->fresh()->name)->toBe($this->officer->name);
    $log = UserActivityLog::query()->where('route_name', 'profile.update')->latest('id')->firstOrFail();
    expect($log->user_id)->toBe($this->target->id)
        ->and($log->metadata['access_preview']['actor_id'])->toBe($this->officer->id)
        ->and($log->description)->toContain($this->officer->name);
    $this->post(route('access-preview.destroy'))->assertRedirect();
    expect($this->target->fresh()->name)->toBe('Updated during preview');
});

it('denies writes that are not granted to the preview identity', function (): void {
    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'user', 'user_id' => $this->target->id,
    ])->assertRedirect();
    $this->postJson(route('roles.store'), ['name' => 'Forbidden role', 'permissions' => []])->assertForbidden();
    $this->assertDatabaseMissing('roles', ['name' => 'Forbidden role']);
});

it('does not permit nested previews even when the target is a database officer', function (): void {
    $this->target->assignRole('Database Officer');
    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'user', 'user_id' => $this->target->id,
    ])->assertRedirect();
    $this->post(route('access-preview.store'), ['mode' => 'user', 'user_id' => $this->officer->id])->assertForbidden();
    $this->get(route('access-preview.create'))->assertForbidden();
    $this->post(route('access-preview.destroy'))->assertRedirect();
    $this->assertAuthenticatedAs($this->officer);
});

it('validates the requested user mode and role', function (array $data, string $error): void {
    $this->actingAs($this->officer)->postJson(route('access-preview.store'), $data)
        ->assertUnprocessable()->assertJsonValidationErrors($error)
        ->assertSessionMissing(AccessPreview::SESSION_KEY);
})->with([
    'invalid user' => [['mode' => 'user', 'user_id' => 999999], 'user_id'],
    'missing user' => [['mode' => 'user'], 'user_id'],
    'invalid mode' => [['mode' => 'unknown', 'user_id' => 1], 'mode'],
    'missing role' => [['mode' => 'role', 'user_id' => 1], 'role_id'],
    'invalid role' => [['mode' => 'role', 'user_id' => 1, 'role_id' => 999999], 'role_id'],
]);

it('rejects inactive targets and roles from another guard', function (): void {
    $this->target->update(['is_active' => false]);
    $this->actingAs($this->officer)->postJson(route('access-preview.store'), [
        'mode' => 'user', 'user_id' => $this->target->id,
    ])->assertJsonValidationErrors('user_id');
    $role = Role::create(['name' => 'API only', 'guard_name' => 'api']);
    $this->postJson(route('access-preview.store'), [
        'mode' => 'role', 'user_id' => $this->officer->id, 'role_id' => $role->id,
    ])->assertJsonValidationErrors('role_id');
});

it('can return even after the target has been deactivated or deleted', function (string $change): void {
    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'user', 'user_id' => $this->target->id,
    ])->assertRedirect();
    if ($change === 'delete') {
        $this->target->delete();
    } else {
        $this->target->update(['is_active' => false]);
    }
    $this->post(route('access-preview.destroy'))->assertRedirectToRoute('users.index')
        ->assertSessionMissing(AccessPreview::SESSION_KEY);
    $this->assertAuthenticatedAs($this->officer);
})->with(['delete', 'deactivate']);

it('automatically ends previews when a target is no longer active', function (): void {
    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'user', 'user_id' => $this->target->id,
    ])->assertRedirect();
    $this->target->update(['is_active' => false]);
    $this->get('/preview-test/context')->assertRedirectToRoute('access-preview.create')
        ->assertSessionMissing(AccessPreview::SESSION_KEY);
});

it('rechecks the original officers authority on every request', function (string $change): void {
    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'user', 'user_id' => $this->target->id,
    ])->assertRedirect();
    if ($change === 'deactivate') {
        $this->officer->update(['is_active' => false]);
    } else {
        $this->officer->removeRole('Database Officer');
    }
    $this->putJson(route('profile.update'), ['name' => 'Must not change', 'email' => $this->target->email])
        ->assertRedirectToRoute('dashboard')->assertSessionMissing(AccessPreview::SESSION_KEY);
    expect($this->target->fresh()->name)->toBe($this->target->name);
})->with(['deactivate', 'remove role']);

it('logs out the original session and ends the preview', function (): void {
    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'user', 'user_id' => $this->target->id,
    ])->assertRedirect();
    $this->post(route('logout'))->assertRedirect('/')->assertSessionMissing(AccessPreview::SESSION_KEY);
    $this->assertGuest();
    expect(UserActivityLog::query()->where('description', 'like', 'Access preview logout:%')->exists())->toBeTrue();
});

it('keeps the original login in the session across guard reloads', function (): void {
    $this->post('/login', ['email' => $this->officer->email, 'password' => '123456'])->assertRedirect();
    $guardSessionKey = Auth::guard('web')->getName();
    $this->post(route('access-preview.store'), ['mode' => 'user', 'user_id' => $this->target->id])
        ->assertRedirect()->assertSessionHas($guardSessionKey, $this->officer->id);
    Auth::forgetGuards();
    $this->get('/preview-test/context')->assertJsonPath('user_id', $this->target->id)
        ->assertSessionHas($guardSessionKey, $this->officer->id);
    Auth::forgetGuards();
    $this->post(route('access-preview.destroy'))->assertRedirect()->assertSessionHas($guardSessionKey, $this->officer->id);
    $this->assertAuthenticatedAs($this->officer);
});

it('stops a role preview when the selected role is deleted', function (): void {
    $role = Role::findOrCreate('Temporary role', 'web');
    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'role', 'user_id' => $this->target->id, 'role_id' => $role->id,
    ])->assertRedirect();
    $role->delete();
    $this->get('/preview-test/context')->assertRedirectToRoute('access-preview.create')
        ->assertSessionMissing(AccessPreview::SESSION_KEY);
});

it('does not reuse preview state under a different signed in account', function (): void {
    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => 'user', 'user_id' => $this->target->id,
    ])->assertRedirect();
    $otherOfficer = User::factory()->create();
    $otherOfficer->assignRole('Database Officer');
    $this->actingAs($otherOfficer)->get('/preview-test/context')->assertRedirectToRoute('dashboard')
        ->assertSessionMissing(AccessPreview::SESSION_KEY);
});

it('renders both translations and the role entry point', function (): void {
    $this->officer->update(['preferred_locale' => 'ar']);
    $this->actingAs($this->officer)->get(route('access-preview.create', ['mode' => 'role']))
        ->assertOk()->assertSee('تجربة المنظومة')->assertSee('value="role" selected', false);
    $this->get(route('users.index'))->assertOk()->assertSee(route('access-preview.create'));
    $this->get(route('roles.index'))->assertOk()->assertSee(route('access-preview.create', ['mode' => 'role']));
    $this->get(route('users.access', $this->target))->assertOk()
        ->assertSee(route('access-preview.create', ['user_id' => $this->target->id]));
});

it('renders the access preview role control for browser interaction', function (string $mode): void {
    $response = $this->actingAs($this->officer)
        ->get(route('access-preview.create', ['mode' => $mode]))
        ->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $roleControl = $document->getElementById('preview-role');

    expect($roleControl->hasAttribute('disabled'))->toBe($mode !== 'role')
        ->and($roleControl->hasAttribute('required'))->toBe($mode === 'role');

    file_put_contents(sys_get_temp_dir().'/phc-access-preview-'.$mode.'.html', $response->getContent());
})->with(['user', 'role']);

it('uses the preview users workspaces instead of the officers damage assessment workspace', function (string $mode): void {
    $borrowersRole = Role::findOrCreate('Project Officer - Borrowers', 'web');
    $rehRole = Role::findOrCreate('مسؤول موديول REH', 'web');
    $rehRole->givePermissionTo(Permission::findOrCreate('reh.view', 'web'));
    $this->target->assignRole($borrowersRole, $rehRole);

    $this->actingAs($this->officer)->post(route('access-preview.store'), [
        'mode' => $mode,
        'user_id' => $mode === 'user' ? $this->target->id : $this->officer->id,
        'role_id' => $borrowersRole->id,
    ])->assertRedirect();

    $this->get(route('access-preview.show'))->assertOk()
        ->assertSee('title="'.__('مقترضو بنك التنمية الإسلامي').'"', false)
        ->assertDontSee('data-module-option="damage_assessment"', false)
        ->assertSee(__('access_preview.stop'));
    $this->get(route('dashboard'))->assertRedirect(app_route('damage-assessment-borrowers.index'));
    $this->post(route('access-preview.destroy'))->assertRedirect();
    $this->get(route('dashboard'))->assertRedirect(app_route('damageAssessment.index'));
})->with(['user', 'role']);
