<?php

use App\Models\AssessmentStatus;

it('resolves reusable badge classes labels and html from status names', function () {
    app()->setLocale('ar');

    $status = new AssessmentStatus([
        'name' => 'assignedto_engineer',
        'label_en' => 'Assigned To Engineer',
        'label_ar' => 'محول للمهندس',
        'stage' => 'engineer',
        'order_step' => 2,
    ]);

    expect($status->color)->toBe('info')
        ->and($status->badge_class)->toBe('badge badge-light-info')
        ->and($status->label)->toBe('محول للمهندس')
        ->and($status->badge_html)->toContain('badge badge-light-info')
        ->and(AssessmentStatus::badgeClassForName('final_reject'))->toBe('badge badge-light-danger')
        ->and(AssessmentStatus::badgeClassForName('unknown_status'))->toBe('badge badge-light-light');
});
