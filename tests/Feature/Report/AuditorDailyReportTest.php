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
