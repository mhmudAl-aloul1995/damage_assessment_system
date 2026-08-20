<?php

declare(strict_types=1);

use App\Models\CsoSurvey;
use App\Models\CsoSurveyAuditHistory;
use App\Models\CsoSurveyAuditStatus;
use App\Models\CsoSurveyFilter;
use App\Models\InfAuditAssignment;
use App\Models\InfEditAssessment;
use App\Models\User;
use Database\Seeders\InfAuditRolesSeeder;
use Database\Seeders\InfAuditStatusesSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
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
        ->assertSee('Sunny');

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.cso.field-update', $survey), [
            'table_type' => 'cso_survey_table',
            'auditable_id' => $survey->id,
            'field_name' => 'weather',
            'field_value' => '1',
            'notes' => 'Verified from call',
        ])
        ->assertOk();

    expect(InfEditAssessment::query()->where('table_type', 'cso_survey_table')->where('field_name', 'weather')->exists())->toBeTrue();

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.cso.status', $survey), [
            'status' => 'accepted',
        ])
        ->assertOk();

    expect(CsoSurveyAuditStatus::query()->where('cso_survey_id', $survey->id)->count())->toBe(2);
});
