<?php

declare(strict_types=1);

use App\Models\CsoSurvey;
use App\Models\CsoSurveyAuditStatus;
use App\Models\InfAuditAssignment;
use App\Models\InfAuditStatus;
use App\Models\User;
use Database\Seeders\InfAuditRolesSeeder;
use Database\Seeders\InfAuditStatusesSeeder;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->seed([InfAuditRolesSeeder::class, InfAuditStatusesSeeder::class]);
    $this->officer = User::factory()->create(['preferred_locale' => 'ar']);
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($this->officer);
});

function csoFilterSurvey(array $attributes = []): CsoSurvey
{
    return CsoSurvey::query()->create([
        'objectid' => fake()->unique()->numberBetween(1000, 9999),
        'globalid' => fake()->uuid(),
        'field_status' => 'COMPLETED',
        'building_name' => 'مبنى المؤسسة',
        'organization_name' => 'مؤسسة الأمل',
        'municipalitie' => 'غزة',
        'neighborhood' => 'الرمال',
        'assignedto' => 'field.completed',
        'creationdate' => '2026-10-10 12:00:00',
        ...$attributes,
    ]);
}

it('only returns completed surveys and cannot be overridden by request parameters', function (): void {
    $completed = csoFilterSurvey(['objectid' => 1001]);
    foreach (['Not_Completed', 'IN_PROGRESS', '', null] as $status) {
        csoFilterSurvey(['field_status' => $status]);
    }
    $final = csoFilterSurvey();
    CsoSurveyAuditStatus::query()->create([
        'cso_survey_id' => $final->id,
        'status_id' => InfAuditStatus::query()->where('name', 'final_approval')->value('id'),
    ]);

    $this->getJson(route('inf-audit.cso.data', [
        'draw' => 1, 'start' => 0, 'length' => 10, 'field_status' => 'Not_Completed',
    ]))->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.objectid', $completed->objectid);
});

it('renders labeled primary and advanced filters using only eligible survey options', function (): void {
    csoFilterSurvey();
    csoFilterSurvey(['field_status' => 'Not_Completed', 'municipalitie' => 'Hidden municipality', 'neighborhood' => 'Hidden neighborhood', 'assignedto' => 'field.incomplete']);
    $final = csoFilterSurvey(['municipalitie' => 'Final municipality', 'neighborhood' => 'Final neighborhood', 'assignedto' => 'field.final']);
    CsoSurveyAuditStatus::query()->create([
        'cso_survey_id' => $final->id, 'status_id' => InfAuditStatus::query()->where('name', 'final_approval')->value('id'),
    ]);
    $auditor = User::factory()->create(['name' => 'أحمد محمود']);
    $auditor->assignRole('Inf - QC/QA Engineer');
    User::factory()->create(['username_arcgis' => 'field.completed', 'name' => 'محمد عبد الرحمن · مهندس ميداني']);

    $response = $this->get(route('inf-audit.cso.index'))->assertOk()
        ->assertSee('مكتملة ميدانياً')->assertSee('تطبيق الفلاتر')->assertSee('فلاتر إضافية')
        ->assertSee('for="filter_objectid"', false)->assertSee('for="cso_survey_date_range"', false)
        ->assertSee('aria-controls="cso_advanced_filters"', false)
        ->assertSee('id="cso_assignment_bar"', false)
        ->assertViewHas('municipalities', fn ($values): bool => $values->all() === ['غزة'])
        ->assertViewHas('neighborhoods', fn ($values): bool => $values->all() === ['الرمال'])
        ->assertViewHas('fieldEngineers', fn (array $values): bool => array_column($values, 'value') === ['field.completed'])
        ->assertDontSee('field.incomplete')->assertDontSee('Final municipality')
        ->assertDontSee('<option value="final_approval">', false);

    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'phc-cso-filter-preview.html', $response->getContent());
});

it('filters completed surveys by municipality and date', function (): void {
    $matching = csoFilterSurvey(['municipalitie' => 'غزة', 'creationdate' => '2026-10-10 12:00:00']);
    csoFilterSurvey(['municipalitie' => 'غزة', 'creationdate' => '2026-09-01 12:00:00']);
    csoFilterSurvey(['municipalitie' => 'خان يونس']);
    csoFilterSurvey(['field_status' => 'Not_Completed']);

    $this->getJson(route('inf-audit.cso.data', [
        'draw' => 1, 'start' => 0, 'length' => 10,
        'municipalitie' => 'غزة', 'from_date' => '2026-10-01', 'to_date' => '2026-10-14',
    ]))->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.objectid', $matching->objectid);
});

it('only assigns completed surveys when mixed record identifiers are submitted', function (): void {
    $completed = csoFilterSurvey();
    $incomplete = csoFilterSurvey(['field_status' => 'Not_Completed']);
    $auditor = User::factory()->create();
    $auditor->assignRole('Inf - QC/QA Engineer');

    $this->postJson(route('inf-audit.cso.assign'), [
        'ids' => [$completed->id, $incomplete->id], 'assigned_to' => $auditor->id,
    ])->assertOk()->assertJsonPath('message', 'تم إسناد 1 سجل بنجاح.');
    expect(InfAuditAssignment::query()->where('globalid', $completed->globalid)->exists())->toBeTrue()
        ->and(InfAuditAssignment::query()->where('globalid', $incomplete->globalid)->exists())->toBeFalse();
});
