<?php

declare(strict_types=1);

use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\User;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;

test('it shows cso survey listing and details like other survey pages', function (): void {
    $user = User::factory()->create();

    $survey = CsoSurvey::query()->create([
        'objectid' => 7301,
        'globalid' => 'cso-survey-page-global-id',
        'organization_name' => 'Civil Support Organization',
        'building_name' => 'CSO Main Building',
        'assignedto' => 'Engineer CSO',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Rimal',
        'building_damage_status' => 'partial_damage',
        'operational_status' => 'partially_operational',
        'creationdate' => '2026-08-19 19:55:00',
        'raw_payload' => [
            'weather' => 'sunny',
        ],
    ]);

    CsoSurveyOrganization::query()->create([
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
    ]);

    $indexResponse = $this->actingAs($user)->get(route('cso-surveys.index'));

    $indexResponse->assertOk()
        ->assertSee('CSO Damage Assessment')
        ->assertSee('CSO Filters')
        ->assertSee('CSO Surveys')
        ->assertSee('Total Surveys');

    $dataResponse = $this->actingAs($user)->get(route('cso-surveys.data', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Rimal',
        'q' => 'Civil Support',
        'columns' => [
            ['data' => 'objectid', 'name' => 'objectid', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'organization_name', 'name' => 'organization_name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'building_name', 'name' => 'building_name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'municipalitie', 'name' => 'municipalitie', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'neighborhood', 'name' => 'neighborhood', 'searchable' => 'true', 'orderable' => 'true'],
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
        ->assertSee('CSO Main Building');

    $showResponse = $this->actingAs($user)->get(route('cso-surveys.show', $survey));

    $showResponse->assertOk()
        ->assertSee('Civil Support Organization')
        ->assertSee('Survey')
        ->assertSee('CSO Organizations')
        ->assertSee('Unit Information')
        ->assertSee('Civil Support Organization Branch')
        ->assertSee('Ground Floor Unit')
        ->assertSee('لا يوجد جواب');
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
});
