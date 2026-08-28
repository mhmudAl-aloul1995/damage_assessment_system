<?php

use App\Exports\HousingUnitBoqExport;
use App\Models\Assessment;
use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\Building;
use App\Models\Filter;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

beforeEach(function (): void {
    ensureAuditedHousingSurveyColumns();
});

it('shows grouped housing unit filters from the assessment survey', function () {
    $user = User::factory()->create();

    seedHousingFilterOptions();

    AuditedBuilding::query()->create([
        'objectid' => 2001,
        'globalid' => 'building-housing-1',
        'assignedto' => 'Engineer One',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3001,
        'globalid' => 'housing-unit-1',
        'parentglobalid' => 'building-housing-1',
        'housing_unit_type' => 'apartment',
        'unit_damage_status' => 'fully_damaged2',
    ]);

    Assessment::query()->create([
        'name' => 'housing_unit_type',
        'label' => 'Housing unit type',
        'hint' => 'نوع الوحدة السكنية',
        'type' => '0',
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing');

    $response->assertOk();
    $response->assertSee('Housing Unit Filters');
    $response->assertSee('Unit information and damage');
    $response->assertSee('Resident and household');
    $response->assertSee('Unit security situation');
    $response->assertSee('name="filters[security_situation_unit][]"', false);
    $response->assertSee('value="yes"', false);
    $response->assertSee('value="no"', false);
    $response->assertSee('Housing Unit Object IDs');
    $response->assertSee('Paste up to 200 Object IDs from Excel');
    $response->assertSee('var url_phc', false);
    $response->assertSee('export-housings.js?v=', false);
    $response->assertSee('Apartment');
    $response->assertSee('Totally Damaged');
    $response->assertSee('Engineer One');

    expect(file_get_contents(base_path('assets/js/custom/DamageAssessment/export-housings.js')))
        ->toContain('showExportLoading')
        ->toContain('hideExportLoading')
        ->toContain('startDownload')
        ->not->toContain('120000');
});

it('counts housing page total units from fully and partially damaged units only', function () {
    $user = User::factory()->create();

    AuditedHousingUnit::query()->create([
        'objectid' => 3003,
        'globalid' => 'housing-total-fully',
        'unit_damage_status' => 'fully_damaged2',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3004,
        'globalid' => 'housing-total-partially',
        'unit_damage_status' => 'partially_damaged2',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3005,
        'globalid' => 'housing-total-committee',
        'unit_damage_status' => 'committee_review2',
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing');

    $response->assertOk();
    $response->assertViewHas('housingSummary', function (array $housingSummary): bool {
        return $housingSummary['total'] === 2
            && $housingSummary['fully_damaged'] === 1
            && $housingSummary['partially_damaged'] === 1
            && $housingSummary['committee_review'] === 1;
    });
});

it('filters housing unit datatable records using grouped filters and ranges', function () {
    $user = User::factory()->create();

    seedHousingFilterOptions();

    AuditedBuilding::query()->create([
        'objectid' => 2001,
        'globalid' => 'building-housing-1',
        'assignedto' => 'Engineer One',
    ]);

    AuditedBuilding::query()->create([
        'objectid' => 2002,
        'globalid' => 'building-housing-2',
        'assignedto' => 'Engineer Two',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3001,
        'globalid' => 'housing-unit-1',
        'parentglobalid' => 'building-housing-1',
        'housing_unit_type' => 'apartment',
        'unit_damage_status' => 'fully_damaged2',
        'housing_unit_number' => '12',
        'q_9_3_1_first_name' => 'Mona',
        'q_9_3_4_last_name' => 'Saleh',
        'id_number1' => '900123456',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'floor_number' => 3,
        'damaged_area_m2' => 80,
        'unit_support_needed' => 'yes',
        'rubble_removal_is_needed' => 'yes',
        'activation_of_uxo_ha_d_material_clearance' => 'no',
        'has_fire' => 'no',
        'editdate' => '2026-06-02 10:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3002,
        'globalid' => 'housing-unit-2',
        'parentglobalid' => 'building-housing-2',
        'housing_unit_type' => 'warehouse',
        'unit_damage_status' => 'partially_damaged2',
        'housing_unit_number' => '2',
        'q_9_3_1_first_name' => 'Hani',
        'q_9_3_4_last_name' => 'Nassar',
        'id_number1' => '800123456',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Camp',
        'floor_number' => 1,
        'damaged_area_m2' => 20,
        'unit_support_needed' => 'no',
        'rubble_removal_is_needed' => 'no',
        'activation_of_uxo_ha_d_material_clearance' => 'no',
        'has_fire' => 'no',
        'editdate' => '2026-06-03 10:00:00',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'unit_damage_status' => ['fully_damaged2'],
            'housing_unit_type' => ['apartment'],
            'municipalitie' => ['Gaza'],
            'damaged_area_m2_from' => 50,
            'unsafe_column' => 'ignored',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Mona Saleh');
    $response->assertSee('Totally Damaged');
    $response->assertSee('Support needed');
    $response->assertSee('Rubble removal');
    $response->assertDontSee('Hani Nassar');
});

it('filters housing unit datatable records by unclassified damage status', function () {
    $user = User::factory()->create();

    AuditedHousingUnit::query()->create([
        'objectid' => 3006,
        'globalid' => 'housing-unit-unclassified-null',
        'housing_unit_number' => '91',
        'unit_damage_status' => null,
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3007,
        'globalid' => 'housing-unit-unclassified-empty',
        'housing_unit_number' => '92',
        'unit_damage_status' => '',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3008,
        'globalid' => 'housing-unit-classified',
        'housing_unit_number' => '93',
        'unit_damage_status' => 'fully_damaged2',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'unit_damage_status' => ['__blank__'],
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 2);
    expect(collect($response->json('data'))->pluck('housing_unit_number')->sort()->values()->all())
        ->toBe(['91', '92']);
});

it('filters housing unit datatable records by housing unit objectid', function () {
    $user = User::factory()->create();

    AuditedHousingUnit::query()->create([
        'objectid' => 3001,
        'globalid' => 'housing-unit-objectid-1',
        'housing_unit_number' => '12',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3002,
        'globalid' => 'housing-unit-objectid-2',
        'housing_unit_number' => '13',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 13001,
        'globalid' => 'housing-unit-objectid-partial',
        'housing_unit_number' => '14',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'objectid' => '3001',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('12');
    expect(collect($response->json('data'))->pluck('housing_unit_number')->all())
        ->toBe(['12']);
});

it('filters housing unit datatable records by submission date range', function () {
    $user = User::factory()->create();

    AuditedHousingUnit::query()->create([
        'objectid' => 3101,
        'globalid' => 'housing-unit-submission-date-inside',
        'housing_unit_number' => '21',
        'q_9_3_1_first_name' => 'Inside',
        'q_9_3_4_last_name' => 'Range',
        'building_submit_date' => '2026-06-15 09:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3102,
        'globalid' => 'housing-unit-submission-date-outside',
        'housing_unit_number' => '22',
        'q_9_3_1_first_name' => 'Outside',
        'q_9_3_4_last_name' => 'Range',
        'building_submit_date' => '2026-06-30 09:00:00',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'submission_date_from' => '2026-06-01',
            'submission_date_to' => '2026-06-20',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Inside Range');
    $response->assertDontSee('Outside Range');
});

it('filters housing unit datatable records by the assigned building researcher', function () {
    $user = User::factory()->create();

    AuditedBuilding::query()->create([
        'objectid' => 2201,
        'globalid' => 'building-assigned-one',
        'assignedto' => 'Engineer One',
    ]);

    AuditedBuilding::query()->create([
        'objectid' => 2202,
        'globalid' => 'building-assigned-two',
        'assignedto' => 'Engineer Two',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3201,
        'globalid' => 'housing-unit-assigned-one',
        'parentglobalid' => 'building-assigned-one',
        'housing_unit_number' => '31',
        'q_9_3_1_first_name' => 'Assigned',
        'q_9_3_4_last_name' => 'One',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3202,
        'globalid' => 'housing-unit-assigned-two',
        'parentglobalid' => 'building-assigned-two',
        'housing_unit_number' => '32',
        'q_9_3_1_first_name' => 'Assigned',
        'q_9_3_4_last_name' => 'Two',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'assignedto' => ['Engineer One'],
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Assigned One');
    $response->assertSee('Engineer One');
    $response->assertDontSee('Assigned Two');
    $response->assertDontSee('Engineer Two');
});

it('filters housing unit datatable records by security situation from dashboard links', function () {
    $user = User::factory()->create();

    AuditedHousingUnit::query()->create([
        'objectid' => 3251,
        'globalid' => 'housing-unit-security-unsafe',
        'housing_unit_number' => '36',
        'q_9_3_1_first_name' => 'Unsafe',
        'q_9_3_4_last_name' => 'Unit',
        'security_situation_unit' => 'yes',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3252,
        'globalid' => 'housing-unit-security-safe',
        'housing_unit_number' => '37',
        'q_9_3_1_first_name' => 'Safe',
        'q_9_3_4_last_name' => 'Unit',
        'security_situation_unit' => 'no',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'security_situation_unit' => 'yes',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Unsafe Unit');
    $response->assertDontSee('Safe Unit');
});

it('filters housing unit datatable records by the building save date', function () {
    $user = User::factory()->create();

    AuditedBuilding::query()->create([
        'objectid' => 2301,
        'globalid' => 'building-save-date-inside',
        'assignedto' => 'Engineer Inside',
        'end' => '2026-06-15 09:00:00',
    ]);

    AuditedBuilding::query()->create([
        'objectid' => 2302,
        'globalid' => 'building-save-date-outside',
        'assignedto' => 'Engineer Outside',
        'end' => '2026-06-30 09:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3301,
        'globalid' => 'housing-unit-save-date-inside',
        'parentglobalid' => 'building-save-date-inside',
        'housing_unit_number' => '41',
        'q_9_3_1_first_name' => 'Saved',
        'q_9_3_4_last_name' => 'Inside',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 3302,
        'globalid' => 'housing-unit-save-date-outside',
        'parentglobalid' => 'building-save-date-outside',
        'housing_unit_number' => '42',
        'q_9_3_1_first_name' => 'Saved',
        'q_9_3_4_last_name' => 'Outside',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'end_from' => '2026-06-01',
            'end_to' => '2026-06-20',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Saved Inside');
    $response->assertDontSee('Saved Outside');
});

it('shows housing unit excel and pdf export links in the row actions menu', function () {
    $user = User::factory()->create();

    AuditedHousingUnit::query()->create([
        'objectid' => 3401,
        'globalid' => 'housing-unit-actions-export',
        'parentglobalid' => 'building-actions-export',
        'housing_unit_number' => '51',
        'security_situation_unit' => 'yes',
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ]));

    $response->assertOk();

    $actionHtml = $response->json('data.0.action');

    expect($response->json('data.0.security_situation_unit'))->toContain('Yes');

    expect($actionHtml)
        ->toContain('الاستبيان المدقق')
        ->toContain(url('damage-assessment/showAssessmentAudit/building-actions-export/housing-unit-actions-export'))
        ->toContain('تصدير BOQ Excel')
        ->toContain('تصدير BOQ PDF')
        ->toContain(route('housing.export', ['format' => 'xlsx', 'globalid' => 'housing-unit-actions-export']))
        ->toContain(route('housing.export', ['format' => 'pdf', 'globalid' => 'housing-unit-actions-export']));
});

it('exports filtered housing units to excel from the audited housing units view', function () {
    Excel::fake();

    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 2501,
        'globalid' => 'building-export-included',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3501,
        'globalid' => 'housing-unit-export-included',
        'parentglobalid' => 'building-export-included',
        'unit_owner' => 'Original Owner',
        'municipalitie' => 'Gaza',
        'dm1' => '1',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3502,
        'globalid' => 'housing-unit-export-excluded',
        'unit_owner' => 'Excluded Owner',
        'municipalitie' => 'Rafah',
        'dm1' => '7',
    ]);

    Assessment::query()->create([
        'name' => 'dm1',
        'label' => 'DM1-Demolish walls',
        'hint' => 'إزالة حوائط شاملا المعدات والمصنعية والترحيل لأقرب مكب (M2)',
    ]);

    DB::table('edit_assessments')->insert([
        [
            'global_id' => 'housing-unit-export-included',
            'type' => 'housing_table',
            'field_name' => 'unit_owner',
            'field_value' => 'Included Owner',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'global_id' => 'housing-unit-export-included',
            'type' => 'housing_table',
            'field_name' => 'dm1',
            'field_value' => '12.5',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($user)->get(route('housing.export', [
        'format' => 'xlsx',
        'filters' => [
            'municipalitie' => ['Gaza'],
        ],
        'housing_columns' => ['objectid', 'globalid', 'unit_owner', 'municipalitie'],
    ]))->assertOk();

    Excel::matchByRegex();
    Excel::assertDownloaded('/جدول-الكميات-Included-Owner-\d{8}-\d{6}\.xlsx/u', function (HousingUnitBoqExport $export): bool {
        $rows = collect($export->array());

        return ! $export instanceof ShouldAutoSize
            && $rows->contains(['Object ID للمبنى', null, 'اسم مالك الوحدة', 'Object ID للوحدة', null])
            && $rows->contains([2501, null, 'Included Owner', 3501, null])
            && ! $rows->contains(['-', null, 'Original Owner', 3501, null])
            && $rows->contains(['القسم', 'الكود', 'البند', 'الوحدة', 'الكمية'])
            && $rows->contains(fn (array $row): bool => $row[1] === 'DM1' && $row[4] === '12.5')
            && ! $rows->contains(fn (array $row): bool => $row[1] === 'DM1' && $row[4] === '1')
            && ! $rows->contains(fn (array $row): bool => is_string($row[1] ?? null) && str_contains($row[1], ':'))
            && ! $rows->contains(['Object ID', 'رقم الوحدة', 'مالك الوحدة', 'القسم', 'الكود', 'البند', 'الوحدة', 'الكمية']);
    });
});

it('exports housing unit BOQ by pasted housing object ids with a maximum of ten ids', function () {
    Excel::fake();

    $user = User::factory()->create();

    HousingUnit::query()->create([
        'objectid' => 3701,
        'globalid' => 'housing-unit-objectids-included-one',
        'unit_owner' => 'Object One',
        'dm1' => '4',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3702,
        'globalid' => 'housing-unit-objectids-included-two',
        'unit_owner' => 'Object Two',
        'dm1' => '5',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3703,
        'globalid' => 'housing-unit-objectids-excluded',
        'unit_owner' => 'Object Three',
        'dm1' => '6',
    ]);

    Assessment::query()->create([
        'name' => 'dm1',
        'label' => 'DM1-Demolish walls',
        'hint' => 'إزالة حوائط شاملا المعدات والمصنعية والترحيل لأقرب مكب (M2)',
    ]);

    $response = $this->actingAs($user)->get(route('housing.export', [
        'format' => 'xlsx',
        'objectids' => "3701\r\n3702",
    ]));

    $response->assertOk();

    expect($response->baseResponse->headers->get('content-disposition'))->toContain('.zip');

    $zipPath = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;

    expect($zip->open($zipPath))->toBeTrue();

    $zipEntries = collect(range(0, $zip->numFiles - 1))
        ->map(fn (int $index): string => (string) $zip->getNameIndex($index));

    expect($zipEntries->contains(fn (string $name): bool => str_contains($name, 'Object-One-3701.xlsx')))->toBeTrue();
    expect($zipEntries->contains(fn (string $name): bool => str_contains($name, 'Object-Two-3702.xlsx')))->toBeTrue();
    expect($zipEntries->contains(fn (string $name): bool => str_contains($name, 'Object-Three-3703.xlsx')))->toBeFalse();

    $zip->close();
    @unlink($zipPath);
});

it('exports pasted housing object ids to separate pdf files inside a zip', function () {
    Pdf::fake();

    $user = User::factory()->create();

    HousingUnit::query()->create([
        'objectid' => 3751,
        'globalid' => 'housing-unit-objectids-pdf-one',
        'unit_owner' => 'PDF One',
        'dm1' => '4',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3752,
        'globalid' => 'housing-unit-objectids-pdf-two',
        'unit_owner' => 'PDF Two',
        'dm1' => '5',
    ]);

    Assessment::query()->create([
        'name' => 'dm1',
        'label' => 'DM1-Demolish walls',
        'hint' => 'Ø¥Ø²Ø§Ù„Ø© Ø­ÙˆØ§Ø¦Ø· Ø´Ø§Ù…Ù„Ø§ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª ÙˆØ§Ù„Ù…ØµÙ†Ø¹ÙŠØ© ÙˆØ§Ù„ØªØ±Ø­ÙŠÙ„ Ù„Ø£Ù‚Ø±Ø¨ Ù…ÙƒØ¨ (M2)',
    ]);

    $response = $this->actingAs($user)->get(route('housing.export', [
        'format' => 'pdf',
        'objectids' => "3751\r\n3752",
    ]));

    $response->assertOk();
    expect($response->baseResponse->headers->get('content-disposition'))->toContain('.zip');

    Pdf::assertSaved(function (PdfBuilder $pdf, string $path): bool {
        return str_contains($pdf->downloadName, 'PDF-One-3751.pdf')
            && str_contains($path, 'housing-boq-')
            && $pdf->contains('3751')
            && ! $pdf->contains('3752');
    });

    Pdf::assertSaved(function (PdfBuilder $pdf): bool {
        return str_contains($pdf->downloadName, 'PDF-Two-3752.pdf')
            && $pdf->contains('3752')
            && ! $pdf->contains('3751');
    });

    $zipPath = $response->baseResponse->getFile()->getPathname();
    @unlink($zipPath);
});

it('rejects pasted housing object ids above the export limit', function () {
    $user = User::factory()->create();

    $objectIds = collect(range(1, 201))->map(fn (int $id): string => (string) (3800 + $id))->implode("\n");

    $this->actingAs($user)->get(route('housing.export', [
        'format' => 'xlsx',
        'objectids' => $objectIds,
    ]))->assertStatus(422);
});

it('exports a selected housing unit to pdf from the actions menu', function () {
    Pdf::fake();

    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 2601,
        'globalid' => 'building-pdf-export',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3601,
        'globalid' => 'housing-unit-pdf-export',
        'parentglobalid' => 'building-pdf-export',
        'unit_owner' => 'PDF Owner',
        'municipalitie' => 'Gaza',
        'dm1' => '9',
    ]);

    Assessment::query()->create([
        'name' => 'dm1',
        'label' => 'DM1-Demolish walls',
        'hint' => 'إزالة حوائط شاملا المعدات والمصنعية والترحيل لأقرب مكب (M2)',
    ]);

    $response = $this->actingAs($user)->get(route('housing.export', [
        'format' => 'pdf',
        'globalid' => 'housing-unit-pdf-export',
        'housing_columns' => ['objectid', 'globalid', 'unit_owner', 'municipalitie'],
    ]));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        return $pdf->viewName === 'damage-assessment::surveys.housing-units.export_pdf'
            && $pdf->contains('2601')
            && $pdf->contains('3601')
            && $pdf->contains('PDF Owner')
            && $pdf->downloadName !== ''
            && str_contains($pdf->downloadName, 'PDF-Owner')
            && $pdf->contains('v_housing_units_audited')
            && ! $pdf->contains('card-value')
            && ! $pdf->contains('summary')
            && ! $pdf->contains('.unit-block { margin-top: 14px; page-break-inside: avoid; }')
            && $pdf->contains('.unit-block { margin-top: 14px; page-break-inside: auto; }')
            && $pdf->contains('Object ID للمبنى')
            && $pdf->contains('Object ID للوحدة')
            && $pdf->contains('اسم مالك الوحدة')
            && $pdf->contains('جدول الكميات BOQ')
            && $pdf->contains('إزالة حوائط');
    });
});

it('opens a signed housing unit BOQ PDF export link without authentication', function () {
    Pdf::fake();

    Building::query()->create([
        'objectid' => 2602,
        'globalid' => 'building-signed-pdf-export',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3602,
        'globalid' => 'housing-unit-signed-pdf-export',
        'parentglobalid' => 'building-signed-pdf-export',
        'unit_owner' => 'Signed PDF Owner',
        'dm1' => '7',
    ]);

    Assessment::query()->create([
        'name' => 'dm1',
        'label' => 'DM1-Demolish walls',
        'hint' => 'Ø¥Ø²Ø§Ù„Ø© Ø­ÙˆØ§Ø¦Ø· Ø´Ø§Ù…Ù„Ø§ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª ÙˆØ§Ù„Ù…ØµÙ†Ø¹ÙŠØ© ØÙˆØ§Ù„ØªØ±Ø­ÙŠÙ„ Ù„Ø£Ù‚Ø±Ø¨ Ù…ÙƒØ¨ (M2)',
    ]);

    $response = $this->get(URL::signedRoute('housing.export.signed', [
        'format' => 'pdf',
        'globalid' => 'housing-unit-signed-pdf-export',
    ], absolute: false));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        return $pdf->viewName === 'damage-assessment::surveys.housing-units.export_pdf'
            && $pdf->contains('3602')
            && $pdf->contains('Signed PDF Owner');
    });
});

function ensureAuditedHousingSurveyColumns(): void
{
    Schema::table('audited_buildings', function (Blueprint $table): void {
        foreach ([
            'assignedto',
            'owner_name',
            'municipalitie',
            'end',
        ] as $columnName) {
            if (! Schema::hasColumn('audited_buildings', $columnName)) {
                $table->text($columnName)->nullable();
            }
        }
    });

    Schema::table('audited_housing_units', function (Blueprint $table): void {
        foreach ([
            'housing_unit_type',
            'floor_number',
            'housing_unit_number',
            'unit_direction',
            'damaged_area_m2',
            'infra_type2',
            'house_unit_ownership',
            'occupied',
            'unit_roof_type',
            'sex',
            'marital_status',
            'are_there_people_with_disability',
            'is_refugee',
            'the_unit_resident',
            'current_residence',
            'number_of_rooms',
            'age',
            'unit_owner',
            'q_9_3_1_first_name',
            'q_9_3_2_second_name__father',
            'q_9_3_3_third_name__grandfather',
            'q_9_3_4_last_name',
            'id_number1',
            'mobile_number',
            'municipalitie',
            'rubble_removal_is_needed',
            'activation_of_uxo_ha_d_material_clearance',
            'security_situation_unit',
            'editdate',
        ] as $columnName) {
            if (! Schema::hasColumn('audited_housing_units', $columnName)) {
                $table->text($columnName)->nullable();
            }
        }
    });
}

function seedHousingFilterOptions(): void
{
    collect([
        ['housing_unit_type', 'apartment', 'Apartment'],
        ['housing_unit_type', 'warehouse', 'Warehouse'],
        ['unit_damage_status', 'fully_damaged2', 'Totally Damaged'],
        ['unit_damage_status', 'partially_damaged2', 'Partially Damaged'],
        ['sex', 'female', 'Female'],
        ['marital_status', 'Married', 'Married'],
        ['are_there_people_with_disability', 'yes', 'Yes'],
        ['is_refugee', 'yes', 'Yes'],
        ['the_unit_resident', 'owner2', 'Owner'],
        ['current_residence', 'rented2', 'Rented accommodation'],
        ['unit_support_needed', 'yes', 'Yes'],
        ['unit_support_needed', 'no', 'No'],
        ['rubble_removal_is_needed', 'yes', 'Yes'],
        ['rubble_removal_is_needed', 'no', 'No'],
        ['activation_of_uxo_ha_d_material_clearance', 'yes', 'Yes'],
        ['activation_of_uxo_ha_d_material_clearance', 'no', 'No'],
        ['is_the_housing_unit_or_living_habitable', 'yes', 'Yes'],
        ['is_the_housing_unit_or_living_habitable', 'no', 'No'],
        ['security_situation_unit', 'yes', 'Yes'],
        ['security_situation_unit', 'no', 'No'],
    ])->each(function (array $option): void {
        Filter::query()->create([
            'list_name' => $option[0],
            'name' => $option[1],
            'label' => $option[2],
        ]);
    });
}
