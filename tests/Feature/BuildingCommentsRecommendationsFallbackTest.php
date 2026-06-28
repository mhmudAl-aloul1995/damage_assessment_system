<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\Building;
use App\Models\EditAssessment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('uses comments recommendations v1 when comments recommendations is null', function (): void {
    $building = Building::query()->create([
        'objectid' => 990001,
        'globalid' => 'comments-fallback-building',
        'comments_recommendations' => null,
        'comments_recommendations_v1' => 'Fallback recommendation',
    ]);

    expect($building->refresh()->comments_recommendations)->toBe('Fallback recommendation');
});

it('keeps comments recommendations value when it exists', function (): void {
    $building = Building::query()->create([
        'objectid' => 990002,
        'globalid' => 'comments-primary-building',
        'comments_recommendations' => 'Primary recommendation',
        'comments_recommendations_v1' => 'Fallback recommendation',
    ]);

    expect($building->refresh()->comments_recommendations)->toBe('Primary recommendation');
});

it('keeps edited comments recommendations ahead of v1 fallback', function (): void {
    $building = Building::query()->create([
        'objectid' => 990003,
        'globalid' => 'comments-edited-building',
        'comments_recommendations' => null,
        'comments_recommendations_v1' => 'Fallback recommendation',
    ]);

    EditAssessment::query()->create([
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'comments_recommendations',
        'field_value' => 'Edited recommendation',
    ]);

    expect($building->refresh()->load('edits')->comments_recommendations)->toBe('Edited recommendation');
});

it('uses assessment obstacle info v1 when assessment obstacle info is null', function (): void {
    $building = Building::query()->create([
        'objectid' => 990004,
        'globalid' => 'assessment-obstacle-info-fallback-building',
        'assessment_obstacle_info' => null,
        'assessment_obstacle_info_v1' => 'Owner refused assessment because there is no damage',
    ]);

    expect($building->refresh()->assessment_obstacle_info)->toBe('Owner refused assessment because there is no damage');
});

it('shows assessment obstacle info v1 as the original audit answer', function (): void {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-token',
        ], 200),
        '*' => Http::response([
            'attachmentInfos' => [],
        ], 200),
    ]);

    $user = User::factory()->create();

    Assessment::query()->create([
        'name' => 'assessment_obstacle_info',
        'label' => 'Assessment obstacle information',
        'hint' => 'وصف العائق',
    ]);

    $building = Building::query()->create([
        'objectid' => 990005,
        'globalid' => 'assessment-obstacle-info-audit-building',
        'assessment_obstacle_info' => null,
        'assessment_obstacle_info_v1' => 'رفض المالك لعدم وجود أضرار',
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson('/damage-assessment/showBuildings?globalid='.$building->globalid);

    $response->assertOk();

    expect(collect($response->json('data'))->firstWhere('name', 'assessment_obstacle_info')['answer'] ?? null)
        ->toBe('رفض المالك لعدم وجود أضرار');
});
