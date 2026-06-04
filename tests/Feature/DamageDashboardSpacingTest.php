<?php

test('dashboard summary cards use compact vertical spacing', function () {
    $dashboardView = file_get_contents(base_path('app/Modules/DamageAssessment/views/dashboard/damageAssessment.blade.php'));
    preg_match('/\.damage-dashboard-stats \.dashboard-summary-body \{(?<css>.*?)\n\t\t\}/s', $dashboardView, $matches);

    expect($matches['css'] ?? '')
        ->toContain('justify-content: flex-start;')
        ->toContain('gap: var(--summary-row-gap);')
        ->not->toContain('justify-content: space-between;');

    expect($dashboardView)
        ->toContain('--summary-row-gap: 0.35rem;')
        ->toContain('--summary-row-gap: 0.3rem;')
        ->toContain('--summary-row-gap: 0.25rem;');
});
