<?php

test('audit table keeps all columns with responsive text cells', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/DamageAssessment/audit.blade.php');

    expect($view)
        ->toContain('audit-table-wrapper')
        ->toContain('audit-cell-text')
        ->toContain('renderAuditTextCell')
        ->toContain('renderAuditLtrCell')
        ->toContain('audit-cell-date')
        ->toContain('autoWidth: false')
        ->toContain('scrollX: false')
        ->toContain('width: 100% !important')
        ->toContain('overflow-x: visible');
});

test('assessment audit inline edits resolve missing global ids before saving', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/DamageAssessment/assessmentAudit.blade.php');

    expect($view)
        ->toContain('function resolveInlineGlobalId')
        ->toContain("type === 'building_table'")
        ->toContain("type === 'housing_table'")
        ->toContain('يرجى اختيار الوحدة أولاً');
});
