<?php

use App\Models\AssessmentStatus;
use App\Models\AssignedAssessmentUser;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\BuildingStatusHistory;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

it('includes the housing units status progress in the audit table response', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    $role = Role::query()->create([
        'name' => 'Database Officer',
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
