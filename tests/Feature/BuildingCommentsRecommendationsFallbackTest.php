<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\Building;
use App\Models\EditAssessment;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

it('uses comments recommendations v1 when comments recommendations is null', function (): void {
    $building = Building::query()->create([
        'objectid' => 990001,
        'globalid' => 'comments-fallback-building',
        'comments_recommendations' => null,
        'comments_recommendations_v1' => 'Fallback recommendation',
    ]);

    expect($building->refresh()->comments_recommendations)->toBe('Fallback recommendation');
});

it('shows the latest edited value as the audit answer for read only field engineers', function (): void {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-token',
        ], 200),
        '*' => Http::response([
            'attachmentInfos' => [],
        ], 200),
    ]);

    Role::query()->create([
        'name' => 'Field Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'username_arcgis' => 'field.engineer',
    ]);
    $user->assignRole('Field Engineer');

    Assessment::query()->create([
        'name' => 'owner_name',
        'label' => 'Owner Name',
        'hint' => 'Owner full name',
    ]);

    $building = Building::query()->create([
        'objectid' => 990006,
        'globalid' => 'read-only-latest-edit-building',
        'owner_name' => 'Original Owner',
        'assignedto' => 'field.engineer',
    ]);

    EditAssessment::query()->create([
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'owner_name',
        'field_value' => 'First Edited Owner',
        'user_id' => $user->id,
    ]);

    EditAssessment::query()->create([
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'owner_name',
        'field_value' => 'Latest Edited Owner',
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson('/damage-assessment/showBuildings?globalid='.$building->globalid);

    $response->assertOk();

    $ownerRow = collect($response->json('data'))->firstWhere('name', 'owner_name');

    expect($ownerRow['answer'] ?? null)
        ->toBe('Latest Edited Owner')
        ->and($ownerRow['editAnswer'] ?? null)
        ->toBeNull();
});

it('shows the latest edited housing value as the audit answer for read only field engineers', function (): void {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-token',
        ], 200),
        '*' => Http::response([
            'attachmentInfos' => [],
        ], 200),
    ]);

    Role::query()->create([
        'name' => 'Field Engineer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'username_arcgis' => 'field.engineer',
    ]);
    $user->assignRole('Field Engineer');

    Assessment::query()->create([
        'name' => 'unit_owner',
        'label' => 'Unit Owner',
        'hint' => 'Housing unit owner',
    ]);

    $building = Building::query()->create([
        'objectid' => 990007,
        'globalid' => 'read-only-latest-edit-housing-building',
        'assignedto' => 'field.engineer',
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 990008,
        'globalid' => 'read-only-latest-edit-housing-unit',
        'parentglobalid' => $building->globalid,
        'unit_owner' => 'Original Unit Owner',
    ]);

    EditAssessment::query()->create([
        'global_id' => $housingUnit->globalid,
        'type' => 'housing_table',
        'field_name' => 'unit_owner',
        'field_value' => 'First Edited Unit Owner',
        'user_id' => $user->id,
    ]);

    EditAssessment::query()->create([
        'global_id' => $housingUnit->globalid,
        'type' => 'housing_table',
        'field_name' => 'unit_owner',
        'field_value' => 'Latest Edited Unit Owner',
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson('/damage-assessment/showHousings?globalid='.$housingUnit->globalid);

    $response->assertOk();

    $ownerRow = collect($response->json('data'))->firstWhere('name', 'unit_owner');

    expect($ownerRow['answer'] ?? null)
        ->toBe('Latest Edited Unit Owner')
        ->and($ownerRow['editAnswer'] ?? null)
        ->toBeNull();
});

it('shows the latest edited housing value for users without edit permission', function (): void {
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
        'name' => 'housing_unit_group',
        'label' => 'Housing Unit',
        'hint' => '',
    ]);

    Assessment::query()->create([
        'name' => 'unit_damage_status',
        'label' => 'Unit Damage Status',
        'hint' => 'Damage status',
    ]);

    $building = Building::query()->create([
        'objectid' => 990009,
        'globalid' => 'read-only-no-edit-housing-building',
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 990010,
        'globalid' => 'read-only-no-edit-housing-unit',
        'parentglobalid' => $building->globalid,
        'unit_damage_status' => 'Partially Damaged',
    ]);

    EditAssessment::query()->create([
        'global_id' => $housingUnit->globalid,
        'type' => 'housing_table',
        'field_name' => 'unit_damage_status',
        'field_value' => 'Totally Damaged',
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson('/damage-assessment/showHousings?globalid='.$housingUnit->globalid);

    $response->assertOk();

    $damageStatusRow = collect($response->json('data'))->firstWhere('name', 'unit_damage_status');

    expect($damageStatusRow['answer'] ?? null)
        ->toBe('Totally Damaged')
        ->and($damageStatusRow['editAnswer'] ?? null)
        ->toBeNull();
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
