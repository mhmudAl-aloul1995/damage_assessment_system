<?php

declare(strict_types=1);

use App\Models\Building;
use App\Models\EditAssessment;
use Illuminate\Support\Collection;

it('uses comments recommendations v1 when comments recommendations is null', function (): void {
    $building = new Building([
        'comments_recommendations' => null,
        'comments_recommendations_v1' => 'Fallback recommendation',
    ]);

    expect($building->comments_recommendations)->toBe('Fallback recommendation');
});

it('keeps comments recommendations value when it exists', function (): void {
    $building = new Building([
        'comments_recommendations' => 'Primary recommendation',
        'comments_recommendations_v1' => 'Fallback recommendation',
    ]);

    expect($building->comments_recommendations)->toBe('Primary recommendation');
});

it('keeps edited comments recommendations ahead of v1 fallback', function (): void {
    $building = new Building([
        'comments_recommendations' => null,
        'comments_recommendations_v1' => 'Fallback recommendation',
    ]);

    $building->setRelation('edits', new Collection([
        new EditAssessment([
            'field_name' => 'comments_recommendations',
            'field_value' => 'Edited recommendation',
        ]),
    ]));

    expect($building->comments_recommendations)->toBe('Edited recommendation');
});
