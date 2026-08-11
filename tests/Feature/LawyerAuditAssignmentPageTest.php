<?php

use App\Models\AssessmentStatus;
use App\Models\AssignedAssessmentUser;
use App\Models\Building;
use App\Models\LawyerAuditAssignment;
use App\Models\User;
use App\Support\Audit\RestrictedLawyerAuditAccess;
use Spatie\Permission\Models\Role;

it('shows only the current restricted lawyer assignments', function () {
    Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $alaa = User::factory()->create([
        'name' => RestrictedLawyerAuditAccess::ALAA_KATOU,
    ]);
    $alaa->assignRole('Legal Auditor');

    LawyerAuditAssignment::query()->create([
        'excel_index' => 1,
        'source_row_number' => 2,
        'lawyer_name' => RestrictedLawyerAuditAccess::ALAA_KATOU,
        'building_objectid' => 1261,
        'housing_unit_objectid' => 7,
        'building_globalid' => '51ea2320-1c6b-4115-af83-e8103cb335c0',
        'housing_unit_globalid' => '732030f3-5c19-4720-a771-2452c17e95f1',
        'owner_full_name' => 'Alaa Owner',
    ]);

    LawyerAuditAssignment::query()->create([
        'excel_index' => 1001,
        'source_row_number' => 1002,
        'lawyer_name' => RestrictedLawyerAuditAccess::EYAD_BAKRI,
        'building_objectid' => 1111,
        'housing_unit_objectid' => 242,
        'building_globalid' => 'c0ddeae7-9eff-40a3-a9de-bddd337b5016',
        'housing_unit_globalid' => '429b584f-f5e6-4f9b-b3d6-5e1d4a9210a1',
        'owner_full_name' => 'Eyad Owner',
    ]);

    $this->actingAs($alaa)
        ->get(route('audit.lawyer-assignments'))
        ->assertOk()
        ->assertSee('قائمة الوحدات للمحامين')
        ->assertSee(RestrictedLawyerAuditAccess::ALAA_KATOU);

    $this->actingAs($alaa)
        ->getJson(route('audit.lawyer-assignments', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonFragment([
            'excel_index' => 1,
            'owner_full_name' => 'Alaa Owner',
        ])
        ->assertJsonMissing([
            'owner_full_name' => 'Eyad Owner',
        ]);
});

it('builds direct assessment audit links from building and housing global ids', function () {
    $assignment = LawyerAuditAssignment::query()->create([
        'excel_index' => 25,
        'source_row_number' => 26,
        'lawyer_name' => RestrictedLawyerAuditAccess::ALAA_KATOU,
        'building_globalid' => 'building-globalid',
        'housing_unit_globalid' => 'housing-globalid',
    ]);

    expect($assignment->assessmentUrl())
        ->toContain('/damage-assessment/showAssessmentAudit/building-globalid/housing-globalid');
});

it('allows only restricted lawyers and database officers to open the lawyer assignments page', function () {
    Role::findOrCreate('Database Officer', 'web');
    Role::findOrCreate('Auditing Supervisor', 'web');
    Role::findOrCreate('Audit Reviewer', 'web');

    $databaseOfficer = User::factory()->create();
    $databaseOfficer->assignRole('Database Officer');

    $auditingSupervisor = User::factory()->create();
    $auditingSupervisor->assignRole('Auditing Supervisor');

    $auditReviewer = User::factory()->create();
    $auditReviewer->assignRole('Audit Reviewer');

    $this->actingAs($databaseOfficer)
        ->get(route('audit.lawyer-assignments'))
        ->assertOk();

    $this->actingAs($auditingSupervisor)
        ->get(route('audit.lawyer-assignments'))
        ->assertForbidden();

    $this->actingAs($auditReviewer)
        ->get(route('audit.lawyer-assignments'))
        ->assertForbidden();
});

it('keeps restricted lawyers read only on the assessment page and write endpoints', function () {
    Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $lawyer = User::factory()->create([
        'name' => RestrictedLawyerAuditAccess::ALAA_KATOU,
    ]);
    $lawyer->assignRole('Legal Auditor');

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_lawyer',
        'label_en' => 'Accepted By Lawyer',
        'label_ar' => 'Accepted By Lawyer',
        'stage' => 'lawyer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 99001,
        'globalid' => 'readonly-lawyer-building',
        'building_name' => 'Read Only Lawyer Building',
    ]);

    AssignedAssessmentUser::query()->create([
        'building_id' => $building->objectid,
        'user_id' => $lawyer->id,
        'type' => 'Legal Auditor',
    ]);

    $this->actingAs($lawyer)
        ->get("damage-assessment/showAssessmentAudit/{$building->globalid}")
        ->assertOk()
        ->assertDontSee('btn_building_legal_challenge', false)
        ->assertDontSee('data-status="accepted" data-audit-type="Legal Auditor"', false);

    $this->actingAs($lawyer)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'accepted',
            'audit_type' => 'Legal Auditor',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('building_statuses', [
        'building_id' => $building->objectid,
        'status_id' => $status->id,
    ]);
});
