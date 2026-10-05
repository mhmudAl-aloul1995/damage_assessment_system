<?php

use App\Models\AssessmentStatus;
use App\Models\HousingStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('reports missing housing status histories without changing data during a dry run', function () {
    $status = housingBackfillAssessmentStatus();
    $user = User::factory()->create();

    $housingStatusId = insertHousingStatusForBackfill(
        housingId: 6101,
        statusId: $status->id,
        userId: $user->id,
        type: 'Legal Auditor',
        createdAt: '2026-06-01 10:00:00',
    );

    $this->artisan('audit:backfill-housing-status-histories --dry-run')
        ->expectsTable(
            ['Metric', 'Count'],
            [
                ['Missing histories found', 1],
                ['Histories inserted', 0],
            ],
        )
        ->assertSuccessful();

    expect(DB::table('housing_status_histories')->count())->toBe(0)
        ->and(DB::table('housing_status_history_backfills')->count())->toBe(0)
        ->and(HousingStatus::query()->whereKey($housingStatusId)->exists())->toBeTrue();
});

it('backfills missing housing status histories and tracks inserted ids', function () {
    $status = housingBackfillAssessmentStatus();
    $user = User::factory()->create();

    insertHousingStatusForBackfill(
        housingId: 6102,
        statusId: $status->id,
        userId: $user->id,
        type: 'Legal Auditor',
        createdAt: '2026-06-02 10:00:00',
    );

    insertHousingHistoryForBackfill(
        housingId: 6102,
        statusId: $status->id,
        userId: $user->id,
        type: 'Legal Auditor',
        createdAt: '2026-06-01 10:00:00',
    );

    $this->artisan('audit:backfill-housing-status-histories')
        ->expectsTable(
            ['Metric', 'Count'],
            [
                ['Missing histories found', 1],
                ['Histories inserted', 1],
            ],
        )
        ->assertSuccessful();

    $this->assertDatabaseHas('housing_status_histories', [
        'housing_id' => 6102,
        'status_id' => $status->id,
        'type' => 'Legal Auditor',
        'created_at' => '2026-06-02 10:00:00',
    ]);

    expect(DB::table('housing_status_histories')->count())->toBe(2)
        ->and(DB::table('housing_status_history_backfills')->count())->toBe(1);
});

it('rolls back only housing histories inserted by the backfill command', function () {
    $status = housingBackfillAssessmentStatus();
    $user = User::factory()->create();

    insertHousingStatusForBackfill(
        housingId: 6103,
        statusId: $status->id,
        userId: $user->id,
        type: 'QC/QA Engineer',
        createdAt: '2026-06-03 10:00:00',
    );

    insertHousingHistoryForBackfill(
        housingId: 6104,
        statusId: $status->id,
        userId: $user->id,
        type: 'QC/QA Engineer',
        createdAt: '2026-06-04 10:00:00',
    );

    $this->artisan('audit:backfill-housing-status-histories')
        ->assertSuccessful();

    expect(DB::table('housing_status_histories')->count())->toBe(2);

    $this->artisan('audit:backfill-housing-status-histories --rollback')
        ->expectsTable(
            ['Metric', 'Count'],
            [
                ['Tracked backfills found', 1],
                ['Histories deleted', 1],
                ['Already missing', 0],
            ],
        )
        ->assertSuccessful();

    expect(DB::table('housing_status_histories')->count())->toBe(1)
        ->and(DB::table('housing_status_histories')
            ->where('housing_id', 6104)
            ->where('status_id', $status->id)
            ->where('type', 'QC/QA Engineer')
            ->where('created_at', '2026-06-04 10:00:00')
            ->exists())->toBeTrue()
        ->and(DB::table('housing_status_history_backfills')->whereNull('rolled_back_at')->count())->toBe(0);
});

function housingBackfillAssessmentStatus(): AssessmentStatus
{
    return AssessmentStatus::query()->create([
        'name' => 'accepted_by_housing_backfill_test',
        'label_en' => 'Accepted by housing backfill test',
        'label_ar' => 'Accepted by housing backfill test',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);
}

function insertHousingStatusForBackfill(
    int $housingId,
    int $statusId,
    int $userId,
    string $type,
    string $createdAt,
): int {
    return DB::table('housing_statuses')->insertGetId([
        'housing_id' => $housingId,
        'status_id' => $statusId,
        'user_id' => $userId,
        'type' => $type,
        'notes' => 'Backfill test status',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function insertHousingHistoryForBackfill(
    int $housingId,
    int $statusId,
    int $userId,
    string $type,
    string $createdAt,
): int {
    return DB::table('housing_status_histories')->insertGetId([
        'housing_id' => $housingId,
        'status_id' => $statusId,
        'user_id' => $userId,
        'type' => $type,
        'notes' => 'Existing history',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}
