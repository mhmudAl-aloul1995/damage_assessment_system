<?php

declare(strict_types=1);

use App\Models\CsoAuditLog;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyAuditHistory;
use App\Models\CsoSurveyAuditStatus;
use App\Models\CsoSurveyFilter;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\InfAuditAssignment;
use App\Models\User;
use App\services\ArcgisService;
use Database\Seeders\InfAuditRolesSeeder;
use Database\Seeders\InfAuditStatusesSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->mock(ArcgisService::class)->shouldReceive('getToken')->andReturn('test-token');
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/updateFeatures')) {
            return Http::response(['updateResults' => [['success' => true]]]);
        }

        if (str_contains($request->url(), '/attachments')) {
            return Http::response(['attachmentInfos' => []]);
        }

        if (str_contains($request->url(), '/query')) {
            return Http::response(['features' => [['attributes' => ['weather' => '1', 'organization_name_en' => 'Women Support Association Branch']]]]);
        }

        return Http::response(['objectIdField' => 'objectid', 'fields' => [
            ['name' => 'weather', 'type' => 'esriFieldTypeString'],
            ['name' => 'organization_name_en', 'type' => 'esriFieldTypeString'],
        ]]);
    });
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::query()->firstOrCreate(['name' => 'Database Officer']);
    Role::query()->firstOrCreate(['name' => 'Project Officer']);

    $this->seed([
        InfAuditRolesSeeder::class,
        InfAuditStatusesSeeder::class,
    ]);
});

function csoInfAuditUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('database officer can assign and inf engineer can audit cso surveys', function (): void {
    $officer = csoInfAuditUser('Database Officer');
    $engineer = csoInfAuditUser('Inf - QC/QA Engineer');

    User::factory()->create([
        'name' => 'CSO Field Engineer',
        'username_arcgis' => 'cso.arcgis',
    ]);

    CsoSurveyFilter::query()->create([
        'list_name' => 'weather',
        'name' => '1',
        'label' => 'Sunny',
        'sort_order' => 1,
    ]);

    CsoSurvey::query()->create([
        'objectid' => 7101,
        'globalid' => 'cso-audit-global-id',
        'organization_name' => 'Women Support Association',
        'building_name' => 'Association HQ',
        'assignedto' => 'cso.arcgis',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Rimal',
        'building_damage_status' => 'partial_damage',
        'raw_payload' => [
            'weather' => '1',
            'organization_name_en' => 'Women Support Association',
        ],
    ]);

    $survey = CsoSurvey::query()->firstOrFail();

    $organization = CsoSurveyOrganization::query()->create([
        'objectid' => 8101,
        'globalid' => 'cso-child-organization-global-id',
        'parentglobalid' => $survey->globalid,
        'organization_name_en' => 'Women Support Association Branch',
        'operational_status' => 'partial',
        'raw_payload' => [
            'organization_name_en' => 'Women Support Association Branch',
        ],
    ]);

    CsoSurveyUnit::query()->create([
        'objectid' => 9101,
        'globalid' => 'cso-child-unit-global-id',
        'parentglobalid' => $survey->globalid,
        'unit_name' => 'Ground floor unit',
        'unit_damage_status' => 'minor_damage',
        'raw_payload' => [
            'unit_name' => 'Ground floor unit',
        ],
    ]);

    $this->actingAs($officer)
        ->get(route('inf-audit.cso.index'))
        ->assertOk()
        ->assertSee('تدقيق منظمات المجتمع المدني');

    $this->actingAs($officer)
        ->getJson(route('inf-audit.cso.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonFragment([
            'objectid' => 7101,
        ]);

    $this->actingAs($officer)
        ->postJson(route('inf-audit.cso.assign'), [
            'ids' => [$survey->id],
            'assigned_to' => $engineer->id,
        ])
        ->assertOk();

    expect(InfAuditAssignment::query()->where('type', 'cso_survey')->where('globalid', $survey->globalid)->exists())->toBeTrue()
        ->and(CsoSurveyAuditStatus::query()->where('cso_survey_id', $survey->id)->count())->toBe(1)
        ->and(CsoSurveyAuditHistory::query()->where('cso_survey_id', $survey->id)->count())->toBe(1);

    $this->actingAs($engineer)
        ->get(route('inf-audit.cso.show', $survey))
        ->assertOk()
        ->assertSee('Women Support Association')
        ->assertSee('Women Support Association Branch')
        ->assertSee('Ground floor unit')
        ->assertSee('Sunny');

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.cso.field-update', $survey), [
            'table_type' => 'cso_survey_table',
            'auditable_id' => $survey->id,
            'field_name' => 'weather',
            'field_value' => '2',
            'notes' => 'Verified from call',
        ])
        ->assertOk();

    expect(CsoAuditLog::query()->where('record_type', 'cso_survey')->where('field_name', 'weather')->exists())->toBeTrue()
        ->and($survey->fresh()->raw_payload['weather'])->toBe('2');

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.cso.field-update', $survey), [
            'table_type' => 'cso_survey_organization_table',
            'auditable_id' => $organization->id,
            'field_name' => 'organization_name_en',
            'field_value' => 'Women Support Association Branch Verified',
            'notes' => 'Verified child record',
        ])
        ->assertOk();

    expect(CsoAuditLog::query()->where('record_type', 'cso_survey_organization')->where('field_name', 'organization_name_en')->exists())->toBeTrue()
        ->and($organization->fresh()->organization_name_en)->toBe('Women Support Association Branch Verified');

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.cso.status', $survey), [
            'status' => 'accepted',
        ])
        ->assertOk();

    expect(CsoSurveyAuditStatus::query()->where('cso_survey_id', $survey->id)->count())->toBe(2);
});
