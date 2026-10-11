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
it('publishes typed damage schemas excluding identifiers phases and system fields', function (): void {
    $this->getJson('/api/v1/forms')->assertOk()->assertJsonFragment(['key' => 'building-audit']);
    Assessment::query()->create(['name' => 'assignedto', 'label' => 'assignment']);
    Assessment::query()->create(['name' => 'globalid', 'label' => 'id']);
    $schema = $this->getJson('/api/v1/forms/building-audit/schema?record='.$this->auditBuilding->id)->assertOk()->json('data');
    $keys = collect($schema['sections'])->flatMap(fn (array $section): array => $section['fields'])->pluck('key')->all();
    expect($keys)->toBe(['building_type']);
    expect($schema['answers']['building_type'])->toBe('original');
    $this->getJson('/api/v1/forms')->assertOk()->assertJsonFragment(['key' => 'housing-audit']);
});

it('verifies provider TLS during audit synchronization authentication', function (): void {
    config(['services.arcgis.username' => 'fixture-user', 'services.arcgis.password' => 'fixture-password', 'services.arcgis.referer' => 'https://example.test']);
    Http::fake(['https://www.arcgis.com/sharing/rest/generateToken' => function ($request, array $options) {
        expect($options['verify'])->toBeTrue();

        return Http::response(['token' => 'fixture-provider-token']);
    }]);
    expect(app(\App\services\ArcgisAuditedUploadService::class)->generateToken())->toBe('fixture-provider-token');
});
it('records building edits through existing audit history and handles stale snapshots and replay', function (): void {
    $schema = $this->getJson('/api/v1/forms/building-audit/schema?record='.$this->auditBuilding->id)->assertOk()->json('data');
    $payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => $schema['version'], 'record_id' => $this->auditBuilding->id,
        'record_version' => $schema['record_version'], 'answers' => ['building_type' => 'edited']];
    $receipt = $this->postJson('/api/v1/forms/building-audit/submissions', $payload)->assertCreated();
    expect(AssessmentEditHistory::query()->where('global_id', 'mobile-building-1')->count())->toBe(1);
    expect(AssessmentEditHistory::query()->first()->edited_by)->toBe($this->damageFormUser->id);
    Queue::assertPushed(\App\Jobs\SyncAuditEditToArcgis::class);
    $this->postJson('/api/v1/forms/building-audit/submissions', $payload)->assertOk()->assertJsonPath('data.id', $receipt->json('data.id'));
    $this->getJson('/api/v1/forms/building-audit/schema?record='.$this->auditBuilding->id)->assertOk()->assertJsonPath('data.answers.building_type', 'edited');
    $payload['submission_uuid'] = (string) Str::uuid();
    $this->postJson('/api/v1/forms/building-audit/submissions', $payload)->assertConflict();
    expect(AssessmentEditHistory::query()->count())->toBe(1);
});
it('rejects hidden phases unassigned auditors and system field writes', function (): void {
    $hiddenSource = Building::query()->create(['objectid' => 802, 'globalid' => 'phase2', 'phase_number' => 2]);
    $hidden = AuditedBuilding::query()->create(['objectid' => 802, 'globalid' => 'phase2', 'phase_number' => 2])->refresh();
    $this->getJson('/api/v1/forms/building-audit/schema?record='.$hidden->id)->assertNotFound();
    $schema = $this->getJson('/api/v1/forms/building-audit/schema?record='.$this->auditBuilding->id)->json('data');
    $payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => $schema['version'], 'record_id' => $this->auditBuilding->id,
        'record_version' => $schema['record_version'], 'answers' => ['assignedto' => 'someone']];
    $this->postJson('/api/v1/forms/building-audit/submissions', $payload)->assertUnprocessable();
    $auditor = User::factory()->create(['allowed_phase_numbers' => [1]]);
    $auditor->assignRole(Role::findOrCreate('QC/QA Engineer', 'web'));
    $this->app['auth']->forgetGuards();
    $this->withToken($auditor->createToken('auditor', ['mobile:read', 'mobile:submit'])->plainTextToken)
        ->getJson('/api/v1/forms/building-audit/schema?record='.$this->auditBuilding->id)->assertForbidden();
    $this->getJson('/api/v1/forms/building-audit/records')->assertOk()->assertJsonPath('total', 0);
    AssignedAssessmentUser::query()->create(['building_id' => 801, 'user_id' => $auditor->id, 'type' => 'QC/QA Engineer']);
    $this->getJson('/api/v1/forms/building-audit/schema?record='.$this->auditBuilding->id)->assertOk();
    $this->getJson('/api/v1/forms/building-audit/records')->assertOk()->assertJsonPath('total', 1);
    expect(MobileFormSubmission::query()->count())->toBe(0);
});
it('updates housing through the parent building assignment and existing edit service', function (): void {
    HousingUnit::query()->create(['objectid' => 901, 'globalid' => 'mobile-unit-1', 'parentglobalid' => 'mobile-building-1', 'housing_unit_type' => 'old', 'phase_number' => 1]);
    $unit = AuditedHousingUnit::query()->create(['objectid' => 901, 'globalid' => 'mobile-unit-1', 'parentglobalid' => 'mobile-building-1', 'phase_number' => 1])->refresh();
    $schema = $this->getJson('/api/v1/forms/housing-audit/schema?record='.$unit->id)->assertOk()->json('data');
    $payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => $schema['version'], 'record_id' => $unit->id,
        'record_version' => $schema['record_version'], 'answers' => ['housing_unit_type' => 'new']];
    $this->postJson('/api/v1/forms/housing-audit/submissions', $payload)->assertCreated();
    expect(AssessmentEditHistory::query()->first()->type)->toBe('housing_table');
});
it('uses existing infrastructure controllers for root and child audit histories', function (string $form, string $parentModel, ?string $childModel, string $table): void {
    $parent = $parentModel::query()->create(['objectid' => 501, 'globalid' => 'inf-parent', 'phase_number' => 1]);
    $row = $childModel ? $childModel::query()->create(['objectid' => 601, 'globalid' => 'inf-child', 'parentglobalid' => 'inf-parent', 'phase_number' => 1]) : $parent;
    $schema = $this->getJson('/api/v1/forms/'.$form.'/schema?record='.$row->id)->assertOk()->json('data');
    $field = collect($schema['sections'])->flatMap(fn (array $section): array => $section['fields'])->firstWhere('type', 'text');
    expect($field)->not->toBeNull();
    $payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => $schema['version'], 'record_id' => $row->id,
        'record_version' => $schema['record_version'], 'answers' => [$field['key'] => 'تعديل تجريبي']];
    $this->postJson('/api/v1/forms/'.$form.'/submissions', $payload)->assertCreated();
    expect(InfEditAssessment::query()->first()->table_type)->toBe($table);
    $this->getJson('/api/v1/forms/'.$form.'/schema?record='.$row->id)->assertOk()->assertJsonPath('data.answers.'.$field['key'], 'تعديل تجريبي');
    $payload['submission_uuid'] = (string) Str::uuid();
    $payload['record_version'] = $this->getJson('/api/v1/forms/'.$form.'/schema?record='.$row->id)->json('data.record_version');
    $this->postJson('/api/v1/forms/'.$form.'/submissions', $payload)->assertCreated();
    expect(InfEditAssessment::query()->count())->toBe(1);
    $this->getJson('/api/v1/forms/'.$form.'/records')->assertOk()->assertJsonPath('total', 1);
})->with([
    ['public-building-audit', \App\Models\PublicBuildingSurvey::class, null, 'public_building_table'],
    ['public-building-unit-audit', \App\Models\PublicBuildingSurvey::class, \App\Models\PublicBuildingSurveyUnit::class, 'public_building_unit_table'],
    ['road-audit', \App\Models\RoadFacilitySurvey::class, null, 'road_facility_table'],
    ['road-item-audit', \App\Models\RoadFacilitySurvey::class, \App\Models\RoadFacilitySurveyItem::class, 'road_facility_item_table'],
    ['cso-audit', \App\Models\CsoSurvey::class, null, 'cso_survey_table'],
    ['cso-organization-audit', \App\Models\CsoSurvey::class, \App\Models\CsoSurveyOrganization::class, 'cso_survey_organization_table'],
    ['cso-unit-audit', \App\Models\CsoSurvey::class, \App\Models\CsoSurveyUnit::class, 'cso_survey_unit_table'],
]);
