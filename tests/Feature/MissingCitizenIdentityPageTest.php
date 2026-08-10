<?php

use App\Models\AccessCivilRegistryRecord;
use App\Models\HousingUnit;
use App\Models\MissingCitizenIdentityApproval;
use App\Models\MissingCitizenIdentityReport;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('citizens', function (Blueprint $table): void {
        $table->id();
        $table->string('id_card_no')->nullable();
        $table->string('status')->nullable();
        $table->string('full_name')->nullable();
        $table->string('full_name_normalized')->nullable();
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

it('shows the missing citizen identities page', function (): void {
    $response = $this
        ->actingAs(User::factory()->create())
        ->get(route('reports.missing-citizen-identities.index'));

    $response
        ->assertOk()
        ->assertSee(__('ui.missing_citizen_identities.title'))
        ->assertSee(__('ui.missing_citizen_identities.approve_selected'))
        ->assertSee('kt_table_missing_citizen_identities');
});

it('returns housing unit identities that are not active citizens', function (): void {
    HousingUnit::query()->insert([
        [
            'objectid' => 1001,
            'globalid' => 'missing-citizen-id',
            'unit_owner' => 'Missing Owner',
            'id_number1' => '900000001',
        ],
        [
            'objectid' => 1002,
            'globalid' => 'active-citizen-id',
            'unit_owner' => 'Active Owner',
            'id_number1' => '900000002',
        ],
        [
            'objectid' => 1003,
            'globalid' => 'inactive-citizen-id',
            'unit_owner' => 'Inactive Owner',
            'id_number1' => '900000003',
        ],
        [
            'objectid' => 1004,
            'globalid' => 'blank-citizen-id',
            'unit_owner' => 'Blank Owner',
            'id_number1' => '',
        ],
    ]);

    DB::table('citizens')->insert([
        ['id_card_no' => '900000002', 'status' => 'A', 'full_name' => 'Active Owner', 'full_name_normalized' => 'ActiveOwner'],
        ['id_card_no' => '900000003', 'status' => 'I', 'full_name' => 'Inactive Owner', 'full_name_normalized' => 'InactiveOwner'],
        ['id_card_no' => '900000009', 'status' => 'A', 'full_name' => 'Missing Owner', 'full_name_normalized' => 'MissingOwner'],
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    expect(MissingCitizenIdentityReport::query()->count())->toBe(2);

    $response = $this
        ->actingAs(User::factory()->create())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'after_id' => 0,
            'per_page' => 1,
        ]));

    $response
        ->assertOk()
        ->assertJsonFragment(['id_number1' => '900000001'])
        ->assertJsonFragment(['matched_citizen_id_card_no' => '900000009'])
        ->assertJsonMissing(['id_number1' => '900000003'])
        ->assertJsonMissing(['id_number1' => '900000002'])
        ->assertJsonMissing(['id_number1' => ''])
        ->assertJsonPath('has_more', true)
        ->assertJsonPath('per_page', 1)
        ->assertJsonPath('total', 2)
        ->assertJsonPath('next_cursor', 1)
        ->assertJsonCount(1, 'data');

    $this
        ->actingAs(User::factory()->create())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'after_id' => 0,
            'name_match_status' => 'matched',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonFragment(['id_number1' => '900000001'])
        ->assertJsonMissing(['id_number1' => '900000003']);
});

it('does not report identities that exist in sgaza civil registry', function (): void {
    createSgazaTable();

    HousingUnit::query()->create([
        'objectid' => 1101,
        'globalid' => 'sgaza-existing-id',
        'unit_owner' => 'SGaza Existing',
        'id_number1' => '777777777',
    ]);

    DB::table('sgaza')->insert([
        'id_number' => '777777777',
        'first_name' => 'SGaza',
        'father_name' => 'Existing',
        'grandfather_name' => 'Civil',
        'family_name' => 'Registry',
        'full_name' => 'SGaza Existing Civil Registry',
        'full_name_normalized' => 'SGazaExistingCivilRegistry',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    expect(MissingCitizenIdentityReport::query()->count())->toBe(0);
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
        ->actingAs(User::factory()->create())
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('arcgis_status', 'synced');

    expect($housingUnit->fresh()->id_number1)->toBe('222222222')
        ->and(MissingCitizenIdentityApproval::query()->where('housing_unit_id', $housingUnit->id)->exists())->toBeTrue()
        ->and($report->fresh()->approved_at)->not->toBeNull();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://services.example.test/FeatureServer/1/updateFeatures'
            && str_contains((string) $request['features'], '"id_number1":"222222222"');
    });
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

    $user = User::factory()->create();

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
            'status' => 'I',
            'full_name' => 'Correct Citizen',
            'full_name_normalized' => 'CorrectCitizen',
        ],
    ]);

    $accessRecord = AccessCivilRegistryRecord::query()->create([
        'id_card_no' => '666666666',
        'first_name' => 'Correct',
        'father_name' => 'Access',
        'grand_name' => 'Civil',
        'family_name' => 'Registry',
        'full_name' => 'Correct Access Civil Registry',
        'full_name_normalized' => 'CorrectAccessCivilRegistry',
        'mother_name' => 'Mother',
        'neighborhood' => 'Neighborhood',
        'birth_date' => '1980-01-01',
    ]);

    $this->artisan('missing-citizen-identities:refresh', ['--chunk' => 2])
        ->assertSuccessful();

    $report = MissingCitizenIdentityReport::query()->where('housing_unit_id', $housingUnit->id)->firstOrFail();

    expect($report->name_match_status)->toBe('not_found');

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->getJson(route('reports.missing-citizen-identities.citizen-search', [
            'report' => $report,
            'q' => '666666',
        ]))
        ->assertOk()
        ->assertJsonFragment(['id_card_no' => '666666666'])
        ->assertJsonFragment(['source' => __('ui.missing_citizen_identities.source_access')]);

    $this
        ->actingAs($user)
        ->postJson(route('reports.missing-citizen-identities.approve-name-match', $report), [
            'confirm' => true,
            'citizen_id' => 'access:'.$accessRecord->id,
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

    $user = User::factory()->create();

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
        ->actingAs(User::factory()->create())
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
