<?php

use App\Models\CommitteeDecision;
use App\Models\HousingUnit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_units_layer', 1);
});

it('reports target housing unit status differences against latest committee decisions', function (): void {
    $matchingUnitId = DB::table('housing_units')->insertGetId([
        'objectid' => 101,
        'globalid' => 'unit-101',
        'unit_damage_status' => 'committee_review2',
    ]);

    $mismatchingUnitId = DB::table('housing_units')->insertGetId([
        'objectid' => 102,
        'globalid' => 'unit-102',
        'unit_damage_status' => 'partially_damaged2',
    ]);

    $missingTargetUnitId = DB::table('housing_units')->insertGetId([
        'objectid' => 103,
        'globalid' => 'unit-103',
        'unit_damage_status' => 'fully_damaged2',
    ]);

    CommitteeDecision::query()->create([
        'decisionable_type' => HousingUnit::class,
        'decisionable_id' => $matchingUnitId,
        'decision_type' => CommitteeDecision::TYPE_FULLY_DAMAGED,
        'status' => CommitteeDecision::STATUS_COMPLETED,
    ]);

    CommitteeDecision::query()->create([
        'decisionable_type' => HousingUnit::class,
        'decisionable_id' => $matchingUnitId,
        'decision_type' => CommitteeDecision::TYPE_PARTIALLY_DAMAGED,
        'status' => CommitteeDecision::STATUS_COMPLETED,
    ]);

    CommitteeDecision::query()->create([
        'decisionable_type' => HousingUnit::class,
        'decisionable_id' => $mismatchingUnitId,
        'decision_type' => CommitteeDecision::TYPE_FULLY_DAMAGED,
        'status' => CommitteeDecision::STATUS_COMPLETED,
    ]);

    CommitteeDecision::query()->create([
        'decisionable_type' => HousingUnit::class,
        'decisionable_id' => $missingTargetUnitId,
        'decision_type' => CommitteeDecision::TYPE_HIGHER_COMMITTEE,
        'status' => CommitteeDecision::STATUS_COMPLETED,
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => function ($request) {
            if ((int) ($request['resultOffset'] ?? 0) > 0) {
                return Http::response(['features' => []]);
            }

            return Http::response([
                'features' => [
                    ['attributes' => ['objectid' => 5001, 'old_objectid_U' => 101, 'unit_damage_status' => 'partially_damaged2']],
                    ['attributes' => ['objectid' => 5002, 'old_objectid_U' => 102, 'unit_damage_status' => 'partially_damaged2']],
                ],
            ]);
        },
    ]);

    $csvPath = storage_path('app/testing/target-committee-report.csv');
    @unlink($csvPath);

    $exitCode = Artisan::call('arcgis:compare-target-committee', [
        '--status' => CommitteeDecision::STATUS_COMPLETED,
        '--csv' => $csvPath,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('mismatched')
        ->and(file_exists($csvPath))->toBeTrue()
        ->and(file_get_contents($csvPath))->toContain('matched,101')
        ->and(file_get_contents($csvPath))->toContain('mismatch,102')
        ->and(file_get_contents($csvPath))->toContain('target_missing,103')
        ->and(file_get_contents($csvPath))->not->toContain('fully_damaged2,5001');
});
