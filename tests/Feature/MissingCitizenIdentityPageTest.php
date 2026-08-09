<?php

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

it('shows the missing citizen identities page', function (): void {
    $response = $this
        ->actingAs(User::factory()->create())
        ->get(route('reports.missing-citizen-identities.index'));

    $response
        ->assertOk()
        ->assertSee(__('ui.missing_citizen_identities.title'))
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
