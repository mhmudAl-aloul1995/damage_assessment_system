<?php

use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\Heks\Models\HeksBeneficiary;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->portalUser = User::factory()->create();
    $this->portalUser->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->portalToken = $this->portalUser->createToken('portal-test', ['mobile:read', 'mobile:submit'])->plainTextToken;
});
it('exposes HR and the existing enabled modules without internal routes or provider configuration', function (): void {
    $response = $this->withToken($this->portalToken)->getJson('/api/v1/portal/modules')->assertOk()->assertJsonCount(6, 'data');
    expect(collect($response->json('data'))->pluck('key')->all())->toBe(['damage_assessment', 'damage_assessment_borrowers', 'heks-shelter', 'heks', 'hr', 'administration']);
    expect(collect($response->json('data'))->firstWhere('key', 'hr')['record_search'])->toBeFalse();
    $response->assertJsonMissingPath('data.0.provider')->assertJsonMissingPath('data.0.sections.0.url');
});

it('discovers HR only while the employee has attendance access and the module is enabled', function (): void {
    $this->portalUser->removeRole('Database Officer');
    $this->withToken($this->portalToken)->getJson('/api/v1/portal/modules')->assertOk()->assertJsonMissing(['key' => 'hr']);
    $permission = \Spatie\Permission\Models\Permission::findOrCreate('hr.attendance.view-own', 'web');
    $this->portalUser->givePermissionTo($permission);
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/portal/modules')->assertOk()->assertJsonFragment(['key' => 'hr', 'record_search' => false]);
    $this->getJson('/api/v1/portal/hr/records')->assertNotFound();
    $this->getJson('/api/v1/portal/hr/records/'.$this->portalUser->id)->assertNotFound();
    config(['modules.hr.enabled' => false]);
    $this->getJson('/api/v1/portal/modules')->assertOk()->assertJsonMissing(['key' => 'hr']);
    config(['modules.hr.enabled' => true]);
    $this->portalUser->revokePermissionTo($permission);
    $this->app['auth']->forgetGuards();
    $this->withToken($this->portalToken)->getJson('/api/v1/portal/modules')->assertOk()->assertJsonMissing(['key' => 'hr']);
});
it('searches borrowers literally and returns safe paginated records', function (): void {
    DamageAssessmentBorrower::query()->create(['borrower_name' => 'برج الأمل', 'borrower_id_number' => 'fixture-1', 'is_borrower_alive' => true]);
    DamageAssessmentBorrower::query()->create(['borrower_name' => 'آخر', 'is_borrower_alive' => true]);
    $path = '/api/v1/portal/damage_assessment_borrowers/records';
    $response = $this->withToken($this->portalToken)->getJson($path.'?'.http_build_query(['search' => 'الأمل']))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.title', 'برج الأمل')->assertJsonMissingPath('data.0.borrower_id_number');
    $this->getJson($path.'/'.$response->json('data.0.id'))->assertOk()->assertJsonMissingPath('data.raw_data')->assertJsonPath('data.details.0.value', 'برج الأمل');
    $this->getJson($path.'?search=%25')->assertOk()->assertJsonPath('total', 0);
    $this->getJson($path.'?search[]=x&page=0')->assertUnprocessable()->assertJsonValidationErrors(['search', 'page']);
});
it('allows HEKS inquiry but never returns imported raw source answers', function (): void {
    $row = HeksBeneficiary::query()->create(['code' => 'TEST-1', 'name' => 'مستفيد تجريبي', 'raw_data' => ['provider_secret' => 'do-not-return']]);
    $this->withToken($this->portalToken)->getJson('/api/v1/portal/heks/records/'.$row->id)->assertOk()->assertJsonMissingPath('data.raw_data')->assertJsonMissing(['provider_secret' => 'do-not-return']);
});
it('rejects guessed module record endpoints after permission revocation', function (): void {
    $this->portalUser->removeRole('Database Officer');
    $this->withToken($this->portalToken)->getJson('/api/v1/portal/heks/records')->assertForbidden();
    $this->getJson('/api/v1/portal/administration/records')->assertForbidden();
    $this->getJson('/api/v1/portal/unknown/records')->assertNotFound();
});
it('keeps administration account secrets out of the native portal', function (): void {
    $this->withToken($this->portalToken)->getJson('/api/v1/portal/administration/records/'.$this->portalUser->id)
        ->assertOk()->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token')->assertJsonMissingPath('data.id_no');
});
