<?php

use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\User;
use Spatie\Permission\Models\Role;

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

it('hides legal challenge actions from auditing engineers and shows them to other users', function () {
    $engineerRole = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);

    $legalRole = Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $engineer = User::factory()->create();
    $engineer->assignRole($engineerRole);

    $legalAuditor = User::factory()->create();
    $legalAuditor->assignRole($legalRole);

    $building = Building::query()->create([
        'objectid' => 9401,
        'globalid' => 'legal-challenge-visible-building',
    ]);

    $this->actingAs($engineer)
        ->get(route('audit.auditBuilding'))
        ->assertOk()
        ->assertDontSee('id="btn_legal_challenge"', false);

    $this->actingAs($engineer)
        ->get("showAssessmentAudit/{$building->globalid}")
        ->assertOk()
        ->assertDontSee('id="btn_building_legal_challenge"', false)
        ->assertDontSee('id="btn_housing_legal_challenge"', false)
        ->assertDontSee("openLegalChallengeModal('building')", false)
        ->assertDontSee("openLegalChallengeModal('housing')", false);

    $this->actingAs($legalAuditor)
        ->get(route('audit.auditBuilding'))
        ->assertOk()
        ->assertSee('id="btn_legal_challenge"', false);

    $this->actingAs($legalAuditor)
        ->get("showAssessmentAudit/{$building->globalid}")
        ->assertOk()
        ->assertSee('id="btn_building_legal_challenge"', false)
        ->assertSee('id="btn_housing_legal_challenge"', false)
        ->assertSee("openLegalChallengeModal('building')", false)
        ->assertSee("openLegalChallengeModal('housing')", false);
});

it('forbids auditing engineers from updating legal challenges directly', function () {
    $role = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);

    $engineer = User::factory()->create();
    $engineer->assignRole($role);

    $building = Building::query()->create([
        'objectid' => 9501,
        'globalid' => 'legal-challenge-forbidden-building',
    ]);

    $this->actingAs($engineer)
        ->postJson(route('audit.building.legalChallenge'), [
            'building_ids' => [$building->objectid],
            'legal_challenge' => 'missing_legal_documents',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('buildings', [
        'objectid' => $building->objectid,
        'legal_challenge' => 'missing_legal_documents',
    ]);
});
