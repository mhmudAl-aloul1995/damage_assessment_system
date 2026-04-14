<?php

use App\Models\AssessmentStatus;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

it('shows audit dashboard metrics and charts for engineers and lawyers', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $acceptedDay = Carbon::parse('2026-02-21 10:00:00');
    $assignedDay = Carbon::parse('2026-02-22 11:00:00');
    $trendStartDay = Carbon::parse('2026-02-20 09:00:00');
    $oldDay = Carbon::parse('2026-01-10 08:00:00');

    $assignedToEngineerStatus = AssessmentStatus::query()->create([
        'name' => 'assigned_to_engineer',
        'label_en' => 'Assigned To Engineer',
        'label_ar' => 'assigned engineer ar',
        'stage' => 'engineer',
        'order_step' => 3,
    ]);

    $acceptedByEngineerStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'accepted engineer ar',
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

    $assignedToLawyerStatus = AssessmentStatus::query()->create([
        'name' => 'assigned_to_lawyer',
        'label_en' => 'Assigned To Lawyer',
        'label_ar' => 'assigned lawyer ar',
        'stage' => 'lawyer',
        'order_step' => 6,
    ]);

    $acceptedByLawyerStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_lawyer',
        'label_en' => 'Accepted By Lawyer',
        'label_ar' => 'accepted lawyer ar',
        'stage' => 'lawyer',
        'order_step' => 7,
    ]);

    $legalNotesStatus = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'legal notes ar',
        'stage' => 'lawyer',
        'order_step' => 8,
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
        [
            'building_id' => 101,
            'status_id' => $acceptedByEngineerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'notes' => 'accepted building',
            'created_at' => $acceptedDay,
            'updated_at' => $acceptedDay,
        ],
        [
            'building_id' => 102,
            'status_id' => $assignedToEngineerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'notes' => 'assigned building',
            'created_at' => $assignedDay,
            'updated_at' => $assignedDay,
        ],
        [
            'building_id' => 101,
            'status_id' => $acceptedByLawyerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'notes' => 'accepted legal building',
            'created_at' => $acceptedDay,
            'updated_at' => $acceptedDay,
        ],
        [
            'building_id' => 102,
            'status_id' => $legalNotesStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'notes' => 'legal notes building',
            'created_at' => $assignedDay,
            'updated_at' => $assignedDay,
        ],
    ]);

    HousingStatus::query()->insert([
        [
            'housing_id' => 201,
            'status_id' => $acceptedByEngineerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'notes' => 'accepted housing',
            'created_at' => $trendStartDay,
            'updated_at' => $trendStartDay,
        ],
        [
            'housing_id' => 202,
            'status_id' => $needReviewStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'notes' => 'review housing',
            'created_at' => $acceptedDay,
            'updated_at' => $acceptedDay,
        ],
        [
            'housing_id' => 203,
            'status_id' => $acceptedByEngineerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'QC/QA Engineer',
            'notes' => 'old housing',
            'created_at' => $oldDay,
            'updated_at' => $oldDay,
        ],
        [
            'housing_id' => 201,
            'status_id' => $assignedToLawyerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'notes' => 'assigned legal housing',
            'created_at' => $trendStartDay,
            'updated_at' => $trendStartDay,
        ],
        [
            'housing_id' => 202,
            'status_id' => $acceptedByLawyerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'notes' => 'accepted legal housing',
            'created_at' => $acceptedDay,
            'updated_at' => $acceptedDay,
        ],
        [
            'housing_id' => 203,
            'status_id' => $legalNotesStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'notes' => 'legal notes housing',
            'created_at' => $assignedDay,
            'updated_at' => $assignedDay,
        ],
    ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('audit.dashboard', [
            'start_date' => '2026-02-20',
            'end_date' => '2026-02-22',
        ]));

    $response->assertOk();
    $response->assertSee('Audit Dashboard');
    $response->assertSee('Engineering Audit');
    $response->assertSee('Legal Audit');
    $response->assertSee('Daily Audited Housing Units');
    $response->assertViewHas('summaryMetrics', function (array $summaryMetrics) {
        return $summaryMetrics['total_buildings_count'] === 2
            && $summaryMetrics['total_housing_units_count'] === 3
            && $summaryMetrics['engineer']['audited_buildings_count'] === 1
            && $summaryMetrics['engineer']['audited_housing_units_count'] === 2
            && $summaryMetrics['engineer']['audited_buildings_percentage'] === 50.0
            && $summaryMetrics['engineer']['audited_housing_units_percentage'] === 66.7
            && $summaryMetrics['lawyer']['audited_buildings_count'] === 2
            && $summaryMetrics['lawyer']['audited_housing_units_count'] === 2
            && $summaryMetrics['lawyer']['audited_buildings_percentage'] === 100.0
            && $summaryMetrics['lawyer']['audited_housing_units_percentage'] === 66.7;
    });
    $response->assertViewHas('chartData', function (array $chartData) {
        return $chartData['engineer']['building_status_series'] === [1, 1, 0, 0]
            && $chartData['engineer']['housing_status_series'] === [0, 1, 0, 1]
            && $chartData['engineer']['comparison_audited_series'] === [1, 2]
            && $chartData['engineer']['comparison_total_series'] === [2, 3]
            && $chartData['engineer']['daily_housing_achievement_start_date'] === '2026-02-20'
            && $chartData['engineer']['daily_housing_achievement_labels'] === ['2026-02-20', '2026-02-21', '2026-02-22']
            && $chartData['engineer']['daily_housing_achievement_series'] === [1, 1, 0]
            && $chartData['lawyer']['building_status_series'] === [0, 1, 1]
            && $chartData['lawyer']['housing_status_series'] === [1, 1, 1]
            && $chartData['lawyer']['comparison_audited_series'] === [2, 2]
            && $chartData['lawyer']['comparison_total_series'] === [2, 3];
    });
});
