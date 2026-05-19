<?php

use App\Models\Assessment;
use App\Models\AssessmentStatus;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\BuildingStatusHistory;
use App\Models\EditAssessment;
use App\Models\HousingUnit;
use App\Models\User;
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
        return $pdf->viewName === 'modules.damage-assessment.pdf.assessment'
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
        ->get("showAssessmentAudit/{$building->globalid}")
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

it('allows database officers to set undp final approval only after final approval', function () {
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
        ])
        ->assertForbidden();

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
