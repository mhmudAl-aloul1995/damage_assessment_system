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

it('shows audit dashboard metrics and charts', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

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
        'objectid' => 101,
        'globalid' => 'building-101',
    ]);

    Building::query()->create([
        'objectid' => 102,
        'globalid' => 'building-102',
    ]);

    HousingUnit::query()->create([
        'objectid' => 201,
        'globalid' => 'housing-201',
        'parentglobalid' => 'building-101',
    ]);

    HousingUnit::query()->create([
        'objectid' => 202,
        'globalid' => 'housing-202',
        'parentglobalid' => 'building-101',
    ]);

    HousingUnit::query()->create([
        'objectid' => 203,
        'globalid' => 'housing-203',
        'parentglobalid' => 'building-102',
    ]);

    BuildingStatus::query()->insert([
        'building_id' => 101,
        'status_id' => $acceptedStatus->id,
        'user_id' => $viewer->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'accepted building',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    BuildingStatus::query()->insert([
        'building_id' => 102,
        'status_id' => $acceptedStatus->id,
        'user_id' => $viewer->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'old building',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    HousingStatus::query()->insert([
        'housing_id' => 201,
        'status_id' => $acceptedStatus->id,
        'user_id' => $viewer->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'accepted housing',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    HousingStatus::query()->insert([
        'housing_id' => 202,
        'status_id' => $needReviewStatus->id,
        'user_id' => $viewer->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'review housing',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    HousingStatus::query()->insert([
        'housing_id' => 203,
        'status_id' => $acceptedStatus->id,
        'user_id' => $viewer->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'old housing',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('audit.dashboard', [
            'start_date' => now()->subDays(29)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

    $response->assertOk();
    $response->assertSee('Audit Dashboard');
    $response->assertViewHas('summaryMetrics', function (array $summaryMetrics) {
        return $summaryMetrics['total_buildings_count'] === 2
            && $summaryMetrics['audited_buildings_count'] === 1
            && $summaryMetrics['total_housing_units_count'] === 3
            && $summaryMetrics['audited_housing_units_count'] === 2
            && $summaryMetrics['audited_buildings_percentage'] === 50.0
            && $summaryMetrics['audited_housing_units_percentage'] === 66.7;
    });
    $response->assertViewHas('chartData', function (array $chartData) {
        return $chartData['building_status_series'] === [0, 1, 0, 0]
            && $chartData['housing_status_series'] === [0, 1, 0, 1]
            && $chartData['comparison_audited_series'] === [1, 2]
            && $chartData['comparison_total_series'] === [2, 3];
    });
});
