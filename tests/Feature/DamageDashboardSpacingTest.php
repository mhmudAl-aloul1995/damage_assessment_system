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
        ->toContain('--summary-row-gap: 1rem;')
        ->toContain('--summary-row-gap: 0.9rem;')
        ->toContain('--summary-row-gap: 0.8rem;')
        ->toContain('--summary-row-gap: 0.7rem;');

    expect($labelMatches['css'] ?? '')
        ->toContain('display: block;')
        ->toContain('text-wrap: nowrap !important;')
        ->toContain('white-space: nowrap !important;')
        ->toContain('overflow-wrap: normal;')
        ->not->toContain('-webkit-line-clamp');

    expect($dashboardView)
        ->toContain('col-sm-6 col-lg-6 col-xxl-3')
        ->not->toContain('col-sm-6 col-xl-3');
});

test('dashboard toolbar has responsive control groups for all devices', function () {
    $dashboardView = file_get_contents(base_path('app/Modules/DamageAssessment/views/dashboard/damageAssessment.blade.php'));

    expect($dashboardView)
        ->toContain('toolbar-control-group toolbar-neighborhood-group')
        ->toContain('toolbar-control-group toolbar-date-group')
        ->toContain('toolbar-control-group toolbar-period-group')
        ->toContain('@media (min-width: 768px) and (max-width: 1199.98px)')
        ->toContain('@media (max-width: 767.98px)')
        ->toContain('@media (max-width: 420px)')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        ->toContain('grid-template-columns: minmax(0, 1fr);')
        ->toContain('flex: 1 1 100%;')
        ->toContain('width: 100% !important;');
});

test('dashboard summary icons match labels and use a consistent size', function () {
    $dashboardView = file_get_contents(base_path('app/Modules/DamageAssessment/views/dashboard/damageAssessment.blade.php'));

    expect($dashboardView)
        ->toContain('.dashboard-summary-body .symbol-label .ki-duotone')
        ->toContain('font-size: 1.15rem !important;')
        ->toContain('ki-shield-cross')
        ->toContain('ki-rescue')
        ->toContain('ki-questionnaire-tablet')
        ->toContain('ki-route')
        ->toContain('ki-check-circle')
        ->toContain('ki-warning-2')
        ->toContain('ki-flash-circle')
        ->toContain('ki-bucket');
});
