<?php

use App\Models\AssessmentStatus;
use App\Models\AssignedAssessmentUser;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\User;
use App\Modules\DamageAssessment\Http\Controllers\Audit\auditController;
use Illuminate\Support\Collection;

function auditEngineerNameFor(Building $building, array $statuses): string
{
    $method = new ReflectionMethod(auditController::class, 'auditEngineerName');
    $method->setAccessible(true);

    return $method->invoke(new auditController, $building, $statuses);
}

function auditBuildingWithEngineers(?string $statusName = null): Building
{
    $assignedUser = new User(['name' => 'Assigned Middle Engineer']);
    $statusUser = new User(['name' => 'Status Middle Auditor']);

    $assignment = new AssignedAssessmentUser(['type' => 'QC/QA Engineer']);
    $assignment->setRelation('user', $assignedUser);

    $building = new Building;
    $building->setRelation('assignedUsers', new Collection([$assignment]));

    if ($statusName !== null) {
        $status = new BuildingStatus;
        $status->setRelation('status', new AssessmentStatus(['name' => $statusName]));
        $status->setRelation('user', $statusUser);
        $building->setRelation('engineerStatus', $status);
    }

    return $building;
}

it('keeps showing the assigned engineer when no engineering status filter is selected', function () {
    $building = auditBuildingWithEngineers('accepted_by_engineer');

    expect(auditEngineerNameFor($building, []))->toBe('Assigned Engineer');
});

it('shows the assigned engineer for the assigned engineering status', function () {
    $building = auditBuildingWithEngineers('assigned_to_engineer');

    expect(auditEngineerNameFor($building, ['assigned_to_engineer']))->toBe('Assigned Engineer');
});

it('shows the user who set the current engineering status for accepted rows', function () {
    $building = auditBuildingWithEngineers('accepted_by_engineer');

    expect(auditEngineerNameFor($building, ['accepted_by_engineer']))->toBe('Status Auditor');
});
