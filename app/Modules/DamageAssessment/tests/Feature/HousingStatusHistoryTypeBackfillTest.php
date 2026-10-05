<?php

use App\Models\AssessmentStatus;
use App\Models\HousingStatus;
use App\Models\HousingStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

it('backfills empty history type from housing statuses before user roles', function () {
    $legalAuditor = userWithRole('Legal Auditor');
    $status = assessmentStatus('accepted_by_engineer');

    insertEmptyHousingHistory(
        housingId: 1001,
        statusId: $status->id,
        userId: $legalAuditor->id,
    );

    HousingStatus::query()->create([
        'housing_id' => 1001,
        'status_id' => $status->id,
        'user_id' => $legalAuditor->id,
        'type' => 'QC/QA Engineer',
    ]);

    $this->artisan('audit:backfill-housing-status-history-types')
        ->assertSuccessful();

    expect(historyTypeForHousing(1001))->toBe('QC/QA Engineer');
});

it('backfills empty history type from the history user role when no housing status exists', function () {
    $legalAuditor = userWithRole('Legal Auditor');
    $status = assessmentStatus('accepted_by_legal');

    insertEmptyHousingHistory(
        housingId: 1002,
        statusId: $status->id,
        userId: $legalAuditor->id,
    );

    $this->artisan('audit:backfill-housing-status-history-types')
        ->assertSuccessful();

    expect(historyTypeForHousing(1002))->toBe('Legal Auditor');
});

it('leaves unresolved empty history types unchanged instead of guessing', function () {
    $user = User::factory()->create();
    $status = assessmentStatus('need_review');

    insertEmptyHousingHistory(
        housingId: 1003,
        statusId: $status->id,
        userId: $user->id,
    );

    $this->artisan('audit:backfill-housing-status-history-types')
        ->expectsTable(
            ['Metric', 'Count'],
            [
                ['Empty histories found', 1],
                ['Resolved from housing_statuses', 0],
                ['Resolved from user roles', 0],
                ['Still unresolved', 1],
            ],
        )
        ->assertSuccessful();

    expect(historyTypeForHousing(1003))->toBe('');
});

it('prevents creating new housing status history records with an empty type', function () {
    $status = assessmentStatus('accepted_by_engineer');

    HousingStatusHistory::query()->create([
        'housing_id' => 1004,
        'status_id' => $status->id,
        'type' => '',
    ]);
})->throws(InvalidArgumentException::class, 'Housing status history type must not be empty.');

function assessmentStatus(string $name): AssessmentStatus
{
    return AssessmentStatus::query()->create([
        'name' => $name,
        'label_en' => $name,
        'label_ar' => $name,
        'stage' => 'engineer',
        'order_step' => 1,
    ]);
}

function userWithRole(string $roleName): User
{
    $role = Role::findOrCreate($roleName, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function insertEmptyHousingHistory(int $housingId, int $statusId, ?int $userId): void
{
    DB::table('housing_status_histories')->insert([
        'housing_id' => $housingId,
        'status_id' => $statusId,
        'user_id' => $userId,
        'notes' => null,
        'type' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function historyTypeForHousing(int $housingId): ?string
{
    return DB::table('housing_status_histories')
        ->where('housing_id', $housingId)
        ->value('type');
}
