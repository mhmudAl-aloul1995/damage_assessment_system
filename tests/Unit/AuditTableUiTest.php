<?php

test('audit table keeps all columns with responsive text cells', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/audit/audit.blade.php');
    $exportService = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/Services/Audit/AuditExportService.php');

    expect($view)
        ->toContain('audit-table-wrapper')
        ->toContain('audit-cell-text')
        ->toContain('renderAuditTextCell')
        ->toContain('renderAuditLtrCell')
        ->toContain('audit-cell-date')
        ->toContain('autoWidth: false')
        ->toContain('scrollX: true')
        ->toContain('width: 100% !important')
        ->toContain('min-width: 940px')
        ->toContain('table-layout: fixed')
        ->toContain('padding: 0.45rem 0.35rem')
        ->toContain('font-size: 0.74rem')
        ->toContain("width: '22%'")
        ->toContain("width: '10%'")
        ->toContain("width: '9%'")
        ->toContain("width: '11%'")
        ->toContain('overflow-x: auto')
        ->toContain('auditExportModal')
        ->toContain('auditExportForm')
        ->toContain("route('audit.export')")
        ->toContain('building_columns[]')
        ->toContain('housing_columns[]')
        ->toContain('toggle_select_column')
        ->toContain('const selectColumn = table.column(0)')
        ->toContain('visible: false');

    expect($exportService)
        ->toContain('public function buildingColumns(): array')
        ->toContain('public function housingColumns(): array')
        ->toContain("'governorate' =>")
        ->toContain("'municipality' =>")
        ->toContain("'neighborhood' =>")
        ->toContain('building_status_notes')
        ->toContain('housing_status_notes');
});

test('assessment audit inline edits resolve missing global ids before saving', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php');

    expect($view)
        ->toContain('assessment-audit-page')
        ->toContain('overflow: visible')
        ->toContain('audit-summary-column .audit-sticky-menu')
        ->toContain('audit-actions-sticky')
        ->toContain('position: fixed !important')
        ->toContain('top: 165px !important')
        ->toContain('right: var(--audit-summary-right, 95px)')
        ->toContain('width: var(--audit-summary-width, 280px)')
        ->toContain('function syncFixedAuditSummary')
        ->toContain('--audit-summary-right')
        ->toContain('--audit-summary-width')
        ->toContain('#kt_app_main, #kt_app_content, #kt_app_content_container')
        ->not->toContain('audit-sticky-menu .audit-sticky-menu')
        ->toContain('top: 78px !important')
        ->toContain('table-layout: fixed !important')
        ->toContain('autoWidth: false')
        ->not->toContain('min-w-280px')
        ->toContain('function resolveInlineGlobalId')
        ->toContain("type === 'building_table'")
        ->toContain("type === 'housing_table'")
        ->toContain('يرجى اختيار الوحدة أولاً');
});
