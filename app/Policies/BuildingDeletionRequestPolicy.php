<?php

namespace App\Policies;

use App\Enums\BuildingDeletionStatus;
use App\Models\BuildingDeletionRequest;
use App\Models\TeamLeaderFieldEngineer;
use App\Models\User;

class BuildingDeletionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return $user->can('damage-assessment.building-deletion.view')
            || $user->id === $buildingDeletionRequest->requested_by
            || $this->reviewTeamLeader($user, $buildingDeletionRequest)
            || $this->reviewAreaManager($user, $buildingDeletionRequest)
            || $user->can('damage-assessment.building-deletion.gis-review');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return $user->id === $buildingDeletionRequest->requested_by
            && $buildingDeletionRequest->status === BuildingDeletionStatus::Returned;
    }

    public function reviewTeamLeader(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        if (! $user->hasAnyRole(['Team Leader', 'team Leader'])) {
            return false;
        }

        return $buildingDeletionRequest->status === BuildingDeletionStatus::PendingTeamLeaderReview
            && TeamLeaderFieldEngineer::query()
                ->where('team_leader_id', $user->id)
                ->where('field_engineer_id', $buildingDeletionRequest->requested_by)
                ->exists();
    }

    public function reviewAreaManager(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return $user->hasRole('Area Manager')
            && $buildingDeletionRequest->status === BuildingDeletionStatus::PendingAreaManagerReview
            && (int) $buildingDeletionRequest->area_manager_reviewed_by === (int) $user->id;
    }

    public function reviewGis(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return $user->can('damage-assessment.building-deletion.gis-review');
    }

    public function process(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return $user->can('damage-assessment.building-deletion.process');
    }

    public function viewRawSnapshot(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return $user->can('damage-assessment.building-deletion.view-raw-snapshot');
    }

    public function delete(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return false;
    }

    public function restore(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return false;
    }
}
