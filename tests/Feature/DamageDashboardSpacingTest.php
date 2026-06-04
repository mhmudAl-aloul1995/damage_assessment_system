<?php

test('dashboard summary cards use compact vertical spacing', function () {
    $dashboardView = file_get_contents(base_path('app/Modules/DamageAssessment/views/dashboard/damageAssessment.blade.php'));
    preg_match('/\.damage-dashboard-stats \.dashboard-summary-body \{(?<css>.*?)\n\t\t\}/s', $dashboardView, $matches);
    preg_match('/\.damage-dashboard-stats \.d-flex\.align-items-center\.flex-wrap\.w-100>\.mb-1\.pe-3\.flex-grow-1 a \{(?<css>.*?)\n\t\t\}/s', $dashboardView, $labelMatches);

    expect($matches['css'] ?? '')
        ->toContain('height: auto;')
        ->toContain('max-height: none;')
        ->toContain('margin-inline: var(--summary-body-inline-space) !important;')
        ->toContain('flex: 1 1 auto;')
        ->toContain('justify-content: flex-start;')
        ->toContain('gap: var(--summary-row-gap);')
        ->toContain('overflow: visible;')
        ->toContain('padding: var(--summary-body-padding-y) var(--summary-body-padding-x) !important;')
        ->not->toContain('justify-content: space-between;');

    expect($dashboardView)
        ->toContain('--summary-body-inline-space: 2.25rem;')
        ->toContain('--summary-body-inline-space: 1rem;')
        ->toContain('--summary-body-inline-space: 0.75rem;')
        ->toContain('--summary-item-min-height: 2.9rem;')
        ->toContain('--summary-item-min-height: 2.3rem;')
        ->toContain('--summary-row-gap: 0.35rem;')
        ->toContain('--summary-row-gap: 0.3rem;')
        ->toContain('--summary-row-gap: 0.25rem;');

    expect($labelMatches['css'] ?? '')
        ->toContain('display: block;')
        ->toContain('text-wrap: nowrap;')
        ->toContain('white-space: nowrap;')
        ->toContain('overflow-wrap: normal;')
        ->not->toContain('-webkit-line-clamp');
});
