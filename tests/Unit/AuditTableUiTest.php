<?php

test('audit table keeps all columns with responsive text cells', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/audit/audit.blade.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/Http/Controllers/Audit/auditController.php');
    $exportService = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/Services/Audit/AuditExportService.php');

    expect($view)
        ->toContain('audit-table-wrapper')
        ->toContain('audit-cell-text')
        ->toContain('renderAuditTextCell')
        ->toContain('renderAuditLtrCell')
        ->toContain('audit-cell-date')
        ->toContain('autoWidth: false')
        ->toContain('scrollX: true')
        ->toContain('responsive: false')
        ->toContain('ordering: false')
        ->toContain("targets: '_all'")
        ->toContain('width: 100% !important')
        ->toContain('min-width: 940px')
        ->toContain('table-layout: fixed')
        ->toContain('padding: 0.45rem 0.35rem')
        ->toContain('#kt_datatable_audits_wrapper table.dataTable thead th.sorting::before')
        ->toContain('background-image: none !important')
        ->toContain('.dt-column-order')
        ->toContain('font-size: 0.74rem')
        ->toContain("#kt_datatable_audits tbody td {\n\t\t\tfont-size: 1rem;")
        ->not->toContain('font-size: .1rem')
        ->toContain('audit-select-cell')
        ->toContain('audit-actions-cell')
        ->toContain('float: none !important')
        ->toContain('#kt_datatable_audits_wrapper table.dataTable .audit-select-cell')
        ->toContain('position: absolute')
        ->toContain('left: 50%')
        ->toContain('top: 50%')
        ->toContain('transform: translate(-50%, -50%) !important')
        ->toContain("width: '64px'")
        ->toContain("width: '16%'")
        ->toContain("width: '8%'")
        ->toContain("width: '12%'")
        ->toContain("width: '9%'")
        ->toContain("width: '11%'")
        ->toContain("width: '10%'")
        ->toContain('table.columns.adjust()')
        ->toContain('overflow-x: auto')
        ->toContain('auditExportModal')
        ->toContain('auditExportForm')
        ->toContain("route('audit.export')")
        ->toContain('notes_history')
        ->toContain('no_notes_history')
        ->toContain('notesHistory')
        ->toContain('escapeAuditCell(item.notes)')
        ->toContain('btn-building-attachments')
        ->toContain('buildingAttachmentsModal')
        ->toContain('معاينة')
        ->toContain('renderAttachmentPreview')
        ->toContain('isImageAttachment')
        ->toContain('object-fit-cover')
        ->toContain("route('audit.building.attachments.index'")
        ->toContain("route('audit.building.attachments.store'")
        ->toContain("route('audit.building.attachments.replace'")
        ->toContain("route('audit.building.attachments.destroy'")
        ->toContain('building_columns[]')
        ->toContain('housing_columns[]')
        ->toContain('toggle_select_column')
        ->toContain('const selectColumn = table.column(0)')
        ->toContain('visible: false')
        ->toContain('$isFieldEngineerAudit')
        ->toContain("route('audit.fieldEngineer')");

    expect($controller)
        ->toContain('show_all_notes')
        ->toContain('buildingAttachments')
        ->toContain('storeBuildingAttachment')
        ->toContain('replaceBuildingAttachment')
        ->toContain('destroyBuildingAttachment')
        ->toContain('ArcgisAttachmentBackupService')
        ->toContain('backupBuildingAttachment($building, $attachmentId, \'replace\'')
        ->toContain('backupBuildingAttachment($building, $attachmentId, \'delete\'');

    expect($exportService)
        ->toContain('public function buildingColumns(): array')
        ->toContain('public function housingColumns(): array')
        ->toContain("'governorate' =>")
        ->toContain("'municipality' =>")
        ->toContain("'neighborhood' =>")
        ->toContain('building_status_notes')
        ->toContain('housing_status_notes');
});

test('assessment status actions are limited to the matching audit role', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/Http/Controllers/Audit/auditController.php');

    expect($view)
        ->toContain('function setAssessmentActiveStatusButtons')
        ->toContain("button.hasClass('is-active')")
        ->toContain("setAssessmentActiveStatusButtons('.building-status-btn', buildingEngineeringStatus, buildingLegalStatus, buildingCurrentStatus)")
        ->toContain("setAssessmentActiveStatusButtons('.housing-status-btn', row.current_engineering_status, row.current_legal_status, row.current_status)")
        ->toContain('function keepAttachmentRowsVisible')
        ->toContain('return pinnedAttachmentRows.concat(filteredRows.filter(row => !pinnedRows.has(row)))')
        ->toContain('rows = keepAttachmentRowsVisible(lastBuildingRows, rows)')
        ->toContain('rows = keepAttachmentRowsVisible(lastHousingRows, rows)')
        ->not->toContain('$canSetLegalStatus')
        ->not->toContain('$canSetEngineeringStatus')
        ->not->toContain('@disabled(! $canSetLegalStatus)')
        ->not->toContain('@disabled(! $canSetEngineeringStatus)')
        ->not->toContain('canEnableStatusButton')
        ->not->toContain("@hasanyrole('Legal Auditor|Database Officer|Auditing Supervisor|Team Leader|Field Engineer|field Engineer')")
        ->not->toContain("@hasanyrole('QC/QA Engineer|Database Officer|Auditing Supervisor|Team Leader|Field Engineer|field Engineer')");

    expect($controller)
        ->toContain("\$request->audit_type === 'Legal Auditor' && \$user->hasRole('Legal Auditor')")
        ->toContain("\$request->audit_type === 'QC/QA Engineer' && \$user->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor'])")
        ->toContain('private function auditTypeCanSetStatus(string $type, string $status): bool')
        ->toContain("'Legal Auditor' => in_array(\$status, ['accepted', 'legal_notes'], true)")
        ->toContain("'QC/QA Engineer' => in_array(\$status, ['accepted', 'rejected', 'need_review'], true)");
});

test('assessment audit inline edits resolve missing global ids before saving', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php');

    expect($view)
        ->toContain('overflow: visible')
        ->not->toContain('audit-sticky-menu .audit-sticky-menu')
        ->toContain('function resolveInlineGlobalId')
        ->toContain("type === 'building_table'")
        ->toContain("type === 'housing_table'")
        ->toContain('let isAssessmentReadOnly')
        ->toContain('!isAreaManager && !isAssessmentReadOnly')
        ->toContain("'comments_recommendations'")
        ->toContain('comments_recommendations: \'التوصيات\'')
        ->toContain('summary-value-long')
        ->toContain('يرجى اختيار الوحدة أولاً');
});
