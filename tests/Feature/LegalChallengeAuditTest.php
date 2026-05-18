<?php

use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\User;

it('updates legal challenges for selected audit buildings', function () {
    $user = User::factory()->create();

    $firstBuilding = Building::query()->create([
        'objectid' => 9101,
        'globalid' => 'legal-challenge-building-1',
        'building_name' => 'Legal Challenge Building 1',
    ]);

    $secondBuilding = Building::query()->create([
        'objectid' => 9102,
        'globalid' => 'legal-challenge-building-2',
        'building_name' => 'Legal Challenge Building 2',
    ]);

    $this->actingAs($user)
        ->postJson(route('audit.building.legalChallenge'), [
            'building_ids' => [$firstBuilding->objectid, $secondBuilding->objectid],
            'legal_challenge' => 'missing_legal_documents',
        ])
        ->assertOk()
        ->assertJson([
            'status' => true,
            'updated_count' => 2,
            'legal_challenge' => 'missing_legal_documents',
        ]);

    $this->assertDatabaseHas('buildings', [
        'objectid' => $firstBuilding->objectid,
        'legal_challenge' => 'missing_legal_documents',
    ]);

    $this->assertDatabaseHas('buildings', [
        'objectid' => $secondBuilding->objectid,
        'legal_challenge' => 'missing_legal_documents',
    ]);
});

it('updates the selected housing unit legal challenge', function () {
    $user = User::factory()->create();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 9201,
        'globalid' => 'legal-challenge-housing-1',
        'parentglobalid' => 'legal-challenge-building-1',
    ]);

    $this->actingAs($user)
        ->postJson(route('housing.assessment.legalChallenge'), [
            'globalid' => $housingUnit->globalid,
            'legal_challenge' => 'disputes_with_parties',
        ])
        ->assertOk()
        ->assertJson([
            'status' => true,
            'legal_challenge' => 'disputes_with_parties',
        ]);

    $this->assertDatabaseHas('housing_units', [
        'globalid' => $housingUnit->globalid,
        'legal_challenge' => 'disputes_with_parties',
    ]);
});

it('rejects unknown legal challenge values', function () {
    $user = User::factory()->create();

    $building = Building::query()->create([
        'objectid' => 9301,
        'globalid' => 'legal-challenge-building-invalid',
    ]);

    $this->actingAs($user)
        ->postJson(route('audit.building.legalChallenge'), [
            'building_ids' => [$building->objectid],
            'legal_challenge' => 'قيمة عربية لا تحفظ',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['legal_challenge']);

    $this->assertDatabaseMissing('buildings', [
        'objectid' => $building->objectid,
        'legal_challenge' => 'قيمة عربية لا تحفظ',
    ]);
});
