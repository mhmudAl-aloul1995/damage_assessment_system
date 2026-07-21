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

it('shows daily auditor achievement counts grouped by auditing engineer', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $firstAuditor = User::factory()->create([
        'name' => 'Saeed Raihan',
    ]);

    $secondAuditor = User::factory()->create([
        'name' => 'Ahmad Salem',
    ]);

    $acceptedStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'accepted ar',
        'stage' => 'engineer',
        'order_step' => 4,
    ]);

    $rejectedStatus = AssessmentStatus::query()->create([
        'name' => 'rejected_by_engineer',
        'label_en' => 'Rejected By Engineer',
        'label_ar' => 'rejected ar',
        'stage' => 'engineer',
        'order_step' => 3,
    ]);

    $needReviewStatus = AssessmentStatus::query()->create([
        'name' => 'need_review',
        'label_en' => 'Need Review',
        'label_ar' => 'need review ar',
        'stage' => 'engineer',
        'order_step' => 5,
    ]);

    Building::query()->create([
        'objectid' => 501,
        'globalid' => 'building-501',
    ]);

    HousingUnit::query()->create([
        'objectid' => 1001,
        'globalid' => 'housing-1001',
        'parentglobalid' => 'building-501',
    ]);

    HousingUnit::query()->create([
        'objectid' => 1002,
        'globalid' => 'housing-1002',
        'parentglobalid' => 'building-501',
    ]);

    HousingUnit::query()->create([
        'objectid' => 1003,
        'globalid' => 'housing-1003',
        'parentglobalid' => 'building-501',
    ]);

    HousingStatus::query()->create([
        'housing_id' => 1001,
        'status_id' => $acceptedStatus->id,
        'user_id' => $firstAuditor->id,
        'type' => 'Different Type',
        'notes' => 'accepted today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);
    HousingStatus::query()->create([
        'housing_id' => 1002,
        'status_id' => $rejectedStatus->id,
        'user_id' => $firstAuditor->id,
        'type' => 'Different Type',
        'notes' => 'rejected today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);
    HousingStatus::query()->create([
        'housing_id' => 1003,
        'status_id' => $needReviewStatus->id,
        'user_id' => $firstAuditor->id,
        'type' => 'Different Type',
        'notes' => 'review today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);
    BuildingStatus::query()->create([
        'building_id' => 501,
        'status_id' => $acceptedStatus->id,
        'user_id' => $firstAuditor->id,
        'type' => 'Different Type',
        'notes' => 'accepted building today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);
    HousingStatus::query()->insert([
        'housing_id' => 1004,
        'status_id' => $acceptedStatus->id,
        'user_id' => $secondAuditor->id,
        'type' => 'Different Type',
        'notes' => 'old accepted',
        'updated_at' => now()->subDays(3),
        'created_at' => now()->subDays(3),
    ]);
    $response = $this
        ->actingAs($viewer)
        ->get(route('reports.auditors-daily', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

    $response->assertOk();
    $response->assertSee('Saeed Raihan');
    $response->assertDontSee('Ahmad Salem');
    $response->assertSee('daily-achievement-units-btn');
    $response->assertSee('dailyAchievementUnitsModal');
    $response->assertViewHas('rows', function ($rows) {
        $firstRow = collect($rows)->firstWhere('name', 'Saeed Raihan');
        $secondRow = collect($rows)->firstWhere('name', 'Ahmad Salem');

        return $firstRow !== null
            && $firstRow['accepted_count'] === 1
            && $firstRow['rejected_count'] === 1
            && $firstRow['need_review_count'] === 1
            && $firstRow['total_count'] === 3
            && $secondRow === null;
    });
    $response->assertViewHas('totals', function (array $totals) {
        return $totals['accepted_count'] === 1
            && $totals['rejected_count'] === 1
            && $totals['need_review_count'] === 1
            && $totals['total_count'] === 3;
    });
    $response->assertViewHas('chartMetrics', function (array $chartMetrics) {
        return $chartMetrics['buildings']['audited_count'] === 1
            && $chartMetrics['housing_units']['audited_count'] === 3;
    });
});

it('counts current engineering statuses created in the period for existing housing units once', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $auditor = User::factory()->create([
        'name' => 'Wafaa Sarour',
    ]);

    $acceptedStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'accepted ar',
        'stage' => 'engineer',
        'order_step' => 4,
    ]);

    $needReviewStatus = AssessmentStatus::query()->create([
        'name' => 'need_review',
        'label_en' => 'Need Review',
        'label_ar' => 'need review ar',
        'stage' => 'engineer',
        'order_step' => 5,
    ]);

    Building::query()->create([
        'objectid' => 701,
        'globalid' => 'building-701',
    ]);

    foreach ([7001, 7002, 7003] as $objectId) {
        HousingUnit::query()->create([
            'objectid' => $objectId,
            'globalid' => 'housing-'.$objectId,
            'parentglobalid' => 'building-701',
        ]);
    }

    HousingStatus::query()->create([
        'housing_id' => 7001,
        'status_id' => $acceptedStatus->id,
        'user_id' => $auditor->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'new audited unit',
        'updated_at' => now(),
        'created_at' => now(),
    ]);
    $oldHousingStatus = HousingStatus::query()->create([
        'housing_id' => 7002,
        'status_id' => $acceptedStatus->id,
        'user_id' => $auditor->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'old unit decision changed today',
        'updated_at' => now(),
    ]);
    DB::table('housing_statuses')
        ->where('id', $oldHousingStatus->id)
        ->update([
            'created_at' => now()->subDays(5),
            'updated_at' => now(),
        ]);
    HousingStatus::query()->create([
        'housing_id' => 7003,
        'status_id' => $needReviewStatus->id,
        'user_id' => $auditor->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'new audited unit needing review',
        'updated_at' => now(),
        'created_at' => now(),
    ]);
    HousingStatus::query()->create([
        'housing_id' => 7999,
        'status_id' => $acceptedStatus->id,
        'user_id' => $auditor->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'missing housing unit',
        'updated_at' => now(),
        'created_at' => now(),
    ]);
    $response = $this
        ->actingAs($viewer)
        ->get(route('reports.auditors-daily', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

    $response->assertOk();
    $response->assertViewHas('rows', function ($rows) {
        $row = collect($rows)->firstWhere('name', 'Wafaa Sarour');

        return $row !== null
            && $row['accepted_count'] === 1
            && $row['rejected_count'] === 0
            && $row['need_review_count'] === 1
            && $row['total_count'] === 2;
    });
    $response->assertViewHas('totals', function (array $totals) {
        return $totals['accepted_count'] === 1
            && $totals['rejected_count'] === 0
            && $totals['need_review_count'] === 1
            && $totals['total_count'] === 2;
    });
    $response->assertViewHas('chartMetrics', function (array $chartMetrics) {
        return $chartMetrics['housing_units']['audited_count'] === 2;
    });

    $this
        ->actingAs($viewer)
        ->getJson(route('reports.daily-achievement.units', [
            'user_id' => $auditor->id,
            'status' => 'accepted_by_engineer',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'draw' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.objectid', 7001)
        ->assertJsonPath('data.0.housing_unit_number', '-');
});
