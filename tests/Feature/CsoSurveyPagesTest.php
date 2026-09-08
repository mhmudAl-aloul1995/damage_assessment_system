<?php

declare(strict_types=1);

use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\User;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;

test('it shows cso survey listing and details like other survey pages', function (): void {
    app()->setLocale('ar');

    $user = User::factory()->create();

    $survey = CsoSurvey::query()->create([
        'objectid' => 7301,
        'globalid' => 'cso-survey-page-global-id',
        'organization_name' => 'Civil Support Organization',
        'building_name' => 'CSO Main Building',
        'assignedto' => 'Engineer CSO',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Rimal',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'partial_damage',
        'operational_status' => 'partially_operational',
        'creationdate' => '2026-08-19 19:55:00',
        'raw_payload' => [
            'weather' => 'sunny',
        ],
    ]);

    $organization = CsoSurveyOrganization::query()->create([
        'objectid' => 8301,
        'globalid' => 'cso-organization-page-global-id',
        'parentglobalid' => $survey->globalid,
        'organization_name_en' => 'Civil Support Organization Branch',
        'operational_status' => 'operational',
    ]);

    CsoSurveyUnit::query()->create([
        'objectid' => 9301,
        'globalid' => 'cso-unit-page-global-id',
        'parentglobalid' => $survey->globalid,
        'unit_name' => 'Ground Floor Unit',
        'unit_damage_status' => 'minor_damage',
        'raw_payload' => [
            'parentglobalid' => strtoupper('{'.$organization->globalid.'}'),
        ],
    ]);

    CsoSurvey::query()->create([
        'objectid' => 7302,
        'globalid' => 'cso-survey-page-filtered-out-global-id',
        'organization_name' => 'Filtered Out Organization',
        'building_name' => 'Filtered Out Building',
        'assignedto' => 'Engineer CSO',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Rimal',
        'field_status' => 'Not_Completed',
        'building_damage_status' => 'partial_damage',
        'operational_status' => 'partially_operational',
        'creationdate' => '2026-08-19 19:55:00',
    ]);

    $indexResponse = $this->actingAs($user)->get(route('cso-surveys.index'));

    $indexResponse->assertOk()
        ->assertSee('CSO Damage Assessment')
        ->assertSee('CSO Filters')
        ->assertSee('CSO Surveys')
        ->assertSee('Total Surveys')
        ->assertSee('Survey Status')
        ->assertSee('id="filter_field_status"', false)
        ->assertSee('<option value="1">ضرر كلي</option>', false)
        ->assertSee('<option value="2">ضرر جزئي</option>', false)
        ->assertSee('<option value="3">لجنة فنية</option>', false);

    $dataResponse = $this->actingAs($user)->get(route('cso-surveys.data', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Rimal',
        'field_status' => 'COMPLETED',
        'q' => 'Civil Support',
        'columns' => [
            ['data' => 'objectid', 'name' => 'objectid', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'organization_name', 'name' => 'organization_name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'building_name', 'name' => 'building_name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'municipalitie', 'name' => 'municipalitie', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'neighborhood', 'name' => 'neighborhood', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'field_status', 'name' => 'field_status', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'building_damage_status', 'name' => 'building_damage_status', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'creationdate', 'name' => 'creationdate', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'organizations_count', 'name' => 'organizations_count', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'units_count', 'name' => 'units_count', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'assignedto', 'name' => 'assignedto', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'actions', 'name' => 'actions', 'searchable' => 'false', 'orderable' => 'false'],
        ],
        'order' => [
            ['column' => 0, 'dir' => 'desc'],
        ],
        'search' => [
            'value' => '',
            'regex' => 'false',
        ],
    ]));

    $dataResponse->assertOk()
        ->assertSee('Civil Support Organization')
        ->assertSee('CSO Main Building')
        ->assertSee('COMPLETED')
        ->assertDontSee('Filtered Out Organization');

    expect($dataResponse->json('data.0.building_damage_status'))
        ->toContain('ضرر جزئي')
        ->not->toContain('partial_damage');

    $showResponse = $this->actingAs($user)->get(route('cso-surveys.show', $survey));

    $showResponse->assertOk()
        ->assertSee('Civil Support Organization')
        ->assertSee('بيانات الاستمارة')
        ->assertSee('المنظمات ووحداتها')
        ->assertSee('Civil Support Organization Branch')
        ->assertSee('Ground Floor Unit')
        ->assertSee('nav-line-tabs-2x', false)
        ->assertSee('table-row-dashed', false)
        ->assertSee('badge-light-warning', false)
        ->assertSee('aria-label="حالة الضرر"', false)
        ->assertSee('جزئي')
        ->assertSee('لا يوجد جواب');
});

it('groups cso units under their organization from the original repeat parent', function (): void {
    app()->setLocale('ar');

    $user = User::factory()->create();

    $survey = CsoSurvey::query()->create([
        'objectid' => 7501,
        'globalid' => 'survey-unit-parent-map',
        'building_name' => 'Building With CSO Units',
        'building_damage_status' => 'committee_review',
    ]);

    $firstOrganization = CsoSurveyOrganization::query()->create([
        'objectid' => 8501,
        'globalid' => 'ORG-PARENT-A',
        'parentglobalid' => $survey->globalid,
        'organization_name_en' => 'Clinic Committee',
        'organization_name_ar' => 'لجنة العيادة',
        'raw_payload' => [
            'registration_number' => 'REG-A',
            'is_organization_active' => 'yes',
        ],
    ]);

    $secondOrganization = CsoSurveyOrganization::query()->create([
        'objectid' => 8502,
        'globalid' => 'org-parent-b',
        'parentglobalid' => $survey->globalid,
        'organization_name_en' => 'Youth Center',
        'organization_name_ar' => 'مركز الشباب',
    ]);

    $flattenedFirstUnit = CsoSurveyUnit::query()->create([
        'objectid' => 9501,
        'globalid' => 'unit-parent-map-a',
        'parentglobalid' => $survey->globalid,
        'unit_name' => 'Pharmacy Room',
        'unit_floor_number' => 1,
        'unit_damage_status' => 'partial_damage',
        'raw_payload' => [
            'parentglobalid' => '{org-parent-a}',
        ],
    ]);

    $directSecondUnit = CsoSurveyUnit::query()->create([
        'objectid' => 9502,
        'globalid' => 'unit-parent-map-b',
        'parentglobalid' => $secondOrganization->globalid,
        'unit_name' => 'Training Room',
        'unit_floor_number' => 2,
        'unit_damage_status' => 'no_damage',
    ]);

    $unassignedUnit = CsoSurveyUnit::query()->create([
        'objectid' => 9503,
        'globalid' => 'unit-parent-map-unassigned',
        'parentglobalid' => $survey->globalid,
        'unit_name' => 'Legacy Room',
        'unit_damage_status' => 'unexpected_value',
    ]);

    CsoSurveyUnit::query()->create([
        'objectid' => 9504,
        'globalid' => 'unit-parent-map-foreign',
        'parentglobalid' => 'other-survey',
        'unit_name' => 'Foreign Survey Unit',
        'unit_damage_status' => 'total_damage',
        'raw_payload' => [
            'parentglobalid' => $firstOrganization->globalid,
        ],
    ]);

    $response = $this->actingAs($user)->get(route('cso-surveys.show', $survey));

    $groups = $response->viewData('organizationGroups');
    $firstGroup = $groups->firstWhere('key', 'organization-'.$firstOrganization->id);
    $secondGroup = $groups->firstWhere('key', 'organization-'.$secondOrganization->id);
    $unassignedGroup = $groups->firstWhere('key', 'unassigned');

    $response->assertOk()
        ->assertSee('المنظمات ووحداتها')
        ->assertSee('لجنة فنية')
        ->assertSee('لجنة العيادة')
        ->assertSee('Pharmacy Room')
        ->assertSee('مركز الشباب')
        ->assertSee('Training Room')
        ->assertSee('وحدات غير مربوطة')
        ->assertSee('Legacy Room')
        ->assertDontSee('Foreign Survey Unit');

    expect($firstGroup['units']->pluck('id')->all())->toBe([$flattenedFirstUnit->id])
        ->and($firstGroup['damageCounts']->get('partially_damaged'))->toBe(1)
        ->and($firstGroup['damageCounts']->get('fully_damaged'))->toBeNull()
        ->and($secondGroup['units']->pluck('id')->all())->toBe([$directSecondUnit->id])
        ->and($secondGroup['damageCounts']->get('no_damage'))->toBe(1)
        ->and($unassignedGroup['units']->pluck('id')->all())->toBe([$unassignedUnit->id])
        ->and($unassignedGroup['damageCounts']->get('unclassified'))->toBe(1);

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-organization-panel and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-organization-panel="'.$firstGroup['key'].'" and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//template[@id="cso-unit-template-'.$flattenedFirstUnit->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-unit-open="'.$flattenedFirstUnit->id.'"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-organization-panel="'.$secondGroup['key'].'"]//*[@data-unit-row]//*[@data-damage="no_damage" and contains(@class, "badge-light-success")]')->length)->toBe(1);
});

it('renders the cso empty workspace in both layout directions', function (string $locale, string $direction): void {
    app()->setLocale($locale);

    $survey = CsoSurvey::query()->create([
        'objectid' => 7510,
        'globalid' => 'cso-empty-workspace',
        'building_name' => '<script>alert("building")</script>',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('cso-surveys.show', $survey))
        ->assertOk()
        ->assertSee('dir="'.$direction.'"', false)
        ->assertSee(__('cso_details.no_organizations'))
        ->assertSee($survey->building_name)
        ->assertDontSee($survey->building_name, false)
        ->assertSee('id="cso-survey-tab"', false)
        ->assertSee('id="cso-workspace-pane"', false)
        ->assertDontSee('data-organization-panel=', false);
})->with([
    'Arabic' => ['ar', 'rtl'],
    'English' => ['en', 'ltr'],
]);

it('filters cso surveys by child organization fields from dashboard links', function (): void {
    $user = User::factory()->create();

    $matchingSurvey = CsoSurvey::query()->create([
        'objectid' => 7311,
        'globalid' => 'cso-survey-with-operational-org',
        'organization_name' => 'Matching CSO',
        'building_name' => 'Matching Building',
    ]);

    $filteredSurvey = CsoSurvey::query()->create([
        'objectid' => 7312,
        'globalid' => 'cso-survey-with-partial-org',
        'organization_name' => 'Filtered CSO',
        'building_name' => 'Filtered Building',
    ]);

    CsoSurveyOrganization::query()->create([
        'objectid' => 8311,
        'globalid' => 'cso-operational-org',
        'parentglobalid' => $matchingSurvey->globalid,
        'operational_status' => 'operational',
    ]);

    CsoSurveyOrganization::query()->create([
        'objectid' => 8312,
        'globalid' => 'cso-partial-org',
        'parentglobalid' => $filteredSurvey->globalid,
        'operational_status' => 'partially_operational',
    ]);

    $this->actingAs($user)
        ->get(route('cso-surveys.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'organization_filters' => [
                'operational_status' => ['operational'],
            ],
            'columns' => [
                ['data' => 'objectid', 'name' => 'objectid', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'organization_name', 'name' => 'organization_name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'building_name', 'name' => 'building_name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'municipalitie', 'name' => 'municipalitie', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'neighborhood', 'name' => 'neighborhood', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'field_status', 'name' => 'field_status', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'building_damage_status', 'name' => 'building_damage_status', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'creationdate', 'name' => 'creationdate', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'organizations_count', 'name' => 'organizations_count', 'searchable' => 'false', 'orderable' => 'true'],
                ['data' => 'units_count', 'name' => 'units_count', 'searchable' => 'false', 'orderable' => 'true'],
                ['data' => 'assignedto', 'name' => 'assignedto', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'actions', 'name' => 'actions', 'searchable' => 'false', 'orderable' => 'false'],
            ],
            'order' => [
                ['column' => 0, 'dir' => 'desc'],
            ],
            'search' => [
                'value' => '',
                'regex' => 'false',
            ],
        ]))
        ->assertOk()
        ->assertSee('Matching CSO')
        ->assertDontSee('Filtered CSO');
});

it('shows cso export data page and exports selected survey organization and unit columns', function (): void {
    Carbon::setTestNow('2026-08-31 10:15:00');

    $user = User::factory()->create();

    $survey = CsoSurvey::query()->create([
        'objectid' => 7401,
        'globalid' => 'cso-export-survey-global-id',
        'organization_name' => 'Export Civil Support Organization',
        'building_name' => 'Export CSO Building',
        'assignedto' => 'Export CSO Engineer',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Rimal',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'partial_damage',
        'operational_status' => 'partially_operational',
        'creationdate' => '2026-08-19 19:55:00',
        'raw_payload' => [
            'weather' => 'sunny',
        ],
    ]);

    CsoSurveyOrganization::query()->create([
        'objectid' => 8401,
        'globalid' => 'cso-export-organization-global-id',
        'parentglobalid' => $survey->globalid,
        'repeat_index' => 0,
        'organization_name_en' => 'Export CSO Branch',
        'operational_status' => 'operational',
    ]);

    CsoSurveyUnit::query()->create([
        'objectid' => 9401,
        'globalid' => 'cso-export-unit-global-id',
        'parentglobalid' => $survey->globalid,
        'repeat_index' => 0,
        'unit_name' => 'Export Ground Unit',
        'unit_floor_number' => 1,
        'unit_damage_status' => 'minor_damage',
    ]);

    $indexResponse = $this
        ->actingAs($user)
        ->get(route('cso-surveys.index'));

    $indexResponse
        ->assertOk()
        ->assertSee(route('cso-surveys.export-data'), false)
        ->assertSee('صفحة التصدير');

    $pageResponse = $this
        ->actingAs($user)
        ->get(route('cso-surveys.export-data'));

    $pageResponse
        ->assertOk()
        ->assertSee('تصدير بيانات CSO')
        ->assertSee('id="csoExportForm"', false)
        ->assertSee('name="municipalitie[]"', false)
        ->assertSee('name="neighborhood[]"', false)
        ->assertSee('name="assignedto[]"', false)
        ->assertSee('name="building_damage_status[]"', false)
        ->assertSee('name="operational_status[]"', false)
        ->assertSee('name="cso_survey_columns[]"', false)
        ->assertSee('name="cso_organization_columns[]"', false)
        ->assertSee('name="cso_unit_columns[]"', false)
        ->assertSee('value="organization_name"', false)
        ->assertSee('value="organization_name_en"', false)
        ->assertSee('value="unit_name"', false)
        ->assertSee('data-format="xlsx"', false)
        ->assertSee('data-format="csv"', false)
        ->assertSee('data-format="pdf"', false)
        ->assertSee('Sheet Survey')
        ->assertSee('Sheet CSO Organizations')
        ->assertSee('Sheet Unit Information');

    $xlsxResponse = $this
        ->actingAs($user)
        ->get(route('cso-surveys.export', [
            'format' => 'xlsx',
            'cso_survey_columns' => ['objectid', 'organization_name', 'building_name'],
            'cso_organization_columns' => ['survey_objectid', 'organization_name_en'],
            'cso_unit_columns' => ['survey_objectid', 'unit_name', 'unit_damage_status'],
        ]));

    $xlsxResponse->assertOk();
    $xlsxResponse->assertHeader('content-disposition', 'attachment; filename=cso_surveys_20260831_101500.xlsx');

    $reader = new Reader;
    $reader->open($xlsxResponse->baseResponse->getFile()->getPathname());

    $xlsxRows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $xlsxRows[$sheet->getName()] = [];

        foreach ($sheet->getRowIterator() as $row) {
            $xlsxRows[$sheet->getName()][] = $row->toArray();
        }
    }

    $reader->close();

    expect($xlsxRows['Survey'][0])->toBe(['Survey Object ID', 'Organization Name', 'Building Name'])
        ->and($xlsxRows['Survey'][1])->toBe([7401, 'Export Civil Support Organization', 'Export CSO Building'])
        ->and($xlsxRows['CSO Organizations'][0])->toBe(['Survey Object ID', 'Organization Name EN'])
        ->and($xlsxRows['CSO Organizations'][1])->toBe([7401, 'Export CSO Branch'])
        ->and($xlsxRows['Unit Information'][0])->toBe(['Survey Object ID', 'Unit Name', 'Unit Damage Status'])
        ->and($xlsxRows['Unit Information'][1])->toBe([7401, 'Export Ground Unit', 'minor_damage']);

    $csvResponse = $this
        ->actingAs($user)
        ->get(route('cso-surveys.export', [
            'format' => 'csv',
            'cso_survey_columns' => ['objectid', 'organization_name'],
            'cso_organization_columns' => ['organization_name_en'],
            'cso_unit_columns' => ['unit_name'],
        ]));

    $csvResponse->assertOk();
    $csv = file_get_contents($csvResponse->baseResponse->getFile()->getPathname());

    expect($csv)->toContain('Section Type')
        ->toContain('survey')
        ->toContain('organization')
        ->toContain('unit')
        ->toContain('Export Civil Support Organization')
        ->toContain('Export CSO Branch')
        ->toContain('Export Ground Unit');

    $pdfResponse = $this
        ->actingAs($user)
        ->get(route('cso-surveys.export', [
            'format' => 'pdf',
            'cso_survey_columns' => ['objectid', 'organization_name'],
            'cso_organization_columns' => ['organization_name_en'],
            'cso_unit_columns' => ['unit_name'],
        ]));

    $pdfResponse->assertOk();
    $pdfResponse->assertHeader('content-disposition', 'attachment; filename=cso_surveys_20260831_101500.pdf');

    $defaultPdfResponse = $this
        ->actingAs($user)
        ->get(route('cso-surveys.export', [
            'format' => 'pdf',
        ]));

    $defaultPdfResponse->assertOk();
    $defaultPdfResponse->assertHeader('content-disposition', 'attachment; filename=cso_surveys_20260831_101500.pdf');
});
