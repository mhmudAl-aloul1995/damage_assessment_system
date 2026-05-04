<?php

declare(strict_types=1);

use App\Models\Building;
use App\Models\EditAssessment;

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
