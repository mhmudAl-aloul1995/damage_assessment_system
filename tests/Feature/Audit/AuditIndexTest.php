<?php

use App\Models\AssessmentStatus;
use App\Models\Building;
use App\Models\BuildingStatus;
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
        'globalid' => 'audit-building-units-count',
        'building_name' => 'Audit Units Count Building',
        'assignedto' => 'Engineer A',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-25 10:00:00',
    ]);

    $housingWithStatus = HousingUnit::query()->create([
        'objectid' => 8001,
        'globalid' => 'audit-housing-unit-1',
        'parentglobalid' => $building->globalid,
    ]);

    HousingUnit::query()->create([
        'objectid' => 8002,
        'globalid' => 'audit-housing-unit-2',
        'parentglobalid' => $building->globalid,
    ]);

    HousingStatus::query()->create([
        'housing_id' => $housingWithStatus->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
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
        'globalid' => 'audit-building-old-status',
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
        'globalid' => 'audit-building-assigned-status',
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
            'start' => 0,
            'length' => 10,
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonFragment([
            'housing_status_progress' => '1 / 2',
            'housing_units_count' => 2,
            'housing_units_with_status_count' => 1,
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
        ->assertJsonFragment([
            'globalid' => $building->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $olderStatusBuilding->globalid,
        ])
        ->assertJsonMissing([
            'globalid' => $assignedOnlyBuilding->globalid,
        ]);
});
