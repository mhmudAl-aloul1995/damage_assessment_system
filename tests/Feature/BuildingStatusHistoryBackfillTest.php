<?php

use App\Models\AssessmentStatus;
use App\Models\BuildingStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('reports missing building status histories without changing data during a dry run', function () {
    $status = buildingBackfillAssessmentStatus();
    $user = User::factory()->create();

    $buildingStatusId = insertBuildingStatusForBackfill(
        buildingId: 5101,
        statusId: $status->id,
        userId: $user->id,
        type: 'Legal Auditor',
        createdAt: '2026-06-01 10:00:00',
    );

    $this->artisan('audit:backfill-building-status-histories --dry-run')
        ->expectsTable(
            ['Metric', 'Count'],
            [
                ['Missing histories found', 1],
                ['Histories inserted', 0],
            ],
        )
        ->assertSuccessful();

    expect(DB::table('building_status_histories')->count())->toBe(0)
        ->and(DB::table('building_status_history_backfills')->count())->toBe(0)
        ->and(BuildingStatus::query()->whereKey($buildingStatusId)->exists())->toBeTrue();
});

it('backfills missing building status histories and tracks inserted ids', function () {
    $status = buildingBackfillAssessmentStatus();
    $user = User::factory()->create();

    insertBuildingStatusForBackfill(
        buildingId: 5102,
        statusId: $status->id,
        userId: $user->id,
        type: 'Legal Auditor',
        createdAt: '2026-06-02 10:00:00',
    );

    insertBuildingHistoryForBackfill(
        buildingId: 5102,
        statusId: $status->id,
        userId: $user->id,
        type: 'Legal Auditor',
        createdAt: '2026-06-01 10:00:00',
    );

    $this->artisan('audit:backfill-building-status-histories')
        ->expectsTable(
            ['Metric', 'Count'],
            [
                ['Missing histories found', 1],
                ['Histories inserted', 1],
            ],
        )
        ->assertSuccessful();

    $this->assertDatabaseHas('building_status_histories', [
        'building_id' => 5102,
        'status_id' => $status->id,
        'type' => 'Legal Auditor',
        'created_at' => '2026-06-02 10:00:00',
    ]);

    expect(DB::table('building_status_histories')->count())->toBe(2)
        ->and(DB::table('building_status_history_backfills')->count())->toBe(1);
});

it('rolls back only histories inserted by the backfill command', function () {
    $status = buildingBackfillAssessmentStatus();
    $user = User::factory()->create();

    insertBuildingStatusForBackfill(
        buildingId: 5103,
        statusId: $status->id,
        userId: $user->id,
        type: 'QC/QA Engineer',
        createdAt: '2026-06-03 10:00:00',
    );

    insertBuildingHistoryForBackfill(
        buildingId: 5104,
        statusId: $status->id,
        userId: $user->id,
        type: 'QC/QA Engineer',
        createdAt: '2026-06-04 10:00:00',
    );

    $this->artisan('audit:backfill-building-status-histories')
        ->assertSuccessful();

    expect(DB::table('building_status_histories')->count())->toBe(2);

    $this->artisan('audit:backfill-building-status-histories --rollback')
        ->expectsTable(
            ['Metric', 'Count'],
            [
                ['Tracked backfills found', 1],
                ['Histories deleted', 1],
                ['Already missing', 0],
            ],
        )
        ->assertSuccessful();

    expect(DB::table('building_status_histories')->count())->toBe(1)
        ->and(DB::table('building_status_histories')
            ->where('building_id', 5104)
            ->where('status_id', $status->id)
            ->where('type', 'QC/QA Engineer')
            ->where('created_at', '2026-06-04 10:00:00')
            ->exists())->toBeTrue()
        ->and(DB::table('building_status_history_backfills')->whereNull('rolled_back_at')->count())->toBe(0);
});

function buildingBackfillAssessmentStatus(): AssessmentStatus
{
    return AssessmentStatus::query()->create([
        'name' => 'accepted_by_backfill_test',
        'label_en' => 'Accepted by backfill test',
        'label_ar' => 'Accepted by backfill test',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);
}

function insertBuildingStatusForBackfill(
    int $buildingId,
    int $statusId,
    int $userId,
    string $type,
    string $createdAt,
): int {
    return DB::table('building_statuses')->insertGetId([
        'building_id' => $buildingId,
        'status_id' => $statusId,
        'user_id' => $userId,
        'type' => $type,
        'notes' => 'Backfill test status',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function insertBuildingHistoryForBackfill(
    int $buildingId,
    int $statusId,
    int $userId,
    string $type,
    string $createdAt,
): int {
    return DB::table('building_status_histories')->insertGetId([
        'building_id' => $buildingId,
        'status_id' => $statusId,
        'user_id' => $userId,
        'type' => $type,
        'notes' => 'Existing history',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}
