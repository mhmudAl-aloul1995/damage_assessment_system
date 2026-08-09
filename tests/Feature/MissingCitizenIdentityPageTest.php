<?php

use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('citizens', function (Blueprint $table): void {
        $table->id();
        $table->string('id_card_no')->nullable();
        $table->string('status')->nullable();
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
        ['id_card_no' => '900000002', 'status' => 'A'],
        ['id_card_no' => '900000003', 'status' => 'I'],
    ]);

    $response = $this
        ->actingAs(User::factory()->create())
        ->getJson(route('reports.missing-citizen-identities.data', [
            'page' => 1,
        ]));

    $response
        ->assertOk()
        ->assertJsonFragment(['id_number1' => '900000001'])
        ->assertJsonFragment(['id_number1' => '900000003'])
        ->assertJsonMissing(['id_number1' => '900000002'])
        ->assertJsonMissing(['id_number1' => ''])
        ->assertJsonPath('page', 1);
});
