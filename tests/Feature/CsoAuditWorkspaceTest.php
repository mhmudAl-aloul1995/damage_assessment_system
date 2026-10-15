<?php

declare(strict_types=1);

use App\Models\CsoAuditLog;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyFilter;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\InfAuditAssignment;
use App\Models\User;
use App\services\ArcgisService;
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
        $organization = CsoSurveyOrganization::query()->where('objectid', 2101)->firstOrFail();
        CsoSurveyUnit::query()->create([
            'objectid' => $objectid, 'globalid' => fake()->uuid(), 'parentglobalid' => $objectid === 3101 ? $this->survey->globalid : $organization->globalid,
            'unit_name' => $name, 'raw_payload' => ['PARENTGLOBALID' => '{'.strtoupper($organization->globalid).'}'],
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
    fakeCsoWorkspaceAttachments([
        ...array_map(fn (int $id): array => ['id' => $id, 'name' => 'صورة المبنى '.$id.'.jpg', 'contentType' => 'image/jpeg'], range(1, 12)),
        ['id' => 100, 'name' => 'تقرير المعاينة.pdf', 'contentType' => 'application/pdf'],
        ['name' => 'مرفق بدون معرّف', 'contentType' => 'image/jpeg'],
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
    expect($xpath->query('//*[@id="cso_decision_header"]//button[@data-status="accepted"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="cso_gallery_panel"]//button[@data-cso-image-index]')->length)->toBe(12)
        ->and($xpath->query('//*[@id="cso_gallery_panel"]//a[contains(@href, "100.pdf")]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="cso_gallery_panel"]//img[contains(@src, "100.pdf")]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="cso_attachment_viewer"]')->length)->toBe(1);
    Http::assertNothingSent();
    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'phc-cso-workspace-preview.html', $response->getContent());
});

it('groups units by original and direct organization parents and isolates unresolved and foreign units', function (): void {
    $organizations = collect(['منظمة ألف', 'منظمة باء', 'منظمة بلا وحدات'])->map(
        fn (string $name, int $index): CsoSurveyOrganization => CsoSurveyOrganization::query()->create([
            'objectid' => 2101 + $index, 'globalid' => fake()->uuid(), 'parentglobalid' => $this->survey->globalid, 'organization_name_ar' => $name,
        ]),
    );
    $first = CsoSurveyUnit::query()->create([
        'objectid' => 3101, 'globalid' => fake()->uuid(), 'parentglobalid' => $this->survey->globalid,
        'unit_name' => 'وحدة ألف الأولى', 'raw_payload' => ['PARENTGLOBALID' => '{'.strtoupper($organizations[0]->globalid).'}'],
    ]);
    $second = CsoSurveyUnit::query()->create([
        'objectid' => 3102, 'globalid' => fake()->uuid(), 'parentglobalid' => $organizations[0]->globalid, 'unit_name' => 'وحدة ألف الثانية',
    ]);
    $third = CsoSurveyUnit::query()->create([
        'objectid' => 3103, 'globalid' => fake()->uuid(), 'parentglobalid' => $organizations[1]->globalid, 'unit_name' => 'وحدة باء',
    ]);
    $unassigned = CsoSurveyUnit::query()->create([
        'objectid' => 3104, 'globalid' => fake()->uuid(), 'parentglobalid' => $this->survey->globalid, 'unit_name' => 'وحدة غير مرتبطة',
    ]);
    $conflicting = CsoSurveyUnit::query()->create([
        'objectid' => 3105, 'globalid' => fake()->uuid(), 'parentglobalid' => $organizations[0]->globalid,
        'unit_name' => 'وحدة ربط متعارض', 'raw_payload' => ['parentglobalid' => $organizations[1]->globalid],
    ]);
    CsoSurveyUnit::query()->create([
        'globalid' => fake()->uuid(), 'parentglobalid' => 'another-survey', 'unit_name' => 'وحدة خارجية لا تظهر',
        'raw_payload' => ['parentglobalid' => $organizations[0]->globalid],
    ]);
    $response = $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()->assertDontSee('وحدة خارجية لا تظهر')
        ->assertViewHas('organizationGroups', fn (array $groups): bool => array_column($groups, 'unit_count') === [2, 1, 0]);
    $groups = collect($response->viewData('unitGroups'))->keyBy('record_id');
    expect($groups[$first->id]['organization_key'])->toBe('organization-'.$organizations[0]->id)
        ->and($groups[$second->id]['organization_key'])->toBe('organization-'.$organizations[0]->id)
        ->and($groups[$third->id]['organization_key'])->toBe('organization-'.$organizations[1]->id)
        ->and($groups[$unassigned->id]['organization_key'])->toBe('unassigned')
        ->and($groups[$conflicting->id]['organization_key'])->toBe('unassigned');
    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//*[@id="cso_units_pane"]//button[@data-record-target and not(@hidden)]')->length)->toBe(2)
        ->and($xpath->query('//*[@id="cso_units_pane"]//*[@data-record-content and not(@hidden)]')->length)->toBe(1);
    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'phc-cso-workspace-grouped-preview.html', $response->getContent());
});

it('keeps the unit selector visible for a single record and shows empty states for missing children', function (): void {
    CsoSurveyUnit::query()->create([
        'objectid' => 3101, 'globalid' => fake()->uuid(), 'parentglobalid' => $this->survey->globalid, 'unit_name' => 'الوحدة الوحيدة',
    ]);
    $response = $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()
        ->assertSee('لا توجد منظمات مرتبطة بهذا الاستبيان')->assertSee('الوحدة الوحيدة')
        ->assertSee('لا توجد صور مرفقة بهذا الاستبيان.');
    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//*[@id="cso_units_pane"]//button[@data-record-target]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="cso_units_pane"]//*[@data-record-content and not(@hidden)]')->length)->toBe(1);
    expect($xpath->query('//*[@id="cso_gallery_image"]')->length)->toBe(0);
    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'phc-cso-workspace-empty-preview.html', $response->getContent());
});

it('keeps the organization selector visible when the survey has only one organization', function (): void {
    CsoSurveyOrganization::query()->create([
        'objectid' => 2101, 'globalid' => fake()->uuid(), 'parentglobalid' => $this->survey->globalid, 'organization_name_ar' => 'المنظمة الوحيدة',
    ]);
    $response = $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()->assertSee('المنظمة الوحيدة');
    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//*[@id="cso_organizations_pane"]//aside//button[@data-record-target and @aria-pressed="true"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="cso_organizations_pane"]//*[@data-record-content and not(@hidden)]')->length)->toBe(1);
    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'phc-cso-workspace-one-org-preview.html', $response->getContent());
});

it('renders file attachments as links when no images are available', function (): void {
    fakeCsoWorkspaceAttachments([['id' => 100, 'name' => 'تقرير المعاينة.pdf', 'contentType' => 'application/pdf']]);
    $response = $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()
        ->assertSee('تقرير المعاينة.pdf')->assertSee('لا توجد صور مرفقة بهذا الاستبيان.');
    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//*[@id="cso_gallery_image"]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="cso_attachment_viewer"]//a[contains(@href, "100.pdf")]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="cso_attachment_viewer"]//button[@data-cso-image-step]')->length)->toBe(0);
    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'phc-cso-workspace-files-preview.html', $response->getContent());
});

it('disables image navigation for a single attachment', function (): void {
    fakeCsoWorkspaceAttachments([['id' => 1, 'name' => 'الصورة الوحيدة.jpg', 'contentType' => 'image/jpeg']]);
    $response = $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()->assertSee('الصورة الوحيدة.jpg');
    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//button[@data-cso-image-step and not(@disabled)]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="cso_gallery_panel"]//button[@data-cso-image-index]')->length)->toBe(1);
    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'phc-cso-workspace-single-preview.html', $response->getContent());
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

it('renders the location as a read only map with collapsed engineering data', function (): void {
    $source = '{"rings":[[[34.458237535,31.5250917480001],[34.4583436790001,31.5249942890001],[34.4581663660001,31.5248348750001],[34.4579802010001,31.5250013510001],[34.4580732960001,31.5250931890001],[34.4580958970001,31.5250695230001],[34.458146034,31.525116994],[34.4582027000001,31.525067866],[34.458237535,31.5250917480001]]]}';
    $this->survey->update(['location' => $source]);
    $response = $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()
        ->assertSee('الموقع الجغرافي')->assertSee('للعرض فقط')->assertSee('تكبير الخريطة');
    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//*[@data-cso-location]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-cso-location]//details[not(@open)]/pre')->item(0)->textContent)->toBe($source)
        ->and($xpath->query('//*[@data-cso-location]//*[local-name()="svg"]/*[local-name()="path"]')->length)->toBe(1)
        ->and($xpath->query('//button[@data-field-name="location"]')->length)->toBe(0)
        ->and($this->survey->fresh()->location)->toBe($source);
    Http::assertNothingSent();
    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'phc-cso-workspace-location-preview.html', $response->getContent());
});

it('escapes malformed engineering data and never renders it as an editable field', function (): void {
    $this->survey->update(['location' => '</pre><script id="location-injection">alert(1)</script>']);
    $response = $this->get(route('inf-audit.cso.show', $this->survey))->assertSuccessful()->assertSee('بيانات الموقع غير صالحة');
    $xpath = new DOMXPath(csoWorkspaceDocument($response->getContent()));
    expect($xpath->query('//*[@data-cso-location]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="location-injection"]')->length)->toBe(0)
        ->and($xpath->query('//button[@data-field-name="location"]')->length)->toBe(0);
});

function fakeCsoWorkspaceAttachments(array $attachments): void
{
    config(['services.arcgis.cso_survey_layer_url' => 'https://cso.test/FeatureServer']);
    $arcgis = Mockery::mock(ArcgisService::class);
    $arcgis->shouldReceive('getToken')->andReturn('test-token');
    $arcgis->shouldReceive('getAttachmentsFromLayerUrl')->with('https://cso.test/FeatureServer/0', 1001, 'test-token')->andReturn($attachments);
    $arcgis->shouldReceive('buildUrlFromLayerUrl')->andReturnUsing(
        fn (string $layerUrl, int|string $objectId, int|string $attachmentId, string $token): string => url('/cso-test-media/'.$attachmentId.($attachmentId === 100 ? '.pdf' : '.svg')),
    );
    app()->instance(ArcgisService::class, $arcgis);
}
