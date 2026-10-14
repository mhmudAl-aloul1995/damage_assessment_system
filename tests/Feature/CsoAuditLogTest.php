<?php

declare(strict_types=1);

use App\Models\CsoAuditLog;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\InfEditAssessment;
use App\Models\User;
use App\services\ArcgisService;
use App\services\CsoAuditService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    config([
        'services.arcgis.cso_survey_layer_url' => 'https://cso.test/FeatureServer',
        'services.arcgis.cso_survey_organizations_layer_url' => '',
        'services.arcgis.cso_survey_units_layer_url' => '',
    ]);
    $this->mock(ArcgisService::class)->shouldReceive('getToken')->andReturn('test-token');
    $this->officer = User::factory()->create();
    Role::findOrCreate('Inf - QC/QA Engineer', 'web');
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($this->officer);
    $this->survey = CsoSurvey::query()->create([
        'objectid' => 101, 'globalid' => 'audit-survey', 'building_name' => 'Original',
        'raw_payload' => ['building_name' => 'Original', 'weather' => '1', 'damage_date' => '2026-01-01'],
        'damage_date' => '2026-01-01',
    ])->refresh();
});

function fakeCsoAuditRemote(string $fieldName, mixed $oldValue, string $type = 'esriFieldTypeString', array $extraField = [], mixed $updateResponse = null): void
{
    Http::fake(function (Request $request) use ($fieldName, $oldValue, $type, $extraField, $updateResponse) {
        if (str_contains($request->url(), '/updateFeatures')) {
            return $updateResponse ?? Http::response(['updateResults' => [['objectId' => 101, 'success' => true]]]);
        }

        if (str_contains($request->url(), '/query')) {
            return Http::response(['features' => [['attributes' => ['OBJECTID' => 101, $fieldName => $oldValue]]]]);
        }

        return Http::response([
            'objectIdField' => 'OBJECTID',
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID', 'editable' => false],
                ['name' => $fieldName, 'type' => $type, 'nullable' => true, 'editable' => true, 'length' => 255, ...$extraField],
            ],
        ]);
    });
}

it('updates ArcGIS immediately and saves the base value and CSO log for every record type', function (string $model, string $tableType, string $recordType, string $fieldName, int $layer): void {
    $record = $model === CsoSurvey::class ? $this->survey : $model::query()->create([
        'objectid' => 101, 'globalid' => 'audit-child', 'parentglobalid' => $this->survey->globalid,
        $fieldName => 'Original', 'raw_payload' => [strtoupper($fieldName) => 'Original'],
    ])->refresh();
    fakeCsoAuditRemote(strtoupper($fieldName), 'Original');

    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => $tableType, 'auditable_id' => $record->id,
        'field_name' => $fieldName, 'field_value' => 'Verified', 'notes' => 'Confirmed by phone',
        'expected_value' => 'Original',
    ])->assertOk()->assertJsonPath('raw_value', 'Verified')->assertJsonPath('history.0.old_value', 'Original');

    expect($record->fresh()->getRawOriginal($fieldName))->toBe('Verified')
        ->and(app(CsoAuditService::class)->value($record->fresh(), $fieldName))->toBe('Verified')
        ->and(InfEditAssessment::query()->count())->toBe(0);
    $log = CsoAuditLog::query()->sole();
    expect($log->cso_survey_id)->toBe($this->survey->id)
        ->and($log->record_type)->toBe($recordType)
        ->and($log->record_id)->toBe($record->id)
        ->and($log->old_value)->toBe('Original')
        ->and($log->new_value)->toBe('Verified')
        ->and($log->user_id)->toBe($this->officer->id)
        ->and($log->notes)->toBe('Confirmed by phone');
    Http::assertSent(function (Request $request) use ($fieldName, $layer): bool {
        return $request->url() === 'https://cso.test/FeatureServer/'.$layer.'/updateFeatures'
            && $request->method() === 'POST'
            && json_decode($request['features'], true)[0]['attributes'] === ['OBJECTID' => 101, strtoupper($fieldName) => 'Verified'];
    });
    Queue::assertNothingPushed();
})->with([
    [CsoSurvey::class, 'cso_survey_table', 'cso_survey', 'building_name', 0],
    [CsoSurveyOrganization::class, 'cso_survey_organization_table', 'cso_survey_organization', 'organization_name_en', 1],
    [CsoSurveyUnit::class, 'cso_survey_unit_table', 'cso_survey_unit', 'unit_name', 2],
]);

it('rolls back the local value and log when ArcGIS rejects an edit', function (array $body, int $status): void {
    fakeCsoAuditRemote('building_name', 'Original', updateResponse: Http::response($body, $status));
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id,
        'field_name' => 'building_name', 'field_value' => 'Rejected change',
    ])->assertStatus(502);

    expect($this->survey->fresh()->building_name)->toBe('Original')
        ->and($this->survey->fresh()->raw_payload['building_name'])->toBe('Original')
        ->and(CsoAuditLog::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    [['updateResults' => [['success' => false, 'error' => ['description' => 'Not allowed']]]], 200],
    [['error' => ['code' => 498, 'message' => 'Invalid token']], 200],
    [[], 200],
    [['updateResults' => [['success' => true]]], 500],
]);

it('stores payload fields in the base record and clears values without resurrecting legacy edits', function (): void {
    InfEditAssessment::query()->create([
        'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id,
        'global_id' => $this->survey->globalid, 'field_name' => 'weather', 'field_value' => 'legacy-value',
    ]);
    fakeCsoAuditRemote('weather', '1');
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id,
        'field_name' => 'weather', 'field_value' => null,
    ])->assertOk()->assertJsonPath('raw_value', null);
    expect($this->survey->fresh()->raw_payload['weather'])->toBeNull()
        ->and(CsoAuditLog::query()->sole()->new_value)->toBeNull();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/updateFeatures')
        && array_key_exists('weather', json_decode($request['features'], true)[0]['attributes'])
        && json_decode($request['features'], true)[0]['attributes']['weather'] === null);

    $this->get(route('inf-audit.cso.show', $this->survey))->assertOk()->assertDontSee('legacy-value');
});

it('sends numbers using ArcGIS types and records their actual values', function (): void {
    $unit = CsoSurveyUnit::query()->create([
        'objectid' => 101, 'globalid' => 'numeric-unit', 'parentglobalid' => $this->survey->globalid, 'unit_number' => 2,
    ]);
    fakeCsoAuditRemote('unit_number', 2, 'esriFieldTypeInteger');
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_unit_table', 'auditable_id' => $unit->id, 'field_name' => 'unit_number', 'field_value' => '0',
    ])->assertOk()->assertJsonPath('raw_value', '0');
    expect(CsoAuditLog::query()->sole()->new_value)->toBe(0);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/updateFeatures') && json_decode($request['features'], true)[0]['attributes']['unit_number'] === 0);
});

it('sends dates as UTC milliseconds and stores a local date', function (): void {
    fakeCsoAuditRemote('damage_date', 1767225600000, 'esriFieldTypeDate');
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id, 'field_name' => 'damage_date', 'field_value' => '2026-10-14',
    ])->assertOk()->assertJsonPath('raw_value', '2026-10-14');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/updateFeatures') && json_decode($request['features'], true)[0]['attributes']['damage_date'] === 1791936000000);
});

it('rejects protected fields and records outside the survey without contacting ArcGIS', function (): void {
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id, 'field_name' => 'objectid', 'field_value' => '999',
    ])->assertUnprocessable()->assertJsonValidationErrors('field_name');
    $child = CsoSurveyUnit::query()->create(['objectid' => 201, 'globalid' => 'other-unit', 'parentglobalid' => 'other-survey']);
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_unit_table', 'auditable_id' => $child->id, 'field_name' => 'unit_name', 'field_value' => 'Hijacked',
    ])->assertNotFound();
    Http::assertNothingSent();
    expect(CsoAuditLog::query()->count())->toBe(0);
});

it('rejects stale screens and skips unchanged values', function (): void {
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id, 'field_name' => 'building_name',
        'field_value' => 'Another edit', 'expected_value' => 'Outdated',
    ])->assertConflict();
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id, 'field_name' => 'building_name', 'field_value' => 'Original',
    ])->assertOk();
    Http::assertNothingSent();
    expect(CsoAuditLog::query()->count())->toBe(0);
});

it('keeps successive changes in the log and displays the latest base value', function (): void {
    fakeCsoAuditRemote('building_name', 'Original');
    foreach (['First edit', 'Latest edit'] as $value) {
        $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
            'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id,
            'field_name' => 'building_name', 'field_value' => $value,
        ])->assertOk();
    }

    expect($this->survey->fresh()->building_name)->toBe('Latest edit')
        ->and($this->survey->auditLogs()->orderBy('id')->get()->pluck('old_value')->all())->toBe(['Original', 'First edit']);
    $this->get(route('inf-audit.cso.show', $this->survey))->assertOk()
        ->assertSee('القيمة الحالية')->assertSee('Latest edit')->assertSee('First edit');
});

it('rejects invalid ArcGIS values before writing either database', function (): void {
    fakeCsoAuditRemote('weather', '1', extraField: ['domain' => ['type' => 'codedValue', 'codedValues' => [['code' => '1', 'name' => 'Sunny']]]]);
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id, 'field_name' => 'weather', 'field_value' => 'invalid',
    ])->assertUnprocessable()->assertJsonValidationErrors('field_value');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/updateFeatures'));
    expect(CsoAuditLog::query()->count())->toBe(0)->and($this->survey->fresh()->raw_payload['weather'])->toBe('1');
});

it('honors the explicitly configured child layer URL', function (): void {
    config(['services.arcgis.cso_survey_units_layer_url' => 'https://other-cso.test/FeatureServer/7/query']);
    $unit = CsoSurveyUnit::query()->create(['objectid' => 101, 'globalid' => 'configured-layer-unit', 'parentglobalid' => $this->survey->globalid, 'unit_name' => 'Original']);
    fakeCsoAuditRemote('unit_name', 'Original');
    $this->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_unit_table', 'auditable_id' => $unit->id, 'field_name' => 'unit_name', 'field_value' => 'Updated',
    ])->assertOk();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://other-cso.test/FeatureServer/7/updateFeatures');
});

it('prevents an unassigned auditor from changing CSO records', function (): void {
    $auditor = User::factory()->create();
    $auditor->assignRole('Inf - QC/QA Engineer');
    $this->actingAs($auditor)->postJson(route('inf-audit.cso.field-update', $this->survey), [
        'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id, 'field_name' => 'building_name', 'field_value' => 'Unauthorized',
    ])->assertForbidden();
    Http::assertNothingSent();
    expect(CsoAuditLog::query()->count())->toBe(0);
});

it('imports legacy CSO history once and applies the latest value without deleting the original history', function (): void {
    foreach (['First edit', 'Final edit'] as $value) {
        InfEditAssessment::query()->create([
            'table_type' => 'cso_survey_table', 'auditable_id' => $this->survey->id,
            'global_id' => $this->survey->globalid, 'objectid' => 101,
            'field_name' => 'building_name', 'old_value' => 'Original', 'field_value' => $value, 'user_id' => $this->officer->id,
        ]);
    }
    app(CsoAuditService::class)->importLegacyEdits();
    expect(CsoAuditLog::query()->count())->toBe(2)->and($this->survey->fresh()->building_name)->toBe('Final edit')
        ->and(InfEditAssessment::query()->count())->toBe(2);
    $this->survey->refresh()->update(['building_name' => 'Later value']);
    app(CsoAuditService::class)->importLegacyEdits();
    expect(CsoAuditLog::query()->count())->toBe(2)->and($this->survey->fresh()->building_name)->toBe('Later value');
    Http::assertNothingSent();
});
