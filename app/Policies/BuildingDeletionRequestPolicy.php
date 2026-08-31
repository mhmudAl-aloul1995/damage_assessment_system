<?php

namespace App\Policies;

use App\Models\BuildingDeletionRequest;
use App\Models\User;

class BuildingDeletionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('damage-assessment.building-deletion.view')
            || $user->can('damage-assessment.building-deletion.request')
            || $user->can('damage-assessment.building-deletion.gis-review');
    }

    public function view(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return $user->can('damage-assessment.building-deletion.view')
            || $user->id === $buildingDeletionRequest->requested_by
            || $user->can('damage-assessment.building-deletion.gis-review');
    }

    public function create(User $user): bool
    {
        return $user->can('damage-assessment.building-deletion.request');
    }

    public function update(User $user, BuildingDeletionRequest $buildingDeletionRequest): bool
    {
        return $user->id === $buildingDeletionRequest->requested_by
            && $buildingDeletionRequest->status === \App\Enums\BuildingDeletionStatus::Returned;
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
