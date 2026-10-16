<?php

use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    Queue::fake();
    Http::preventStrayRequests();
    $this->storageUser = User::factory()->create();
    $this->storageUser->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->storageToken = $this->storageUser->createToken('storage-test', ['mobile:read', 'mobile:submit'])->plainTextToken;
    $this->withToken($this->storageToken);
});

it('returns an empty receipt page when mobile storage is initialized', function (): void {
    $this->getJson('/api/v1/submissions')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('current_page', 1);
});

it('reports missing storage without blocking catalogs or writing business records', function (string $missingTable): void {
    $schema = $this->getJson('/api/v1/forms/borrower-survey/schema')->assertOk()->json('data');
    Schema::drop('mobile_form_attachments');
    if ($missingTable === 'mobile_form_submissions') {
        Schema::drop('mobile_form_submissions');
    }
    $uuid = (string) Str::uuid();
    $response = $this->getJson('/api/v1/submissions')->assertStatus(503)
        ->assertJsonPath('code', 'mobile_form_storage_not_ready')->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->getJson('/api/v1/submissions/'.$uuid)->assertStatus(503);
    $this->getJson('/api/v1/form-attachments/1')->assertStatus(503);
    $this->postJson('/api/v1/forms/borrower-survey/submissions', [
        'submission_uuid' => $uuid, 'form_version' => $schema['version'],
        'answers' => ['borrower_name' => 'اختبار تهيئة', 'is_borrower_alive' => true],
    ])->assertStatus(503)->assertJsonPath('code', 'mobile_form_storage_not_ready');
    $this->post('/api/v1/forms/borrower-survey/attachments', [
        'submission_uuid' => $uuid, 'file' => UploadedFile::fake()->image('test.jpg', 10, 10),
    ], ['Accept' => 'application/json'])->assertStatus(503);
    $this->postJson('/api/v1/forms/borrower-survey/attachments/1/discard', ['submission_uuid' => $uuid])->assertStatus(503);
    expect(DamageAssessmentBorrower::query()->count())->toBe(0);
    $this->getJson('/api/v1/portal/modules')->assertOk()->assertJsonCount(5, 'data')->assertJsonFragment(['key' => 'hr']);
    $this->getJson('/api/v1/forms')->assertOk();
    $this->getJson('/api/v1/forms/borrower-survey/schema')->assertOk();
    $this->postJson('/api/v1/forms/unknown/submissions', [
        'submission_uuid' => $uuid, 'form_version' => $schema['version'], 'answers' => ['name' => 'test'],
    ])->assertNotFound();
})->with(['mobile_form_submissions', 'mobile_form_attachments']);

it('retains authentication and submit permission checks when storage is missing', function (): void {
    Schema::drop('mobile_form_attachments');
    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', '')->getJson('/api/v1/submissions')->assertUnauthorized();
    $token = $this->storageUser->createToken('read-only', ['mobile:read'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/forms/borrower-survey/submissions', [
        'submission_uuid' => (string) Str::uuid(), 'form_version' => str_repeat('0', 64), 'answers' => ['name' => 'test'],
    ])->assertForbidden();
});
