<?php

use App\Models\Assessment;
use App\Models\AssessmentEditHistory;
use App\Models\AssessmentStatus;
use App\Models\AssignedAssessmentUser;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\BuildingStatusHistory;
use App\Models\EditAssessment;
use App\Models\Filter;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

it('includes the housing units status progress in the audit table response', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    Http::fake([
        '*' => Http::response(['token' => 'fake-token']),
    ]);

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

    $engineer = User::factory()->create([
        'name' => 'Wafaa Nafeth Aqeel Sror',
    ]);

    $lawyer = User::factory()->create([
        'name' => 'Rawan Mahdi Yousef Al Haj Yousef',
    ]);

    foreach (range(1, 3) as $statusIndex) {
        AssessmentStatus::query()->create([
            'name' => 'placeholder_status_'.$statusIndex,
            'label_en' => 'Placeholder '.$statusIndex,
            'label_ar' => 'placeholder ar '.$statusIndex,
            'stage' => 'system',
            'order_step' => $statusIndex,
        ]);
    }

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'accepted ar',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $assignedStatus = AssessmentStatus::query()->create([
        'name' => 'assigned_to_engineer',
        'label_en' => 'Assigned To Engineer',
        'label_ar' => 'assigned ar',
        'stage' => 'engineer',
        'order_step' => 2,
    ]);

    $building = Building::query()->create([
        'objectid' => 7001,
        'globalid' => '084c000a-c0bd-4eed-9a01-3dd491bc1eff',
        'building_name' => 'Audit Units Count Building',
        'municipalitie' => 'Gaza Municipality',
        'assignedto' => 'Engineer A',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-25 10:00:00',
    ]);

    AssignedAssessmentUser::query()->create([
        'manager_id' => $user->id,
        'user_id' => $engineer->id,
        'type' => 'QC/QA Engineer',
        'building_id' => $building->objectid,
    ]);

    AssignedAssessmentUser::query()->create([
        'manager_id' => $user->id,
        'user_id' => $lawyer->id,
        'type' => 'Legal Auditor',
        'building_id' => $building->objectid,
    ]);

    $housingWithStatus = HousingUnit::query()->create([
        'objectid' => 8001,
        'globalid' => 'audit-housing-unit-1',
        'parentglobalid' => $building->globalid,
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza Municipality',
        'neighborhood' => 'Rimal',
    ]);

    HousingUnit::query()->create([
        'objectid' => 8002,
        'globalid' => 'audit-housing-unit-2',
        'parentglobalid' => $building->globalid,
    ]);

    HousingStatus::query()->create([
        'housing_id' => $housingWithStatus->objectid,
        'status_id' => 4,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'Building status note',
        'notes' => 'Audited',
    ]);

    $currentStatus = BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);
    $currentStatus->forceFill([
        'created_at' => '2026-04-26 10:00:00',
        'updated_at' => '2026-04-26 10:00:00',
    ])->save();

    $olderStatusBuilding = Building::query()->create([
        'objectid' => 7002,
        'globalid' => '018e2bc7-efab-4b0a-a359-6f1378bb8bd9',
        'building_name' => 'Audit Old Status Building',
        'assignedto' => 'Engineer A',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-25 10:00:00',
    ]);

    $olderStatus = BuildingStatus::query()->create([
        'building_id' => $olderStatusBuilding->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);
    $olderStatus->forceFill([
        'created_at' => '2026-03-20 10:00:00',
        'updated_at' => '2026-03-20 10:00:00',
    ])->save();

    $assignedOnlyBuilding = Building::query()->create([
        'objectid' => 7003,
        'globalid' => '027c4ebf-6a38-4a8c-9948-043fcf315e5d',
        'building_name' => 'Audit Assigned Status Building',
        'assignedto' => 'Engineer A',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-25 10:00:00',
    ]);

    $assignedOnlyStatus = BuildingStatus::query()->create([
        'building_id' => $assignedOnlyBuilding->objectid,
        'status_id' => $assignedStatus->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);
    $assignedOnlyStatus->forceFill([
        'created_at' => '2026-04-27 10:00:00',
        'updated_at' => '2026-04-27 10:00:00',
    ])->save();

    $this->actingAs($user)
        ->getJson(route('audit.index', [
            'draw' => 1,
            'columns' => [
                [
                    'data' => 'objectid',
                    'name' => 'objectid',
                    'searchable' => 'false',
                    'orderable' => 'false',
                ],
                [
                    'data' => 'building_name',
                    'name' => 'building_name',
                    'searchable' => 'true',
                    'orderable' => 'true',
                ],
                [
                    'data' => 'housing_status_progress',
                    'name' => 'housing_status_progress',
                    'searchable' => 'false',
                    'orderable' => 'false',
                ],
            ],
            'order' => [
                [
                    'column' => 0,
                    'dir' => 'asc',
                ],
            ],
            'start' => 0,
            'length' => 10,
            'search' => [
                'value' => '',
                'regex' => 'false',
            ],
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonMissingPath('error')
        ->assertJsonFragment([
            'housing_status_progress' => '1 / 2',
            'housing_units_count' => 2,
            'housing_units_with_status_count' => 1,
        ])
        ->assertJsonFragment([
            'engineer' => 'Wafaa Sror',
            'lawyer' => 'Rawan Yousef',
        ])
        ->assertJsonMissing([
            'engineer' => 'Wafaa Nafeth Aqeel Sror',
            'lawyer' => 'Rawan Mahdi Yousef Al Haj Yousef',
        ])
        ->assertJsonFragment([
            'globalid' => $assignedOnlyBuilding->globalid,
        ]);

    $this->actingAs($user)
        ->getJson(route('audit.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'field_engineer' => ['Engineer A'],
            'eng_status' => ['accepted_by_engineer', 'need_review'],
            'status_from_date' => '2026-04-25',
            'status_to_date' => '2026-04-30',
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonMissingPath('error')
        ->assertJsonFragment([
            'globalid' => $building->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $olderStatusBuilding->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $assignedOnlyBuilding->globalid,
        ]);

    $this->actingAs($user)
        ->getJson(route('audit.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'objectid' => '7001',
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonMissingPath('error')
        ->assertJsonFragment([
            'globalid' => $building->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $olderStatusBuilding->globalid,
        ]);

    $this->actingAs($user)
        ->get(route('audit.export', [
            'export_type' => 'buildings_with_units',
            'objectid' => '7001',
            'building_columns' => [
                'objectid',
                'building_name',
                'municipality',
                'housing_status_progress',
                'building_status_notes',
            ],
            'housing_columns' => [
                'building_objectid',
                'governorate',
                'municipality',
                'neighborhood',
                'objectid',
                'parentglobalid',
                'housing_status_notes',
            ],
        ]))
        ->assertOk()
        ->assertHeader('content-disposition');

    config()->set('database.default', 'sqlite');
    DB::purge('mysql');
});

it('filters accepted buildings that contain unevaluated housing units', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    Http::fake([
        '*' => Http::response(['token' => 'fake-token']),
    ]);

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

    $finalStatus = AssessmentStatus::query()->create([
        'name' => 'final_approval',
        'label_en' => 'Final Approval',
        'label_ar' => 'مقبول',
        'stage' => 'team_leader',
        'order_step' => 1,
    ]);

    $acceptedByEngineerStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'مقبول هندسيا',
        'stage' => 'engineer',
        'order_step' => 2,
    ]);

    $acceptedByLawyerStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_lawyer',
        'label_en' => 'Accepted By Lawyer',
        'label_ar' => 'مقبول قانونيا',
        'stage' => 'lawyer',
        'order_step' => 3,
    ]);

    $needReviewStatus = AssessmentStatus::query()->create([
        'name' => 'need_review',
        'label_en' => 'Need Review',
        'label_ar' => 'بحاجة مراجعة',
        'stage' => 'engineer',
        'order_step' => 4,
    ]);

    $assignedStatus = AssessmentStatus::query()->create([
        'name' => 'assigned_to_lawyer',
        'label_en' => 'Assigned To Lawyer',
        'label_ar' => 'محول للمحامي',
        'stage' => 'lawyer',
        'order_step' => 5,
    ]);

    $acceptedWithPendingUnit = Building::query()->create([
        'objectid' => 7401,
        'globalid' => 'accepted-building-with-pending-unit',
        'building_name' => 'Accepted With Pending Unit',
        'field_status' => 'COMPLETED',
    ]);

    $acceptedWithReviewUnit = Building::query()->create([
        'objectid' => 7402,
        'globalid' => 'accepted-building-with-review-unit',
        'building_name' => 'Accepted With Review Unit',
        'field_status' => 'COMPLETED',
    ]);

    $acceptedWithAssignedUnit = Building::query()->create([
        'objectid' => 7403,
        'globalid' => 'accepted-building-with-assigned-unit',
        'building_name' => 'Accepted With Assigned Unit',
        'field_status' => 'COMPLETED',
    ]);

    $acceptedWithAcceptedUnit = Building::query()->create([
        'objectid' => 7404,
        'globalid' => 'accepted-building-with-accepted-unit',
        'building_name' => 'Accepted With Accepted Unit',
        'field_status' => 'COMPLETED',
    ]);

    $engineerAcceptedWithPendingUnit = Building::query()->create([
        'objectid' => 7405,
        'globalid' => 'engineer-accepted-building-with-pending-unit',
        'building_name' => 'Engineer Accepted With Pending Unit',
        'field_status' => 'COMPLETED',
    ]);

    $lawyerAcceptedWithReviewUnit = Building::query()->create([
        'objectid' => 7406,
        'globalid' => 'lawyer-accepted-building-with-review-unit',
        'building_name' => 'Lawyer Accepted With Review Unit',
        'field_status' => 'COMPLETED',
    ]);

    $notAcceptedWithReviewUnit = Building::query()->create([
        'objectid' => 7407,
        'globalid' => 'not-accepted-building-with-review-unit',
        'building_name' => 'Not Accepted With Review Unit',
        'field_status' => 'COMPLETED',
    ]);

    foreach ([$acceptedWithPendingUnit, $acceptedWithReviewUnit, $acceptedWithAssignedUnit, $acceptedWithAcceptedUnit] as $building) {
        BuildingStatus::query()->create([
            'building_id' => $building->objectid,
            'status_id' => $finalStatus->id,
            'user_id' => $user->id,
            'type' => 'Team Leader',
        ]);
    }

    BuildingStatus::query()->create([
        'building_id' => $engineerAcceptedWithPendingUnit->objectid,
        'status_id' => $acceptedByEngineerStatus->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $lawyerAcceptedWithReviewUnit->objectid,
        'status_id' => $acceptedByLawyerStatus->id,
        'user_id' => $user->id,
        'type' => 'Legal Auditor',
    ]);

    HousingUnit::query()->create([
        'objectid' => 8401,
        'globalid' => 'pending-unit',
        'parentglobalid' => $acceptedWithPendingUnit->globalid,
    ]);

    $reviewUnit = HousingUnit::query()->create([
        'objectid' => 8402,
        'globalid' => 'review-unit',
        'parentglobalid' => $acceptedWithReviewUnit->globalid,
    ]);

    $assignedUnit = HousingUnit::query()->create([
        'objectid' => 8403,
        'globalid' => 'assigned-unit',
        'parentglobalid' => $acceptedWithAssignedUnit->globalid,
    ]);

    $acceptedUnit = HousingUnit::query()->create([
        'objectid' => 8404,
        'globalid' => 'accepted-unit',
        'parentglobalid' => $acceptedWithAcceptedUnit->globalid,
    ]);

    HousingUnit::query()->create([
        'objectid' => 8405,
        'globalid' => 'engineer-accepted-pending-unit',
        'parentglobalid' => $engineerAcceptedWithPendingUnit->globalid,
    ]);

    $lawyerAcceptedReviewUnit = HousingUnit::query()->create([
        'objectid' => 8406,
        'globalid' => 'lawyer-accepted-review-unit',
        'parentglobalid' => $lawyerAcceptedWithReviewUnit->globalid,
    ]);

    $notAcceptedReviewUnit = HousingUnit::query()->create([
        'objectid' => 8407,
        'globalid' => 'not-accepted-review-unit',
        'parentglobalid' => $notAcceptedWithReviewUnit->globalid,
    ]);

    HousingStatus::query()->create([
        'housing_id' => $reviewUnit->objectid,
        'status_id' => $needReviewStatus->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    HousingStatus::query()->create([
        'housing_id' => $assignedUnit->objectid,
        'status_id' => $assignedStatus->id,
        'user_id' => $user->id,
        'type' => 'Legal Auditor',
    ]);

    HousingStatus::query()->create([
        'housing_id' => $acceptedUnit->objectid,
        'status_id' => $acceptedByEngineerStatus->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    HousingStatus::query()->create([
        'housing_id' => $lawyerAcceptedReviewUnit->objectid,
        'status_id' => $needReviewStatus->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    HousingStatus::query()->create([
        'housing_id' => $notAcceptedReviewUnit->objectid,
        'status_id' => $needReviewStatus->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('id="toggle_accepted_with_unevaluated_units"', false)
        ->assertSee('مقبول وبداخله وحدات غير مقيمة');

    $this->actingAs($user)
        ->getJson(route('audit.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'accepted_with_unevaluated_units' => '1',
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonMissingPath('error')
        ->assertJsonFragment([
            'globalid' => $acceptedWithPendingUnit->globalid,
        ])
        ->assertJsonFragment([
            'globalid' => $acceptedWithReviewUnit->globalid,
        ])
        ->assertJsonFragment([
            'globalid' => $acceptedWithAssignedUnit->globalid,
        ])
        ->assertJsonFragment([
            'globalid' => $engineerAcceptedWithPendingUnit->globalid,
        ])
        ->assertJsonFragment([
            'globalid' => $lawyerAcceptedWithReviewUnit->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $acceptedWithAcceptedUnit->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $notAcceptedWithReviewUnit->globalid,
        ]);

    config()->set('database.default', 'sqlite');
    DB::purge('mysql');
});

it('filters buildings with mismatched unit floor areas and excludes roof units', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    Http::fake([
        '*' => Http::response(['token' => 'fake-token']),
    ]);

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

    $mismatchedBuilding = Building::query()->create([
        'objectid' => 7451,
        'globalid' => 'floor-area-mismatch-building',
        'building_name' => 'Floor Area Mismatch Building',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'ground_floor_area__m2' => '100',
        'floor_area_m2' => '80',
    ]);

    $matchedBuilding = Building::query()->create([
        'objectid' => 7452,
        'globalid' => 'floor-area-matched-building',
        'building_name' => 'Floor Area Matched Building',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'ground_floor_area__m2' => '100',
        'floor_area_m2' => '80',
    ]);

    $partialMismatchedBuilding = Building::query()->create([
        'objectid' => 7453,
        'globalid' => 'partial-floor-area-mismatch-building',
        'building_name' => 'Partial Floor Area Mismatch Building',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'partially_damaged',
        'ground_floor_area__m2' => '100',
        'floor_area_m2' => '80',
    ]);

    HousingUnit::query()->create([
        'objectid' => 8451,
        'globalid' => 'mismatch-ground-unit',
        'parentglobalid' => $mismatchedBuilding->globalid,
        'floor_number' => '0',
        'damaged_area_m2' => '90',
    ]);

    HousingUnit::query()->create([
        'objectid' => 8452,
        'globalid' => 'mismatch-repeated-unit',
        'parentglobalid' => $mismatchedBuilding->globalid,
        'floor_number' => '1',
        'damaged_area_m2' => '80',
    ]);

    HousingUnit::query()->create([
        'objectid' => 8453,
        'globalid' => 'mismatch-roof-unit',
        'parentglobalid' => $mismatchedBuilding->globalid,
        'floor_number' => 'roof',
        'damaged_area_m2' => '10',
    ]);

    HousingUnit::query()->create([
        'objectid' => 8454,
        'globalid' => 'matched-ground-unit',
        'parentglobalid' => $matchedBuilding->globalid,
        'floor_number' => '0',
        'damaged_area_m2' => '100',
    ]);

    $matchedRepeatedUnit = HousingUnit::query()->create([
        'objectid' => 8455,
        'globalid' => 'matched-repeated-unit',
        'parentglobalid' => $matchedBuilding->globalid,
        'floor_number' => '2',
        'damaged_area_m2' => '70',
    ]);

    HousingUnit::query()->create([
        'objectid' => 8456,
        'globalid' => 'matched-roof-unit',
        'parentglobalid' => $matchedBuilding->globalid,
        'floor_number' => 'roof',
        'damaged_area_m2' => '999',
    ]);

    HousingUnit::query()->create([
        'objectid' => 8457,
        'globalid' => 'partial-mismatch-ground-unit',
        'parentglobalid' => $partialMismatchedBuilding->globalid,
        'floor_number' => '0',
        'damaged_area_m2' => '50',
    ]);

    EditAssessment::query()->create([
        'global_id' => $matchedRepeatedUnit->globalid,
        'type' => 'housing_table',
        'field_name' => 'floor_number',
        'field_value' => '1',
        'user_id' => $user->id,
    ]);

    EditAssessment::query()->create([
        'global_id' => $matchedRepeatedUnit->globalid,
        'type' => 'housing_table',
        'field_name' => 'damaged_area_m2',
        'field_value' => '80',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('id="toggle_floor_area_mismatch"', false)
        ->assertSee('id="export_floor_area_mismatch"', false)
        ->assertSee('مخالف لمساحات الطوابق');

    $this->actingAs($user)
        ->getJson(route('audit.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'floor_area_mismatch' => '1',
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonMissingPath('error')
        ->assertJsonFragment([
            'globalid' => $mismatchedBuilding->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $matchedBuilding->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $partialMismatchedBuilding->globalid,
        ]);

    $exportResponse = $this->actingAs($user)
        ->get(route('audit.floor-area-mismatches.export'));

    $exportResponse
        ->assertOk()
        ->assertHeader('content-disposition');

    $filePath = $exportResponse->baseResponse->getFile()->getPathname();
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheetByName('مخالفات المساحات');

    expect($sheet)->not->toBeNull();
    expect($sheet->rangeToArray('A1:C1'))->toBe([
        ['ObjectID', 'فرق الطابق الأرضي 0', 'فرق الطابق المتكرر 1'],
    ]);
    expect((int) $sheet->getCell('A2')->getValue())->toBe(7451)
        ->and((float) $sheet->getCell('B2')->getValue())->toBe(-10.0)
        ->and((float) $sheet->getCell('C2')->getValue())->toBe(0.0)
        ->and($sheet->getCell('A3')->getValue())->toBeNull();

    $spreadsheet->disconnectWorksheets();

    config()->set('database.default', 'sqlite');
    DB::purge('mysql');
});

it('shows and filters engineer audit questionnaire changes', function () {
    $databaseOfficerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);
    $engineerRole = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);
    Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($databaseOfficerRole);

    $engineer = User::factory()->create([
        'name' => 'Engineer Change Auditor',
    ]);
    $engineer->assignRole($engineerRole);

    $otherEngineer = User::factory()->create([
        'name' => 'Other Change Auditor',
    ]);
    $otherEngineer->assignRole($engineerRole);

    $nonAuditor = User::factory()->create([
        'name' => 'Non Auditor User',
    ]);

    Assessment::query()->create([
        'name' => 'building_name',
        'label' => 'اسم المبنى',
    ]);
    Assessment::query()->create([
        'name' => 'unit_owner',
        'label' => 'اسم مالك الوحدة',
    ]);

    $building = Building::query()->create([
        'objectid' => 7401,
        'globalid' => 'engineer-change-log-building',
        'building_name' => 'Engineer Change Log Building',
        'field_status' => 'COMPLETED',
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 8401,
        'globalid' => 'engineer-change-log-housing-unit',
        'parentglobalid' => $building->globalid,
        'housing_unit_number' => 'A-1',
    ]);

    AssessmentEditHistory::query()->create([
        'global_id' => $building->globalid,
        'objectid' => $building->objectid,
        'type' => 'building_table',
        'field_name' => 'building_name',
        'old_value' => 'Old Building Name',
        'new_value' => 'New Building Name',
        'edited_by' => $engineer->id,
        'created_at' => '2026-08-18 10:00:00',
        'updated_at' => '2026-08-18 10:00:00',
    ]);

    AssessmentEditHistory::query()->create([
        'global_id' => $housingUnit->globalid,
        'objectid' => $housingUnit->objectid,
        'type' => 'housing_table',
        'field_name' => 'unit_owner',
        'old_value' => 'Old Owner',
        'new_value' => 'New Owner',
        'edited_by' => $engineer->id,
        'created_at' => '2026-08-18 11:00:00',
        'updated_at' => '2026-08-18 11:00:00',
    ]);

    AssessmentEditHistory::query()->create([
        'global_id' => $building->globalid,
        'objectid' => $building->objectid,
        'type' => 'building_table',
        'field_name' => 'owner_name',
        'old_value' => 'Hidden Old Owner',
        'new_value' => 'Hidden New Owner',
        'edited_by' => $nonAuditor->id,
    ]);

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('id="btn_engineer_change_log"', false)
        ->assertSee('id="engineerChangeLogModal"', false)
        ->assertSee('اسم الحقل');

    $this->actingAs($user)
        ->getJson(route('audit.engineer-change-log', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonPath('recordsFiltered', 2)
        ->assertJsonFragment([
            'record_type_label' => 'مبنى',
            'field_name' => 'building_name',
            'field_label' => 'اسم المبنى',
            'engineer_name' => 'Engineer Change Auditor',
        ])
        ->assertJsonFragment([
            'record_type_label' => 'وحدة',
            'field_name' => 'unit_owner',
            'field_label' => 'اسم مالك الوحدة',
            'housing_unit_number' => 'A-1',
        ])
        ->assertJsonMissing([
            'field_name' => 'owner_name',
        ]);

    $this->actingAs($user)
        ->getJson(route('audit.engineer-change-log', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'field_name' => 'unit_owner',
        ]))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonFragment([
            'field_name' => 'unit_owner',
        ])
        ->assertJsonMissing([
            'field_name' => 'building_name',
        ]);

    $this->actingAs($user)
        ->getJson(route('audit.engineer-change-log', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'engineer_id' => $otherEngineer->id,
        ]))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 0);
});

it('exports requested legal and engineering audit notes with auditor names', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

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

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('id="audit_include_legal_notes"', false)
        ->assertSee('id="audit_include_engineering_notes"', false)
        ->assertSee('id="audit_legal_notes_filter"', false)
        ->assertSee('id="audit_engineering_notes_filter"', false);

    $engineer = User::factory()->create(['name' => 'Engineering Auditor Name']);
    $lawyer = User::factory()->create(['name' => 'Legal Auditor Name']);

    $engineeringStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'مقبول هندسيا',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $legalStatus = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'ملاحظات قانونية',
        'stage' => 'lawyer',
        'order_step' => 2,
    ]);

    $includedBuilding = Building::query()->create([
        'objectid' => 7201,
        'globalid' => 'audit-notes-building-included',
        'building_name' => 'Building With Notes',
        'field_status' => 'COMPLETED',
    ]);

    $excludedBuilding = Building::query()->create([
        'objectid' => 7202,
        'globalid' => 'audit-notes-building-excluded',
        'building_name' => 'Building Without Legal Notes',
        'field_status' => 'COMPLETED',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $includedBuilding->objectid,
        'status_id' => $engineeringStatus->id,
        'user_id' => $engineer->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'Engineering note text',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $includedBuilding->objectid,
        'status_id' => $legalStatus->id,
        'user_id' => $lawyer->id,
        'type' => 'Legal Auditor',
        'notes' => 'Legal note text',
    ]);

    BuildingStatus::query()->create([
        'building_id' => $excludedBuilding->objectid,
        'status_id' => $engineeringStatus->id,
        'user_id' => $engineer->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'Engineering only note',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('audit.export', [
            'export_type' => 'buildings',
            'building_columns' => ['objectid'],
            'include_legal_notes' => '1',
            'include_engineering_notes' => '1',
            'legal_notes_filter' => 'with_notes',
        ]));

    $response->assertOk();

    $filePath = $response->baseResponse->getFile()->getPathname();
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheetByName('Buildings');

    expect($sheet)->not->toBeNull();
    expect($sheet->rangeToArray('A1:E2'))->toBe([
        ['ObjectID', 'اسم المدقق القانوني', 'الملاحظات القانونية', 'اسم المدقق الهندسي', 'الملاحظات الهندسية'],
        ['7201', 'Legal Auditor Name', 'Legal note text', 'Engineering Auditor Name', 'Engineering note text'],
    ]);

    $spreadsheet->disconnectWorksheets();

    config()->set('database.default', 'sqlite');
    DB::purge('mysql');
});

it('allows reassigning an already assigned audit building to a different engineer or lawyer', function () {
    $managerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $manager = User::factory()->create();
    $manager->assignRole($managerRole);

    $cases = [
        [
            'type' => 'QC/QA Engineer',
            'role' => 'QC/QA Engineer',
            'status' => 'assigned_to_engineer',
            'stage' => 'engineer',
            'building_id' => 7101,
            'globalid' => 'audit-building-reassign-engineer',
        ],
        [
            'type' => 'Legal Auditor',
            'role' => 'Legal Auditor',
            'status' => 'assigned_to_lawyer',
            'stage' => 'lawyer',
            'building_id' => 7102,
            'globalid' => 'audit-building-reassign-lawyer',
        ],
    ];

    foreach ($cases as $case) {
        $auditorRole = Role::query()->create([
            'name' => $case['role'],
            'guard_name' => 'web',
        ]);

        $previousAuditor = User::factory()->create();
        $previousAuditor->assignRole($auditorRole);

        $newAuditor = User::factory()->create();
        $newAuditor->assignRole($auditorRole);

        $assignedStatus = AssessmentStatus::query()->create([
            'name' => $case['status'],
            'label_en' => 'Assigned',
            'label_ar' => 'assigned ar',
            'stage' => $case['stage'],
            'order_step' => 1,
        ]);

        $building = Building::query()->create([
            'objectid' => $case['building_id'],
            'globalid' => $case['globalid'],
            'building_name' => 'Audit Reassign Building',
            'field_status' => 'COMPLETED',
            'creationdate' => '2026-04-25 10:00:00',
        ]);

        AssignedAssessmentUser::query()->create([
            'manager_id' => $manager->id,
            'user_id' => $previousAuditor->id,
            'type' => $case['type'],
            'building_id' => $building->objectid,
        ]);

        BuildingStatus::query()->create([
            'building_id' => $building->objectid,
            'status_id' => $assignedStatus->id,
            'user_id' => $previousAuditor->id,
            'type' => $case['type'],
        ]);

        BuildingStatusHistory::query()->create([
            'building_id' => $building->objectid,
            'status_id' => $assignedStatus->id,
            'user_id' => $manager->id,
            'type' => $case['type'],
        ]);

        $this->actingAs($manager)
            ->postJson(route('audit.assign'), [
                'building_ids' => [$building->objectid],
                'user_id' => $newAuditor->id,
                'type' => $case['type'],
                'status_id' => $assignedStatus->id,
            ])
            ->assertOk()
            ->assertJson([
                'status' => true,
                'rejected_buildings' => [],
            ]);

        $this->assertDatabaseHas('assigned_assessment_users', [
            'building_id' => $building->objectid,
            'type' => $case['type'],
            'user_id' => $newAuditor->id,
        ]);

        $this->assertDatabaseHas('building_statuses', [
            'building_id' => $building->objectid,
            'type' => $case['type'],
            'status_id' => $assignedStatus->id,
            'user_id' => $newAuditor->id,
        ]);

        $this->assertDatabaseHas('building_status_histories', [
            'building_id' => $building->objectid,
            'type' => $case['type'],
            'status_id' => $assignedStatus->id,
            'user_id' => $manager->id,
        ]);
    }
});

it('keeps previous building notes visible in the notes history response', function () {
    $role = Role::query()->create([
        'name' => 'Database Officer',
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

    $legalStatus = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'Legal Notes',
        'stage' => 'lawyer',
        'order_step' => 2,
    ]);

    $building = Building::query()->create([
        'objectid' => 7201,
        'globalid' => 'audit-building-notes-history',
        'building_name' => 'Building With Notes History',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-25 10:00:00',
    ]);

    BuildingStatusHistory::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $engineerStatus->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'Engineer note stays visible',
    ]);

    BuildingStatusHistory::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $legalStatus->id,
        'user_id' => $user->id,
        'type' => 'Legal Auditor',
        'notes' => 'Lawyer note also stays visible',
    ]);

    $this->actingAs($user)
        ->getJson(route('audit.building.history', [
            'globalid' => $building->globalid,
        ]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonFragment([
            'notes' => 'Engineer note stays visible',
        ])
        ->assertJsonFragment([
            'notes' => 'Lawyer note also stays visible',
        ]);
});

it('shows and applies advanced building filters on the audit index', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    Http::fake([
        '*' => Http::response(['token' => 'fake-token']),
    ]);

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

    Filter::query()->create([
        'list_name' => 'building_material',
        'name' => 'concrete',
        'label' => 'Concrete',
    ]);

    Filter::query()->create([
        'list_name' => 'building_material',
        'name' => 'wood',
        'label' => 'Wood',
    ]);

    $matchingBuilding = Building::query()->create([
        'objectid' => 9101,
        'globalid' => 'audit-advanced-filter-matching',
        'building_name' => 'Audit Advanced Matching Building',
        'assignedto' => 'Engineer A',
        'field_status' => 'COMPLETED',
        'building_material' => 'concrete',
        'units_nos' => 8,
    ]);

    $excludedBuilding = Building::query()->create([
        'objectid' => 9102,
        'globalid' => 'audit-advanced-filter-excluded',
        'building_name' => 'Audit Advanced Excluded Building',
        'assignedto' => 'Engineer B',
        'field_status' => 'COMPLETED',
        'building_material' => 'wood',
        'units_nos' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('advanced_audit_building_filters', false)
        ->assertSee('Concrete');

    $this->actingAs($user)
        ->getJson(route('audit.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'advanced_filters' => [
                'building_material' => ['concrete'],
                'units_nos_from' => 5,
            ],
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonMissingPath('error')
        ->assertJsonFragment([
            'globalid' => $matchingBuilding->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $excludedBuilding->globalid,
        ]);

    config()->set('database.default', 'sqlite');
    DB::purge('mysql');
});

it('filters audit buildings by field status and completes field status on arcgis', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ], 200),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/updateFeatures' => Http::response([
            'updateResults' => [['success' => true]],
        ], 200),
    ]);

    $user = User::factory()->create();

    $completedBuilding = Building::query()->create([
        'objectid' => 7301,
        'globalid' => 'audit-completed-field-status-building',
        'building_name' => 'Already Completed Audit Building',
        'field_status' => 'COMPLETED',
    ]);

    $notCompletedBuilding = Building::query()->create([
        'objectid' => 7302,
        'globalid' => 'audit-not-completed-field-status-building',
        'building_name' => 'Needs Completed Audit Building',
        'field_status' => 'Not_Completed',
    ]);

    $this->actingAs($user)
        ->getJson(route('audit.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonFragment([
            'globalid' => $completedBuilding->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $notCompletedBuilding->globalid,
        ]);

    $this->actingAs($user)
        ->getJson(route('audit.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'field_status' => 'Not_Completed',
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonFragment([
            'globalid' => $notCompletedBuilding->globalid,
        ])
        ->assertSee('btn-complete-building-field-status', false)
        ->assertJsonMissing([
            'globalid' => $completedBuilding->globalid,
        ]);

    $this->actingAs($user)
        ->postJson(route('audit.building.field-status.completed', $notCompletedBuilding->globalid))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('field_status', 'COMPLETED');

    expect($notCompletedBuilding->refresh()->field_status)->toBe('COMPLETED');

    Http::assertSent(function ($request): bool {
        $features = json_decode((string) data_get($request->data(), 'features'), true);

        return str_contains($request->url(), '/FeatureServer/0/updateFeatures')
            && data_get($features, '0.attributes.objectid') === 7302
            && data_get($features, '0.attributes.field_status') === 'COMPLETED';
    });
});

it('hides audit management action buttons for temporary excepted users only', function () {
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

    $exceptedUser = User::factory()->create([
        'name' => 'ياسمين ماهر مصطفى ابومدللة',
    ]);
    $exceptedUser->assignRole($role);

    $identityExceptedUser = User::factory()->create([
        'id_no' => '800409062',
    ]);
    $identityExceptedUser->assignRole($role);

    $regularUser = User::factory()->create([
        'name' => 'Regular Database Officer',
    ]);
    $regularUser->assignRole($role);

    $hiddenActionIds = [
        'id="btn_final_approve"',
        'id="btn_undp_final_approve"',
        'id="btn_assign_to_lawyer"',
        'id="btn_assign_to_engineer"',
        'id="btn_import_final_approve"',
    ];

    $response = $this->actingAs($exceptedUser)
        ->get(route('audit.index'))
        ->assertOk();

    foreach ($hiddenActionIds as $buttonId) {
        $response->assertDontSee($buttonId, false);
    }

    $response = $this->actingAs($identityExceptedUser)
        ->get(route('audit.index'))
        ->assertOk();

    foreach ($hiddenActionIds as $buttonId) {
        $response->assertDontSee($buttonId, false);
    }

    $response = $this->actingAs($regularUser)
        ->get(route('audit.index'))
        ->assertOk();

    foreach ($hiddenActionIds as $buttonId) {
        $response->assertSee($buttonId, false);
    }
});

it('opens the audit index for team leaders without management actions', function () {
    $role = Role::query()->create([
        'name' => 'Team Leader',
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

    $response = $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertOk();

    foreach ([
        'id="btn_final_approve"',
        'id="btn_undp_final_approve"',
        'id="btn_assign_to_lawyer"',
        'id="btn_assign_to_engineer"',
        'id="btn_import_final_approve"',
        'id="toggle_select_column"',
    ] as $buttonId) {
        $response->assertDontSee($buttonId, false);
    }
});

it('forbids users with only a field engineer role from opening the audit index', function () {
    $role = Role::query()->create([
        'name' => 'Field Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertForbidden();
});

it('does not forbid field engineers with another role from opening the audit index', function () {
    $fieldEngineerRole = Role::query()->create([
        'name' => 'Field Engineer',
        'guard_name' => 'web',
    ]);
    $teamLeaderRole = Role::query()->create([
        'name' => 'Team Leader',
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
    $user->assignRole($fieldEngineerRole, $teamLeaderRole);

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertOk();
});
