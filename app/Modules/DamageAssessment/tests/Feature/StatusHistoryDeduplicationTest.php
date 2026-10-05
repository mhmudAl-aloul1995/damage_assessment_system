<?php

use App\Models\AssessmentStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('reports duplicate status history rows without deleting during a dry run', function () {
    $status = dedupeStatusAssessmentStatus();
    $user = User::factory()->create();

    insertDedupeBuildingHistory(7101, $status->id, $user->id, 'Legal Auditor', 'Same note', '2026-06-01 10:00:00');
    insertDedupeBuildingHistory(7101, $status->id, $user->id, 'Legal Auditor', 'Same note', '2026-06-02 10:00:00');

    $this->artisan('audit:dedupe-status-histories building --dry-run')
        ->expectsTable(
            ['Target', 'Duplicate groups', 'Duplicate rows', 'Rows deleted'],
            [
                ['Building histories', 1, 1, 0],
            ],
        )
        ->assertSuccessful();

    expect(DB::table('building_status_histories')->count())->toBe(2)
        ->and(DB::table('status_history_deduplications')->count())->toBe(0);
});

it('deduplicates building histories and keeps the oldest id', function () {
    $status = dedupeStatusAssessmentStatus();
    $user = User::factory()->create();

    $oldestId = insertDedupeBuildingHistory(7102, $status->id, $user->id, 'Legal Auditor', 'Same note', '2026-06-01 10:00:00');
    $duplicateId = insertDedupeBuildingHistory(7102, $status->id, $user->id, 'Legal Auditor', 'Same note', '2026-06-02 10:00:00');
    insertDedupeBuildingHistory(7102, $status->id, $user->id, 'Legal Auditor', 'Different note', '2026-06-03 10:00:00');

    $this->artisan('audit:dedupe-status-histories building')
        ->expectsTable(
            ['Target', 'Duplicate groups', 'Duplicate rows', 'Rows deleted'],
            [
                ['Building histories', 1, 1, 1],
            ],
        )
        ->assertSuccessful();

    expect(DB::table('building_status_histories')->where('id', $oldestId)->exists())->toBeTrue()
        ->and(DB::table('building_status_histories')->where('id', $duplicateId)->exists())->toBeFalse()
        ->and(DB::table('building_status_histories')->count())->toBe(2)
        ->and(DB::table('status_history_deduplications')->where('history_id', $duplicateId)->exists())->toBeTrue();
});

it('deduplicates housing histories and rolls back deleted rows', function () {
    $status = dedupeStatusAssessmentStatus();
    $user = User::factory()->create();

    $oldestId = insertDedupeHousingHistory(8101, $status->id, $user->id, 'QC/QA Engineer', 'Same note', '2026-06-01 10:00:00');
    $duplicateId = insertDedupeHousingHistory(8101, $status->id, $user->id, 'QC/QA Engineer', 'Same note', '2026-06-02 10:00:00');

    $this->artisan('audit:dedupe-status-histories housing')
        ->assertSuccessful();

    expect(DB::table('housing_status_histories')->where('id', $oldestId)->exists())->toBeTrue()
        ->and(DB::table('housing_status_histories')->where('id', $duplicateId)->exists())->toBeFalse();

    $this->artisan('audit:dedupe-status-histories housing --rollback')
        ->expectsTable(
            ['Target', 'Tracked deletions', 'Rows restored', 'Already present'],
            [
                ['Housing histories', 1, 1, 0],
            ],
        )
        ->assertSuccessful();

    expect(DB::table('housing_status_histories')->where('id', $oldestId)->exists())->toBeTrue()
        ->and(DB::table('housing_status_histories')->where('id', $duplicateId)->exists())->toBeTrue()
        ->and(DB::table('status_history_deduplications')->whereNull('restored_at')->count())->toBe(0);
});

function dedupeStatusAssessmentStatus(): AssessmentStatus
{
    return AssessmentStatus::query()->create([
        'name' => 'dedupe_status_test',
        'label_en' => 'Dedupe status test',
        'label_ar' => 'Dedupe status test',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);
}

function insertDedupeBuildingHistory(
    int $buildingId,
    int $statusId,
    int $userId,
    string $type,
    string $notes,
    string $createdAt,
): int {
    return DB::table('building_status_histories')->insertGetId([
        'building_id' => $buildingId,
        'status_id' => $statusId,
        'user_id' => $userId,
        'type' => $type,
        'notes' => $notes,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function insertDedupeHousingHistory(
    int $housingId,
    int $statusId,
    int $userId,
    string $type,
    string $notes,
    string $createdAt,
): int {
    return DB::table('housing_status_histories')->insertGetId([
        'housing_id' => $housingId,
        'status_id' => $statusId,
        'user_id' => $userId,
        'type' => $type,
        'notes' => $notes,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}
