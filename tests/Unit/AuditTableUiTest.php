<?php

test('engineer audit report leaves empty table messaging to datatables', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/reports/engineer_audit.blade.php');

    expect($view)
        ->toContain('id="engineer_audit_table"')
        ->toContain('@foreach ($rows as $row)')
        ->toContain("emptyTable: 'لا توجد بيانات ضمن الفلاتر المحددة.'")
        ->not->toContain('@forelse ($rows as $row)')
        ->not->toContain('@empty')
        ->not->toContain('<td colspan="6" class="text-center text-muted">');
});

test('assessment audit hides obstacle details when assessment has no obstacle', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php');

    expect($view)
        ->toContain('function removeInactiveDependentRows')
        ->toContain("normalizeSurveyName(row.name) === 'assessment_obstacle'")
        ->toContain("return answer === 'yes' || answer === 'نعم'")
        ->toContain("return !['obstacle_type', 'assessment_obstacle_info'].includes(normalizeSurveyName(row.name))")
        ->toContain('rows = removeInactiveDependentRows(rows, prefix)');
});

test('housing audit detail card fills the row beside the summary card', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/audit/assessmentAudit.blade.php');

    expect($view)
        ->toContain('col-12 col-lg-3 col-xl-2')
        ->toContain('col-12 col-lg-9 col-xl-10')
        ->not->toContain('col-12 col-lg-8 col-xl-9');
});

test('audit table keeps all columns with responsive text cells', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/views/audit/audit.blade.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/Http/Controllers/Audit/auditController.php');
    $dashboardController = file_get_contents(dirname(__DIR__, 2).'/app/Modules/DamageAssessment/Http/Controllers/Dashboard/DamageAssessmentController.php');
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
        ->toContain('housing_unit_attachments_tab_pane')
        ->toContain('housing_unit_attachment_select')
        ->toContain('housingUnitAttachmentForm')
        ->toContain('housing_unit_attachment_file')
        ->toContain('housingUnitAttachmentSubmit')
        ->toContain('housingUnitAttachmentsTableBody')
        ->toContain('resetHousingUnitAttachmentSelect')
        ->toContain('selectedHousingUnitAttachmentUnit')
        ->toContain('resetHousingUnitAttachmentForm')
        ->toContain('renderSelectedHousingUnitAttachments')
        ->toContain('loadHousingUnitAttachments')
        ->toContain('btn-replace-housing-unit-attachment')
        ->toContain('btn-delete-housing-unit-attachment')
        ->toContain("route('audit.building.housing-unit-attachments.index'")
        ->toContain("route('audit.building.attachments.index'")
        ->toContain("route('audit.building.attachments.store'")
        ->toContain("route('audit.building.attachments.replace'")
        ->toContain("route('audit.building.attachments.destroy'")
        ->toContain("route('audit.housing-unit.attachments.store'")
        ->toContain("route('audit.housing-unit.attachments.replace'")
        ->toContain("route('audit.housing-unit.attachments.destroy'")
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
        ->toContain('buildingHousingUnitAttachments')
        ->toContain('housingUnitAttachmentTitle')
        ->toContain('storeBuildingAttachment')
        ->toContain('replaceBuildingAttachment')
        ->toContain('destroyBuildingAttachment')
        ->toContain('storeHousingUnitAttachment')
        ->toContain('replaceHousingUnitAttachment')
        ->toContain('destroyHousingUnitAttachment')
        ->toContain('ArcgisAttachmentBackupService')
        ->toContain('backupBuildingAttachment($building, $attachmentId, \'replace\'')
        ->toContain('backupBuildingAttachment($building, $attachmentId, \'delete\'')
        ->toContain('backupHousingUnitAttachment($housingUnit, $attachmentId, \'replace\'')
        ->toContain('backupHousingUnitAttachment($housingUnit, $attachmentId, \'delete\'');

    expect($dashboardController)
        ->toContain("\$record['submission_date'] = \$model->end")
        ->toContain("\$record['submition_date'] = \$model->end")
        ->toContain("'building_name' => 'اسم المبنى'")
        ->toContain("'scorite_area' => 'مساحة الصاج'")
        ->toContain("'comments_recommendations' => 'ملاحظات المهندس'");

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
        ->toContain("let currentHousingFilter = 'answered';")
        ->toContain("let currentBuildingFilter = 'answered';")
        ->toContain('function syncDefaultAuditFilters')
        ->toContain("\$('.building-filter-btn[data-filter=\"answered\"]').addClass('is-active')")
        ->toContain("\$('.housing-filter-btn[data-filter=\"answered\"]').addClass('is-active')")
        ->toContain("button.hasClass('is-active')")
        ->toContain("setAssessmentActiveStatusButtons('.building-status-btn', buildingEngineeringStatus, buildingLegalStatus, buildingCurrentStatus)")
        ->toContain("setAssessmentActiveStatusButtons('.housing-status-btn', row.current_engineering_status, row.current_legal_status, row.current_status)")
        ->toContain('function keepAttachmentRowsVisible')
        ->toContain("return name === 'attachments' || section === 'المرفقات' || section === 'attachments'")
        ->not->toContain("name.includes('photo')")
        ->not->toContain("name.includes('image')")
        ->not->toContain("name.includes('attachment')")
        ->not->toContain("name.includes('comments')")
        ->toContain('return pinnedAttachmentRows.concat(filteredRows.filter(row => !pinnedRows.has(row)))')
        ->toContain('rows = keepAttachmentRowsVisible(lastBuildingRows, rows)')
        ->toContain('rows = keepAttachmentRowsVisible(lastHousingRows, rows)')
        ->not->toContain('$canSetLegalStatus')
        ->not->toContain('$canSetEngineeringStatus')
        ->not->toContain('@disabled(! $canSetLegalStatus)')
        ->not->toContain('@disabled(! $canSetEngineeringStatus)')
        ->not->toContain('@elseif($canViewStatusButtons)')
        ->not->toContain('canEnableStatusButton')
        ->not->toContain("@hasanyrole('Legal Auditor|Database Officer|Auditing Supervisor|Team Leader|Field Engineer|field Engineer')")
        ->not->toContain("@hasanyrole('QC/QA Engineer|Database Officer|Auditing Supervisor|Team Leader|Field Engineer|field Engineer')");

    expect($controller)
        ->toContain("\$request->audit_type === 'Legal Auditor' && \$user->hasRole('Legal Auditor')")
        ->toContain("\$request->audit_type === 'QC/QA Engineer' && \$user->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor'])")
        ->toContain('private function auditTypeCanSetStatus(string $type, string $status): bool')
        ->toContain('private function canSetAssessmentStatusForBuilding(?User $user, ?Building $building, string $type): bool')
        ->toContain('You cannot set this status unless this assessment type is assigned to you.')
        ->toContain("->where('type', \$type)")
        ->toContain("'fully_damaged2'")
        ->toContain("'floor_number',\n            'housing_unit_number',\n            'external_finishing_of_the_unit'")
        ->toContain("'is_the_housing_unit_or_living_habitable' => 'هل الوحدة مناسبة للسكن'")
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
        ->toContain("'building_name'")
        ->toContain("'scorite_area'")
        ->toContain("'comments_recommendations'")
        ->toContain("comments_recommendations: 'ملاحظات المهندس'")
        ->toContain('renderHousingSummaryItems(res.summary_items || [])')
        ->not->toContain('function housingSummaryTitle(summaryMode)')
        ->not->toContain('ملخص الوحدة في حالة نوع الضرر Partially')
        ->not->toContain('ملخص الوحدة في حالة نوع الضرر Totally')
        ->toContain('summary-value-long')
        ->toContain('يرجى اختيار الوحدة أولاً');
});
