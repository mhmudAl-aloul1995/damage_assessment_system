<?php

use App\Models\MobileFormAttachment;
use App\Models\MobileFormSubmission;
use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksFollowUp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    $this->formUser = User::factory()->create();
    $this->formUser->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->formToken = $this->formUser->createToken('form-test', ['mobile:read', 'mobile:submit'])->plainTextToken;
    $this->withToken($this->formToken);
    $this->schema = $this->getJson('/api/v1/forms/borrower-survey/schema')->assertOk()->json('data');
    $this->payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => $this->schema['version'],
        'answers' => ['borrower_name' => 'أسرة تجريبية', 'is_borrower_alive' => true]];
});
it('grants submit ability only to users with a permitted writable form', function (): void {
    $response = $this->postJson('/api/v1/auth/login', ['email' => $this->formUser->email, 'password' => '123456', 'device_name' => 'Android'])->assertOk();
    expect($this->formUser->tokens()->latest('id')->first()->abilities)->toBe(['mobile:read', 'mobile:submit']);
    $forms = $this->withToken($response->json('access_token'))->getJson('/api/v1/forms')->assertOk()->assertJsonPath('data.0.can_submit', true);
    expect(collect($forms->json('data'))->pluck('key')->all())->toContain('borrower-survey', 'heks-beneficiary', 'heks-followup');
});
it('publishes all borrower questions including typed repeat sections and conditions', function (): void {
    expect($this->schema['sections'])->toHaveCount(4);
    $fields = collect($this->schema['sections'])->flatMap(fn (array $section): array => $section['fields']);
    expect($fields->firstWhere('key', 'resident_households')['type'])->toBe('repeat');
    expect($fields->firstWhere('key', 'displaced_to_governorate')['visible_when']['field'])->toBe('displacement_status');
    $this->getJson('/api/v1/forms/heks-beneficiary/schema')->assertUnprocessable();
    $this->getJson('/api/v1/forms/raw-kobo/schema')->assertNotFound();
});
it('atomically creates one borrower and replays a durable receipt without duplicate writes', function (): void {
    $this->payload['answers'] += ['loan_unit_area' => 100, 'loan_unit_floor_type' => 'ground', 'loan_unit_damage_status' => 'destroyed', 'resident_households' => [['head_name' => 'أسرة', 'members_count' => 4]]];
    $first = $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertCreated()->assertJsonPath('replayed', false)->assertJsonPath('data.status', 'received');
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $first->json('data.id'));
    expect(DamageAssessmentBorrower::query()->count())->toBe(1);
    expect((float) DamageAssessmentBorrower::query()->first()->boq_total_usd)->toBe(32500.0);
    expect(MobileFormSubmission::query()->count())->toBe(1);
    expect(MobileFormSubmission::query()->first()->getRawOriginal('changes'))->not->toContain('أسرة');
    $this->payload['answers']['borrower_name'] = 'اسم مختلف';
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertConflict();
});
it('rejects invalid answers nested mass assignment and stale form versions without side effects', function (): void {
    $this->payload['answers']['submitted_by'] = 999;
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertUnprocessable();
    unset($this->payload['answers']['submitted_by']);
    $this->payload['answers']['resident_households'] = [['head_name' => 'x', 'is_admin' => true]];
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertUnprocessable();
    unset($this->payload['answers']['resident_households']);
    $this->payload['answers']['family_members_count'] = 101;
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('answers.family_members_count');
    $this->payload['form_version'] = str_repeat('0', 64);
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertConflict();
    expect(DamageAssessmentBorrower::query()->count())->toBe(0);
    expect(MobileFormSubmission::query()->count())->toBe(0);
});
it('requires exact record versions and never silently upserts an existing identity', function (): void {
    $row = DamageAssessmentBorrower::query()->create(['borrower_name' => 'قديم', 'borrower_id_number' => 'FIXTURE-123', 'is_borrower_alive' => true]);
    $this->payload['answers']['borrower_id_number'] = 'FIXTURE-123';
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('answers.borrower_id_number');
    $schema = $this->getJson('/api/v1/forms/borrower-survey/schema?record='.$row->id)->assertOk()->json('data');
    $this->payload += ['record_id' => $row->id, 'record_version' => $schema['record_version']];
    $row->update(['borrower_name' => 'تعديل أحدث']);
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertConflict();
    $this->payload['record_version'] = $this->getJson('/api/v1/forms/borrower-survey/schema?record='.$row->id)->json('data.record_version');
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertCreated();
    expect($row->fresh()->borrower_name)->toBe('أسرة تجريبية');
});
it('requires submit ability current role and active account independently', function (): void {
    $readOnly = $this->formUser->createToken('read', ['mobile:read'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($readOnly)->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertForbidden();
    $this->formUser->removeRole('Database Officer');
    $this->app['auth']->forgetGuards();
    $this->withToken($this->formToken)->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertForbidden();
    $this->formUser->update(['is_active' => false]);
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/forms')->assertForbidden();
    expect(MobileFormSubmission::query()->count())->toBe(0);
});
it('uploads private bounded files binds them to one account and rejects unsupported types', function (): void {
    Storage::fake('local');
    $file = UploadedFile::fake()->image('photo.jpg', 10, 10);
    $path = '/api/v1/forms/borrower-survey/attachments';
    $upload = $this->post($path, ['submission_uuid' => $this->payload['submission_uuid'], 'file' => $file], ['Accept' => 'application/json'])->assertCreated();
    $id = $upload->json('data.id');
    $this->post($path, ['submission_uuid' => $this->payload['submission_uuid'], 'file' => $file], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.id', $id);
    expect(MobileFormAttachment::query()->count())->toBe(1);
    $upload->assertJsonMissingPath('data.path');
    $this->get('/api/v1/form-attachments/'.$id)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $other = User::factory()->create();
    $other->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $foreign = MobileFormAttachment::factory()->create(['user_id' => $other->id, 'submission_uuid' => $this->payload['submission_uuid']]);
    $this->payload['attachments'] = [$foreign->id];
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertUnprocessable();
    $this->get('/api/v1/form-attachments/'.$foreign->id)->assertNotFound();
    $this->postJson('/api/v1/forms/borrower-survey/attachments/'.$foreign->id.'/discard', ['submission_uuid' => $this->payload['submission_uuid']])->assertOk();
    expect($foreign->fresh())->not->toBeNull();
    $this->payload['attachments'] = [$id];
    $receipt = $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertCreated()->assertJsonCount(1, 'data.attachments');
    $this->getJson('/api/v1/submissions/'.$this->payload['submission_uuid'])->assertOk()->assertJsonPath('data.id', $receipt->json('data.id'));
    $this->postJson('/api/v1/forms/borrower-survey/attachments/'.$id.'/discard', ['submission_uuid' => $this->payload['submission_uuid']])->assertConflict();
    $this->post($path, ['submission_uuid' => (string) Str::uuid(), 'file' => UploadedFile::fake()->create('bad.exe', 1)], ['Accept' => 'application/json'])->assertUnprocessable();
});
it('updates only published HEKS fields and records server receipt without enabling raw Kobo writes', function (): void {
    $beneficiary = HeksBeneficiary::query()->create(['code' => 'HEKS-TEST', 'name' => 'تجريبي', 'raw_data' => ['locked' => 'unchanged']]);
    $schema = $this->getJson('/api/v1/forms/heks-beneficiary/schema?record='.$beneficiary->id)->assertOk()->json('data');
    $payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => $schema['version'], 'record_id' => $beneficiary->id,
        'record_version' => $schema['record_version'], 'answers' => ['engineer_notes' => 'زيارة مكتملة']];
    $this->postJson('/api/v1/forms/heks-beneficiary/submissions', $payload)->assertCreated();
    expect($beneficiary->fresh()->raw_data)->toBe(['locked' => 'unchanged']);
    $followup = HeksFollowUp::query()->create(['heks_beneficiary_id' => $beneficiary->id, 'code' => 'HEKS-TEST', 'source_record_key' => 'test']);
    $schema = $this->getJson('/api/v1/forms/heks-followup/schema?record='.$followup->id)->assertOk()->json('data');
    $payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => $schema['version'], 'record_id' => $followup->id,
        'record_version' => $schema['record_version'], 'answers' => ['completion_percentage' => 101]];
    $this->postJson('/api/v1/forms/heks-followup/submissions', $payload)->assertUnprocessable();
    $payload['answers']['completion_percentage'] = 70;
    $this->postJson('/api/v1/forms/heks-followup/submissions', $payload)->assertCreated();
    $this->getJson('/api/v1/forms/heks-followup/records?search=تجريبي')->assertOk()->assertJsonPath('total', 1);
    $this->getJson('/api/v1/submissions')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.changes');
});

it('removes only staged own attachments and preserves received files', function (): void {
    Storage::fake('local');
    $file = UploadedFile::fake()->image('photo.png', 10, 10);
    $response = $this->post('/api/v1/forms/borrower-survey/attachments', ['submission_uuid' => $this->payload['submission_uuid'], 'file' => $file], ['Accept' => 'application/json'])->assertCreated();
    $id = $response->json('data.id');
    $path = MobileFormAttachment::query()->findOrFail($id)->path;
    $this->postJson('/api/v1/forms/borrower-survey/attachments/'.$id.'/discard', ['submission_uuid' => $this->payload['submission_uuid']])->assertOk();
    Storage::disk('local')->assertMissing($path);
    $this->postJson('/api/v1/forms/borrower-survey/attachments/'.$id.'/discard', ['submission_uuid' => $this->payload['submission_uuid']])->assertOk();
    $this->get('/api/v1/form-attachments/'.$id)->assertNotFound();
});

it('rejects contradictory vulnerability answers without losing visit time precision', function (): void {
    $this->payload['answers']['vulnerability_types'] = ['none', 'disabled'];
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('answers.vulnerability_types');
    $this->payload['answers']['vulnerability_types'] = ['disabled'];
    $this->payload['answers']['surveyed_at'] = '2026-10-11T09:35:21Z';
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertCreated();
    expect(DamageAssessmentBorrower::query()->first()->surveyed_at->format('H:i:s'))->toBe('09:35:21');
});

it('validates optional GPS coordinates and encrypts precise receipt locations', function (): void {
    foreach ([[], ['latitude' => 91, 'longitude' => 34.4], ['latitude' => 31.5, 'longitude' => 181], ['latitude' => 31.5, 'longitude' => 34.4, 'secret' => 'blocked']] as $invalid) {
        $this->payload['location'] = $invalid;
        $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertUnprocessable();
    }
    $this->payload['location'] = ['latitude' => 31.5, 'longitude' => 34.4, 'accuracy' => 10];
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertCreated();
    $receipt = MobileFormSubmission::query()->first();
    expect($receipt->location['latitude'])->toBe(31.5);
    expect($receipt->getRawOriginal('location'))->not->toContain('31.5');
    expect((float) DamageAssessmentBorrower::query()->first()->location_latitude)->toBe(31.5);
});

it('keeps receipt replay stable when optional defaults are omitted or explicit', function (): void {
    $receipt = $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertCreated();
    $this->payload += ['record_id' => null, 'record_version' => null, 'attachments' => [], 'location' => null];
    $this->postJson('/api/v1/forms/borrower-survey/submissions', $this->payload)->assertOk()->assertJsonPath('data.id', $receipt->json('data.id'));
});
