<?php

use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CommitteeDecision;
use App\Models\CommitteeDecisionSignature;
use App\Models\CommitteeMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    app(RolesAndPermissionsSeeder::class)->run();
});

it('reports temporary technical committee rollback without changing records during dry run', function () {
    [$building, $decision] = createTemporaryCommitteeRollbackFixture();

    Artisan::call('committee:rollback-temporary-technical-seed');

    $building->refresh();
    $decision->refresh();

    expect($building->building_damage_status)->toBe('fully_damaged')
        ->and($building->field_status)->toBe('Not_Completed')
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_COMPLETED)
        ->and(CommitteeDecisionSignature::query()->where('committee_decision_id', $decision->id)->count())->toBe(1)
        ->and(Artisan::output())->toContain('Dry run completed');
});

it('rolls back temporary technical committee changes from archive snapshots', function () {
    [$building, $decision] = createTemporaryCommitteeRollbackFixture();

    Artisan::call('committee:rollback-temporary-technical-seed', ['--force' => true]);

    $building->refresh();
    $decision->refresh();

    expect($building->building_damage_status)->toBe('committee_review')
        ->and($building->field_status)->toBe('COMPLETED')
        ->and($building->building_name)->toBe('Old Committee Building Name')
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_PENDING_SIGNATURES)
        ->and($decision->completed_at)->toBeNull()
        ->and($decision->arcgis_sync_status)->toBeNull()
        ->and(CommitteeDecisionSignature::query()->where('committee_decision_id', $decision->id)->count())->toBe(0);
});

/**
 * @return array{Building, CommitteeDecision}
 */
function createTemporaryCommitteeRollbackFixture(): array
{
    $user = User::factory()->create();
    $member = CommitteeMember::query()->create([
        'user_id' => $user->id,
        'name' => 'Temporary Committee Member',
        'is_active' => true,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 4401,
        'globalid' => 'temporary-rollback-building',
        'building_name' => 'Current Committee Building Name',
        'building_damage_status' => 'fully_damaged',
        'field_status' => 'Not_Completed',
    ]);

    $decision = CommitteeDecision::query()->create([
        'decisionable_type' => Building::class,
        'decisionable_id' => $building->id,
        'decision_type' => 'fully_damaged',
        'decision_text' => 'Temporary decision',
        'status' => CommitteeDecision::STATUS_COMPLETED,
        'completed_at' => now(),
        'arcgis_sync_status' => 'skipped',
        'arcgis_last_response' => 'Temporary seed updated local status fields only.',
    ]);

    CommitteeDecisionSignature::query()->create([
        'committee_decision_id' => $decision->id,
        'committee_member_id' => $member->id,
        'is_required' => true,
        'sort_order' => 1,
        'status' => 'approved',
        'signed_at' => now(),
        'signed_by_user_id' => $user->id,
    ]);

    BuildingSurveyArchiveObject::query()->create([
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'source_type' => 'committee_decision',
        'committee_decision_id' => $decision->id,
        'archived_by' => $user->id,
        'archived_at' => now()->subMinute(),
        'notes' => 'Temporary technical committee seed: Gaza',
        'building_snapshot' => [
            'objectid' => $building->objectid,
            'globalid' => $building->globalid,
            'building_name' => 'Old Committee Building Name',
            'building_damage_status' => 'committee_review',
            'field_status' => 'COMPLETED',
        ],
        'committee_decision_snapshot' => [
            'decision_type' => 'fully_damaged',
            'status' => CommitteeDecision::STATUS_COMPLETED,
        ],
    ]);

    BuildingSurveyArchiveObject::query()->create([
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'source_type' => 'temporary_committee_excel_archive',
        'committee_decision_id' => $decision->id,
        'archived_by' => $user->id,
        'archived_at' => now(),
        'notes' => 'Exceptional archive from temporary committee Excel seed.',
        'building_snapshot' => [
            'objectid' => $building->objectid,
            'globalid' => $building->globalid,
            'building_name' => 'Current Committee Building Name',
            'building_damage_status' => 'fully_damaged',
            'field_status' => 'Not_Completed',
        ],
    ]);

    return [$building, $decision];
}
