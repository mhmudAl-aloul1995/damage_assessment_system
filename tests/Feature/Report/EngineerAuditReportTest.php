<?php

use App\Models\AssessmentStatus;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('summarizes completed building audit statuses by field engineer and exports excel', function () {
    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $acceptedStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 4,
    ]);

    $rejectedStatus = AssessmentStatus::query()->create([
        'name' => 'rejected_by_engineer',
        'label_en' => 'Rejected By Engineer',
        'label_ar' => 'Rejected By Engineer',
        'stage' => 'engineer',
        'order_step' => 3,
    ]);

    $needReviewStatus = AssessmentStatus::query()->create([
        'name' => 'need_review',
        'label_en' => 'Need Review',
        'label_ar' => 'Need Review',
        'stage' => 'engineer',
        'order_step' => 5,
    ]);

    Building::query()->insert([
        [
            'objectid' => 701,
            'globalid' => 'building-701',
            'assignedto' => 'Engineer One',
            'field_status' => 'completed',
            'editdate' => '2026-05-01 10:00:00',
            'end' => '2026-05-01 10:00:00',
        ],
        [
            'objectid' => 702,
            'globalid' => 'building-702',
            'assignedto' => 'Engineer One',
            'field_status' => 'COMPLETED',
            'editdate' => '2026-05-02 10:00:00',
            'end' => '2026-05-02 10:00:00',
        ],
        [
            'objectid' => 703,
            'globalid' => 'building-703',
            'assignedto' => 'Engineer One',
            'field_status' => 'completed',
            'editdate' => '2026-05-03 10:00:00',
            'end' => '2026-05-03 10:00:00',
        ],
        [
            'objectid' => 704,
            'globalid' => 'building-704',
            'assignedto' => 'Engineer Two',
            'field_status' => 'completed',
            'editdate' => '2026-05-04 10:00:00',
            'end' => '2026-05-04 10:00:00',
        ],
        [
            'objectid' => 705,
            'globalid' => 'building-705',
            'assignedto' => 'Engineer One',
            'field_status' => 'Not_Completed',
            'editdate' => '2026-05-05 10:00:00',
            'end' => '2026-05-05 10:00:00',
        ],
        [
            'objectid' => 706,
            'globalid' => 'building-706',
            'assignedto' => 'Engineer Old',
            'field_status' => 'completed',
            'editdate' => '2026-05-06 10:00:00',
            'end' => '2025-12-31 10:00:00',
        ],
    ]);

    BuildingStatus::query()->insert([
        [
            'building_id' => 701,
            'status_id' => $acceptedStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'created_at' => '2026-05-01 11:00:00',
            'updated_at' => '2026-05-01 11:00:00',
        ],
        [
            'building_id' => 702,
            'status_id' => $rejectedStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'created_at' => '2026-05-02 11:00:00',
            'updated_at' => '2026-05-02 11:00:00',
        ],
        [
            'building_id' => 703,
            'status_id' => $needReviewStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'created_at' => '2026-05-03 11:00:00',
            'updated_at' => '2026-05-03 11:00:00',
        ],
        [
            'building_id' => 704,
            'status_id' => $acceptedStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'created_at' => '2026-05-04 11:00:00',
            'updated_at' => '2026-05-04 11:00:00',
        ],
        [
            'building_id' => 705,
            'status_id' => $acceptedStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'created_at' => '2026-05-05 11:00:00',
            'updated_at' => '2026-05-05 11:00:00',
        ],
        [
            'building_id' => 706,
            'status_id' => $acceptedStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'created_at' => '2025-12-31 11:00:00',
            'updated_at' => '2025-12-31 11:00:00',
        ],
    ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('reports.engineer-audit'));

    $response
        ->assertOk()
        ->assertSee('تقرير تقييم المهندسين')
        ->assertSee('نطاق تاريخ التقديم')
        ->assertSee('Engineer One')
        ->assertSee('Engineer Two');

    $response->assertViewHas('rows', function ($rows): bool {
        $engineerOne = collect($rows)->firstWhere('field_engineer_name', 'Engineer One');
        $engineerTwo = collect($rows)->firstWhere('field_engineer_name', 'Engineer Two');
        $engineerOld = collect($rows)->firstWhere('field_engineer_name', 'Engineer Old');

        return $engineerOne !== null
            && $engineerTwo !== null
            && $engineerOld === null
            && $rows->first()->field_engineer_name === 'Engineer One'
            && $engineerOne->accepted_count === 1
            && $engineerOne->rejected_count === 1
            && $engineerOne->need_review_count === 1
            && $engineerOne->total_completed_count === 3
            && $engineerTwo->accepted_count === 1
            && $engineerTwo->total_completed_count === 1;
    });

    $response->assertViewHas('summary', function (array $summary): bool {
        return $summary['accepted_count'] === 2
            && $summary['rejected_count'] === 1
            && $summary['need_review_count'] === 1
            && $summary['total_completed_count'] === 4;
    });

    $response->assertViewHas('filters', function (array $filters): bool {
        return $filters['start_date'] === '2026-01-01'
            && $filters['end_date'] === today()->toDateString();
    });

    $this
        ->actingAs($viewer)
        ->get(route('reports.engineer-audit', ['assignedto' => 'Engineer One']))
        ->assertOk()
        ->assertViewHas('filters', fn (array $filters): bool => $filters['assignedto'] === 'Engineer One')
        ->assertViewHas('rows', function ($rows): bool {
            return $rows->count() === 1
                && $rows->first()->field_engineer_name === 'Engineer One'
                && $rows->first()->total_completed_count === 3;
        });

    $this
        ->actingAs($viewer)
        ->get(route('reports.engineer-audit.export', ['assignedto' => 'Engineer One']))
        ->assertOk()
        ->assertHeader('content-disposition');
});
