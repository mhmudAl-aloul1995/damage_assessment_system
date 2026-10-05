<?php

use App\Models\Building;
use App\Models\CommitteeDecision;
use Database\Seeders\CommitteeDecisionExcelSeeder;

it('seeds committee decisions from the bundled committee review workbook', function () {
    $building = Building::query()->create([
        'objectid' => 3293,
        'globalid' => '{0FEB5DF2-7062-4F23-B61B-BB5C046A62E1}',
        'building_name' => 'Committee Review Seed Building',
        'building_damage_status' => 'committee_review',
    ]);

    $this->seed(CommitteeDecisionExcelSeeder::class);

    $decision = CommitteeDecision::query()
        ->whereMorphedTo('decisionable', $building)
        ->firstOrFail();

    expect($decision->decision_text)->toBe('هدم كلي')
        ->and($decision->action_text)->toBe('اعادة المبنى للمهندس لحصره')
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_PENDING_SIGNATURES);
});
