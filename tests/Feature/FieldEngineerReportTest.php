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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('renders the field engineer report and serves all tab endpoints', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    Assessment::query()->create([
        'name' => 'neighborhood',
        'label' => 'Neighborhood Label',
        'hint' => 'Neighborhood',
    ]);

    $engineerAccepted = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted',
        'label_ar' => 'مقبول',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $legalAccepted = AssessmentStatus::query()->create([
        'name' => 'accepted_by_legal',
        'label_en' => 'Legal Accepted',
        'label_ar' => 'مقبول قانونيًا',
        'stage' => 'lawyer',
        'order_step' => 2,
    ]);

    $finalNeedReview = AssessmentStatus::query()->create([
        'name' => 'need_review',
        'label_en' => 'Need Review',
        'label_ar' => 'بحاجة لمراجعة',
        'stage' => 'team_leader',
        'order_step' => 3,
    ]);

    $engineerRejected = AssessmentStatus::query()->create([
        'name' => 'rejected_by_engineer',
        'label_en' => 'Rejected',
        'label_ar' => 'Rejected',
        'stage' => 'engineer',
        'order_step' => 4,
    ]);

    $latestBuildingStatus = AssessmentStatus::query()->create([
        'name' => 'field_reviewed',
        'label_en' => 'Field Reviewed',
        'label_ar' => 'Field Reviewed',
        'stage' => 'engineer',
        'order_step' => 5,
    ]);

    $building = Building::query()->create([
        'objectid' => 5001,
        'globalid' => 'building-field-engineer-1',
        'assignedto' => 'Engineer One',
        'building_name' => 'Original Building',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Old Neighborhood',
        'parcel_no1' => 77,
        'building_use' => 'residential',
        'building_damage_status' => 'partially_damaged',
        'field_status' => 'COMPLETED',
        'end' => '2026-04-20 08:45:00',
        'creationdate' => '2026-04-20 08:00:00',
        'editdate' => '2026-04-22 09:30:00',
    ]);

    $otherBuilding = Building::query()->create([
        'objectid' => 5002,
        'globalid' => 'building-field-engineer-2',
        'assignedto' => 'Engineer Two',
        'building_name' => 'Other Building',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Other Neighborhood',
        'building_damage_status' => 'fully_damaged',
        'field_status' => 'NOT_COMPLETED',
        'creationdate' => '2026-04-21 08:00:00',
        'editdate' => '2026-04-22 10:30:00',
    ]);

    Building::query()->create([
        'objectid' => 5003,
        'globalid' => 'building-field-engineer-3',
        'assignedto' => 'Engineer One',
        'building_name' => 'Saved Outside Range Building',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Old Neighborhood',
        'building_damage_status' => 'partially_damaged',
        'field_status' => 'COMPLETED',
        'end' => '2026-04-20 08:45:00',
        'creationdate' => '2026-04-20 08:00:00',
        'editdate' => '2026-05-02 09:30:00',
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 9001,
        'globalid' => 'housing-field-engineer-1',
        'parentglobalid' => $building->globalid,
        'housing_unit_type' => 'residential',
        'unit_damage_status' => 'minor_damage',
        'occupied' => 'occupied',
        'building_submit_date' => '2026-04-21 12:15:00',
        'creationdate' => '2026-04-21 11:00:00',
        'editdate' => '2026-04-23 13:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 9002,
        'globalid' => 'housing-field-engineer-2',
        'parentglobalid' => $otherBuilding->globalid,
        'housing_unit_type' => 'commercial',
        'unit_damage_status' => 'major_damage',
        'occupied' => 'vacant',
        'creationdate' => '2026-04-21 11:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 9003,
        'globalid' => 'housing-field-engineer-3',
        'parentglobalid' => $building->globalid,
        'housing_unit_type' => 'residential',
        'unit_damage_status' => 'major_damage',
        'occupied' => 'occupied',
        'building_submit_date' => '2026-04-24 12:15:00',
        'creationdate' => '2026-04-24 11:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 9004,
        'globalid' => 'housing-field-engineer-4',
        'parentglobalid' => $building->globalid,
        'housing_unit_type' => 'residential',
        'unit_damage_status' => 'major_damage',
        'occupied' => 'occupied',
        'building_submit_date' => '2026-04-25 12:15:00',
        'creationdate' => '2026-04-25 11:00:00',
    ]);

    EditAssessment::query()->create([
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'neighborhood',
        'field_value' => 'Mid Neighborhood',
        'user_id' => $user->id,
        'updated_at' => '2026-04-22 10:00:00',
        'created_at' => '2026-04-22 10:00:00',
    ]);

    EditAssessment::query()->create([
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'neighborhood',
        'field_value' => 'New Neighborhood',
        'user_id' => $user->id,
        'updated_at' => '2026-04-22 11:00:00',
        'created_at' => '2026-04-22 11:00:00',
    ]);

    EditAssessment::query()->create([
        'global_id' => $housingUnit->globalid,
        'type' => 'housing_table',
        'field_name' => 'housing_unit_type',
        'field_value' => 'temporary_shelter',
        'user_id' => $user->id,
        'updated_at' => '2026-04-22 12:00:00',
        'created_at' => '2026-04-22 12:00:00',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $engineerAccepted->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'Engineer accepted',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $legalAccepted->id,
        'user_id' => $user->id,
        'type' => 'Legal Auditor',
        'notes' => 'Legal accepted',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $finalNeedReview->id,
        'user_id' => $user->id,
        'type' => 'Team Leader',
        'notes' => 'Needs review',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $latestBuildingStatus->id,
        'user_id' => $user->id,
        'type' => 'Field Review',
        'notes' => 'Latest building status',
    ]);

    BuildingStatusHistory::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $engineerAccepted->id,
        'type' => 'QC/QA Engineer',
        'user_id' => $user->id,
        'notes' => 'Building history',
        'created_at' => '2026-04-22 08:00:00',
        'updated_at' => '2026-04-22 08:00:00',
    ]);

    HousingStatus::query()->create([
        'housing_id' => $housingUnit->objectid,
        'status_id' => $engineerAccepted->id,
        'type' => 'QC/QA Engineer',
        'user_id' => $user->id,
        'notes' => 'Accepted housing unit',
        'created_at' => '2026-04-22 09:00:00',
        'updated_at' => '2026-04-22 09:00:00',
    ]);

    HousingStatus::query()->create([
        'housing_id' => 9003,
        'status_id' => $engineerRejected->id,
        'type' => 'QC/QA Engineer',
        'user_id' => $user->id,
        'notes' => 'Rejected housing unit',
        'created_at' => '2026-04-24 09:00:00',
        'updated_at' => '2026-04-24 09:00:00',
    ]);

    HousingStatus::query()->create([
        'housing_id' => 9004,
        'status_id' => $finalNeedReview->id,
        'type' => 'QC/QA Engineer',
        'user_id' => $user->id,
        'notes' => 'Needs review housing unit',
        'created_at' => '2026-04-25 09:00:00',
        'updated_at' => '2026-04-25 09:00:00',
    ]);

    HousingStatusHistory::query()->create([
        'housing_id' => $housingUnit->objectid,
        'status_id' => $finalNeedReview->id,
        'type' => 'Team Leader',
        'user_id' => $user->id,
        'notes' => 'Housing history',
        'created_at' => '2026-04-22 09:00:00',
        'updated_at' => '2026-04-22 09:00:00',
    ]);

    AssignedAssessmentUser::query()->create([
        'manager_id' => $user->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
        'building_id' => $building->id,
        'created_at' => '2026-04-22 07:30:00',
        'updated_at' => '2026-04-22 07:30:00',
    ]);

    $query = [
        'assignedto' => 'Engineer One',
        'building_objectid' => '5001',
        'municipalitie' => 'Gaza',
        'from_date' => '2026-04-01',
        'to_date' => '2026-04-30',
        'tab' => 'buildings',
    ];

    $this->actingAs($user)
        ->get(route('reports.field-engineer.index', $query))
        ->assertOk()
        ->assertSee(__('multilingual.field_engineer_report.title'), false)
        ->assertSee('Engineer One', false)
        ->assertSee('name="building_objectid"', false)
        ->assertSee('value="5001"', false)
        ->assertSee('name="from_date"', false)
        ->assertSee('name="saved_from_date"', false)
        ->assertSee(__('multilingual.field_engineer_report.filters.approval_date'), false)
        ->assertSee(__('multilingual.field_engineer_report.filters.saved_date'), false)
        ->assertDontSee('name="from_date "', false)
        ->assertSee(__('multilingual.field_engineer_report.tabs.buildings'), false)
        ->assertSee(__('multilingual.field_engineer_report.stats.total_buildings'), false);

    $independentFilterQuery = [
        'municipalitie' => 'Gaza',
        'from_date' => '2026-04-20',
        'to_date' => '2026-04-30',
    ];

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.buildings', array_merge($independentFilterQuery, [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])))
        ->assertOk()
        ->assertJsonFragment([
            'objectid' => 5001,
        ])
        ->assertJsonMissing([
            'objectid' => 5002,
        ]);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.buildings', [
            'municipalitie' => 'Gaza',
            'saved_from_date' => '2026-04-22',
            'saved_to_date' => '2026-04-22',
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
        ->assertOk()
        ->assertJsonFragment([
            'objectid' => 5001,
        ])
        ->assertJsonMissing([
            'objectid' => 5002,
        ])
        ->assertJsonMissing([
            'objectid' => 5003,
        ]);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.buildings', array_merge($query, [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])))
        ->assertOk()
        ->assertJsonFragment([
            'objectid' => 5001,
            'assignedto' => 'Engineer One',
            'building_name' => 'Original Building',
            'neighborhood' => 'New Neighborhood',
            'upload_date' => '2026-04-20 08:45 AM',
        ])
        ->assertSee('Accepted', false)
        ->assertDontSee('Ù…Ù‚Ø¨ÙˆÙ„', false)
        ->assertDontSee('Field Reviewed', false)
        ->assertJsonMissing([
            'objectid' => 5002,
        ]);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.housing-units', array_merge($query, [
            'from_date' => '2026-04-21',
            'to_date' => '2026-04-21',
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])))
        ->assertOk()
        ->assertJsonFragment([
            'objectid' => 9001,
            'building_name' => 'Original Building',
            'housing_unit_type' => 'temporary_shelter',
            'upload_date' => '2026-04-21 12:15 PM',
        ])
        ->assertJsonMissing([
            'objectid' => 9002,
        ]);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.stats', $query))
        ->assertOk()
        ->assertJsonPath('summary.audited_housing_units', 3)
        ->assertJsonPath('summary.accepted_statuses', 1)
        ->assertJsonPath('summary.rejected_statuses', 1)
        ->assertJsonPath('summary.need_review_statuses', 1);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.stats', array_merge($query, [
            'from_date' => '2026-04-22',
            'to_date' => '2026-04-22',
        ])))
        ->assertOk()
        ->assertJsonPath('summary.building_edits', 2)
        ->assertJsonPath('summary.housing_edits', 0);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.stats', array_merge($query, [
            'from_date' => '2026-04-23',
            'to_date' => '2026-04-23',
        ])))
        ->assertOk()
        ->assertJsonPath('summary.building_edits', 0)
        ->assertJsonPath('summary.housing_edits', 1);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.edits', array_merge($query, [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])))
        ->assertOk()
        ->assertJsonFragment([
            'global_id' => $building->globalid,
            'old_value' => 'Mid Neighborhood',
            'new_value' => 'New Neighborhood',
        ]);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.status-history', array_merge($query, [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])))
        ->assertOk()
        ->assertJsonFragment([
            'item_type' => __('multilingual.field_engineer_report.types.building'),
            'item_number' => 5001,
        ])
        ->assertJsonFragment([
            'item_type' => __('multilingual.field_engineer_report.types.housing'),
            'item_number' => 9001,
        ]);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.assignments', array_merge($query, [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])))
        ->assertOk()
        ->assertJsonFragment([
            'building_id' => $building->id,
        ]);

    $this->actingAs($user)
        ->get(route('reports.field-engineer.export', array_merge($query, [
            'tab' => 'buildings',
            'format' => 'csv',
        ])))
        ->assertOk();
});

it('counts engineer housing audit statuses from latest history when current status is missing', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $acceptedStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $rejectedStatus = AssessmentStatus::query()->create([
        'name' => 'rejected_by_engineer',
        'label_en' => 'Rejected By Engineer',
        'label_ar' => 'Rejected By Engineer',
        'stage' => 'engineer',
        'order_step' => 2,
    ]);

    $needReviewStatus = AssessmentStatus::query()->create([
        'name' => 'need_review',
        'label_en' => 'Need Review',
        'label_ar' => 'Need Review',
        'stage' => 'engineer',
        'order_step' => 3,
    ]);

    $building = Building::query()->create([
        'objectid' => 5101,
        'globalid' => 'history-only-status-building',
        'assignedto' => 'History Engineer',
        'building_name' => 'History Status Building',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'building_damage_status' => 'partially_damaged',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-21 08:00:00',
    ]);

    $acceptedHousing = HousingUnit::query()->create([
        'objectid' => 9101,
        'globalid' => 'history-only-accepted-housing',
        'parentglobalid' => $building->globalid,
        'unit_damage_status' => 'minor_damage',
        'building_submit_date' => '2026-04-21 12:15:00',
        'creationdate' => '2026-04-21 11:00:00',
    ]);

    $rejectedHousing = HousingUnit::query()->create([
        'objectid' => 9102,
        'globalid' => 'history-only-rejected-housing',
        'parentglobalid' => $building->globalid,
        'unit_damage_status' => 'major_damage',
        'building_submit_date' => '2026-04-21 12:20:00',
        'creationdate' => '2026-04-21 11:10:00',
    ]);

    $needReviewHousing = HousingUnit::query()->create([
        'objectid' => 9103,
        'globalid' => 'history-only-review-housing',
        'parentglobalid' => $building->globalid,
        'unit_damage_status' => 'major_damage',
        'building_submit_date' => '2026-04-21 12:25:00',
        'creationdate' => '2026-04-21 11:20:00',
    ]);

    HousingStatusHistory::query()->create([
        'housing_id' => $acceptedHousing->objectid,
        'status_id' => $acceptedStatus->id,
        'type' => 'QC/QA Engineer',
        'user_id' => $user->id,
        'created_at' => '2026-04-22 09:00:00',
        'updated_at' => '2026-04-22 09:00:00',
    ]);

    HousingStatusHistory::query()->create([
        'housing_id' => $rejectedHousing->objectid,
        'status_id' => $rejectedStatus->id,
        'type' => 'QC/QA Engineer',
        'user_id' => $user->id,
        'created_at' => '2026-04-22 09:05:00',
        'updated_at' => '2026-04-22 09:05:00',
    ]);

    HousingStatusHistory::query()->create([
        'housing_id' => $needReviewHousing->objectid,
        'status_id' => $needReviewStatus->id,
        'type' => 'QC/QA Engineer',
        'user_id' => $user->id,
        'created_at' => '2026-04-22 09:10:00',
        'updated_at' => '2026-04-22 09:10:00',
    ]);

    $this->actingAs($user)
        ->getJson(route('reports.field-engineer.stats', [
            'assignedto' => 'History Engineer',
        ]))
        ->assertOk()
        ->assertJsonPath('summary.audited_housing_units', 3)
        ->assertJsonPath('summary.accepted_statuses', 1)
        ->assertJsonPath('summary.rejected_statuses', 1)
        ->assertJsonPath('summary.need_review_statuses', 1);
});
