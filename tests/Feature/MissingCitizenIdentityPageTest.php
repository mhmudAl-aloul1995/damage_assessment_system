<?php

use App\Exports\MissingCitizenIdentityReportExport;
use App\Models\HousingUnit;
use App\Models\MissingCitizenIdentityApproval;
use App\Models\MissingCitizenIdentityReport;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Schema::create('citizens', function (Blueprint $table): void {
        $table->id();
        $table->string('id_card_no')->nullable();
        $table->string('status')->nullable();
        $table->string('first_name')->nullable();
        $table->string('father_name')->nullable();
        $table->string('grand_name')->nullable();
        $table->string('family_name')->nullable();
        $table->string('full_name')->nullable();
        $table->string('full_name_normalized')->nullable();
        $table->date('birth_date')->nullable();
        $table->string('mobile_number')->nullable();
    });
});

function createSgazaTable(): void
{
    Schema::create('sgaza', function (Blueprint $table): void {
        $table->string('id_number')->nullable();
        $table->string('first_name')->nullable();
        $table->string('father_name')->nullable();
        $table->string('grandfather_name')->nullable();
        $table->string('family_name')->nullable();
        $table->string('full_name')->nullable();
        $table->string('full_name_normalized')->nullable();
        $table->string('mother_name')->nullable();
        $table->string('الحي')->nullable();
        $table->dateTime('تاريخ الميلاد')->nullable();
    });
}

function createHusbandRegistryTable(): void
{
    Schema::create('citizens_to_set_husband_id', function (Blueprint $table): void {
        $table->id();
        $table->string('status')->default('A');
        $table->string('id_card_no')->nullable();
        $table->string('first_name')->nullable();
        $table->string('father_name')->nullable();
        $table->string('grand_name')->nullable();
        $table->string('family_name')->nullable();
        $table->string('full_name')->nullable();
        $table->string('full_name_normalized')->nullable();
        $table->string('breadwinner_id_card_no')->nullable();
    });
}

function missingCitizenIdentityUser(string $roleName = 'Database Officer'): User
{
    $role = Role::findOrCreate($roleName, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('shows the missing citizen identities page', function (): void {
    $response = $this
        ->actingAs(missingCitizenIdentityUser())
        ->get(route('reports.missing-citizen-identities.index'));

    $content = $response->getContent();
    $nameHeaderPosition = strpos($content, 'missing_citizen_identity_name_header');
    $idNumberHeaderPosition = strpos($content, 'missing_citizen_identity_number_header');
    $unitNumberHeaderPosition = strpos($content, __('ui.missing_citizen_identities.housing_unit_objectid'), $idNumberHeaderPosition);

    $response
        ->assertOk()
        ->assertSee(__('ui.missing_citizen_identities.title'))
        ->assertSee(__('ui.missing_citizen_identities.housing_unit_objectid'))
        ->assertSee(__('ui.missing_citizen_identities.marital_status'))
        ->assertSee(__('ui.missing_citizen_identities.issue_type'))
        ->assertSee(__('ui.missing_citizen_identities.unit_objectid_placeholder'))
        ->assertSee(__('ui.missing_citizen_identities.issue_owner_without_identity'))
        ->assertSee(__('ui.missing_citizen_identities.approve_selected'))
        ->assertSee(__('ui.missing_citizen_identities.select_all_matches'))
        ->assertSee(__('ui.missing_citizen_identities.export_excel'))
        ->assertSee(__('ui.missing_citizen_identities.all_marital_statuses'))
        ->assertSee('data-kt-missing-citizens-filter="marital-status"', false)
        ->assertSee('data-kt-missing-citizens-action="open-unit-objectids-modal"', false)
        ->assertSee('data-kt-missing-citizens-action="export"', false)
        ->assertSee('missing_citizen_unit_objectids_modal')
        ->assertSee('data-kt-missing-citizens-action="select-all-visible"', false)
        ->assertSee('kt_table_missing_citizen_identities');

    expect($nameHeaderPosition)
        ->toBeLessThan($idNumberHeaderPosition)
        ->and($idNumberHeaderPosition)
        ->toBeLessThan($unitNumberHeaderPosition);
});

it('allows auditing supervisor and project officer roles to access the missing identities page', function (string $roleName): void {
    $this
        ->actingAs(missingCitizenIdentityUser($roleName))
        ->get(route('reports.missing-citizen-identities.index'))
        ->assertOk();
})->with([
    'auditing supervisor' => 'Auditing Supervisor',
    'project officer' => 'Project Officer',
]);

it('blocks users without an allowed role from the missing identities page', function (): void {
    $this
        ->actingAs(User::factory()->create())
        ->get(route('reports.missing-citizen-identities.index'))
        ->assertForbidden();
});

it('returns housing unit identities that are not active citizens', function (): void {
    HousingUnit::query()->insert([
        [
            'objectid' => 1001,
            'globalid' => 'missing-citizen-id',
            'unit_owner' => 'Missing Owner',
            'id_number1' => '900000001',
            'marital_status' => 'Married',
        ],
        [
            'objectid' => 1002,
            'globalid' => 'active-citizen-id',
            'unit_owner' => 'Active Owner',
            'id_number1' => '900000002',
            'marital_status' => null,
        ],
        [
            'objectid' => 1003,
            'globalid' => 'inactive-citizen-id',
            'unit_owner' => 'Inactive Owner',
            'id_number1' => '900000003',
            'marital_status' => null,
        ],
        [
            'objectid' => 1004,
            'globalid' => 'blank-citizen-id',
            'unit_owner' => 'Blank Owner',
            'id_number1' => '',
            'marital_status' => null,
        ],
    ]);

    DB::table('citizens')->insert([
        ['id_card_no' => '900000002', 'status' => 'A', 'full_name' => 'Active Owner', 'full_name_normalized' => 'ActiveOwner'],
        ['id_card_no' => '900000003', 'status' => 'I', 'full_name' => 'Inactive Owner', 'full_name_normalized' => 'InactiveOwner'],
        ['id_card_no' => '900000009', 'status' => 'A', 'full_name' => 'Missing Owner', 'full_name_normalized' => 'MissingOwner'],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    expect(MissingCitizenIdentityReport::query()->count())->toBe(3);

    $response = $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'after_id' => 0,
            'per_page' => 1,
        ]));

    $response
        ->assertOk()
        ->assertJsonFragment(['housing_unit_objectid' => '1001'])
        ->assertJsonFragment(['issue_type' => 'missing_civil_registry_identity'])
        ->assertJsonFragment(['id_number1' => '900000001'])
        ->assertJsonFragment(['marital_status' => 'Married'])
        ->assertJsonFragment(['identity_name_field' => 'unit_owner'])
        ->assertJsonFragment(['identity_number_field' => 'id_number1'])
        ->assertJsonFragment(['matched_citizen_id_card_no' => '900000009'])
        ->assertJsonFragment(['housing_unit_objectid' => '1003'])
        ->assertJsonFragment(['id_number1' => '900000003'])
        ->assertJsonFragment(['housing_unit_objectid' => '1004'])
        ->assertJsonFragment(['id_number1' => '-'])
        ->assertJsonFragment(['issue_type' => 'owner_without_identity'])
        ->assertJsonMissing(['id_number1' => '900000002'])
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('per_page', 100)
        ->assertJsonPath('total', 3)
        ->assertJsonPath('next_cursor', 3)
        ->assertJsonCount(3, 'data');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'after_id' => 0,
            'name_match_status' => 'matched',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonFragment(['id_number1' => '900000001'])
        ->assertJsonMissing(['id_number1' => '900000003']);

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'issue_type' => 'owner_without_identity',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonFragment(['housing_unit_objectid' => '1004'])
        ->assertJsonFragment(['id_number1' => '-'])
        ->assertJsonFragment(['issue_type' => 'owner_without_identity'])
        ->assertJsonMissing(['id_number1' => '900000001']);

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'unit_objectid' => 1003,
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonFragment(['housing_unit_objectid' => '1003'])
        ->assertJsonFragment(['id_number1' => '900000003'])
        ->assertJsonMissing(['id_number1' => '900000001']);

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'marital_status' => 'Married',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonFragment(['housing_unit_objectid' => '1001'])
        ->assertJsonFragment(['marital_status' => 'Married'])
        ->assertJsonMissing(['housing_unit_objectid' => '1003'])
        ->assertJsonMissing(['housing_unit_objectid' => '1004']);

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'unit_objectid' => "1001\n1004, 9999",
        ]))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonFragment(['housing_unit_objectid' => '1001'])
        ->assertJsonFragment(['housing_unit_objectid' => '1004'])
        ->assertJsonMissing(['housing_unit_objectid' => '1003']);

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.data'), [
            'unit_objectid' => "1001\n1004, 9999",
        ])
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonFragment(['housing_unit_objectid' => '1001'])
        ->assertJsonFragment(['housing_unit_objectid' => '1004'])
        ->assertJsonMissing(['housing_unit_objectid' => '1003']);
});

it('exports missing citizen identities using the active filters', function (): void {
    Excel::fake();

    $ownerHousingUnit = HousingUnit::query()->create([
        'objectid' => 2101,
        'globalid' => 'export-owner-unit',
        'unit_owner' => 'Export Owner',
        'id_number1' => '900000101',
    ]);

    $spouseHousingUnit = HousingUnit::query()->create([
        'objectid' => 2102,
        'globalid' => 'export-spouse-unit',
        'unit_owner' => 'Export Spouse Owner',
        'id_number1' => '900000102',
        'marital_status' => 'Married',
        'spouse1' => 'Filtered Spouse',
        'spouse1_id' => '900000103',
    ]);

    MissingCitizenIdentityReport::query()->create([
        'housing_unit_id' => $ownerHousingUnit->id,
        'identity_subject' => 'owner',
        'identity_index' => null,
        'identity_name_field' => 'unit_owner',
        'identity_number_field' => 'id_number1',
        'owner_name' => 'Export Owner',
        'id_number' => '900000101',
        'issue_type' => 'missing_civil_registry_identity',
        'name_match_status' => 'not_found',
        'matched_citizens_count' => 0,
    ]);

    MissingCitizenIdentityReport::query()->create([
        'housing_unit_id' => $spouseHousingUnit->id,
        'identity_subject' => 'spouse',
        'identity_index' => 1,
        'identity_name_field' => 'spouse1',
        'identity_number_field' => 'spouse1_id',
        'owner_name' => 'Filtered Spouse',
        'id_number' => '900000103',
        'issue_type' => 'missing_civil_registry_identity',
        'name_match_status' => 'matched',
        'matched_citizen_id_card_no' => '900000104',
        'matched_citizen_full_name' => 'Matched Spouse',
        'matched_citizens_count' => 1,
    ]);

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->get(route('reports.missing-citizen-identities.export', [
            'identity_subject' => 'spouse',
            'marital_status' => 'Married',
            'name_match_status' => 'matched',
            'unit_objectid' => '2102, 9999',
        ]))
        ->assertOk();

    Excel::assertDownloaded('missing-citizen-identities.xlsx', function (MissingCitizenIdentityReportExport $export): bool {
        $rows = $export->collection()->values();

        return $rows->count() === 1
            && $rows[0][0] === __('ui.missing_citizen_identities.identity_spouse_1')
            && $rows[0][1] === 'Export Spouse Owner'
            && $rows[0][3] === 'Filtered Spouse'
            && $rows[0][4] === '900000103'
            && $rows[0][5] === 'Married'
            && $rows[0][6] === '2102'
            && $rows[0][8] === __('ui.missing_citizen_identities.name_match_matched')
            && $rows[0][9] === 'Matched Spouse'
            && $rows[0][10] === '900000104';
    });
});

it('does not report identities that exist in sgaza civil registry', function (): void {
    createSgazaTable();

    HousingUnit::query()->create([
        'objectid' => 1101,
        'globalid' => 'sgaza-existing-id',
        'unit_owner' => 'علي احمد يونس حمودة',
        'id_number1' => '938900636',
    ]);

    DB::table('sgaza')->insert([
        'id_number' => ' 938900636 ',
        'first_name' => 'علي',
        'father_name' => 'احمد',
        'grandfather_name' => 'يونس',
        'family_name' => 'حمودة',
        'full_name' => 'علي احمد يونس حمودة',
        'full_name_normalized' => 'علياحمديونسحموده',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    expect(MissingCitizenIdentityReport::query()->count())->toBe(0);
});

it('returns only arcgis ownership image attachments for a missing identity report', function (): void {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services2.arcgis.com/*/FeatureServer/1/910/attachments' => Http::response([
            'attachmentInfos' => [
                [
                    'id' => 22,
                    'name' => 'ownership_image_910.pdf',
                    'contentType' => 'application/pdf',
                    'size' => 1200,
                ],
                [
                    'id' => 23,
                    'name' => 'damage_photo_2.jpg',
                    'contentType' => 'image/jpeg',
                    'size' => 900,
                ],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 910,
        'globalid' => 'missing-identity-documents',
        'unit_owner' => 'Document Owner',
        'id_number1' => '900000010',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->firstOrFail();

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.documents', $report))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'ownership_image_910.pdf')
        ->assertJsonPath('data.0.url', 'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/910/attachments/22?token=arcgis-token')
        ->assertJsonPath('data.0.type', 'pdf')
        ->assertJsonPath('data.0.source', __('ui.missing_citizen_identities.source_arcgis'));
});

it('returns a clean json response when a report was refreshed away', function (): void {
    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.name-candidates', ['report' => 55333]))
        ->assertNotFound()
        ->assertJsonPath('message', __('ui.missing_citizen_identities.report_missing'))
        ->assertJsonPath('data', []);
});

it('matches sgaza records using structured housing unit owner name fields', function (): void {
    createSgazaTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 1102,
        'globalid' => 'sgaza-structured-name-match',
        'unit_owner' => null,
        'q_9_3_1_first_name' => 'واصل',
        'q_9_3_2_second_name__father' => 'محمود',
        'q_9_3_3_third_name__grandfather' => 'سعيد',
        'q_9_3_4_last_name' => 'لحسان',
        'id_number1' => '966605550',
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '966605552',
        'first_name' => 'واصل',
        'father_name' => 'محمود',
        'grandfather_name' => 'سعيد',
        'family_name' => 'لحسان',
        'full_name' => 'واصل محمود سعيد لحسان',
        'full_name_normalized' => 'واصلمحمودسعيدلحسان',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    expect($report->owner_name)->toBe('واصل محمود سعيد لحسان')
        ->and($report->name_match_status)->toBe('matched')
        ->and($report->matched_citizen_id_card_no)->toBe('966605552')
        ->and($report->matched_citizen_full_name)->toBe('واصل محمود سعيد لحسان');
});

it('approves a single name match and syncs the new identity to arcgis', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 501],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 501,
        'globalid' => 'missing-citizen-id',
        'unit_owner' => 'أحمد عطا الله',
        'id_number1' => '111111111',
    ]);

    DB::table('citizens')->insert([
        [
            'id_card_no' => '222222222',
            'status' => 'A',
            'full_name' => 'احمد عطا الله',
            'full_name_normalized' => 'احمدعطاالله',
        ],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    expect($report->name_match_status)->toBe('matched');

    $response = $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->id_number1)->toBe('222222222')
        ->and(MissingCitizenIdentityApproval::query()->where('housing_unit_id', $housingUnit->id)->exists())->toBeTrue()
        ->and($report->fresh()->approved_at)->not->toBeNull();

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.data'))
        ->assertOk()
        ->assertJsonMissing(['id_number1' => '111111111']);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://services.example.test/FeatureServer/1/updateFeatures'
            && str_contains((string) $request['features'], '"id_number1":"222222222"');
    });
});

it('approves an owner identity match into the owner name fields and arcgis', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 503],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 503,
        'globalid' => 'approve-owner-name-fields',
        'unit_owner' => 'Wrong Owner Name',
        'id_number1' => '111111112',
    ]);

    DB::table('citizens')->insert([
        'id_card_no' => '222222223',
        'status' => 'A',
        'full_name' => 'Correct Owner Full Family',
        'full_name_normalized' => 'CorrectOwnerFullFamily',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();
    $chosenCitizenId = DB::table('citizens')->where('id_card_no', '222222223')->value('id');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
            'citizen_id' => (string) $chosenCitizenId,
        ])
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->id_number1)->toBe('222222223')
        ->and($housingUnit->fresh()->unit_owner)->toBe('Correct Owner Full Family')
        ->and($housingUnit->fresh()->q_9_3_1_first_name)->toBe('Correct')
        ->and($housingUnit->fresh()->q_9_3_2_second_name__father)->toBe('Owner')
        ->and($housingUnit->fresh()->q_9_3_3_third_name__grandfather)->toBe('Full')
        ->and($housingUnit->fresh()->q_9_3_4_last_name)->toBe('Family');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://services.example.test/FeatureServer/1/updateFeatures'
            && str_contains((string) $request['features'], '"id_number1":"222222223"')
            && str_contains((string) $request['features'], '"unit_owner":"Correct Owner Full Family"')
            && str_contains((string) $request['features'], '"q_9_3_1_first_name":"Correct"')
            && str_contains((string) $request['features'], '"q_9_3_4_last_name":"Family"');
    });
});

it('approves an owner without identity by using the matched civil registry record', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 502],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 502,
        'globalid' => 'owner-without-identity',
        'unit_owner' => 'Owner Without Identity',
        'id_number1' => '',
    ]);

    DB::table('citizens')->insert([
        [
            'id_card_no' => '333333339',
            'status' => 'A',
            'full_name' => 'Owner Without Identity',
            'full_name_normalized' => 'OwnerWithoutIdentity',
        ],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    expect($report->issue_type)->toBe('owner_without_identity')
        ->and($report->name_match_status)->toBe('matched')
        ->and($report->matched_citizen_id_card_no)->toBe('333333339');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
        ])
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->id_number1)->toBe('333333339')
        ->and($report->fresh()->approved_at)->not->toBeNull();
});

it('lists ambiguous name candidates and approves the selected citizen', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 777],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 777,
        'globalid' => 'ambiguous-citizen-id',
        'unit_owner' => 'Ambiguous Owner',
        'id_number1' => '333333333',
    ]);

    DB::table('citizens')->insert([
        [
            'id_card_no' => '444444444',
            'status' => 'A',
            'full_name' => 'Ambiguous Owner',
            'full_name_normalized' => 'AmbiguousOwner',
        ],
        [
            'id_card_no' => '555555555',
            'status' => 'A',
            'full_name' => 'Ambiguous Owner',
            'full_name_normalized' => 'AmbiguousOwner',
        ],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    expect($report->name_match_status)->toBe('ambiguous')
        ->and($report->matched_citizens_count)->toBe(2);

    $user = missingCitizenIdentityUser();

    $this
        ->actingAs($user)
        ->getJson(route('reports.missing-citizen-identities.name-candidates', $report))
        ->assertOk()
        ->assertJsonFragment(['id_card_no' => '444444444'])
        ->assertJsonFragment(['id_card_no' => '555555555'])
        ->assertJsonCount(2, 'data');

    $chosenCitizenId = DB::table('citizens')->where('id_card_no', '555555555')->value('id');

    $this
        ->actingAs($user)
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
            'citizen_id' => (string) $chosenCitizenId,
        ])
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->id_number1)->toBe('555555555')
        ->and($report->fresh()->matched_citizen_id_card_no)->toBe('555555555')
        ->and($report->fresh()->approved_at)->not->toBeNull();
});

it('prioritizes citizens candidates before sgaza for matching names', function (): void {
    createSgazaTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 778,
        'globalid' => 'sgaza-priority-name',
        'unit_owner' => 'Priority Owner',
        'id_number1' => '333333334',
    ]);

    DB::table('citizens')->insert([
        [
            'id_card_no' => '444444445',
            'status' => 'A',
            'full_name' => 'Priority Owner',
            'full_name_normalized' => 'PriorityOwner',
        ],
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '555555556',
        'first_name' => 'Priority',
        'father_name' => 'Owner',
        'grandfather_name' => null,
        'family_name' => null,
        'full_name' => 'Priority Owner',
        'full_name_normalized' => 'PriorityOwner',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    expect($report->name_match_status)->toBe('ambiguous')
        ->and($report->matched_citizens_count)->toBe(2);

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.name-candidates', $report))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '444444445')
        ->assertJsonPath('data.0.source', __('ui.missing_citizen_identities.source_citizens'))
        ->assertJsonPath('data.1.id_card_no', '555555556')
        ->assertJsonPath('data.1.source', __('ui.missing_citizen_identities.source_sgaza'));
});

it('counts duplicate sgaza and citizen candidates with the same identity as one match', function (): void {
    createSgazaTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 779,
        'globalid' => 'duplicate-source-name',
        'unit_owner' => 'Duplicate Source',
        'id_number1' => '333333335',
    ]);

    DB::table('citizens')->insert([
        [
            'id_card_no' => '555555557',
            'status' => 'A',
            'full_name' => 'Duplicate Source',
            'full_name_normalized' => 'DuplicateSource',
        ],
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '555555557',
        'first_name' => 'Duplicate',
        'father_name' => 'Source',
        'grandfather_name' => null,
        'family_name' => null,
        'full_name' => 'Duplicate Source',
        'full_name_normalized' => 'DuplicateSource',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    expect($report->name_match_status)->toBe('matched')
        ->and($report->matched_citizens_count)->toBe(1)
        ->and($report->matched_citizen_id_card_no)->toBe('555555557');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.name-candidates', $report))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id_card_no', '555555557');
});

it('searches the civil registry for unmatched names and approves a manually selected citizen', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 888],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 888,
        'globalid' => 'unmatched-citizen-id',
        'unit_owner' => 'Unknown Owner',
        'id_number1' => '666666660',
    ]);

    DB::table('citizens')->insert([
        [
            'id_card_no' => '666666666',
            'status' => 'A',
            'full_name' => 'Correct Citizen',
            'full_name_normalized' => 'CorrectCitizen',
        ],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    expect($report->name_match_status)->toBe('not_found');

    $user = missingCitizenIdentityUser();

    $this
        ->actingAs($user)
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'q' => '666666',
        ]))
        ->assertOk()
        ->assertJsonFragment(['id_card_no' => '666666666'])
        ->assertJsonFragment(['source' => __('ui.missing_citizen_identities.source_citizens')]);

    $chosenCitizenId = DB::table('citizens')->where('id_card_no', '666666666')->value('id');

    $this
        ->actingAs($user)
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
            'citizen_id' => (string) $chosenCitizenId,
        ])
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->id_number1)->toBe('666666666')
        ->and($report->fresh()->matched_citizen_id_card_no)->toBe('666666666')
        ->and($report->fresh()->approved_at)->not->toBeNull();
});

it('searches sgaza civil registry and approves a manually selected sgaza identity', function (): void {
    createSgazaTable();

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 889],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 889,
        'globalid' => 'sgaza-manual-id',
        'unit_owner' => 'Unknown SGaza Owner',
        'id_number1' => '777777770',
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '777777777',
        'first_name' => 'SGaza',
        'father_name' => 'Manual',
        'grandfather_name' => 'Civil',
        'family_name' => 'Registry',
        'full_name' => 'SGaza Manual Civil Registry',
        'full_name_normalized' => 'SGazaManualCivilRegistry',
        'mother_name' => 'Mother',
        'الحي' => 'Neighborhood',
        'تاريخ الميلاد' => '1980-01-01 00:00:00',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    expect($report->name_match_status)->toBe('not_found');

    $user = missingCitizenIdentityUser();

    $this
        ->actingAs($user)
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'q' => '777777',
        ]))
        ->assertOk()
        ->assertJsonFragment(['id_card_no' => '777777777'])
        ->assertJsonFragment(['source' => __('ui.missing_citizen_identities.source_sgaza')]);

    $this
        ->actingAs($user)
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
            'citizen_id' => 'sgaza:777777777',
        ])
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->id_number1)->toBe('777777777')
        ->and($report->fresh()->matched_citizen_id_card_no)->toBe('777777777')
        ->and($report->fresh()->approved_at)->not->toBeNull();
});

it('searches sgaza by first father grandfather and family name fields', function (): void {
    createSgazaTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 890,
        'globalid' => 'sgaza-structured-search',
        'unit_owner' => 'Unknown Owner',
        'id_number1' => '966605550',
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '966605552',
        'first_name' => 'واصل',
        'father_name' => 'محمود',
        'grandfather_name' => 'سعيد',
        'family_name' => 'لحسان',
        'full_name' => null,
        'full_name_normalized' => null,
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'q' => 'واصل محمود سعيد لحسان',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '966605552')
        ->assertJsonPath('data.0.full_name', 'واصل محمود سعيد لحسان')
        ->assertJsonPath('data.0.source', __('ui.missing_citizen_identities.source_sgaza'));
});

it('searches sgaza by separate name part inputs without a general query', function (): void {
    createSgazaTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 891,
        'globalid' => 'sgaza-name-part-search',
        'unit_owner' => 'Unknown Owner',
        'id_number1' => '966605551',
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '966605552',
        'first_name' => 'واصل',
        'father_name' => 'محمود',
        'grandfather_name' => 'سعيد',
        'family_name' => 'لحسان',
        'full_name' => null,
        'full_name_normalized' => null,
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'first_name' => 'وا',
            'father_name' => 'مح',
            'grandfather_name' => 'سع',
            'family_name' => 'لح',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '966605552')
        ->assertJsonPath('data.0.full_name', 'واصل محمود سعيد لحسان')
        ->assertJsonPath('data.0.source', __('ui.missing_citizen_identities.source_sgaza'));
});

it('searches by identity number without mixing name part filters and prefers citizens over sgaza', function (): void {
    createSgazaTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 8911,
        'globalid' => 'identity-search-with-name-parts',
        'unit_owner' => 'Unknown Owner',
        'id_number1' => '123450000',
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '123456789',
        'first_name' => 'Identity',
        'father_name' => 'Search',
        'grandfather_name' => 'Preferred',
        'family_name' => 'Sgaza',
        'full_name' => 'Identity Search Preferred Sgaza',
        'full_name_normalized' => 'IdentitySearchPreferredSgaza',
    ]);

    DB::table('citizens')->insert([
        'status' => 'A',
        'id_card_no' => '123456789',
        'first_name' => 'Conflicting',
        'father_name' => 'Name',
        'grand_name' => 'Parts',
        'family_name' => 'Citizen',
        'full_name' => 'Conflicting Name Parts Citizen',
        'full_name_normalized' => 'ConflictingNamePartsCitizen',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->firstOrFail();

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'q' => '123456',
            'first_name' => 'NoMatch',
            'father_name' => 'NoMatch',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '123456789')
        ->assertJsonPath('data.0.source', __('ui.missing_citizen_identities.source_citizens'));
});

it('searches spouse name parts in the husband registry before citizens and sgaza', function (): void {
    createSgazaTable();
    createHusbandRegistryTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 8912,
        'globalid' => 'spouse-registry-name-part-search',
        'unit_owner' => 'Registry Husband',
        'id_number1' => '880000001',
        'mobile_number' => '0598800001',
        'marital_status' => 'Married',
        'spouse1' => 'Registry Wife',
        'spouse1_id' => '999999999',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        [
            'status' => 'A',
            'id_card_no' => '880000001',
            'first_name' => 'Registry',
            'father_name' => 'Husband',
            'grand_name' => 'From',
            'family_name' => 'Table',
            'full_name' => 'Registry Husband From Table',
            'full_name_normalized' => 'RegistryHusbandFromTable',
            'breadwinner_id_card_no' => '880000001',
        ],
        [
            'status' => 'A',
            'id_card_no' => '880000002',
            'first_name' => 'Not',
            'father_name' => 'Structured',
            'grand_name' => 'For',
            'family_name' => 'Search',
            'full_name' => 'Registry Wife From Table',
            'full_name_normalized' => 'RegistryWifeFromTable',
            'breadwinner_id_card_no' => '880000001',
        ],
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '880000003',
        'first_name' => 'Registry',
        'father_name' => 'Wife',
        'grandfather_name' => 'From',
        'family_name' => 'Table',
        'full_name' => 'Registry Wife From Table',
        'full_name_normalized' => 'RegistryWifeFromTable',
    ]);

    DB::table('citizens')->insert([
        [
            'status' => 'A',
            'id_card_no' => '880000001',
            'first_name' => 'Registry',
            'father_name' => 'Husband',
            'grand_name' => 'From',
            'family_name' => 'Table',
            'full_name' => 'Registry Husband From Table',
            'full_name_normalized' => 'RegistryHusbandFromTable',
            'birth_date' => '1975-05-01',
            'mobile_number' => null,
        ],
        [
            'status' => 'A',
            'id_card_no' => '880000002',
            'first_name' => 'Registry',
            'father_name' => 'Wife',
            'grand_name' => 'From',
            'family_name' => 'Table',
            'full_name' => 'Registry Wife From Table',
            'full_name_normalized' => 'RegistryWifeFromTable',
            'birth_date' => '1980-02-03',
            'mobile_number' => '0598800002',
        ],
        [
            'status' => 'A',
            'id_card_no' => '880000004',
            'first_name' => 'Registry',
            'father_name' => 'Wife',
            'grand_name' => 'From',
            'family_name' => 'Table',
            'full_name' => 'Registry Wife From Table',
            'full_name_normalized' => 'RegistryWifeFromTable',
            'birth_date' => null,
            'mobile_number' => null,
        ],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_subject', 'spouse')
        ->firstOrFail();

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'first_name' => 'Registry',
            'father_name' => 'Wife',
            'grandfather_name' => 'From',
            'family_name' => 'Table',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '880000002')
        ->assertJsonPath('data.0.source', __('ui.missing_citizen_identities.source_husband_registry'))
        ->assertJsonPath('data.0.related_spouse_name', 'Registry Husband From Table')
        ->assertJsonPath('data.0.related_spouse_id_number', '880000001')
        ->assertJsonPath('data.0.citizen_birth_date', '1980-02-03')
        ->assertJsonPath('data.0.citizen_mobile_number', '0598800002')
        ->assertJsonPath('data.0.related_spouse_birth_date', '1975-05-01')
        ->assertJsonPath('data.0.related_spouse_mobile_number', '0598800001');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'breadwinner_id_card_no' => '880000001',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '880000002')
        ->assertJsonPath('data.0.related_spouse_name', 'Registry Husband From Table')
        ->assertJsonPath('data.0.related_spouse_id_number', '880000001');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'wife_id_card_no' => '880000002',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '880000002')
        ->assertJsonPath('data.0.related_spouse_name', 'Registry Husband From Table')
        ->assertJsonPath('data.0.related_spouse_id_number', '880000001');
});

it('adds husband registry breadwinner hints to spouse candidates from sgaza', function (): void {
    createSgazaTable();
    createHusbandRegistryTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 8913,
        'globalid' => 'sgaza-spouse-breadwinner-hint',
        'unit_owner' => 'Hint Husband',
        'id_number1' => '900176942',
        'sex' => 'M',
        'marital_status' => 'Married',
        'spouse1' => 'شيما نمر محمد الاشقر',
        'spouse1_id' => '999999999',
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '901111111',
        'first_name' => 'شيما',
        'father_name' => 'نمر',
        'grandfather_name' => 'محمد',
        'family_name' => 'الاشقر',
        'full_name' => 'شيما نمر محمد الاشقر',
        'full_name_normalized' => 'شيمازمرمحمدالاشقر',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '901111111',
        'full_name' => 'شيما نمر محمد الاشقر',
        'full_name_normalized' => 'شيمازمرمحمدالاشقر',
        'breadwinner_id_card_no' => '900176943',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_subject', 'spouse')
        ->firstOrFail();

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'q' => '901111',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '901111111')
        ->assertJsonPath('data.0.source', __('ui.missing_citizen_identities.source_sgaza'))
        ->assertJsonPath('data.0.details', __('ui.missing_citizen_identities.breadwinner_id_card_no').': 900176943');
});

it('adds female owner registry hints to spouse husband candidates', function (): void {
    createSgazaTable();
    createHusbandRegistryTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 8914,
        'globalid' => 'female-owner-spouse-hint',
        'unit_owner' => 'Female Owner',
        'id_number1' => '970000021',
        'sex' => 'F',
        'marital_status' => 'Married',
        'spouse1' => 'Female Owner Husband',
        'spouse1_id' => '999999999',
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '970000022',
        'first_name' => 'Female',
        'father_name' => 'Owner',
        'grandfather_name' => 'Husband',
        'family_name' => 'Candidate',
        'full_name' => 'Female Owner Husband Candidate',
        'full_name_normalized' => 'FemaleOwnerHusbandCandidate',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '970000021',
        'full_name' => 'Female Owner',
        'full_name_normalized' => 'FemaleOwner',
        'breadwinner_id_card_no' => '970000022',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_subject', 'spouse')
        ->firstOrFail();

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'q' => '970000022',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '970000022')
        ->assertJsonPath('data.0.details', __('ui.missing_citizen_identities.linked_owner_id_card_no').': 970000021');
});

it('returns missing spouse identities separately from owner identities', function (): void {
    HousingUnit::query()->create([
        'objectid' => 892,
        'globalid' => 'missing-spouse-identities',
        'unit_owner' => 'Active Owner',
        'id_number1' => '900000020',
        'q_9_3_1_first_name' => 'Active',
        'q_9_3_2_second_name__father' => 'Owner',
        'q_9_3_3_third_name__grandfather' => 'Father',
        'q_9_3_4_last_name' => 'Family',
        'marital_status' => 'Married',
        'spouse1' => 'Active Spouse',
        'spouse1_id' => '900000021',
        'spouse2' => 'Missing Spouse',
        'spouse2_id' => '900000022',
        'spouse3' => 'Blank Spouse',
        'spouse3_id' => '',
        'spouse4' => 'Matched Spouse',
        'spouse4_id' => '900000024',
    ]);

    DB::table('citizens')->insert([
        ['id_card_no' => '900000020', 'status' => 'A', 'full_name' => 'Active Owner', 'full_name_normalized' => 'ActiveOwner'],
        ['id_card_no' => '900000021', 'status' => 'A', 'full_name' => 'Active Spouse', 'full_name_normalized' => 'ActiveSpouse'],
        ['id_card_no' => '900000025', 'status' => 'A', 'full_name' => 'Matched Spouse', 'full_name_normalized' => 'MatchedSpouse'],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    expect(MissingCitizenIdentityReport::query()->count())->toBe(3)
        ->and(MissingCitizenIdentityReport::query()->where('identity_subject', 'owner')->count())->toBe(0)
        ->and(MissingCitizenIdentityReport::query()->where('identity_number_field', 'spouse2_id')->exists())->toBeTrue()
        ->and(MissingCitizenIdentityReport::query()->where('identity_number_field', 'spouse3_id')->exists())->toBeTrue()
        ->and(MissingCitizenIdentityReport::query()->where('identity_number_field', 'spouse4_id')->where('name_match_status', 'matched')->exists())->toBeTrue();

    $response = $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'identity_subject' => 'spouse',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonFragment(['identity_label' => __('ui.missing_citizen_identities.identity_spouse_2')])
        ->assertJsonFragment(['identity_label' => __('ui.missing_citizen_identities.identity_spouse_3')])
        ->assertJsonFragment(['identity_label' => __('ui.missing_citizen_identities.identity_spouse_4')])
        ->assertJsonFragment(['housing_unit_owner_id_number' => '900000020'])
        ->assertJsonFragment(['id_number1' => '900000022'])
        ->assertJsonFragment(['id_number1' => '-']);

    $spouse2Row = collect($response->json('data'))->firstWhere('id_number1', '900000022');

    expect($spouse2Row['housing_unit_owner_name'])->toBe('Active Owner Father Family')
        ->and($spouse2Row['housing_unit_owner_id_number'])->toBe('900000020')
        ->and($spouse2Row['owner_name'])->toBe('Missing Spouse')
        ->and($spouse2Row['housing_unit_identity_details'])->toBe([
            [
                'label' => __('ui.missing_citizen_identities.identity_owner'),
                'name' => 'Active Owner Father Family',
                'id_number' => '900000020',
            ],
            [
                'label' => __('ui.missing_citizen_identities.identity_spouse_1'),
                'name' => 'Active Spouse',
                'id_number' => '900000021',
            ],
            [
                'label' => __('ui.missing_citizen_identities.identity_spouse_2'),
                'name' => 'Missing Spouse',
                'id_number' => '900000022',
            ],
            [
                'label' => __('ui.missing_citizen_identities.identity_spouse_3'),
                'name' => 'Blank Spouse',
                'id_number' => '-',
            ],
            [
                'label' => __('ui.missing_citizen_identities.identity_spouse_4'),
                'name' => 'Matched Spouse',
                'id_number' => '900000024',
            ],
        ]);
});

it('approves a spouse identity match into the spouse identity field', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 893],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 893,
        'globalid' => 'approve-spouse-identity',
        'unit_owner' => 'Active Owner',
        'id_number1' => '900000030',
        'marital_status' => 'Married',
        'spouse2' => 'Second Spouse Typo',
        'spouse2_id' => '900000032',
    ]);

    DB::table('citizens')->insert([
        ['id_card_no' => '900000030', 'status' => 'A', 'full_name' => 'Active Owner', 'full_name_normalized' => 'ActiveOwner'],
        ['id_card_no' => '900000033', 'status' => 'A', 'full_name' => 'Second Spouse Corrected', 'full_name_normalized' => 'SecondSpouseCorrected'],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_number_field', 'spouse2_id')
        ->firstOrFail();

    $chosenCitizenId = DB::table('citizens')->where('id_card_no', '900000033')->value('id');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
            'citizen_id' => (string) $chosenCitizenId,
        ])
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->id_number1)->toBe('900000030')
        ->and($housingUnit->fresh()->spouse2_id)->toBe('900000033')
        ->and($housingUnit->fresh()->spouse2)->toBe('Second Spouse Corrected')
        ->and($report->fresh()->approved_at)->not->toBeNull();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://services.example.test/FeatureServer/1/updateFeatures'
            && str_contains((string) $request['features'], '"spouse2_id":"900000033"')
            && str_contains((string) $request['features'], '"spouse2":"Second Spouse Corrected"')
            && ! str_contains((string) $request['features'], '"id_number1":"900000033"');
    });
});

it('backfills previously approved owner and spouse names into the database and arcgis', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 892],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 892,
        'globalid' => 'approved-spouse-name-backfill',
        'unit_owner' => 'Active Owner',
        'id_number1' => '900000040',
        'marital_status' => 'Married',
        'spouse1' => 'Old Spouse Name',
        'spouse1_id' => '900000041',
    ]);

    $ownerHousingUnit = HousingUnit::query()->create([
        'objectid' => 891,
        'globalid' => 'approved-owner-name-backfill',
        'unit_owner' => 'Old Owner Name',
        'id_number1' => '900000050',
    ]);

    $report = MissingCitizenIdentityReport::query()->create([
        'housing_unit_id' => $housingUnit->id,
        'identity_subject' => 'spouse',
        'identity_index' => 1,
        'identity_name_field' => 'spouse1',
        'identity_number_field' => 'spouse1_id',
        'owner_name' => 'Old Spouse Name',
        'normalized_owner_name' => 'OldSpouseName',
        'id_number' => '900000041',
        'issue_type' => 'missing_civil_registry_identity',
        'name_match_status' => 'matched',
        'matched_citizen_id' => 0,
        'matched_citizen_id_card_no' => '900000042',
        'matched_citizen_full_name' => 'Correct Spouse',
        'matched_citizens_count' => 1,
        'approved_at' => now(),
    ]);

    DB::table('citizens')->insert([
        'id_card_no' => '900000042',
        'status' => 'A',
        'full_name' => 'Correct Approved Spouse Full',
        'full_name_normalized' => 'CorrectApprovedSpouseFull',
    ]);

    $ownerReport = MissingCitizenIdentityReport::query()->create([
        'housing_unit_id' => $ownerHousingUnit->id,
        'identity_subject' => 'owner',
        'identity_index' => 0,
        'identity_name_field' => 'unit_owner',
        'identity_number_field' => 'id_number1',
        'owner_name' => 'Old Owner Name',
        'normalized_owner_name' => 'OldOwnerName',
        'id_number' => '900000050',
        'issue_type' => 'missing_civil_registry_identity',
        'name_match_status' => 'matched',
        'matched_citizen_id' => 0,
        'matched_citizen_id_card_no' => '900000052',
        'matched_citizen_full_name' => 'Correct Owner Approved Family',
        'matched_citizens_count' => 1,
        'approved_at' => now(),
    ]);

    MissingCitizenIdentityApproval::query()->create([
        'missing_citizen_identity_report_id' => $report->id,
        'housing_unit_id' => $housingUnit->id,
        'housing_unit_objectid' => $housingUnit->objectid,
        'old_id_number' => '900000041',
        'new_id_number' => '900000042',
        'owner_name' => 'Old Spouse Name',
        'citizen_id' => 0,
        'citizen_full_name' => 'Correct Spouse',
        'arcgis_sync_status' => 'synced',
    ]);

    MissingCitizenIdentityApproval::query()->create([
        'missing_citizen_identity_report_id' => $ownerReport->id,
        'housing_unit_id' => $ownerHousingUnit->id,
        'housing_unit_objectid' => $ownerHousingUnit->objectid,
        'old_id_number' => '900000050',
        'new_id_number' => '900000052',
        'owner_name' => 'Old Owner Name',
        'citizen_id' => 0,
        'citizen_full_name' => 'Correct Owner Approved Family',
        'arcgis_sync_status' => 'synced',
    ]);

    $this
        ->artisan('missing-citizen-identities:backfill-approved-identities', ['--chunk' => 1])
        ->assertSuccessful();

    expect($housingUnit->fresh()->spouse1)->toBe('Correct Approved Spouse Full')
        ->and($housingUnit->fresh()->spouse1_id)->toBe('900000042')
        ->and($ownerHousingUnit->fresh()->unit_owner)->toBe('Correct Owner Approved Family')
        ->and($ownerHousingUnit->fresh()->id_number1)->toBe('900000052')
        ->and($ownerHousingUnit->fresh()->q_9_3_1_first_name)->toBe('Correct')
        ->and($ownerHousingUnit->fresh()->q_9_3_2_second_name__father)->toBe('Owner')
        ->and($ownerHousingUnit->fresh()->q_9_3_3_third_name__grandfather)->toBe('Approved')
        ->and($ownerHousingUnit->fresh()->q_9_3_4_last_name)->toBe('Family')
        ->and($report->fresh()->arcgis_sync_status)->toBe('synced')
        ->and($ownerReport->fresh()->arcgis_sync_status)->toBe('synced')
        ->and(MissingCitizenIdentityApproval::query()->where('missing_citizen_identity_report_id', $report->id)->latest('id')->first()?->arcgis_sync_status)->toBe('synced');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://services.example.test/FeatureServer/1/updateFeatures'
            && str_contains((string) $request['features'], '"spouse1":"Correct Approved Spouse Full"')
            && str_contains((string) $request['features'], '"spouse1_id":"900000042"');
    });

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://services.example.test/FeatureServer/1/updateFeatures'
            && str_contains((string) $request['features'], '"unit_owner":"Correct Owner Approved Family"')
            && str_contains((string) $request['features'], '"id_number1":"900000052"')
            && str_contains((string) $request['features'], '"q_9_3_1_first_name":"Correct"')
            && str_contains((string) $request['features'], '"q_9_3_4_last_name":"Family"');
    });
});

it('backfills housing unit owner and spouse names from civil registry identity numbers only', function (): void {
    createSgazaTable();

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 993],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 993,
        'globalid' => 'housing-unit-name-registry-backfill',
        'unit_owner' => 'Owner Short',
        'id_number1' => '920000001',
        'q_9_3_1_first_name' => 'Keep',
        'q_9_3_2_second_name__father' => 'These',
        'q_9_3_3_third_name__grandfather' => 'Owner',
        'q_9_3_4_last_name' => 'Fields',
        'spouse1' => 'Wife Short',
        'spouse1_id' => '920000002',
        'spouse2' => 'Second Short',
        'spouse2_id' => '920000003',
        'spouse3' => 'Repeated Placeholder',
        'spouse3_id' => '999999999',
    ]);

    DB::table('citizens')->insert([
        [
            'id_card_no' => '920000001',
            'status' => 'A',
            'full_name' => 'Owner Full Civil Registry',
            'full_name_normalized' => 'OwnerFullCivilRegistry',
        ],
        [
            'id_card_no' => '920000002',
            'status' => 'A',
            'full_name' => 'First Wife Full Registry',
            'full_name_normalized' => 'FirstWifeFullRegistry',
        ],
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '920000003',
        'first_name' => 'Second',
        'father_name' => 'Wife',
        'grandfather_name' => 'Civil',
        'family_name' => 'Registry',
    ]);

    $this
        ->artisan('housing-units:backfill-names-from-civil-registry', ['--chunk' => 1])
        ->assertSuccessful();

    $housingUnit->refresh();

    expect($housingUnit->unit_owner)->toBe('Owner Full Civil Registry')
        ->and($housingUnit->spouse1)->toBe('First Wife Full Registry')
        ->and($housingUnit->spouse2)->toBe('Second Wife Civil Registry')
        ->and($housingUnit->spouse3)->toBe('Repeated Placeholder')
        ->and($housingUnit->q_9_3_1_first_name)->toBe('Keep')
        ->and($housingUnit->q_9_3_2_second_name__father)->toBe('These')
        ->and($housingUnit->q_9_3_3_third_name__grandfather)->toBe('Owner')
        ->and($housingUnit->q_9_3_4_last_name)->toBe('Fields');

    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://services.example.test/FeatureServer/1/updateFeatures') {
            return false;
        }

        $features = (string) $request['features'];

        return str_contains($features, '"unit_owner":"Owner Full Civil Registry"')
            && str_contains($features, '"spouse1":"First Wife Full Registry"')
            && str_contains($features, '"spouse2":"Second Wife Civil Registry"')
            && ! str_contains($features, 'q_9_3_1_first_name')
            && ! str_contains($features, 'q_9_3_2_second_name__father')
            && ! str_contains($features, 'q_9_3_3_third_name__grandfather')
            && ! str_contains($features, 'q_9_3_4_last_name');
    });
});

it('does not report spouse identities found in the husband registry table', function (): void {
    createHusbandRegistryTable();

    HousingUnit::query()->create([
        'objectid' => 894,
        'globalid' => 'spouse-id-found-in-husband-registry',
        'unit_owner' => 'Registry Husband',
        'id_number1' => '910000001',
        'marital_status' => 'Married',
        'spouse1' => 'Registry Spouse',
        'spouse1_id' => '910000002',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '910000002',
        'full_name' => 'Registry Spouse',
        'full_name_normalized' => 'RegistrySpouse',
        'breadwinner_id_card_no' => '910000001',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    expect(MissingCitizenIdentityReport::query()
        ->where('identity_number_field', 'spouse1_id')
        ->exists())->toBeFalse();
});

it('keeps spouse identities missing when the registry wife belongs to another breadwinner', function (): void {
    createHusbandRegistryTable();

    HousingUnit::query()->create([
        'objectid' => 898,
        'globalid' => 'spouse-id-linked-to-another-breadwinner',
        'unit_owner' => 'Registry Husband',
        'id_number1' => '910000001',
        'marital_status' => 'Married',
        'spouse1' => 'Registry Spouse',
        'spouse1_id' => '910000002',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '910000002',
        'full_name' => 'Registry Spouse',
        'full_name_normalized' => 'RegistrySpouse',
        'breadwinner_id_card_no' => '910000009',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    expect(MissingCitizenIdentityReport::query()
        ->where('identity_number_field', 'spouse1_id')
        ->where('id_number', '910000002')
        ->exists())->toBeTrue();
});

it('matches a bad spouse name to the only wife registered for the breadwinner', function (): void {
    createHusbandRegistryTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 9011,
        'globalid' => 'bad-spouse-name-single-registry-wife',
        'unit_owner' => 'Bad Spouse Name Husband',
        'id_number1' => '934953951',
        'marital_status' => 'Married',
        'spouse1' => '|',
        'spouse1_id' => '999999999',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '928640739',
        'full_name' => 'غاليه علي أحمد بدر',
        'full_name_normalized' => 'غاليهعلياحمدبدر',
        'breadwinner_id_card_no' => '934953951',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_number_field', 'spouse1_id')
        ->firstOrFail();

    expect($report->owner_name)->toBe('|')
        ->and($report->id_number)->toBe('999999999')
        ->and($report->name_match_status)->toBe('matched')
        ->and($report->matched_citizen_id_card_no)->toBe('928640739')
        ->and($report->matched_citizen_full_name)->toBe('غاليه علي أحمد بدر');
});

it('uses the husband registry spouse as the primary match instead of adding the same wife again', function (): void {
    createHusbandRegistryTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 9012,
        'globalid' => 'partial-spouse-name-registry-match',
        'unit_owner' => 'عمر فتحي محمد بدر',
        'id_number1' => '800407728',
        'marital_status' => 'Married',
        'spouse1' => 'اسلام الشافعي بدر',
        'spouse1_id' => '801390356',
        'spouse2' => null,
        'spouse2_id' => null,
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '801390956',
        'full_name' => 'اسلام الشافعي اسماعيل بدر',
        'full_name_normalized' => 'اسلامالشافعيسماعيلبدر',
        'breadwinner_id_card_no' => '800407728',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $reports = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_subject', 'spouse')
        ->orderBy('identity_index')
        ->get();

    expect($reports)->toHaveCount(1)
        ->and($reports->first()->identity_number_field)->toBe('spouse1_id')
        ->and($reports->first()->name_match_status)->toBe('matched')
        ->and($reports->first()->matched_citizen_id_card_no)->toBe('801390956')
        ->and($reports->first()->matched_citizen_full_name)->toBe('اسلام الشافعي اسماعيل بدر');
});

it('matches and approves missing spouse identities from the husband registry table', function (): void {
    createHusbandRegistryTable();

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 895],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 895,
        'globalid' => 'spouse-match-from-husband-registry',
        'unit_owner' => 'Registry Husband',
        'id_number1' => '920000001',
        'marital_status' => 'Married',
        'spouse3' => 'Registry Third Spouse',
        'spouse3_id' => '',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '920000003',
        'full_name' => 'Registry Third Spouse',
        'full_name_normalized' => 'RegistryThirdSpouse',
        'breadwinner_id_card_no' => '920000001',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_number_field', 'spouse3_id')
        ->firstOrFail();

    expect($report->name_match_status)->toBe('matched')
        ->and($report->matched_citizen_id_card_no)->toBe('920000003');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->getJson(route('reports.missing-citizen-identities.name-candidates', $report))
        ->assertOk()
        ->assertJsonPath('data.0.id_card_no', '920000003')
        ->assertJsonPath('data.0.source', __('ui.missing_citizen_identities.source_husband_registry'));

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
        ])
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->spouse3_id)->toBe('920000003');
});

it('creates spouse rows from the husband registry when housing spouse fields are empty', function (): void {
    createHusbandRegistryTable();

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 896],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 896,
        'globalid' => 'registry-only-spouse',
        'unit_owner' => 'Registry Only Husband',
        'id_number1' => '930000001',
        'marital_status' => 'Married',
        'spouse1' => null,
        'spouse1_id' => null,
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '930000002',
        'full_name' => 'Registry Only Spouse',
        'full_name_normalized' => 'RegistryOnlySpouse',
        'breadwinner_id_card_no' => '930000001',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_number_field', 'spouse1_id')
        ->firstOrFail();

    expect($report->owner_name)->toBe('Registry Only Spouse')
        ->and($report->id_number)->toBe('')
        ->and($report->name_match_status)->toBe('matched')
        ->and($report->matched_citizen_id_card_no)->toBe('930000002');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
        ])
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->spouse1)->toBe('Registry Only Spouse')
        ->and($housingUnit->fresh()->spouse1_id)->toBe('930000002');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://services.example.test/FeatureServer/1/updateFeatures'
            && str_contains((string) $request['features'], '"spouse1":"Registry Only Spouse"')
            && str_contains((string) $request['features'], '"spouse1_id":"930000002"');
    });
});

it('creates spouse rows only up to the four supported spouse slots', function (): void {
    createHusbandRegistryTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 899,
        'globalid' => 'multiple-registry-spouses',
        'unit_owner' => 'Multiple Spouses Husband',
        'id_number1' => '950000001',
        'marital_status' => 'Married',
        'spouse1' => 'Existing First Spouse',
        'spouse1_id' => '950000002',
        'spouse2' => null,
        'spouse2_id' => null,
        'spouse3' => null,
        'spouse3_id' => null,
        'spouse4' => null,
        'spouse4_id' => null,
    ]);

    DB::table('citizens')->insert([
        'status' => 'A',
        'id_card_no' => '950000001',
        'full_name' => 'Multiple Spouses Husband',
        'full_name_normalized' => 'MultipleSpousesHusband',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        [
            'status' => 'A',
            'id_card_no' => '950000002',
            'full_name' => 'Existing First Spouse',
            'full_name_normalized' => 'ExistingFirstSpouse',
            'breadwinner_id_card_no' => '950000001',
        ],
        [
            'status' => 'A',
            'id_card_no' => '950000003',
            'full_name' => 'Second Registry Spouse',
            'full_name_normalized' => 'SecondRegistrySpouse',
            'breadwinner_id_card_no' => '950000001',
        ],
        [
            'status' => 'A',
            'id_card_no' => '950000004',
            'full_name' => 'Third Registry Spouse',
            'full_name_normalized' => 'ThirdRegistrySpouse',
            'breadwinner_id_card_no' => '950000001',
        ],
        [
            'status' => 'A',
            'id_card_no' => '950000005',
            'full_name' => 'Fourth Registry Spouse',
            'full_name_normalized' => 'FourthRegistrySpouse',
            'breadwinner_id_card_no' => '950000001',
        ],
        [
            'status' => 'A',
            'id_card_no' => '950000006',
            'full_name' => 'Fifth Registry Spouse',
            'full_name_normalized' => 'FifthRegistrySpouse',
            'breadwinner_id_card_no' => '950000001',
        ],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $reports = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_subject', 'spouse')
        ->orderBy('identity_index')
        ->get();

    expect($reports)->toHaveCount(3)
        ->and($reports->pluck('identity_number_field')->all())->toBe(['spouse2_id', 'spouse3_id', 'spouse4_id'])
        ->and($reports->pluck('matched_citizen_id_card_no')->all())->toBe(['950000003', '950000004', '950000005'])
        ->and($reports->pluck('matched_citizen_id_card_no'))->not->toContain('950000002')
        ->and($reports->pluck('matched_citizen_id_card_no'))->not->toContain('950000006');
});

it('does not report spouse rows when the marital status is not married', function (): void {
    createHusbandRegistryTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 900,
        'globalid' => 'single-declared-spouse',
        'unit_owner' => 'Single Declared Husband',
        'id_number1' => '960000001',
        'marital_status' => 'Single2',
        'spouse1' => null,
        'spouse1_id' => '999999999',
        'spouse2' => null,
        'spouse2_id' => null,
    ]);

    DB::table('citizens')->insert([
        'status' => 'A',
        'id_card_no' => '960000001',
        'full_name' => 'Single Declared Husband',
        'full_name_normalized' => 'SingleDeclaredHusband',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '960000002',
        'full_name' => 'Registry Single Spouse',
        'full_name_normalized' => 'RegistrySingleSpouse',
        'breadwinner_id_card_no' => '960000001',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    expect(MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_subject', 'spouse')
        ->exists())->toBeFalse();
});

it('does not report a female owner spouse identity when it matches the registry breadwinner', function (): void {
    createHusbandRegistryTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 9013,
        'globalid' => 'female-owner-existing-husband',
        'unit_owner' => 'Female Owner',
        'id_number1' => '970000001',
        'marital_status' => 'Married',
        'spouse1' => 'Female Owner Husband',
        'spouse1_id' => '970000002',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '970000001',
        'full_name' => 'Female Owner',
        'full_name_normalized' => 'FemaleOwner',
        'breadwinner_id_card_no' => '970000002',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    expect(MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_number_field', 'spouse1_id')
        ->exists())->toBeFalse();
});

it('matches a female owner spouse identity from the registry breadwinner', function (): void {
    createHusbandRegistryTable();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 9014,
        'globalid' => 'female-owner-wrong-husband',
        'unit_owner' => 'Female Owner',
        'id_number1' => '970000011',
        'marital_status' => 'Married',
        'spouse1' => 'Female Owner Husband',
        'spouse1_id' => '999999999',
    ]);

    DB::table('citizens')->insert([
        'status' => 'A',
        'id_card_no' => '970000012',
        'full_name' => 'Female Owner Husband',
        'full_name_normalized' => 'FemaleOwnerHusband',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '970000011',
        'full_name' => 'Female Owner',
        'full_name_normalized' => 'FemaleOwner',
        'breadwinner_id_card_no' => '970000012',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_number_field', 'spouse1_id')
        ->firstOrFail();

    expect($report->name_match_status)->toBe('matched')
        ->and($report->matched_citizen_id_card_no)->toBe('970000012')
        ->and($report->matched_citizen_full_name)->toBe('Female Owner Husband');
});

it('creates spouse rows from the husband registry when the owner is female', function (): void {
    createHusbandRegistryTable();

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 897],
            ],
        ]),
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 897,
        'globalid' => 'female-owner-registry-spouse',
        'unit_owner' => 'Female Owner',
        'id_number1' => '940000001',
        'marital_status' => 'Married',
        'spouse1' => null,
        'spouse1_id' => null,
    ]);

    DB::table('citizens')->insert([
        'status' => 'A',
        'id_card_no' => '940000002',
        'full_name' => 'Female Owner Husband',
        'full_name_normalized' => 'FemaleOwnerHusband',
    ]);

    DB::table('citizens_to_set_husband_id')->insert([
        'status' => 'A',
        'id_card_no' => '940000001',
        'full_name' => 'Female Owner',
        'full_name_normalized' => 'FemaleOwner',
        'breadwinner_id_card_no' => '940000002',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()
        ->where('housing_unit_id', $housingUnit->id)
        ->where('identity_number_field', 'spouse1_id')
        ->firstOrFail();

    expect($report->owner_name)->toBe('Female Owner Husband')
        ->and($report->name_match_status)->toBe('matched')
        ->and($report->matched_citizen_id_card_no)->toBe('940000002');

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
        ])
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->spouse1)->toBe('Female Owner Husband')
        ->and($housingUnit->fresh()->spouse1_id)->toBe('940000002');
});

it('bulk approves selected single name matches', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/1/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true],
            ],
        ]),
    ]);

    $firstHousingUnit = HousingUnit::query()->create([
        'objectid' => 901,
        'globalid' => 'bulk-one',
        'unit_owner' => 'Bulk One',
        'id_number1' => '701701701',
    ]);

    $secondHousingUnit = HousingUnit::query()->create([
        'objectid' => 902,
        'globalid' => 'bulk-two',
        'unit_owner' => 'Bulk Two',
        'id_number1' => '702702702',
    ]);

    DB::table('citizens')->insert([
        [
            'id_card_no' => '801801801',
            'status' => 'A',
            'full_name' => 'Bulk One',
            'full_name_normalized' => 'BulkOne',
        ],
        [
            'id_card_no' => '802802802',
            'status' => 'A',
            'full_name' => 'Bulk Two',
            'full_name_normalized' => 'BulkTwo',
        ],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $reports = MissingCitizenIdentityReport::query()
        ->whereIn('housing_unit_id', [$firstHousingUnit->id, $secondHousingUnit->id])
        ->orderBy('id')
        ->get();

    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.bulk-approve-name-matches'), [
            'report_ids' => $reports->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJsonPath('approved', 2)
        ->assertJsonPath('failed', 0)
        ->assertJsonPath('skipped', 0);

    expect($firstHousingUnit->fresh()->id_number1)->toBe('801801801')
        ->and($secondHousingUnit->fresh()->id_number1)->toBe('802802802')
        ->and(MissingCitizenIdentityApproval::query()->whereIn('housing_unit_id', [$firstHousingUnit->id, $secondHousingUnit->id])->count())->toBe(2);
});

it('allows bulk approving up to five hundred selected reports', function (): void {
    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.bulk-approve-name-matches'), [
            'report_ids' => range(1, 500),
        ])
        ->assertOk()
        ->assertJsonPath('approved', 0)
        ->assertJsonPath('failed', 0)
        ->assertJsonPath('skipped', 0);
});

it('rejects bulk approving more than five hundred selected reports', function (): void {
    $this
        ->actingAs(missingCitizenIdentityUser())
        ->postJson(route('reports.missing-citizen-identities.bulk-approve-name-matches'), [
            'report_ids' => range(1, 501),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('report_ids');
});
