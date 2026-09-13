<?php

use App\Models\HousingUnit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('citizens', function (Blueprint $table): void {
        $table->id();
        $table->string('status')->default('A');
        $table->string('id_card_no')->nullable();
        $table->string('full_name')->nullable();
        $table->unsignedBigInteger('husband_id')->nullable();
    });
});

it('previews spouse identity corrections from the husband registry without changing housing units', function (): void {
    $housingUnit = HousingUnit::query()->create([
        'objectid' => 147,
        'globalid' => 'spouse-preview',
        'unit_owner' => 'Preview Husband',
        'id_number1' => '800000001',
        'marital_status' => 'Married',
        'spouse1' => 'سناء محمد عيسى أحمد',
        'spouse1_id' => '999999999',
    ]);

    $husbandId = DB::table('citizens')->insertGetId([
        'status' => 'A',
        'id_card_no' => '800000001',
        'full_name' => 'Preview Husband',
    ]);

    DB::table('citizens')->insert([
        'status' => 'A',
        'id_card_no' => '901464339',
        'full_name' => 'سناء محمد عيسى احمد',
        'husband_id' => $husbandId,
    ]);

    $this->artisan('spouse-identities:check-by-husband', ['--unit' => [147]])
        ->expectsOutputToContain('Dry run complete')
        ->expectsOutputToContain('901464339')
        ->assertSuccessful();

    expect($housingUnit->fresh()->spouse1_id)->toBe('999999999');
});

it('applies spouse identity corrections and fills empty spouse slots from the husband registry', function (): void {
    $housingUnit = HousingUnit::query()->create([
        'objectid' => 147,
        'globalid' => 'spouse-apply',
        'unit_owner' => 'Apply Husband',
        'id_number1' => '800000001',
        'marital_status' => 'Married',
        'spouse1' => 'سناء محمد عيسى أحمد',
        'spouse1_id' => '999999999',
        'spouse2' => null,
        'spouse2_id' => null,
    ]);

    $husbandId = DB::table('citizens')->insertGetId([
        'status' => 'A',
        'id_card_no' => '800000001',
        'full_name' => 'Apply Husband',
    ]);

    DB::table('citizens')->insert([
        [
            'status' => 'A',
            'id_card_no' => '901464339',
            'full_name' => 'سناء محمد عيسى احمد',
            'husband_id' => $husbandId,
        ],
        [
            'status' => 'A',
            'id_card_no' => '901464340',
            'full_name' => 'زوجة ثانية',
            'husband_id' => $husbandId,
        ],
    ]);

    $this->artisan('spouse-identities:check-by-husband', [
        '--unit' => [147],
        '--apply' => true,
    ])
        ->expectsOutputToContain('Spouse identity check applied')
        ->assertSuccessful();

    $housingUnit->refresh();

    expect($housingUnit->spouse1_id)->toBe('901464339')
        ->and($housingUnit->spouse2)->toBe('زوجة ثانية')
        ->and($housingUnit->spouse2_id)->toBe('901464340');
});
