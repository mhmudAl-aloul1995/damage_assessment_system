<?php

use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CommitteeDecision;
use App\Models\CommitteeDecisionSignature;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\Models\User;
use App\services\TemporaryTechnicalCommitteeDecisionImportService;

it('temporarily completes building committee decisions from static seed records when the building is in committee review', function () {
    temporaryCommitteeUsers(['934863572', '900277229', '801933490', '800282667']);

    $building = Building::query()->create([
        'objectid' => 3293,
        'globalid' => 'building-globalid-3293',
        'building_name' => 'Gaza Committee Building',
        'building_damage_status' => 'committee_review',
        'field_status' => 'COMPLETED',
    ]);

    Building::query()->create([
        'objectid' => 3294,
        'globalid' => 'building-globalid-3294',
        'building_name' => 'Not Committee Building',
        'building_damage_status' => 'partially_damaged',
        'field_status' => 'COMPLETED',
    ]);

    $summary = app(TemporaryTechnicalCommitteeDecisionImportService::class)->importRecords([
        [
            'record_type' => 'building',
            'municipality' => 'غزة',
            'sheet' => 'غزة',
            'row' => 2,
            'objectid' => 3293,
            'globalid' => $building->globalid,
            'decision_type' => 'fully_damaged',
            'decision_text' => 'هدم كلي',
            'action_text' => 'اعادة المبنى للمهندس لحصره',
            'member_id_numbers' => ['934863572', '900277229', '801933490', '800282667'],
        ],
        [
            'record_type' => 'building',
            'municipality' => 'غزة',
            'sheet' => 'غزة',
            'row' => 3,
            'objectid' => 3294,
            'globalid' => 'building-globalid-3294',
            'decision_type' => 'fully_damaged',
            'decision_text' => 'هدم كلي',
            'action_text' => 'اعادة المبنى للمهندس لحصره',
            'member_id_numbers' => ['934863572', '900277229', '801933490', '800282667'],
        ],
    ]);

    $decision = CommitteeDecision::query()->whereMorphedTo('decisionable', $building)->firstOrFail();

    expect($summary['decisions_completed'])->toBe(1)
        ->and($summary['skipped_rows'])->toBe(1)
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_COMPLETED)
        ->and($decision->decision_type)->toBe('fully_damaged')
        ->and($decision->action_text)->toBe('اعادة المبنى للمهندس لحصره')
        ->and($decision->arcgis_sync_status)->toBe('skipped');

    $building->refresh();

    expect($building->building_damage_status)->toBe('fully_damaged')
        ->and($building->field_status)->toBe('Not_Completed')
        ->and(CommitteeDecisionSignature::query()->where('committee_decision_id', $decision->id)->where('status', 'approved')->count())->toBe(4)
        ->and(CommitteeMember::query()->count())->toBe(4)
        ->and(BuildingSurveyArchiveObject::query()->where('committee_decision_id', $decision->id)->where('building_objectid', 3293)->exists())->toBeTrue();
});

it('temporarily completes housing unit committee decisions from static unit sheet records and updates the parent building field status', function () {
    temporaryCommitteeUsers(['801933490', '800282667', '800846958', '804475044']);

    $building = Building::query()->create([
        'objectid' => 598,
        'globalid' => 'parent-building-globalid',
        'building_name' => 'Khan Younis Parent',
        'building_damage_status' => 'partially_damaged',
        'field_status' => 'COMPLETED',
    ]);

    $unit = HousingUnit::query()->create([
        'objectid' => 1082,
        'globalid' => 'unit-globalid-1082',
        'parentglobalid' => $building->globalid,
        'housing_unit_number' => '2',
        'unit_damage_status' => 'committee_review2',
    ]);

    $summary = app(TemporaryTechnicalCommitteeDecisionImportService::class)->importRecords([[
        'record_type' => 'housing-unit',
        'municipality' => 'خانيونس',
        'sheet' => 'لجان - وحدات خانيونس',
        'row' => 2,
        'objectid' => 1082,
        'globalid' => $unit->globalid,
        'decision_type' => 'partially_damaged',
        'decision_text' => 'هدم جزئي',
        'action_text' => 'اعادة المبنى للمهندس لحصره',
        'member_id_numbers' => ['801933490', '800282667', '800846958', '804475044'],
    ]]);

    $decision = CommitteeDecision::query()->whereMorphedTo('decisionable', $unit)->firstOrFail();

    expect($summary['decisions_completed'])->toBe(1)
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_COMPLETED)
        ->and($decision->decision_type)->toBe('partially_damaged')
        ->and(CommitteeDecisionSignature::query()->where('committee_decision_id', $decision->id)->where('status', 'approved')->count())->toBe(4);

    $unit->refresh();
    $building->refresh();

    expect($unit->unit_damage_status)->toBe('partially_damaged2')
        ->and($building->field_status)->toBe('Not_Completed')
        ->and(BuildingSurveyArchiveObject::query()
            ->where('committee_decision_id', $decision->id)
            ->where('building_objectid', 598)
            ->where('housing_unit_objectid', 1082)
            ->exists())->toBeTrue();
});

/**
 * @param  list<string>  $idNumbers
 */
function temporaryCommitteeUsers(array $idNumbers): void
{
    foreach ($idNumbers as $index => $idNumber) {
        User::factory()->create([
            'name' => "Committee User {$idNumber}",
            'id_no' => $idNumber,
            'phone' => '05900000'.$index,
        ]);
    }
}
