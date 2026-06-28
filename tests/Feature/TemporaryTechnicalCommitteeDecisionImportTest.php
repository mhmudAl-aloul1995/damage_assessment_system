<?php

use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CommitteeDecision;
use App\Models\CommitteeDecisionSignature;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\Models\User;
use App\services\TemporaryTechnicalCommitteeDecisionImportService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.committee_decisions.arcgis.base_url', '');
    config()->set('services.committee_decisions.arcgis.token', '');
    config()->set('services.arcgis.buildings_url', '');
});

it('temporarily completes building committee decisions from static seed records when the building is in committee review', function () {
    config()->set('services.committee_decisions.arcgis.base_url', 'https://example.test/arcgis/FeatureServer');
    config()->set('services.committee_decisions.arcgis.building_layer_id', 0);
    config()->set('services.committee_decisions.arcgis.identifier_field', 'objectid');
    config()->set('services.committee_decisions.arcgis.token', 'static-token');

    Http::fake([
        'https://example.test/arcgis/FeatureServer/0/updateFeatures' => Http::response([
            'updateResults' => [['success' => true]],
        ], 200),
    ]);

    temporaryCommitteeUsers(['934863572', '900277229', '801933490', '800282667', '956242622']);

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
            'member_id_numbers' => ['934863572', '900277229', '801933490', '800282667', '956242622'],
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
            'member_id_numbers' => ['934863572', '900277229', '801933490', '800282667', '956242622'],
        ],
    ]);

    $decision = CommitteeDecision::query()->whereMorphedTo('decisionable', $building)->firstOrFail();
    $archiveObject = BuildingSurveyArchiveObject::query()
        ->where('committee_decision_id', $decision->id)
        ->where('building_objectid', 3293)
        ->firstOrFail();

    expect($summary['decisions_completed'])->toBe(1)
        ->and($summary['skipped_rows'])->toBe(1)
        ->and($summary['skip_reasons']['not_committee_review'])->toBe(1)
        ->and($summary['issues'][0]['current_status'])->toBe('partially_damaged')
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_COMPLETED)
        ->and($decision->decision_type)->toBe('fully_damaged')
        ->and($decision->action_text)->toBe('اعادة المبنى للمهندس لحصره')
        ->and($decision->arcgis_sync_status)->toBe('synced');

    $building->refresh();

    expect($building->building_damage_status)->toBe('fully_damaged')
        ->and($building->field_status)->toBe('Not_Completed')
        ->and(CommitteeDecisionSignature::query()->where('committee_decision_id', $decision->id)->where('status', 'approved')->count())->toBe(5)
        ->and(CommitteeMember::query()->count())->toBe(5)
        ->and($archiveObject->building_snapshot['building_damage_status'])->toBe('committee_review')
        ->and($archiveObject->building_snapshot['field_status'])->toBe('COMPLETED')
        ->and($archiveObject->committee_decision_snapshot['decision_type'])->toBe('fully_damaged');

    Http::assertSent(function ($request): bool {
        $features = json_decode((string) data_get($request->data(), 'features'), true);

        return str_contains($request->url(), '/0/updateFeatures')
            && data_get($features, '0.attributes.objectid') === 3293
            && data_get($features, '0.attributes.building_damage_status') === 'fully_damaged'
            && data_get($features, '0.attributes.field_status') === 'Not_Completed';
    });
});

it('temporarily completes housing unit committee decisions from static unit sheet records and updates the parent building field status', function () {
    temporaryCommitteeUsers(['801933490', '800282667', '800846958', '804475044', '801113747']);

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
        'member_id_numbers' => ['801933490', '800282667', '800846958', '804475044', '801113747'],
    ]]);

    $decision = CommitteeDecision::query()->whereMorphedTo('decisionable', $unit)->firstOrFail();

    expect($summary['decisions_completed'])->toBe(1)
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_COMPLETED)
        ->and($decision->decision_type)->toBe('partially_damaged')
        ->and(CommitteeDecisionSignature::query()->where('committee_decision_id', $decision->id)->where('status', 'approved')->count())->toBe(5);

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

it('syncs configured municipality signatures onto existing committee review decisions not included in seed records', function () {
    temporaryCommitteeUsers(['801933490', '800282667', '800846958', '804475044', '801113747']);
    $oldSigner = User::factory()->create(['id_no' => '700000001']);
    $oldMember = CommitteeMember::query()->create([
        'user_id' => $oldSigner->id,
        'name' => 'Old Signer',
        'is_active' => true,
        'is_required' => false,
        'sort_order' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 21048,
        'globalid' => 'existing-review-building',
        'building_name' => 'Existing Review Building',
        'building_damage_status' => 'committee_review',
        'governorate' => 'Middle Area',
        'municipalitie' => null,
        'neighborhood' => 'Khan Younis Ref-Camp',
    ]);

    $decision = CommitteeDecision::query()->create([
        'decisionable_type' => Building::class,
        'decisionable_id' => $building->id,
        'decision_type' => null,
        'decision_text' => 'قرار اللجنة: هدم جزئي',
        'status' => CommitteeDecision::STATUS_PENDING_SIGNATURES,
    ]);

    CommitteeDecisionSignature::query()->create([
        'committee_decision_id' => $decision->id,
        'committee_member_id' => $oldMember->id,
        'is_required' => false,
        'sort_order' => 1,
        'status' => 'pending',
    ]);

    $summary = app(TemporaryTechnicalCommitteeDecisionImportService::class)
        ->syncExistingCommitteeReviewDecisionSignatures();

    $decision->refresh()->load('signatures.committeeMember.user');
    $building->refresh();

    expect($summary['decisions_synced'])->toBe(1)
        ->and($summary['decisions_completed'])->toBe(1)
        ->and($summary['skipped_without_decision_type'])->toBe(0)
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_COMPLETED)
        ->and($decision->decision_type)->toBe('partially_damaged')
        ->and($decision->arcgis_sync_status)->toBe('not_configured')
        ->and($building->building_damage_status)->toBe('partially_damaged')
        ->and($building->field_status)->toBe('Not_Completed')
        ->and(BuildingSurveyArchiveObject::query()->where('committee_decision_id', $decision->id)->exists())->toBeTrue()
        ->and($decision->signatures)->toHaveCount(5)
        ->and($decision->signatures->every(fn ($signature): bool => $signature->is_required))->toBeTrue()
        ->and($decision->signatures->every(fn ($signature): bool => $signature->status === 'approved'))->toBeTrue()
        ->and($decision->signatures->every(fn ($signature): bool => $signature->signed_at !== null))->toBeTrue()
        ->and($decision->signatures->pluck('committeeMember.user.id_no')->all())->toBe([
            '801933490',
            '800282667',
            '800846958',
            '804475044',
            '801113747',
        ]);
});

it('temporarily completes existing committee review decisions with a default partial decision type when the decision has no type text', function () {
    temporaryCommitteeUsers(['934863572', '900277229', '801933490', '800282667', '956242622']);

    $building = Building::query()->create([
        'objectid' => 21048,
        'globalid' => 'sarsour-review-building',
        'building_name' => 'Sarsour Review Building',
        'building_damage_status' => 'committee_review',
        'neighborhood' => 'Sarsour',
    ]);

    $decision = CommitteeDecision::query()->create([
        'decisionable_type' => Building::class,
        'decisionable_id' => $building->id,
        'decision_type' => null,
        'decision_text' => null,
        'status' => CommitteeDecision::STATUS_PENDING_SIGNATURES,
    ]);

    $summary = app(TemporaryTechnicalCommitteeDecisionImportService::class)
        ->syncExistingCommitteeReviewDecisionSignatures();

    $decision->refresh()->load('signatures.committeeMember.user');
    $building->refresh();

    expect($summary['decisions_synced'])->toBe(1)
        ->and($summary['decisions_completed'])->toBe(1)
        ->and($summary['skipped_without_decision_type'])->toBe(0)
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_COMPLETED)
        ->and($decision->decision_type)->toBe('partially_damaged')
        ->and($decision->arcgis_sync_status)->toBe('not_configured')
        ->and($building->building_damage_status)->toBe('partially_damaged')
        ->and($building->field_status)->toBe('Not_Completed')
        ->and($decision->signatures->pluck('committeeMember.user.id_no')->all())->toBe([
            '934863572',
            '900277229',
            '801933490',
            '800282667',
            '956242622',
        ]);
});

it('creates and completes missing committee decisions for review records that show no decision yet', function () {
    temporaryCommitteeUsers(['801933490', '800282667', '800846958', '804475044', '801113747']);

    $building = Building::query()->create([
        'objectid' => 18362,
        'globalid' => 'missing-decision-review-building',
        'building_name' => 'Missing Decision Building',
        'building_damage_status' => 'committee_review',
        'neighborhood' => 'Khan Younis Ref-Camp',
    ]);

    $summary = app(TemporaryTechnicalCommitteeDecisionImportService::class)
        ->syncExistingCommitteeReviewDecisionSignatures();

    $decision = CommitteeDecision::query()->whereMorphedTo('decisionable', $building)->firstOrFail();
    $decision->load('signatures.committeeMember.user');
    $building->refresh();

    expect($summary['decisions_synced'])->toBe(1)
        ->and($summary['decisions_completed'])->toBe(1)
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_COMPLETED)
        ->and($decision->decision_type)->toBe('partially_damaged')
        ->and($decision->arcgis_sync_status)->toBe('not_configured')
        ->and($building->building_damage_status)->toBe('partially_damaged')
        ->and($building->field_status)->toBe('Not_Completed')
        ->and($decision->signatures)->toHaveCount(5)
        ->and($decision->signatures->pluck('committeeMember.user.id_no')->all())->toBe([
            '801933490',
            '800282667',
            '800846958',
            '804475044',
            '801113747',
        ]);
});

it('creates and completes missing block f committee decisions with the gaza committee', function () {
    temporaryCommitteeUsers(['934863572', '900277229', '801933490', '800282667', '956242622']);

    $building = Building::query()->create([
        'objectid' => 17363,
        'globalid' => 'block-f-review-building',
        'building_name' => 'Block F Review Building',
        'building_damage_status' => 'committee_review',
        'neighborhood' => 'Block_F',
    ]);

    $summary = app(TemporaryTechnicalCommitteeDecisionImportService::class)
        ->syncExistingCommitteeReviewDecisionSignatures();

    $decision = CommitteeDecision::query()->whereMorphedTo('decisionable', $building)->firstOrFail();
    $decision->load('signatures.committeeMember.user');
    $building->refresh();

    expect($summary['decisions_synced'])->toBe(1)
        ->and($summary['decisions_completed'])->toBe(1)
        ->and($decision->status)->toBe(CommitteeDecision::STATUS_COMPLETED)
        ->and($decision->decision_type)->toBe('partially_damaged')
        ->and($decision->arcgis_sync_status)->toBe('not_configured')
        ->and($building->building_damage_status)->toBe('partially_damaged')
        ->and($building->field_status)->toBe('Not_Completed')
        ->and($decision->signatures)->toHaveCount(5)
        ->and($decision->signatures->pluck('committeeMember.user.id_no')->all())->toBe([
            '934863572',
            '900277229',
            '801933490',
            '800282667',
            '956242622',
        ]);
});

it('exceptionally archives full current records for static excel seed rows even after they left committee review', function () {
    temporaryCommitteeUsers(['934863572', '900277229', '801933490', '800282667', '956242622']);

    $building = Building::query()->create([
        'objectid' => 3293,
        'globalid' => 'already-completed-building-globalid',
        'building_name' => 'Already Completed From Excel',
        'building_damage_status' => 'fully_damaged',
        'field_status' => 'Not_Completed',
    ]);

    $summary = app(TemporaryTechnicalCommitteeDecisionImportService::class)->archiveSeedRecords([[
        'record_type' => 'building',
        'municipality' => 'Gaza',
        'sheet' => 'Gaza buildings',
        'row' => 2,
        'objectid' => 3293,
        'globalid' => $building->globalid,
        'decision_type' => 'fully_damaged',
        'decision_text' => 'Full demolition',
        'action_text' => null,
        'member_id_numbers' => ['934863572', '900277229', '801933490', '800282667', '956242622'],
    ]]);

    $archiveObject = BuildingSurveyArchiveObject::query()
        ->where('source_type', 'temporary_committee_excel_archive')
        ->where('building_objectid', 3293)
        ->firstOrFail();

    expect($summary['rows'])->toBe(1)
        ->and($summary['archived'])->toBe(1)
        ->and($summary['skipped_rows'])->toBe(0)
        ->and($archiveObject->building_snapshot['building_name'])->toBe('Already Completed From Excel')
        ->and($archiveObject->building_snapshot['building_damage_status'])->toBe('fully_damaged')
        ->and($archiveObject->notes)->toContain('sheet=Gaza buildings row=2 decision_type=fully_damaged');
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
