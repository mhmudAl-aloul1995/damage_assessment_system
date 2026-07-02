<?php

use App\Models\Assessment;
use App\Models\AssessmentStatus;
use App\Models\AssignedAssessmentUser;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\BuildingStatusHistory;
use App\Models\EditAssessment;
use App\Models\HousingStatus;
use App\Models\HousingStatusHistory;
use App\Models\HousingUnit;
use App\Models\User;
use App\Modules\DamageAssessment\Http\Controllers\Audit\auditController;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Role;

it('shows the building name and pdf export action on the assessment page', function () {
    $user = User::factory()->create();

    $building = Building::query()->create([
        'objectid' => 101,
        'globalid' => 'building-101',
        'building_name' => 'Tower A',
    ]);

    HousingUnit::query()->create([
        'objectid' => 201,
        'globalid' => 'housing-201',
        'parentglobalid' => $building->globalid,
        'q_9_3_1_first_name' => 'Ali',
        'q_9_3_4_last_name' => 'Saleh',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('assessment.show', $building->globalid));

    $response->assertOk();
    $response->assertSee('Tower A');
    $response->assertSee(route('assessment.pdf', $building->globalid), false);
    $response->assertSee('PDF');
});

it('exports the assessment page as a pdf with attachments', function () {
    Pdf::fake();

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-token',
        ], 200),
        '*' => function (Request $request) {
            $url = $request->url();

            if (str_contains($url, '/FeatureServer/0/101/attachments')) {
                return Http::response([
                    'attachmentInfos' => [
                        [
                            'id' => 91,
                            'name' => 'building-photo.jpg',
                            'contentType' => 'image/jpeg',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/FeatureServer/1/201/attachments')) {
                return Http::response([
                    'attachmentInfos' => [
                        [
                            'id' => 77,
                            'name' => 'housing-photo.jpg',
                            'contentType' => 'image/jpeg',
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'attachmentInfos' => [],
            ], 200);
        },
    ]);

    $user = User::factory()->create();

    Assessment::query()->create([
        'name' => 'owner_name',
        'label' => 'Owner Name',
        'hint' => 'Owner full name',
    ]);

    Assessment::query()->create([
        'name' => 'q_9_3_1_first_name',
        'label' => 'First Name',
        'hint' => 'Housing owner first name',
    ]);

    $building = Building::query()->create([
        'objectid' => 101,
        'globalid' => 'building-101',
        'building_name' => 'Tower A',
        'owner_name' => 'Original Owner',
    ]);

    HousingUnit::query()->create([
        'objectid' => 201,
        'globalid' => 'housing-201',
        'parentglobalid' => $building->globalid,
        'q_9_3_1_first_name' => 'Ali',
        'q_9_3_4_last_name' => 'Saleh',
    ]);

    EditAssessment::query()->create([
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'owner_name',
        'field_value' => 'Edited Owner',
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('assessment.pdf', $building->globalid));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        return $pdf->viewName === 'damage-assessment::pdf.assessment'
            && $pdf->contains('Tower A')
            && $pdf->contains('Edited Owner')
            && $pdf->contains('building-photo.jpg')
            && $pdf->contains('housing-photo.jpg')
            && $pdf->contains('fake-token');
    });
});

it('returns inline edit metadata and field history when saving an audit edit', function () {
    $user = User::factory()->create([
        'name' => 'Audit Editor',
    ]);

    $building = Building::query()->create([
        'objectid' => 501,
        'globalid' => 'building-inline-history',
        'building_name' => 'Original Building',
    ]);

    EditAssessment::query()->create([
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'building_name',
        'field_value' => 'Previous Building',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('assessment.inline.update'), [
            'type' => 'building_table',
            'globalid' => $building->globalid,
            'field' => 'building_name',
            'value' => 'Updated Building',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('field_value', 'Updated Building')
        ->assertJsonPath('user_name', 'Audit Editor')
        ->assertJsonCount(1, 'history')
        ->assertJsonFragment([
            'value' => 'Updated Building',
            'user_name' => 'Audit Editor',
        ]);

    $this->assertDatabaseHas('assessment_edit_histories', [
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'building_name',
        'old_value' => 'Previous Building',
        'new_value' => 'Updated Building',
        'edited_by' => $user->id,
    ]);
});

it('keeps previous housing unit inline edits in edit assessments when saving a new edit', function () {
    $user = User::factory()->create();

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 601,
        'globalid' => 'housing-inline-history',
        'unit_owner' => 'Original Owner',
    ]);

    $this->actingAs($user)
        ->postJson(route('assessment.inline.update'), [
            'type' => 'housing_table',
            'globalid' => $housingUnit->globalid,
            'field' => 'unit_owner',
            'value' => 'First Edited Owner',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->actingAs($user)
        ->postJson(route('assessment.inline.update'), [
            'type' => 'housing_table',
            'globalid' => $housingUnit->globalid,
            'field' => 'unit_owner',
            'value' => 'Second Edited Owner',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    expect(EditAssessment::query()
        ->where('global_id', $housingUnit->globalid)
        ->where('type', 'housing_table')
        ->where('field_name', 'unit_owner')
        ->count())->toBe(2);

    expect(EditAssessment::query()
        ->where('global_id', $housingUnit->globalid)
        ->where('type', 'housing_table')
        ->where('field_name', 'unit_owner')
        ->latest('id')
        ->value('field_value'))->toBe('Second Edited Owner');
});

it('shows only total damage housing fields in the sidebar summary', function () {
    $user = User::factory()->create();

    $housing = HousingUnit::query()->create([
        'objectid' => 3201,
        'globalid' => 'housing-total-summary',
        'unit_damage_status' => 'totally',
        'unit_owner' => 'Total Owner',
        'damaged_area_m2' => '140',
        'external_finishing_of_the_unit' => 'External Finish',
        'internal_finishing_of_the_unit' => 'Internal Finish',
        'floor_number' => '8',
        'housing_unit_number' => '9',
        'reh_kitchen' => 'yes',
        'reh_bathroom' => 'yes',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('housing.summary', ['globalid' => $housing->globalid]))
        ->assertOk()
        ->assertJsonPath('summary_mode', 'totally')
        ->assertJsonCount(6, 'summary_items');

    expect(collect($response->json('summary_items'))->pluck('field')->all())->toBe([
        'unit_owner',
        'damaged_area_m2',
        'external_finishing_of_the_unit',
        'internal_finishing_of_the_unit',
        'floor_number',
        'housing_unit_number',
    ]);
});

it('keeps the existing partial damage housing fields in the sidebar summary', function () {
    $user = User::factory()->create();

    $housing = HousingUnit::query()->create([
        'objectid' => 3202,
        'globalid' => 'housing-partial-summary',
        'unit_damage_status' => 'partially',
        'unit_owner' => 'Partial Owner',
        'damaged_area_m2' => '70',
        'reh_kitchen' => 'yes',
        'reh_bathroom' => 'no',
        'is_the_housing_unit_or_living_habitable' => 'yes',
        'external_finishing_of_the_unit' => 'External Partial',
        'internal_finishing_of_the_unit' => 'Internal Partial',
        'floor_number' => '4',
        'housing_unit_number' => '12',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('housing.summary', ['globalid' => $housing->globalid]))
        ->assertOk()
        ->assertJsonPath('summary_mode', 'partial')
        ->assertJsonCount(7, 'summary_items');

    expect(collect($response->json('summary_items'))->pluck('field')->all())->toBe([
        'unit_owner',
        'damaged_area_m2',
        'reh_kitchen',
        'reh_bathroom',
        'is_the_housing_unit_or_living_habitable',
        'external_finishing_of_the_unit',
        'internal_finishing_of_the_unit',
    ]);
});

it('allows auditing supervisors to final approve from the assessment audit page', function () {
    $role = Role::query()->create([
        'name' => 'Auditing Supervisor',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $engineerStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $lawyerStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_lawyer',
        'label_en' => 'Accepted By Lawyer',
        'label_ar' => 'Accepted By Lawyer',
        'stage' => 'lawyer',
        'order_step' => 2,
    ]);

    $finalStatus = AssessmentStatus::query()->create([
        'name' => 'final_approval',
        'label_en' => 'Final Approval',
        'label_ar' => 'Final Approval',
        'stage' => 'system',
        'order_step' => 3,
    ]);

    $building = Building::query()->create([
        'objectid' => 901,
        'globalid' => 'audit-final-building',
        'building_name' => 'Final Building',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $engineerStatus->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $lawyerStatus->id,
        'user_id' => $user->id,
        'type' => 'Legal Auditor',
    ]);

    $this->actingAs($user)
        ->get("damage-assessment/showAssessmentAudit/{$building->globalid}")
        ->assertOk()
        ->assertSee('btn_show_assessment_final_approve');

    $this->actingAs($user)
        ->postJson(route('audit.building.finalApprove'), [
            'building_ids' => [$building->objectid],
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->assertDatabaseHas('building_statuses', [
        'building_id' => $building->objectid,
        'status_id' => $finalStatus->id,
        'type' => 'final',
    ]);
});

it('requires building final approval before undp final approval status', function () {
    $role = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $finalStatus = AssessmentStatus::query()->create([
        'name' => 'final_approval',
        'label_en' => 'Final Approval',
        'label_ar' => 'Final Approval',
        'stage' => 'team_leader',
        'order_step' => 9,
    ]);

    $undpStatus = AssessmentStatus::query()->where('name', 'undp_final_approve')->firstOrFail();

    $building = Building::query()->create([
        'objectid' => 9301,
        'globalid' => 'undp-building',
        'building_name' => 'UNDP Building',
    ]);

    AssignedAssessmentUser::query()->create([
        'building_id' => $building->objectid,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'undp_final_approve',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'لا يمكن اعتماد UNDP Final Approve قبل الاعتماد النهائي للمبنى.');

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $finalStatus->id,
        'user_id' => $user->id,
        'type' => 'final',
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'undp_final_approve',
        ])
        ->assertOk()
        ->assertJsonPath('data.status_name', 'undp_final_approve');

    $this->assertDatabaseHas('building_status_histories', [
        'building_id' => $building->objectid,
        'status_id' => $undpStatus->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'undp_final_approve',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'لا يمكن تكرار نفس الحالة الحالية.');
});

it('checks parent building final approval before housing undp final approval status', function () {
    $role = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $finalStatus = AssessmentStatus::query()->create([
        'name' => 'final_approval',
        'label_en' => 'Final Approval',
        'label_ar' => 'Final Approval',
        'stage' => 'team_leader',
        'order_step' => 9,
    ]);

    $undpStatus = AssessmentStatus::query()->where('name', 'undp_final_approve')->firstOrFail();

    $building = Building::query()->create([
        'objectid' => 9401,
        'globalid' => 'undp-parent-building',
        'building_name' => 'UNDP Parent Building',
    ]);

    AssignedAssessmentUser::query()->create([
        'building_id' => $building->objectid,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    $housing = HousingUnit::query()->create([
        'objectid' => 9402,
        'globalid' => 'undp-housing',
        'parentglobalid' => $building->globalid,
        'unit_owner' => 'Owner',
    ]);

    $this->actingAs($user)
        ->postJson(route('housing.assessment.set.status'), [
            'globalid' => $housing->globalid,
            'status' => 'undp_final_approve',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'لا يمكن اعتماد UNDP Final Approve قبل الاعتماد النهائي للمبنى.');

    BuildingStatusHistory::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $finalStatus->id,
        'user_id' => $user->id,
        'type' => 'final',
    ]);

    $this->actingAs($user)
        ->postJson(route('housing.assessment.set.status'), [
            'globalid' => $housing->globalid,
            'status' => 'undp_final_approve',
        ])
        ->assertOk()
        ->assertJsonPath('data.status_name', 'undp_final_approve');

    $this->assertDatabaseHas('housing_statuses', [
        'housing_id' => $housing->objectid,
        'status_id' => $undpStatus->id,
        'type' => 'QC/QA Engineer',
    ]);

    $this->actingAs($user)
        ->postJson(route('housing.assessment.set.status'), [
            'globalid' => $housing->globalid,
            'status' => 'undp_final_approve',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'لا يمكن تكرار نفس الحالة الحالية.');
});

it('allows database officers to set audit statuses and undp final approval', function () {
    $role = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $finalStatus = AssessmentStatus::query()->create([
        'name' => 'final_approval',
        'label_en' => 'Final Approval',
        'label_ar' => 'Final Approval',
        'stage' => 'team_leader',
        'order_step' => 9,
    ]);

    $undpStatus = AssessmentStatus::query()->where('name', 'undp_final_approve')->firstOrFail();
    $lawyerAcceptedStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_lawyer',
        'label_en' => 'Accepted By Lawyer',
        'label_ar' => 'Accepted By Lawyer',
        'stage' => 'lawyer',
        'order_step' => 10,
    ]);

    $building = Building::query()->create([
        'objectid' => 9501,
        'globalid' => 'database-officer-undp-building',
        'building_name' => 'Database Officer UNDP Building',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $finalStatus->id,
        'user_id' => $user->id,
        'type' => 'final',
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'accepted',
            'audit_type' => 'Legal Auditor',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'Legal Auditor')
        ->assertJsonPath('data.status_name', 'accepted_by_lawyer');

    $this->assertDatabaseHas('building_statuses', [
        'building_id' => $building->objectid,
        'status_id' => $lawyerAcceptedStatus->id,
        'type' => 'Legal Auditor',
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'undp_final_approve',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'Database Officer')
        ->assertJsonPath('data.status_name', 'undp_final_approve');

    $this->assertDatabaseHas('building_statuses', [
        'building_id' => $building->objectid,
        'status_id' => $undpStatus->id,
        'type' => 'Database Officer',
    ]);

    $bulkBuilding = Building::query()->create([
        'objectid' => 9502,
        'globalid' => 'database-officer-undp-bulk-building',
        'building_name' => 'Database Officer UNDP Bulk Building',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $bulkBuilding->objectid,
        'status_id' => $finalStatus->id,
        'user_id' => $user->id,
        'type' => 'final',
    ]);

    $this->actingAs($user)
        ->postJson(route('audit.building.undpFinalApprove'), [
            'building_ids' => [$bulkBuilding->objectid],
        ])
        ->assertOk()
        ->assertJsonPath('approved_count', 1);

    $this->assertDatabaseHas('building_statuses', [
        'building_id' => $bulkBuilding->objectid,
        'status_id' => $undpStatus->id,
        'type' => 'Database Officer',
    ]);
});

it('allows database officers to set housing audit statuses for legal and engineering tracks', function () {
    $role = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $legalNotesStatus = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'Legal Notes',
        'stage' => 'lawyer',
        'order_step' => 10,
    ]);

    $needReviewStatus = AssessmentStatus::query()->create([
        'name' => 'need_review',
        'label_en' => 'Need Review',
        'label_ar' => 'Need Review',
        'stage' => 'engineer',
        'order_step' => 11,
    ]);

    $building = Building::query()->create([
        'objectid' => 9511,
        'globalid' => 'database-officer-all-buttons-building',
        'building_name' => 'Database Officer All Buttons Building',
    ]);

    $housing = HousingUnit::query()->create([
        'objectid' => 9512,
        'globalid' => 'database-officer-all-buttons-housing',
        'parentglobalid' => $building->globalid,
        'unit_owner' => 'Owner',
    ]);

    $this->actingAs($user)
        ->postJson(route('housing.assessment.set.status'), [
            'globalid' => $housing->globalid,
            'status' => 'legal_notes',
            'audit_type' => 'Legal Auditor',
            'notes' => 'Legal note',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'Legal Auditor')
        ->assertJsonPath('data.status_name', 'legal_notes');

    $this->actingAs($user)
        ->postJson(route('housing.assessment.set.status'), [
            'globalid' => $housing->globalid,
            'status' => 'need_review',
            'audit_type' => 'QC/QA Engineer',
            'notes' => 'Engineering note',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'QC/QA Engineer')
        ->assertJsonPath('data.status_name', 'need_review');

    $this->assertDatabaseHas('housing_statuses', [
        'housing_id' => $housing->objectid,
        'status_id' => $legalNotesStatus->id,
        'type' => 'Legal Auditor',
    ]);

    $this->assertDatabaseHas('housing_statuses', [
        'housing_id' => $housing->objectid,
        'status_id' => $needReviewStatus->id,
        'type' => 'QC/QA Engineer',
    ]);
});

it('prevents unassigned auditors from setting building and housing statuses', function () {
    $role = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 9521,
        'globalid' => 'unassigned-status-building',
        'building_name' => 'Unassigned Status Building',
    ]);

    $housing = HousingUnit::query()->create([
        'objectid' => 9522,
        'globalid' => 'unassigned-status-housing',
        'parentglobalid' => $building->globalid,
        'unit_owner' => 'Owner',
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'accepted',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('housing.assessment.set.status'), [
            'globalid' => $housing->globalid,
            'status' => 'accepted',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('building_statuses', [
        'building_id' => $building->objectid,
    ]);

    $this->assertDatabaseMissing('housing_statuses', [
        'housing_id' => $housing->objectid,
    ]);
});

it('allows assigned auditors to set building and housing statuses', function () {
    $role = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 9531,
        'globalid' => 'assigned-status-building',
        'building_name' => 'Assigned Status Building',
    ]);

    $housing = HousingUnit::query()->create([
        'objectid' => 9532,
        'globalid' => 'assigned-status-housing',
        'parentglobalid' => $building->globalid,
        'unit_owner' => 'Owner',
    ]);

    AssignedAssessmentUser::query()->create([
        'building_id' => $building->objectid,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'accepted',
        ])
        ->assertOk()
        ->assertJsonPath('data.status_name', 'accepted_by_engineer');

    $this->actingAs($user)
        ->postJson(route('housing.assessment.set.status'), [
            'globalid' => $housing->globalid,
            'status' => 'accepted',
        ])
        ->assertOk()
        ->assertJsonPath('data.status_name', 'accepted_by_engineer');

    $this->assertDatabaseHas('building_statuses', [
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'type' => 'QC/QA Engineer',
    ]);

    $this->assertDatabaseHas('housing_statuses', [
        'housing_id' => $housing->objectid,
        'status_id' => $status->id,
        'type' => 'QC/QA Engineer',
    ]);
});

it('allows temporarily excepted auditors to set statuses without assignment', function () {
    $role = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);

    $temporaryAllowedUserNames = (new \ReflectionClass(auditController::class))
        ->getReflectionConstant('TEMPORARY_HIDDEN_AUDIT_ACTION_USER_NAMES')
        ->getValue();

    $user = User::factory()->create([
        'name' => $temporaryAllowedUserNames[0],
    ]);
    $user->assignRole($role);

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 9536,
        'globalid' => 'temporary-status-exception-building',
        'building_name' => 'Temporary Status Exception Building',
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'accepted',
        ])
        ->assertOk()
        ->assertJsonPath('data.status_name', 'accepted_by_engineer');

    $this->assertDatabaseHas('building_statuses', [
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'type' => 'QC/QA Engineer',
    ]);
});

it('allows auditing supervisors to set audit statuses without assignment', function () {
    $role = Role::query()->create([
        'name' => 'Auditing Supervisor',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_lawyer',
        'label_en' => 'Accepted By Lawyer',
        'label_ar' => 'Accepted By Lawyer',
        'stage' => 'lawyer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 9541,
        'globalid' => 'supervisor-status-building',
        'building_name' => 'Supervisor Status Building',
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'accepted',
            'audit_type' => 'Legal Auditor',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'Legal Auditor')
        ->assertJsonPath('data.status_name', 'accepted_by_lawyer');

    $this->assertDatabaseHas('building_statuses', [
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'type' => 'Legal Auditor',
    ]);
});

it('prevents team leaders from setting audit statuses without assignment', function () {
    $role = Role::query()->create([
        'name' => 'Team Leader',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    AssessmentStatus::query()->create([
        'name' => 'need_review',
        'label_en' => 'Need Review',
        'label_ar' => 'Need Review',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 9546,
        'globalid' => 'team-leader-status-building',
        'building_name' => 'Team Leader Status Building',
    ]);

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'need_review',
            'audit_type' => 'QC/QA Engineer',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('building_status_histories', [
        'building_id' => $building->objectid,
    ]);
});

it('shows audit status controls as read only for team leaders', function () {
    $role = Role::query()->create([
        'name' => 'Team Leader',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 95461,
        'globalid' => 'team-leader-read-only-building',
        'building_name' => 'Team Leader Read Only Building',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    $this->actingAs($user)
        ->get("damage-assessment/showAssessmentAudit/{$building->globalid}")
        ->assertOk()
        ->assertSee('let isAssessmentReadOnly = true;', false)
        ->assertSee('data-status="accepted"', false)
        ->assertSee("setBuildingStatus('accepted', 'QC/QA Engineer')", false);
});

it('prevents team leaders from inline editing audit assessment answers', function () {
    $role = Role::query()->create([
        'name' => 'Team Leader',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $building = Building::query()->create([
        'objectid' => 95462,
        'globalid' => 'team-leader-inline-edit-building',
        'building_name' => 'Original Building',
    ]);

    $this->actingAs($user)
        ->postJson(route('assessment.inline.update'), [
            'type' => 'building_table',
            'globalid' => $building->globalid,
            'field' => 'building_name',
            'value' => 'Changed Building',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('edit_assessments', [
        'global_id' => $building->globalid,
        'field_name' => 'building_name',
        'field_value' => 'Changed Building',
    ]);
});

it('shows only the latest status markup for read only users without allowing field engineer status changes', function () {
    $role = Role::query()->create([
        'name' => 'Field Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'username_arcgis' => 'field.engineer',
    ]);
    $user->assignRole($role);

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 9547,
        'globalid' => 'field-engineer-status-buttons-building',
        'building_name' => 'Field Engineer Status Buttons Building',
        'assignedto' => 'field.engineer',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    $view = file_get_contents(base_path('app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php'));

    expect($view)
        ->toContain('@if($isStatusPreviewOnly)')
        ->toContain('آخر حالة')
        ->toContain('housing_engineering_status_preview')
        ->toContain('housing_legal_status_preview')
        ->toContain('@elseif($canViewStatusButtons)');

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'accepted',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('building_status_histories', [
        'building_id' => $building->objectid,
    ]);
});

it('returns the latest housing status for read only field engineers', function () {
    $role = Role::query()->create([
        'name' => 'Field Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'username_arcgis' => 'field.engineer',
    ]);
    $user->assignRole($role);

    $engineeringStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $legalStatus = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'Legal Notes',
        'stage' => 'lawyer',
        'order_step' => 2,
    ]);

    $building = Building::query()->create([
        'objectid' => 9548,
        'globalid' => 'field-engineer-housing-status-building',
        'building_name' => 'Field Engineer Housing Status Building',
        'assignedto' => 'field.engineer',
    ]);

    $housing = HousingUnit::query()->create([
        'objectid' => 9549,
        'globalid' => 'field-engineer-housing-status-unit',
        'parentglobalid' => $building->globalid,
        'unit_owner' => 'Owner',
    ]);

    HousingStatus::query()->create([
        'housing_id' => $housing->objectid,
        'status_id' => $engineeringStatus->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    HousingStatus::query()->create([
        'housing_id' => $housing->objectid,
        'status_id' => $legalStatus->id,
        'user_id' => $user->id,
        'type' => 'Legal Auditor',
    ]);

    $this->actingAs($user)
        ->getJson(route('housing.units.by.building', ['globalid' => $building->globalid]))
        ->assertOk()
        ->assertJsonPath('data.0.current_status', 'legal_notes')
        ->assertJsonPath('data.0.current_engineering_status', 'accepted_by_engineer')
        ->assertJsonPath('data.0.current_legal_status', 'legal_notes');
});

it('can undo a scheduled housing unit deletion before it is committed', function () {
    Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole('Database Officer');

    $building = Building::query()->create([
        'objectid' => 9650,
        'globalid' => 'undo-housing-delete-building',
    ]);

    $housing = HousingUnit::query()->create([
        'objectid' => 9651,
        'globalid' => 'undo-housing-delete-unit',
        'parentglobalid' => $building->globalid,
    ]);

    $schedule = $this->actingAs($user)
        ->postJson(route('housing.assessment.delete.schedule'), [
            'globalids' => [$housing->globalid],
            'mode' => 'database',
            'building_globalid' => $building->globalid,
        ])
        ->assertOk()
        ->json();

    $this->actingAs($user)
        ->postJson(route('housing.assessment.delete.undo'), [
            'token' => $schedule['token'],
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('housing.assessment.delete.commit'), [
            'token' => $schedule['token'],
        ])
        ->assertStatus(410);

    $this->assertDatabaseHas('housing_units', [
        'globalid' => $housing->globalid,
    ]);
});

it('commits scheduled housing unit database deletion and removes related audit records', function () {
    Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole('Database Officer');

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 9660,
        'globalid' => 'commit-housing-delete-building',
    ]);

    $housing = HousingUnit::query()->create([
        'objectid' => 9661,
        'globalid' => 'commit-housing-delete-unit',
        'parentglobalid' => $building->globalid,
    ]);

    HousingStatus::query()->create([
        'housing_id' => $housing->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    HousingStatusHistory::query()->create([
        'housing_id' => $housing->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    EditAssessment::query()->create([
        'global_id' => $housing->globalid,
        'type' => 'housing_table',
        'field_name' => 'housing_unit_number',
        'field_value' => '12',
        'user_id' => $user->id,
    ]);

    $schedule = $this->actingAs($user)
        ->postJson(route('housing.assessment.delete.schedule'), [
            'globalids' => [$housing->globalid],
            'mode' => 'database',
            'building_globalid' => $building->globalid,
        ])
        ->assertOk()
        ->json();

    $this->actingAs($user)
        ->postJson(route('housing.assessment.delete.commit'), [
            'token' => $schedule['token'],
        ])
        ->assertOk()
        ->assertJsonPath('deleted_from_database', 1);

    $this->assertDatabaseMissing('housing_units', [
        'globalid' => $housing->globalid,
    ]);

    $this->assertDatabaseMissing('housing_statuses', [
        'housing_id' => $housing->objectid,
    ]);

    $this->assertDatabaseMissing('housing_status_histories', [
        'housing_id' => $housing->objectid,
    ]);

    $this->assertDatabaseMissing('edit_assessments', [
        'global_id' => $housing->globalid,
        'type' => 'housing_table',
    ]);
});

it('returns structured status history payload for rendering badges safely', function () {
    $user = User::factory()->create();

    $status = AssessmentStatus::query()->create([
        'name' => 'rejected_by_engineer',
        'label_en' => 'Rejected By Engineer',
        'label_ar' => 'Rejected By Engineer',
        'stage' => 'engineer',
        'order_step' => 2,
    ]);

    $building = Building::query()->create([
        'objectid' => 9801,
        'globalid' => 'status-history-badge-building',
        'building_name' => 'Status Badge Building',
    ]);

    BuildingStatusHistory::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
        'notes' => '<strong>Needs review</strong>',
    ]);

    $this->actingAs($user)
        ->getJson(route('building.status.history', ['globalid' => $building->globalid]))
        ->assertOk()
        ->assertJsonPath('history.0.status_name', 'Rejected By Engineer')
        ->assertJsonPath('history.0.status_label', 'Rejected By Engineer')
        ->assertJsonPath('history.0.status_badge_class', 'badge badge-light-danger fw-bold')
        ->assertJsonPath('history.0.notes', '<strong>Needs review</strong>');
});

it('does not show the separate note edit action', function () {
    $moduleView = file_get_contents(base_path('app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php'));
    $legacyView = file_get_contents(resource_path('views/DamageAssessment/assessmentAudit.blade.php'));
    $routes = file_get_contents(base_path('app/Modules/DamageAssessment/routes/web.php'));
    $controller = file_get_contents(app_path('Modules/DamageAssessment/Http/Controllers/Audit/AuditStatusHistoryController.php'));

    expect($moduleView)
        ->not->toContain("openNotesModal('building','edit_note')")
        ->not->toContain("openNotesModal('housing','edit_note')")
        ->not->toContain('loadEditableNote(')
        ->not->toContain('updateExistingNote(')
        ->and($legacyView)
        ->not->toContain("openNotesModal('building','edit_note')")
        ->not->toContain("openNotesModal('housing','edit_note')")
        ->not->toContain('loadEditableNote(')
        ->not->toContain('updateExistingNote(')
        ->and($routes)
        ->not->toContain('assessment.notes.edit.data')
        ->and($controller)
        ->not->toContain('function getEditableNote');
});

it('shows all audit status button groups to database officers in the assessment audit view', function () {
    $view = file_get_contents(base_path('app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php'));

    expect($view)
        ->toContain('@if($isStatusPreviewOnly)')
        ->toContain('@elseif($canViewStatusButtons)')
        ->toContain('@disabled(! $canEditAssessment)')
        ->toContain("@hasanyrole('Legal Auditor|Database Officer|Auditing Supervisor|Team Leader|Field Engineer|field Engineer')")
        ->toContain("@hasanyrole('QC/QA Engineer|Database Officer|Auditing Supervisor|Team Leader|Field Engineer|field Engineer')")
        ->toContain("setBuildingStatus('accepted', 'Legal Auditor')")
        ->toContain("setBuildingStatus('accepted', 'QC/QA Engineer')")
        ->toContain("setHousingStatus('legal_notes', 'Legal Auditor')")
        ->toContain("setHousingStatus('need_review', 'QC/QA Engineer')");
});

it('keeps audit attachment rows visible when regular filters are applied', function () {
    $view = file_get_contents(base_path('app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php'));

    expect($view)
        ->toContain('function keepAttachmentRowsVisible(rows, filteredRows)')
        ->toContain("if (filter === 'missing') return keepAttachmentRowsVisible(rows, rows.filter(row => !isAnswered(row)))")
        ->toContain("if (filter === 'edited') return keepAttachmentRowsVisible(rows, rows.filter(row => isEdited(row)))")
        ->toContain("if (filter === 'answered') return keepAttachmentRowsVisible(rows, rows.filter(row => isAnswered(row)))")
        ->toContain("if (filter === 'attachments') {")
        ->toContain('return rows.filter(row => isAuditAttachmentRow(row));');
});

it('allows auditors to edit only their own matching note type', function () {
    $legalRole = Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $engineerRole = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);

    $legalAuditor = User::factory()->create();
    $legalAuditor->assignRole($legalRole);

    $engineer = User::factory()->create();
    $engineer->assignRole($engineerRole);

    $status = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'Legal Notes',
        'stage' => 'lawyer',
        'order_step' => 6,
    ]);

    $building = Building::query()->create([
        'objectid' => 9821,
        'globalid' => 'note-edit-forbidden-building',
        'building_name' => 'Note Edit Forbidden Building',
    ]);

    $history = BuildingStatusHistory::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'user_id' => $legalAuditor->id,
        'type' => 'Legal Auditor',
        'notes' => 'Original note',
    ]);

    $this->actingAs($legalAuditor)
        ->getJson(route('building.status.history', [
            'globalid' => $building->globalid,
        ]))
        ->assertOk()
        ->assertJsonPath('history.0.note_id', $history->id)
        ->assertJsonPath('history.0.can_edit', true);

    $this->actingAs($engineer)
        ->getJson(route('building.status.history', [
            'globalid' => $building->globalid,
        ]))
        ->assertOk()
        ->assertJsonPath('history.0.note_id', $history->id)
        ->assertJsonPath('history.0.can_edit', false);

    $this->actingAs($legalAuditor)
        ->postJson(route('assessment.notes.update'), [
            'id' => $history->id,
            'type' => 'building',
            'notes' => 'Changed note',
        ])
        ->assertOk();

    $this->assertDatabaseHas('building_status_histories', [
        'id' => $history->id,
        'notes' => 'Changed note',
    ]);

    $this->actingAs($engineer)
        ->postJson(route('assessment.notes.update'), [
            'id' => $history->id,
            'type' => 'building',
            'notes' => 'Engineer changed note',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('building_status_histories', [
        'id' => $history->id,
        'notes' => 'Changed note',
    ]);
});

it('allows database officers to edit any status note without changing the note owner', function () {
    $databaseOfficerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $legalRole = Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $databaseOfficer = User::factory()->create();
    $databaseOfficer->assignRole($databaseOfficerRole);

    $legalAuditor = User::factory()->create();
    $legalAuditor->assignRole($legalRole);

    $status = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'Legal Notes',
        'stage' => 'lawyer',
        'order_step' => 6,
    ]);

    $building = Building::query()->create([
        'objectid' => 9823,
        'globalid' => 'note-edit-database-officer-building',
        'building_name' => 'Database Officer Note Edit Building',
    ]);

    $history = BuildingStatusHistory::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'user_id' => $legalAuditor->id,
        'type' => 'Legal Auditor',
        'notes' => 'Original legal note',
    ]);

    $this->actingAs($databaseOfficer)
        ->getJson(route('building.status.history', [
            'globalid' => $building->globalid,
        ]))
        ->assertOk()
        ->assertJsonPath('history.0.note_id', $history->id)
        ->assertJsonPath('history.0.can_edit', true);

    $this->actingAs($databaseOfficer)
        ->postJson(route('assessment.notes.update'), [
            'id' => $history->id,
            'type' => 'building',
            'notes' => 'Database officer updated note',
        ])
        ->assertOk();

    $this->assertDatabaseHas('building_status_histories', [
        'id' => $history->id,
        'type' => 'Legal Auditor',
        'user_id' => $legalAuditor->id,
        'notes' => 'Database officer updated note',
    ]);
});

it('allows auditing supervisors to edit notes and records them as the latest editor', function () {
    $legalRole = Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $supervisorRole = Role::query()->create([
        'name' => 'Auditing Supervisor',
        'guard_name' => 'web',
    ]);

    $legalAuditor = User::factory()->create();
    $legalAuditor->assignRole($legalRole);

    $supervisor = User::factory()->create();
    $supervisor->assignRole($supervisorRole);

    $status = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'Legal Notes',
        'stage' => 'lawyer',
        'order_step' => 6,
    ]);

    $building = Building::query()->create([
        'objectid' => 9822,
        'globalid' => 'note-edit-supervisor-building',
        'building_name' => 'Supervisor Note Edit Building',
    ]);

    $history = BuildingStatusHistory::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'user_id' => $legalAuditor->id,
        'type' => 'Legal Auditor',
        'notes' => 'Original legal note',
    ]);

    $this->actingAs($supervisor)
        ->getJson(route('building.status.history', [
            'globalid' => $building->globalid,
        ]))
        ->assertOk()
        ->assertJsonPath('history.0.note_id', $history->id)
        ->assertJsonPath('history.0.can_edit', true);

    $this->actingAs($supervisor)
        ->postJson(route('assessment.notes.update'), [
            'id' => $history->id,
            'type' => 'building',
            'notes' => 'Supervisor updated note',
        ])
        ->assertOk()
        ->assertJsonPath('user_name', $supervisor->name);

    $this->assertDatabaseHas('building_status_histories', [
        'id' => $history->id,
        'type' => 'Legal Auditor',
        'user_id' => $supervisor->id,
        'notes' => 'Supervisor updated note',
    ]);
});
