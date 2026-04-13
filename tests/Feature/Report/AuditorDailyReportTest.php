<?php

use App\Models\AssessmentStatus;
use App\Models\HousingStatus;
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

    $auditorRole = Role::query()->create([
        'name' => 'QC/QA Engineer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $firstAuditor = User::factory()->create([
        'name' => 'Saeed Raihan',
    ]);
    $firstAuditor->assignRole($auditorRole);

    $secondAuditor = User::factory()->create([
        'name' => 'Ahmad Salem',
    ]);
    $secondAuditor->assignRole($auditorRole);

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

    HousingStatus::query()->create([
        'housing_id' => 1001,
        'status_id' => $acceptedStatus->id,
        'user_id' => $firstAuditor->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'accepted today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    HousingStatus::query()->create([
        'housing_id' => 1002,
        'status_id' => $rejectedStatus->id,
        'user_id' => $firstAuditor->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'rejected today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    HousingStatus::query()->create([
        'housing_id' => 1003,
        'status_id' => $needReviewStatus->id,
        'user_id' => $firstAuditor->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'review today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    HousingStatus::query()->insert([
        'housing_id' => 1004,
        'status_id' => $acceptedStatus->id,
        'user_id' => $secondAuditor->id,
        'type' => 'QC/QA Engineer',
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
    $response->assertSee('Ahmad Salem');
    $response->assertViewHas('rows', function ($rows) {
        $firstRow = collect($rows)->firstWhere('name', 'Saeed Raihan');
        $secondRow = collect($rows)->firstWhere('name', 'Ahmad Salem');

        return $firstRow !== null
            && $firstRow['accepted_count'] === 1
            && $firstRow['rejected_count'] === 1
            && $firstRow['need_review_count'] === 1
            && $firstRow['total_count'] === 3
            && $secondRow !== null
            && $secondRow['accepted_count'] === 0
            && $secondRow['rejected_count'] === 0
            && $secondRow['need_review_count'] === 0
            && $secondRow['total_count'] === 0;
    });
    $response->assertViewHas('totals', function (array $totals) {
        return $totals['accepted_count'] === 1
            && $totals['rejected_count'] === 1
            && $totals['need_review_count'] === 1
            && $totals['total_count'] === 3;
    });
});
