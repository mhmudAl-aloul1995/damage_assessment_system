<?php

declare(strict_types=1);

use App\Models\CsoAuditLog;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyFilter;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\InfAuditAssignment;
use App\Models\User;
use Database\Seeders\InfAuditRolesSeeder;
use Database\Seeders\InfAuditStatusesSeeder;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['services.arcgis.cso_survey_layer_url' => '']);
    $this->seed([InfAuditRolesSeeder::class, InfAuditStatusesSeeder::class]);
    $this->officer = User::factory()->create(['preferred_locale' => 'ar', 'name' => 'مدقق الاختبار']);
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($this->officer);
    $this->survey = CsoSurvey::query()->create([
        'objectid' => 1001, 'globalid' => fake()->uuid(), 'field_status' => 'COMPLETED',
        'organization_name' => 'مؤسسة الأمل للتنمية', 'building_name' => 'المقر الرئيسي',
        'municipalitie' => 'غزة', 'neighborhood' => 'الرمال',
        'raw_payload' => ['weather' => '1', 'building_name' => 'المقر الرئيسي'],
    ]);
});

it('renders separate record browsers with only children of the selected survey and compact field history', function (): void {
    $auditor = User::factory()->create(['name' => 'أحمد محمود']);
    $auditor->assignRole('Inf - QC/QA Engineer');
    foreach ([2101 => 'جمعية التنمية المجتمعية', 2102 => 'مركز الدعم المجتمعي'] as $objectid => $name) {
        CsoSurveyOrganization::query()->create([
            'objectid' => $objectid, 'globalid' => fake()->uuid(), 'parentglobalid' => $this->survey->globalid,
            'organization_name_ar' => $name, 'organization_name_en' => 'Organization '.$objectid,
        ]);
    }
    foreach ([3101 => 'قاعة التدريب', 3102 => 'غرفة الاستقبال'] as $objectid => $name) {
        CsoSurveyUnit::query()->create([
            'objectid' => $objectid, 'globalid' => fake()->uuid(), 'parentglobalid' => $this->survey->globalid,
            'unit_name' => $name,
        ]);
    }
    CsoSurveyOrganization::query()->create(['globalid' => fake()->uuid(), 'parentglobalid' => fake()->uuid(), 'organization_name_ar' => 'منظمة خارجية مخفية']);
    CsoSurveyUnit::query()->create(['globalid' => fake()->uuid(), 'parentglobalid' => fake()->uuid(), 'unit_name' => 'وحدة خارجية مخفية']);
    CsoSurveyFilter::query()->create(['list_name' => 'weather', 'name' => '1', 'label' => 'مشمس', 'sort_order' => 1]);
    CsoSurveyFilter::query()->create(['list_name' => 'weather', 'name' => '2', 'label' => 'غائم', 'sort_order' => 2]);
    CsoAuditLog::factory()->create([
        'cso_survey_id' => $this->survey->id, 'record_id' => $this->survey->id,
        'record_globalid' => $this->survey->globalid, 'user_id' => $this->officer->id,
        'old_value' => 'المقر السابق', 'new_value' => 'المقر الرئيسي', 'notes' => 'تمت مطابقة الاسم',
    ]);

    $response = $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()
        ->assertViewIs('damage-assessment::infrastructure-audit.cso.show')
        ->assertViewHas('organizationGroups', fn (array $groups): bool => count($groups) === 2 && array_column($groups, 'objectid') === [2101, 2102])
        ->assertViewHas('unitGroups', fn (array $groups): bool => count($groups) === 2 && array_column($groups, 'objectid') === [3101, 3102])
        ->assertSee('id="cso_survey_tab"', false)->assertSee('id="cso_organizations_tab"', false)->assertSee('id="cso_units_tab"', false)
        ->assertSee('جمعية التنمية المجتمعية')->assertSee('قاعة التدريب')->assertSee('تم تعديله · السجل (1)')
        ->assertSee('المقر السابق')->assertSee('تمت مطابقة الاسم')
        ->assertDontSee('منظمة خارجية مخفية')->assertDontSee('وحدة خارجية مخفية')
        ->assertDontSee('inf-audit-edit-card');

    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//*[@id="cso_organizations_pane"]//*[@data-record-content and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="cso_units_pane"]//*[@data-record-content and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="cso_organizations_pane"]//button[@data-table-type="cso_survey_unit_table"]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="cso_units_pane"]//button[@data-table-type="cso_survey_organization_table"]')->length)->toBe(0)
        ->and($xpath->query('//button[@data-field-name="objectid" or @data-field-name="globalid"]')->length)->toBe(0);
    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'phc-cso-workspace-preview.html', $response->getContent());
});

it('shows a single record directly and provides clear empty states for missing children', function (): void {
    CsoSurveyUnit::query()->create([
        'objectid' => 3101, 'globalid' => fake()->uuid(), 'parentglobalid' => $this->survey->globalid, 'unit_name' => 'الوحدة الوحيدة',
    ]);
    $response = $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()
        ->assertSee('لا توجد منظمات مرتبطة بهذا الاستبيان')->assertSee('الوحدة الوحيدة');
    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//*[@id="cso_units_pane"]//button[@data-record-target]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="cso_units_pane"]//*[@data-record-content and not(@hidden)]')->length)->toBe(1);
});

it('shows editing and decision controls to CSO officers and keeps project viewers read only', function (): void {
    $csoOfficer = User::factory()->create(['preferred_locale' => 'ar']);
    $csoOfficer->assignRole(Role::findOrCreate('CSO Officer', 'web'));
    $this->actingAs($csoOfficer)->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()
        ->assertSee('id="fieldEditModal"', false)->assertSee('data-status="final_approval"', false);

    $viewer = User::factory()->create(['preferred_locale' => 'ar']);
    $viewer->assignRole(Role::findOrCreate('Project Officer', 'web'));
    $response = $this->actingAs($viewer)->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()
        ->assertViewHas('canEditFields', false)->assertDontSee('id="fieldEditModal"', false);
    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//button[@data-field-name]')->length)->toBe(0)
        ->and($xpath->query('//button[@data-status]')->length)->toBe(0);
});

it('restricts auditors to their assigned surveys and displays their decision controls', function (): void {
    $auditor = User::factory()->create(['preferred_locale' => 'ar']);
    $auditor->assignRole('Inf - QC/QA Engineer');
    $this->actingAs($auditor)->get(route('inf-audit.cso.show', $this->survey))->assertForbidden();
    InfAuditAssignment::query()->create([
        'type' => 'cso_survey', 'globalid' => $this->survey->globalid, 'user_id' => $auditor->id, 'manager_id' => $this->officer->id,
    ]);
    $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()
        ->assertSee('data-status="accepted"', false)->assertDontSee('data-status="final_approval"', false);
});

function csoWorkspaceDocument(string $html): DOMDocument
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $document;
}
