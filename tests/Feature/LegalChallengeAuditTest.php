<?php

use App\Models\AssignedAssessmentUser;
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

it('updates selected housing units with the same legal challenge', function () {
    $user = User::factory()->create();

    $firstHousingUnit = HousingUnit::query()->create([
        'objectid' => 9202,
        'globalid' => 'legal-challenge-housing-2',
        'parentglobalid' => 'legal-challenge-building-1',
    ]);

    $secondHousingUnit = HousingUnit::query()->create([
        'objectid' => 9203,
        'globalid' => 'legal-challenge-housing-3',
        'parentglobalid' => 'legal-challenge-building-1',
    ]);

    $this->actingAs($user)
        ->postJson(route('housing.assessment.legalChallenge'), [
            'globalids' => [$firstHousingUnit->globalid, $secondHousingUnit->globalid],
            'legal_challenge' => 'missing_inheritance_documents',
        ])
        ->assertOk()
        ->assertJson([
            'status' => true,
            'updated_count' => 2,
            'legal_challenge' => 'missing_inheritance_documents',
        ]);

    $this->assertDatabaseHas('housing_units', [
        'globalid' => $firstHousingUnit->globalid,
        'legal_challenge' => 'missing_inheritance_documents',
    ]);

    $this->assertDatabaseHas('housing_units', [
        'globalid' => $secondHousingUnit->globalid,
        'legal_challenge' => 'missing_inheritance_documents',
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
        ->get("damage-assessment/showAssessmentAudit/{$building->globalid}")
        ->assertOk()
        ->assertDontSee('id="btn_building_legal_challenge"', false)
        ->assertDontSee('id="btn_housing_legal_challenge"', false)
        ->assertDontSee("openLegalChallengeModal('building')", false)
        ->assertDontSee('housing-legal-challenge-btn', false);

    $this->actingAs($legalAuditor)
        ->get(route('audit.auditBuilding'))
        ->assertOk()
        ->assertSee('id="btn_legal_challenge"', false);

    $this->actingAs($legalAuditor)
        ->get("damage-assessment/showAssessmentAudit/{$building->globalid}")
        ->assertOk()
        ->assertSee('id="btn_building_legal_challenge"', false)
        ->assertSee('id="btn_housing_legal_challenge"', false)
        ->assertDontSee('housing-legal-challenge-btn', false)
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

it('filters audit buildings by legal challenge', function () {
    $role = Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $legalAuditor = User::factory()->create();
    $legalAuditor->assignRole($role);

    $matchingBuilding = Building::query()->create([
        'objectid' => 9601,
        'globalid' => 'legal-challenge-filter-matching',
        'building_name' => 'Matching Legal Challenge Building',
        'legal_challenge' => 'missing_legal_documents',
    ]);

    $otherBuilding = Building::query()->create([
        'objectid' => 9602,
        'globalid' => 'legal-challenge-filter-other',
        'building_name' => 'Other Legal Challenge Building',
        'legal_challenge' => 'disputes_with_parties',
    ]);

    foreach ([$matchingBuilding, $otherBuilding] as $building) {
        AssignedAssessmentUser::query()->create([
            'manager_id' => $legalAuditor->id,
            'user_id' => $legalAuditor->id,
            'type' => 'Legal Auditor',
            'building_id' => $building->objectid,
        ]);
    }

    $this->actingAs($legalAuditor)
        ->getJson(route('audit.auditBuilding', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'legal_challenge' => ['missing_legal_documents'],
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonMissingPath('error')
        ->assertJsonFragment([
            'globalid' => $matchingBuilding->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $otherBuilding->globalid,
        ]);
});

it('filters the main audit table by legal challenge', function () {
    $role = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);
    Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);
    Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $matchingBuilding = Building::query()->create([
        'objectid' => 9701,
        'globalid' => 'main-audit-legal-challenge-filter-matching',
        'building_name' => 'Main Audit Matching Legal Challenge',
        'field_status' => 'COMPLETED',
        'legal_challenge' => 'missing_legal_documents',
    ]);

    $otherBuilding = Building::query()->create([
        'objectid' => 9702,
        'globalid' => 'main-audit-legal-challenge-filter-other',
        'building_name' => 'Main Audit Other Legal Challenge',
        'field_status' => 'COMPLETED',
        'legal_challenge' => 'disputes_with_parties',
    ]);

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('id="filter_legal_challenge"', false);

    $this->actingAs($user)
        ->getJson(route('audit.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'legal_challenge' => ['missing_legal_documents'],
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonMissingPath('error')
        ->assertJsonFragment([
            'globalid' => $matchingBuilding->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $otherBuilding->globalid,
        ]);
});
