<?php

use App\Models\Assessment;
use App\Models\AssessmentEditHistory;
use App\Models\AssignedAssessmentUser;
use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\InfEditAssessment;
use App\Models\MobileFormSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    $this->damageFormUser = User::factory()->create(['allowed_phase_numbers' => [1]]);
    $this->damageFormUser->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->damageFormToken = $this->damageFormUser->createToken('damage-form', ['mobile:read', 'mobile:submit'])->plainTextToken;
    $this->withToken($this->damageFormToken);
    Assessment::query()->create(['name' => 'building_type', 'label' => 'نوع المبنى']);
    Assessment::query()->create(['name' => 'housing_unit_group', 'label' => 'مجموعة الوحدة']);
    Assessment::query()->create(['name' => 'housing_unit_type', 'label' => 'نوع الوحدة']);
    $this->sourceBuilding = Building::query()->create(['objectid' => 801, 'globalid' => 'mobile-building-1', 'building_type' => 'original', 'phase_number' => 1]);
    $this->auditBuilding = AuditedBuilding::query()->create(['objectid' => 801, 'globalid' => 'mobile-building-1', 'phase_number' => 1])->refresh();
});
it('publishes only the remaining questionnaires and hides retired audit receipts', function (): void {
    $response = $this->getJson('/api/v1/forms')->assertOk()->assertJsonCount(3, 'data');
    expect(collect($response->json('data'))->pluck('key')->all())->toBe(['borrower-survey', 'heks-beneficiary', 'heks-followup']);
    $this->getJson('/api/v1/forms/building-audit/schema?record='.$this->auditBuilding->id)->assertNotFound();
    $this->getJson('/api/v1/forms/housing-audit/records')->assertNotFound();
    $receipt = MobileFormSubmission::factory()->create(['user_id' => $this->damageFormUser->id, 'module' => 'damage_assessment', 'form_key' => 'building-audit', 'record_id' => $this->auditBuilding->id]);
    $this->getJson('/api/v1/submissions')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/submissions/'.$receipt->submission_uuid)->assertNotFound();
    expect($receipt->fresh())->not->toBeNull();
});
it('verifies provider TLS during audit synchronization authentication', function (): void {
    config(['services.arcgis.username' => 'fixture-user', 'services.arcgis.password' => 'fixture-password', 'services.arcgis.referer' => 'https://example.test']);
    Http::fake(['https://www.arcgis.com/sharing/rest/generateToken' => function ($request, array $options) {
        expect($options['verify'])->toBeTrue();

        return Http::response(['token' => 'fixture-provider-token']);
    }]);
    expect(app(\App\services\ArcgisAuditedUploadService::class)->generateToken())->toBe('fixture-provider-token');
});
it('rejects old building submissions and repeated attempts without audit edits or provider jobs', function (): void {
    $payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => str_repeat('a', 64), 'record_id' => $this->auditBuilding->id,
        'record_version' => str_repeat('b', 64), 'answers' => ['building_type' => 'edited']];
    $this->postJson('/api/v1/forms/building-audit/submissions', $payload)->assertNotFound();
    $this->postJson('/api/v1/forms/building-audit/submissions', $payload)->assertNotFound();
    expect(AssessmentEditHistory::query()->count())->toBe(0);
    expect(MobileFormSubmission::query()->count())->toBe(0);
    expect($this->sourceBuilding->fresh()->building_type)->toBe('original');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});
it('keeps retired audit forms unavailable for hidden phases and assigned auditors', function (): void {
    $hidden = AuditedBuilding::query()->create(['objectid' => 802, 'globalid' => 'phase2', 'phase_number' => 2]);
    $this->getJson('/api/v1/forms/building-audit/schema?record='.$hidden->id)->assertNotFound();
    $auditor = User::factory()->create(['allowed_phase_numbers' => [1]]);
    $auditor->assignRole(Role::findOrCreate('QC/QA Engineer', 'web'));
    $this->app['auth']->forgetGuards();
    $this->withToken($auditor->createToken('auditor', ['mobile:read', 'mobile:submit'])->plainTextToken)
        ->getJson('/api/v1/forms/building-audit/schema?record='.$this->auditBuilding->id)->assertNotFound();
    AssignedAssessmentUser::query()->create(['building_id' => 801, 'user_id' => $auditor->id, 'type' => 'QC/QA Engineer']);
    $this->getJson('/api/v1/forms/building-audit/schema?record='.$this->auditBuilding->id)->assertNotFound();
    $this->getJson('/api/v1/forms/building-audit/records')->assertNotFound();
    $this->postJson('/api/v1/forms/building-audit/submissions', ['submission_uuid' => (string) Str::uuid(), 'form_version' => str_repeat('a', 64), 'answers' => ['assignedto' => 'someone']])->assertNotFound();
    expect(MobileFormSubmission::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
it('rejects retired housing questionnaires while retaining housing inquiry', function (): void {
    HousingUnit::query()->create(['objectid' => 901, 'globalid' => 'mobile-unit-1', 'parentglobalid' => 'mobile-building-1', 'housing_unit_type' => 'old', 'phase_number' => 1]);
    $unit = AuditedHousingUnit::query()->create(['objectid' => 901, 'globalid' => 'mobile-unit-1', 'parentglobalid' => 'mobile-building-1', 'phase_number' => 1]);
    $this->getJson('/api/v1/forms/housing-audit/schema?record='.$unit->id)->assertNotFound();
    $this->postJson('/api/v1/forms/housing-audit/submissions', ['submission_uuid' => (string) Str::uuid(), 'form_version' => str_repeat('a', 64), 'record_id' => $unit->id, 'record_version' => str_repeat('b', 64), 'answers' => ['housing_unit_type' => 'new']])->assertNotFound();
    $this->getJson('/api/v1/damage-assessment/housing-units?search=901')->assertOk()->assertJsonPath('data.0.objectid', 901);
    expect(AssessmentEditHistory::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
it('disables every infrastructure audit form endpoint without changing existing records', function (string $form, string $parentModel, ?string $childModel, string $table): void {
    $parent = $parentModel::query()->create(['objectid' => 501, 'globalid' => 'inf-parent', 'phase_number' => 1]);
    $row = $childModel ? $childModel::query()->create(['objectid' => 601, 'globalid' => 'inf-child', 'parentglobalid' => 'inf-parent', 'phase_number' => 1]) : $parent;
    $original = $row->refresh()->getAttributes();
    $uuid = (string) Str::uuid();
    $path = '/api/v1/forms/'.$form;
    $this->getJson($path.'/schema?record='.$row->id)->assertNotFound();
    $this->getJson($path.'/records')->assertNotFound();
    $this->postJson($path.'/submissions', ['submission_uuid' => $uuid, 'form_version' => str_repeat('a', 64), 'record_id' => $row->id, 'record_version' => str_repeat('b', 64), 'answers' => ['notes' => 'test']])->assertNotFound();
    $this->post($path.'/attachments', ['submission_uuid' => $uuid, 'file' => \Illuminate\Http\UploadedFile::fake()->image('test.jpg', 10, 10)], ['Accept' => 'application/json'])->assertNotFound();
    $this->postJson($path.'/attachments/1/discard', ['submission_uuid' => $uuid])->assertNotFound();
    expect($row->fresh()->getAttributes())->toBe($original);
    expect(InfEditAssessment::query()->where('table_type', $table)->count())->toBe(0);
    expect(MobileFormSubmission::query()->count())->toBe(0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with([
    ['public-building-audit', \App\Models\PublicBuildingSurvey::class, null, 'public_building_table'],
    ['public-building-unit-audit', \App\Models\PublicBuildingSurvey::class, \App\Models\PublicBuildingSurveyUnit::class, 'public_building_unit_table'],
    ['road-audit', \App\Models\RoadFacilitySurvey::class, null, 'road_facility_table'],
    ['road-item-audit', \App\Models\RoadFacilitySurvey::class, \App\Models\RoadFacilitySurveyItem::class, 'road_facility_item_table'],
    ['cso-audit', \App\Models\CsoSurvey::class, null, 'cso_survey_table'],
    ['cso-organization-audit', \App\Models\CsoSurvey::class, \App\Models\CsoSurveyOrganization::class, 'cso_survey_organization_table'],
    ['cso-unit-audit', \App\Models\CsoSurvey::class, \App\Models\CsoSurveyUnit::class, 'cso_survey_unit_table'],
]);
