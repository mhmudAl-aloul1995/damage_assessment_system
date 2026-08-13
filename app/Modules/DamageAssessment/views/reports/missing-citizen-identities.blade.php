@extends('layouts.app')
@section('title', __('ui.missing_citizen_identities.title'))
@section('pageName', __('ui.missing_citizen_identities.title'))

@section('content')
    <div class="row g-5 mb-5">
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body d-flex align-items-center justify-content-between gap-4">
                    <div>
                        <div class="text-muted fs-7 mb-2">{{ __('ui.missing_citizen_identities.total_missing') }}</div>
                        <div class="fs-2x fw-bold text-danger" id="missing_citizens_total">{{ number_format($totalMissingCitizenIdentities) }}</div>
                    </div>
                    <div class="symbol symbol-50px">
                        <div class="symbol-label bg-light-danger">
                            <i class="ki-duotone ki-profile-circle fs-1 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="fw-bold fs-4 mb-2">{{ __('ui.missing_citizen_identities.rule_title') }}</div>
                    <div class="text-muted fs-6">{{ __('ui.missing_citizen_identities.rule_description') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex flex-column align-items-stretch gap-4">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <h3 class="fw-bold m-0">{{ __('ui.missing_citizen_identities.table_title') }}</h3>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <button type="button" class="btn btn-sm btn-light-primary text-nowrap" data-kt-missing-citizens-action="refresh">
                        <i class="ki-duotone ki-arrows-circle fs-2"></i>
                        {{ __('ui.missing_citizen_identities.refresh') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-light-warning text-nowrap" data-kt-missing-citizens-action="select-all-visible" disabled>
                        <i class="ki-duotone ki-check-square fs-2"></i>
                        <span data-kt-missing-citizens-select-all-label>{{ __('ui.missing_citizen_identities.select_all_matches') }}</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-light-success text-nowrap" data-kt-missing-citizens-action="bulk-approve" disabled>
                        <i class="ki-duotone ki-check-square fs-2"></i>
                        {{ __('ui.missing_citizen_identities.approve_selected') }}
                    </button>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                <div class="btn-group flex-shrink-0" role="group" aria-label="{{ __('ui.missing_citizen_identities.identity_subject') }}">
                    <button type="button" class="btn btn-sm btn-primary" data-kt-missing-citizens-subject="owner">
                        {{ __('ui.missing_citizen_identities.identity_owner') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-light-primary" data-kt-missing-citizens-subject="spouse">
                        {{ __('ui.missing_citizen_identities.identity_spouses') }}
                    </button>
                </div>
                <div class="position-relative flex-shrink-0">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-4"></i>
                    <input type="text" data-kt-missing-citizens-filter="search" class="form-control form-control-sm form-control-solid w-250px ps-12" placeholder="{{ __('ui.missing_citizen_identities.search_placeholder') }}">
                </div>
                <div class="input-group input-group-sm flex-shrink-0 w-250px">
                    <span class="input-group-text border-0 bg-light">
                        <i class="ki-duotone ki-magnifier fs-3"></i>
                    </span>
                    <input type="text" data-kt-missing-citizens-filter="unit-objectid" class="form-control form-control-sm form-control-solid" inputmode="numeric" placeholder="{{ __('ui.missing_citizen_identities.unit_objectid_placeholder') }}">
                    <button type="button" class="btn btn-sm btn-light-info btn-icon position-relative" data-kt-missing-citizens-action="open-unit-objectids-modal" title="فلترة متعددة">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge badge-circle badge-primary d-none" data-kt-missing-citizens-unit-objectids-count>0</span>
                    </button>
                </div>
                <select class="form-select form-select-sm form-select-solid w-200px flex-shrink-0" data-kt-missing-citizens-filter="issue-type">
                    <option value="">{{ __('ui.missing_citizen_identities.all_issue_types') }}</option>
                    <option value="missing_civil_registry_identity">{{ __('ui.missing_citizen_identities.issue_missing_civil_registry_identity') }}</option>
                    <option value="owner_without_identity">{{ __('ui.missing_citizen_identities.issue_owner_without_identity') }}</option>
                </select>
                <select class="form-select form-select-sm form-select-solid w-175px flex-shrink-0" data-kt-missing-citizens-filter="name-match-status">
                    <option value="">{{ __('ui.missing_citizen_identities.all_name_matches') }}</option>
                    <option value="matched">{{ __('ui.missing_citizen_identities.name_match_matched') }}</option>
                    <option value="ambiguous">{{ __('ui.missing_citizen_identities.name_match_ambiguous') }}</option>
                    <option value="not_found">{{ __('ui.missing_citizen_identities.name_match_not_found') }}</option>
                    <option value="no_owner_name">{{ __('ui.missing_citizen_identities.name_match_no_owner') }}</option>
                    <option value="not_checked">{{ __('ui.missing_citizen_identities.name_match_not_checked') }}</option>
                </select>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <span class="text-muted fs-7">{{ __('ui.missing_citizen_identities.rows_per_page') }}</span>
                    <select class="form-select form-select-sm form-select-solid w-100px" data-kt-missing-citizens-filter="per-page">
                        <option value="100" selected>100</option>
                        <option value="200">200</option>
                        <option value="500">500</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5 w-100" id="kt_table_missing_citizen_identities">
                    <thead>
                        <tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">
                            <th class="w-30px">
                                <input class="form-check-input" type="checkbox" data-kt-missing-citizens-action="select-all" title="{{ __('ui.missing_citizen_identities.select_all_matches') }}">
                            </th>
                            <th>{{ __('ui.missing_citizen_identities.identity_subject') }}</th>
                            <th class="d-none" data-kt-missing-citizens-owner-context>{{ __('ui.missing_citizen_identities.owner_name') }}</th>
                            <th class="d-none" data-kt-missing-citizens-owner-context>{{ __('ui.missing_citizen_identities.owner_id_number') }}</th>
                            <th id="missing_citizen_identity_name_header">{{ __('ui.missing_citizen_identities.owner_name') }}</th>
                            <th id="missing_citizen_identity_number_header">{{ __('ui.missing_citizen_identities.id_number') }}</th>
                            <th>{{ __('ui.missing_citizen_identities.housing_unit_objectid') }}</th>
                            <th>{{ __('ui.missing_citizen_identities.issue_type') }}</th>
                            <th>{{ __('ui.missing_citizen_identities.name_match_status') }}</th>
                            <th>{{ __('ui.missing_citizen_identities.matched_citizen') }}</th>
                            <th class="text-end">{{ __('ui.missing_citizen_identities.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 fw-semibold"></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-5">
                <div class="text-muted fs-7" id="missing_citizens_page_info"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light" data-kt-missing-citizens-action="previous">
                        {{ __('ui.missing_citizen_identities.previous') }}
                    </button>
                    <button type="button" class="btn btn-primary" data-kt-missing-citizens-action="next">
                        {{ __('ui.missing_citizen_identities.next') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="missing_citizen_candidates_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">{{ __('ui.missing_citizen_identities.candidates_title') }}</h3>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light-info" data-kt-missing-citizens-action="show-documents">
                            <i class="ki-duotone ki-document fs-3"></i>
                            {{ __('ui.missing_citizen_identities.show_unit_documents') }}
                        </button>
                        <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal" aria-label="Close">
                            <i class="ki-duotone ki-cross fs-1"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="bg-light-danger rounded p-4 mb-5 overflow-hidden">
                        <div class="d-flex align-items-center gap-4 flex-nowrap min-w-0">
                            <div class="d-flex align-items-center gap-2 overflow-auto flex-nowrap flex-grow-1 min-w-0 pb-1" id="missing_citizen_identity_summary" style="white-space: nowrap; scrollbar-width: thin;"></div>
                        </div>
                    </div>
                    <div class="bg-light rounded p-4 mb-5 d-none" id="missing_citizen_documents_panel">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="fw-bold text-gray-800">{{ __('ui.missing_citizen_identities.unit_documents') }}</div>
                            <button type="button" class="btn btn-sm btn-icon btn-light" data-kt-missing-citizens-action="hide-documents">
                                <i class="ki-duotone ki-cross fs-2"></i>
                            </button>
                        </div>
                        <div id="missing_citizen_documents_body"></div>
                    </div>
                    <div class="input-group mb-5">
                        <button type="button" class="btn btn-light-danger" data-kt-missing-citizen-clear-input="#missing_citizen_manual_search">
                            مسح
                        </button>
                        <div class="position-relative flex-grow-1">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-4"></i>
                            <input type="text" class="form-control form-control-solid ps-12" id="missing_citizen_manual_search" placeholder="{{ __('ui.missing_citizen_identities.citizen_search_placeholder') }}">
                        </div>
                    </div>
                    <div class="row g-3 mb-5">
                        <div class="col-md-3">
                            <div class="input-group">
                                <button type="button" class="btn btn-light-danger" data-kt-missing-citizen-clear-nearest>
                                    مسح
                                </button>
                                <input type="text" class="form-control form-control-solid" data-kt-missing-citizen-name-part="first_name" placeholder="{{ __('ui.missing_citizen_identities.first_name') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <button type="button" class="btn btn-light-danger" data-kt-missing-citizen-clear-nearest>
                                    مسح
                                </button>
                                <input type="text" class="form-control form-control-solid" data-kt-missing-citizen-name-part="father_name" placeholder="{{ __('ui.missing_citizen_identities.father_name') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <button type="button" class="btn btn-light-danger" data-kt-missing-citizen-clear-nearest>
                                    مسح
                                </button>
                                <input type="text" class="form-control form-control-solid" data-kt-missing-citizen-name-part="grandfather_name" placeholder="{{ __('ui.missing_citizen_identities.grandfather_name') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <button type="button" class="btn btn-light-danger" data-kt-missing-citizen-clear-nearest>
                                    مسح
                                </button>
                                <input type="text" class="form-control form-control-solid" data-kt-missing-citizen-name-part="family_name" placeholder="{{ __('ui.missing_citizen_identities.family_name') }}">
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle">
                            <thead>
                                <tr class="text-muted fw-bold fs-7">
                                    <th>{{ __('ui.missing_citizen_identities.matched_citizen') }}</th>
                                    <th>{{ __('ui.missing_citizen_identities.id_number') }}</th>
                                    <th class="text-end">{{ __('ui.missing_citizen_identities.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="missing_citizen_candidates_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="missing_citizen_unit_objectids_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">فلترة متعددة لأرقام الوحدات</h3>
                    <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ki-duotone ki-cross fs-1"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold text-gray-700" for="missing_citizen_unit_objectids_textarea">ألصق أرقام الوحدات السكنية</label>
                    <textarea class="form-control form-control-solid" id="missing_citizen_unit_objectids_textarea" data-kt-missing-citizens-unit-objectids-textarea rows="8" style="resize: vertical;" placeholder="49869&#10;49838&#10;50120"></textarea>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div class="text-muted fs-7">يمكن الفصل بسطر جديد، فاصلة، أو مسافة.</div>
                        <div class="badge badge-light-primary" data-kt-missing-citizens-unit-objectids-preview>0 رقم</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-kt-missing-citizens-action="clear-unit-objectids">مسح</button>
                    <button type="button" class="btn btn-primary" data-kt-missing-citizens-action="apply-unit-objectids">تطبيق الفلتر</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        var KTMissingCitizenIdentities = function () {
            var table = document.getElementById('kt_table_missing_citizen_identities');
            var tbody = table ? table.querySelector('tbody') : null;
            var pageInfo = document.getElementById('missing_citizens_page_info');
            var currentSearch = '';
            var currentUnitObjectId = '';
            var currentIssueType = '';
            var currentNameMatchStatus = '';
            var currentIdentitySubject = 'owner';
            var nameHeader = document.getElementById('missing_citizen_identity_name_header');
            var idNumberHeader = document.getElementById('missing_citizen_identity_number_header');
            var hasMore = false;
            var loading = false;
            var currentPerPage = 100;
            var cursorStack = [0];
            var cursorIndex = 0;
            var nextCursor = null;
            var total = document.getElementById('missing_citizens_total');
            var approveUrlTemplate = @json(route('reports.missing-citizen-identities.approve-name-match', ['report' => '__REPORT__']));
            var bulkApproveUrl = @json(route('reports.missing-citizen-identities.bulk-approve-name-matches'));
            var candidatesUrlTemplate = @json(route('reports.missing-citizen-identities.name-candidates', ['report' => '__REPORT__']));
            var citizenSearchUrlTemplate = @json(route('reports.missing-citizen-identities.citizen-search', ['report' => '__REPORT__']));
            var documentsUrlTemplate = @json(route('reports.missing-citizen-identities.documents', ['report' => '__REPORT__']));
            var csrfToken = @json(csrf_token());
            var candidatesModalElement = document.getElementById('missing_citizen_candidates_modal');
            var candidatesBody = document.getElementById('missing_citizen_candidates_body');
            var identitySummary = document.getElementById('missing_citizen_identity_summary');
            var documentsPanel = document.getElementById('missing_citizen_documents_panel');
            var documentsBody = document.getElementById('missing_citizen_documents_body');
            var manualSearch = document.getElementById('missing_citizen_manual_search');
            var namePartInputs = Array.prototype.slice.call(document.querySelectorAll('[data-kt-missing-citizen-name-part]'));
            var unitObjectIdsModalElement = document.getElementById('missing_citizen_unit_objectids_modal');
            var unitObjectIdsTextarea = document.querySelector('[data-kt-missing-citizens-unit-objectids-textarea]');
            var unitObjectIdsPreview = document.querySelector('[data-kt-missing-citizens-unit-objectids-preview]');
            var unitObjectIdsCount = document.querySelector('[data-kt-missing-citizens-unit-objectids-count]');
            var activeCandidateReportId = null;
            var manualSearchTimer = null;
            var rowsByReportId = {};
            var currentUnitObjectIds = '';

            var rowCheckboxes = function () {
                return Array.prototype.slice.call(document.querySelectorAll('[data-kt-missing-citizens-row-check]'))
                    .filter(function (checkbox) {
                        return !checkbox.disabled;
                    });
            };

            var selectedReportIds = function () {
                return rowCheckboxes()
                    .filter(function (checkbox) {
                        return checkbox.checked;
                    })
                    .map(function (checkbox) {
                        return checkbox.value;
                    });
            };

            var allVisibleRowsSelected = function () {
                var checkboxes = rowCheckboxes();

                return checkboxes.length > 0 && checkboxes.every(function (checkbox) {
                    return checkbox.checked;
                });
            };

            var setVisibleRowsSelection = function (checked) {
                rowCheckboxes().forEach(function (checkbox) {
                    checkbox.checked = checked;
                });

                updateBulkState();
            };

            var updateBulkState = function () {
                var bulkApprove = document.querySelector('[data-kt-missing-citizens-action="bulk-approve"]');
                var selectAll = document.querySelector('[data-kt-missing-citizens-action="select-all"]');
                var selectAllVisible = document.querySelector('[data-kt-missing-citizens-action="select-all-visible"]');
                var selectAllLabel = document.querySelector('[data-kt-missing-citizens-select-all-label]');
                var checkboxes = rowCheckboxes();
                var checkedCount = selectedReportIds().length;
                var hasSelectableRows = checkboxes.length > 0;
                var allSelected = hasSelectableRows && checkedCount === checkboxes.length;

                if (bulkApprove) {
                    bulkApprove.disabled = loading || checkedCount === 0;
                }

                if (selectAll) {
                    selectAll.disabled = loading || !hasSelectableRows;
                    selectAll.checked = allSelected;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
                }

                if (selectAllVisible) {
                    selectAllVisible.disabled = loading || !hasSelectableRows;
                }

                if (selectAllLabel) {
                    selectAllLabel.textContent = allSelected
                        ? '{{ __('ui.missing_citizen_identities.clear_selection') }}'
                        : '{{ __('ui.missing_citizen_identities.select_all_matches') }}';
                }
            };

            var setButtonsState = function () {
                var previous = document.querySelector('[data-kt-missing-citizens-action="previous"]');
                var next = document.querySelector('[data-kt-missing-citizens-action="next"]');

                if (previous) {
                    previous.disabled = loading || cursorIndex <= 0;
                }

                if (next) {
                    next.disabled = loading || !hasMore;
                }

                updateBulkState();
            };

            var escapeHtml = function (value) {
                return String(value || '-')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            var ownerContextClass = function () {
                return currentIdentitySubject === 'spouse' ? '' : 'd-none';
            };

            var visibleColumnsCount = function () {
                return currentIdentitySubject === 'spouse' ? 11 : 9;
            };

            var updateOwnerContextVisibility = function () {
                Array.prototype.slice.call(document.querySelectorAll('[data-kt-missing-citizens-owner-context]')).forEach(function (element) {
                    element.classList.toggle('d-none', currentIdentitySubject !== 'spouse');
                });
            };

            var resetDocuments = function () {
                if (documentsPanel) {
                    documentsPanel.classList.add('d-none');
                }

                if (documentsBody) {
                    documentsBody.innerHTML = '';
                }
            };

            var documentIcon = function (document) {
                if (document.type === 'image') {
                    return '<img src="' + escapeHtml(document.url) + '" class="rounded border border-gray-300 object-fit-cover" style="width:56px;height:56px;" alt="' + escapeHtml(document.title) + '">';
                }

                var iconClass = document.type === 'pdf' ? 'ki-file-down text-danger' : 'ki-document text-primary';

                return '<div class="symbol symbol-55px"><div class="symbol-label bg-white"><i class="ki-duotone ' + iconClass + ' fs-2x"></i></div></div>';
            };

            var renderDocuments = function (documents) {
                if (!documentsPanel || !documentsBody) {
                    return;
                }

                documentsPanel.classList.remove('d-none');

                if (!documents || documents.length === 0) {
                    documentsBody.innerHTML = '<div class="text-muted text-center py-5">{{ __('ui.missing_citizen_identities.no_unit_documents') }}</div>';
                    return;
                }

                documentsBody.innerHTML = documents.map(function (document) {
                    return '<div class="d-flex align-items-center justify-content-between gap-4 border-bottom border-gray-300 py-3">'
                        + '<div class="d-flex align-items-center gap-3 min-w-0">'
                        + documentIcon(document)
                        + '<div class="min-w-0">'
                        + '<div class="fw-semibold text-gray-800 text-truncate">' + escapeHtml(document.title) + '</div>'
                        + '<div class="text-muted fs-8">' + escapeHtml(document.source) + '</div>'
                        + '</div>'
                        + '</div>'
                        + '<a class="btn btn-sm btn-light-primary flex-shrink-0" href="' + escapeHtml(document.url) + '" target="_blank" rel="noopener">{{ __('ui.missing_citizen_identities.open_document') }}</a>'
                        + '</div>';
                }).join('');
            };

            var loadDocuments = function (reportId) {
                if (!documentsPanel || !documentsBody) {
                    return;
                }

                documentsPanel.classList.remove('d-none');
                documentsBody.innerHTML = '<div class="text-muted text-center py-5">{{ __('ui.missing_citizen_identities.loading_documents') }}</div>';

                fetch(documentsUrlTemplate.replace('__REPORT__', reportId), {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then(function (response) {
                        return response.json().then(function (payload) {
                            if (!response.ok) {
                                throw payload;
                            }

                            return payload;
                        });
                    })
                    .then(function (payload) {
                        var documents = payload.data || [];

                        if (documents.length > 0 && documents[0].url) {
                            resetDocuments();
                            window.open(documents[0].url, '_blank', 'noopener');

                            return;
                        }

                        renderDocuments(documents);
                    })
                    .catch(function (payload) {
                        documentsBody.innerHTML = '<div class="text-danger text-center py-5">' + escapeHtml(payload.message || '{{ __('ui.messages.unexpected_error') }}') + '</div>';
                    });
            };

            var renderIdentitySummary = function (reportId) {
                if (!identitySummary) {
                    return;
                }

                var row = rowsByReportId[String(reportId)] || {};
                var details = row.housing_unit_identity_details || [];

                if (details.length === 0) {
                    identitySummary.innerHTML = '';
                    identitySummary.classList.add('d-none');

                    return;
                }

                identitySummary.classList.remove('d-none');
                identitySummary.innerHTML = details.map(function (detail) {
                    var title = detail.label + ' - ' + detail.name + ' - ' + detail.id_number;

                    return '<span class="badge badge-light d-inline-flex align-items-center gap-2 border border-gray-300 px-3 py-2 flex-shrink-0" title="' + escapeHtml(title) + '">'
                        + '<span class="text-gray-600 flex-shrink-0">' + escapeHtml(detail.label) + '</span>'
                        + '<span class="fw-bold text-gray-900 d-inline-block">' + escapeHtml(detail.name) + '</span>'
                        + '<span class="badge badge-light-primary flex-shrink-0">' + escapeHtml(detail.id_number) + '</span>'
                        + '</span>';
                }).join('');
            };

            var renderRows = function (rows) {
                if (!tbody) {
                    return;
                }

                rowsByReportId = {};

                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="' + visibleColumnsCount() + '" class="text-center text-muted py-10">{{ __('ui.missing_citizen_identities.empty_table') }}</td></tr>';
                    updateBulkState();
                    return;
                }

                tbody.innerHTML = rows.map(function (row) {
                    rowsByReportId[String(row.id)] = row;
                    var status = matchStatusBadge(row);
                    var matchedCitizen = row.matched_citizen_full_name
                        ? '<div class="fw-semibold">' + escapeHtml(row.matched_citizen_full_name) + '</div><span class="badge badge-light-success">' + escapeHtml(row.matched_citizen_id_card_no) + '</span>'
                        : '<span class="text-muted">-</span>';
                    var issue = issueTypeBadge(row);
                    var action = actionButton(row);
                    var checkbox = row.can_approve_name_match
                        ? '<input class="form-check-input" type="checkbox" data-kt-missing-citizens-row-check value="' + escapeHtml(row.id) + '">'
                        : '';

                    return '<tr data-kt-missing-citizens-row="' + escapeHtml(row.id) + '">'
                        + '<td>' + checkbox + '</td>'
                        + '<td><span class="badge badge-light-dark">' + escapeHtml(row.identity_label) + '</span></td>'
                        + '<td class="' + ownerContextClass() + '" data-kt-missing-citizens-owner-context>' + escapeHtml(row.housing_unit_owner_name) + '</td>'
                        + '<td class="' + ownerContextClass() + '" data-kt-missing-citizens-owner-context><span class="badge badge-light-primary">' + escapeHtml(row.housing_unit_owner_id_number) + '</span></td>'
                        + '<td>' + escapeHtml(row.owner_name) + '</td>'
                        + '<td><span class="badge badge-light-danger">' + escapeHtml(row.id_number1) + '</span></td>'
                        + '<td><span class="badge badge-light-info">' + escapeHtml(row.housing_unit_objectid) + '</span></td>'
                        + '<td>' + issue + '</td>'
                        + '<td>' + status + '</td>'
                        + '<td>' + matchedCitizen + '</td>'
                        + '<td class="text-end">' + action + '</td>'
                        + '</tr>';
                }).join('');

                updateBulkState();
            };

            var actionButton = function (row) {
                if (row.can_approve_name_match) {
                    return '<button type="button" class="btn btn-sm btn-light-success" data-kt-missing-citizens-action="approve-name" data-report-id="' + escapeHtml(row.id) + '">{{ __('ui.missing_citizen_identities.approve_match') }}</button>';
                }

                if (row.can_show_name_candidates) {
                    return '<button type="button" class="btn btn-sm btn-light-warning" data-kt-missing-citizens-action="show-candidates" data-report-id="' + escapeHtml(row.id) + '">{{ __('ui.missing_citizen_identities.show_candidates') }}</button>';
                }

                if (row.can_search_citizens) {
                    return '<button type="button" class="btn btn-sm btn-light-primary" data-kt-missing-citizens-action="search-citizens" data-report-id="' + escapeHtml(row.id) + '" data-owner-name="' + escapeHtml(row.owner_name) + '">{{ __('ui.missing_citizen_identities.search_civil_registry') }}</button>';
                }

                return '<span class="text-muted">-</span>';
            };

            var issueTypeBadge = function (row) {
                var issue = row.issue_type || 'missing_civil_registry_identity';
                var labels = {
                    missing_civil_registry_identity: '{{ __('ui.missing_citizen_identities.issue_missing_civil_registry_identity') }}',
                    owner_without_identity: '{{ __('ui.missing_citizen_identities.issue_owner_without_identity') }}'
                };
                var classes = {
                    missing_civil_registry_identity: 'badge-light-danger',
                    owner_without_identity: 'badge-light-primary'
                };

                return '<span class="badge ' + (classes[issue] || 'badge-light') + '">' + escapeHtml(labels[issue] || issue) + '</span>';
            };

            var matchStatusBadge = function (row) {
                var status = row.name_match_status || 'not_checked';
                var labels = {
                    matched: '{{ __('ui.missing_citizen_identities.name_match_matched') }}',
                    ambiguous: '{{ __('ui.missing_citizen_identities.name_match_ambiguous') }}',
                    not_found: '{{ __('ui.missing_citizen_identities.name_match_not_found') }}',
                    no_owner_name: '{{ __('ui.missing_citizen_identities.name_match_no_owner') }}',
                    not_checked: '{{ __('ui.missing_citizen_identities.name_match_not_checked') }}'
                };
                var classes = {
                    matched: 'badge-light-success',
                    ambiguous: 'badge-light-warning',
                    not_found: 'badge-light-danger',
                    no_owner_name: 'badge-light-secondary',
                    not_checked: 'badge-light'
                };
                var suffix = status === 'ambiguous' && row.matched_citizens_count
                    ? ' (' + row.matched_citizens_count + ')'
                    : '';

                return '<span class="badge ' + (classes[status] || 'badge-light') + '">' + escapeHtml(labels[status] || status) + suffix + '</span>';
            };

            var extractUnitObjectIds = function (value) {
                var matches = String(value || '').match(/\d+/g) || [];

                return matches
                    .filter(function (value, index, values) {
                        return Number(value) > 0 && values.indexOf(value) === index;
                    })
                    .map(function (value) {
                        return String(Number(value));
                    });
            };

            var normalizedUnitObjectIds = function () {
                var source = currentUnitObjectIds || currentUnitObjectId;

                return extractUnitObjectIds(source).join(',');
            };

            var updateUnitObjectIdsState = function () {
                var count = extractUnitObjectIds(currentUnitObjectIds).length;

                if (unitObjectIdsCount) {
                    unitObjectIdsCount.textContent = count;
                    unitObjectIdsCount.classList.toggle('d-none', count === 0);
                }

                if (unitObjectIdsPreview) {
                    unitObjectIdsPreview.textContent = count + ' رقم';
                }
            };

            var loadCursor = function (cursor) {
                if (loading) {
                    return;
                }

                loading = true;
                setButtonsState();

                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="' + visibleColumnsCount() + '" class="text-center text-muted py-10">{{ __('ui.missing_citizen_identities.loading') }}</td></tr>';
                }

                var params = {
                    after_id: cursor,
                    search: currentSearch,
                    unit_objectid: normalizedUnitObjectIds(),
                    issue_type: currentIssueType,
                    name_match_status: currentNameMatchStatus,
                    identity_subject: currentIdentitySubject,
                    per_page: currentPerPage
                };

                fetch("{{ route('reports.missing-citizen-identities.data') }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(params)
                })
                    .then(function (response) {
                        return response.json().then(function (payload) {
                            if (!response.ok) {
                                throw payload;
                            }

                            return payload;
                        });
                    })
                    .then(function (payload) {
                        hasMore = Boolean(payload.has_more);
                        nextCursor = payload.next_cursor;
                        renderRows(payload.data || []);

                        if (total) {
                            total.textContent = Number(payload.total || 0).toLocaleString();
                        }

                        if (pageInfo) {
                            var rowCount = (payload.data || []).length;
                            pageInfo.textContent = '{{ __('ui.missing_citizen_identities.page') }} ' + (cursorIndex + 1)
                                + ' - {{ __('ui.missing_citizen_identities.showing_rows') }} '
                                + rowCount.toLocaleString()
                                + ' {{ __('ui.missing_citizen_identities.from_total') }} '
                                + Number(payload.total || 0).toLocaleString();
                        }
                    })
                    .catch(function () {
                        if (tbody) {
                            tbody.innerHTML = '<tr><td colspan="' + visibleColumnsCount() + '" class="text-center text-danger py-10">{{ __('ui.messages.unexpected_error') }}</td></tr>';
                        }
                    })
                    .finally(function () {
                        loading = false;
                        setButtonsState();
                    });
            };

            var showCandidates = function (reportId) {
                activeCandidateReportId = reportId;
                resetDocuments();
                renderIdentitySummary(reportId);

                if (manualSearch) {
                    manualSearch.value = '';
                }
                clearNamePartInputs();

                if (candidatesBody) {
                    candidatesBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-8">{{ __('ui.missing_citizen_identities.loading') }}</td></tr>';
                }

                if (candidatesModalElement && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(candidatesModalElement).show();
                }

                fetch(candidatesUrlTemplate.replace('__REPORT__', reportId), {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then(function (response) {
                        return response.json().then(function (payload) {
                            if (!response.ok) {
                                throw payload;
                            }

                            return payload;
                        });
                    })
                    .then(function (payload) {
                        var candidates = payload.data || [];

                        if (!candidatesBody) {
                            return;
                        }

                        renderCandidateRows(candidates);
                    })
                    .catch(function (payload) {
                        if (candidatesBody) {
                            candidatesBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-8">' + escapeHtml(payload.message || '{{ __('ui.messages.unexpected_error') }}') + '</td></tr>';
                        }
                    });
            };

            var renderCandidateRows = function (candidates) {
                if (!candidatesBody) {
                    return;
                }

                if (candidates.length === 0) {
                    candidatesBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-8">{{ __('ui.missing_citizen_identities.no_candidates') }}</td></tr>';
                    return;
                }

                candidatesBody.innerHTML = candidates.map(function (candidate) {
                    var source = candidate.source
                        ? '<span class="badge badge-light-info ms-2">' + escapeHtml(candidate.source) + '</span>'
                        : '';
                    var details = candidate.details
                        ? '<div class="text-muted fs-8 mt-1">' + escapeHtml(candidate.details) + '</div>'
                        : '';

                    return '<tr>'
                        + '<td><div class="fw-semibold">' + escapeHtml(candidate.full_name) + source + '</div>' + details + '</td>'
                        + '<td><span class="badge badge-light-primary">' + escapeHtml(candidate.id_card_no) + '</span></td>'
                        + '<td class="text-end"><button type="button" class="btn btn-sm btn-light-success" data-kt-missing-citizens-action="approve-candidate" data-citizen-id="' + escapeHtml(candidate.id) + '">{{ __('ui.missing_citizen_identities.approve_this_candidate') }}</button></td>'
                        + '</tr>';
                }).join('');
            };

            var showToast = function (message, icon) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        text: message,
                        icon: icon || 'info',
                        buttonsStyling: false,
                        confirmButtonText: '{{ __('ui.buttons.ok') }}',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });

                    return;
                }

                alert(message);
            };

            var confirmAction = function (message) {
                if (typeof Swal === 'undefined') {
                    return Promise.resolve(confirm(message));
                }

                return Swal.fire({
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: '{{ __('ui.missing_citizen_identities.yes_approve') }}',
                    cancelButtonText: '{{ __('ui.buttons.cancel') }}',
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-light'
                    }
                }).then(function (result) {
                    return Boolean(result.isConfirmed);
                });
            };

            var removeApprovedRows = function (reportIds) {
                reportIds.forEach(function (reportId) {
                    Array.prototype.slice.call(document.querySelectorAll('[data-kt-missing-citizens-row]'))
                        .filter(function (row) {
                            return row.getAttribute('data-kt-missing-citizens-row') === String(reportId);
                        })
                        .forEach(function (row) {
                            row.remove();
                        });
                });

                updateBulkState();
            };

            var clearNamePartInputs = function () {
                namePartInputs.forEach(function (input) {
                    input.value = '';
                });
            };

            var triggerCitizenSearch = function () {
                clearTimeout(manualSearchTimer);
                manualSearchTimer = setTimeout(function () {
                    if (activeCandidateReportId) {
                        searchCitizens(activeCandidateReportId);
                    }
                }, 350);
            };

            var fillNamePartInputs = function (ownerName) {
                clearNamePartInputs();

                if (!ownerName || ownerName === '-') {
                    return;
                }

                String(ownerName).trim().split(/\s+/).slice(0, 4).forEach(function (part, index) {
                    if (namePartInputs[index]) {
                        namePartInputs[index].value = part;
                    }
                });
            };

            var citizenSearchParams = function () {
                var params = new URLSearchParams();

                if (manualSearch && manualSearch.value.trim() !== '') {
                    params.set('q', manualSearch.value.trim());

                    return params;
                }

                namePartInputs.forEach(function (input) {
                    if (input.value.trim() !== '') {
                        params.set(input.getAttribute('data-kt-missing-citizen-name-part'), input.value.trim());
                    }
                });

                return params;
            };

            var hasCitizenSearchValues = function () {
                return Array.from(citizenSearchParams().values()).some(function (value) {
                    return value.length >= 2;
                });
            };

            var showCitizenSearch = function (reportId, ownerName) {
                activeCandidateReportId = reportId;
                resetDocuments();
                renderIdentitySummary(reportId);

                if (manualSearch) {
                    manualSearch.value = '';
                }
                fillNamePartInputs(ownerName);

                if (candidatesBody) {
                    candidatesBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-8">{{ __('ui.missing_citizen_identities.type_to_search_citizens') }}</td></tr>';
                }

                if (candidatesModalElement && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(candidatesModalElement).show();
                }

                if (hasCitizenSearchValues()) {
                    searchCitizens(reportId);
                }
            };

            var searchCitizens = function (reportId) {
                if (!candidatesBody) {
                    return;
                }

                if (!hasCitizenSearchValues()) {
                    candidatesBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-8">{{ __('ui.missing_citizen_identities.type_to_search_citizens') }}</td></tr>';
                    return;
                }

                candidatesBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-8">{{ __('ui.missing_citizen_identities.loading') }}</td></tr>';

                fetch(citizenSearchUrlTemplate.replace('__REPORT__', reportId) + '?' + citizenSearchParams().toString(), {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then(function (response) {
                        return response.json().then(function (payload) {
                            if (!response.ok) {
                                throw payload;
                            }

                            return payload;
                        });
                    })
                    .then(function (payload) {
                        renderCandidateRows(payload.data || []);
                    })
                    .catch(function (payload) {
                        candidatesBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-8">' + escapeHtml(payload.message || '{{ __('ui.messages.unexpected_error') }}') + '</td></tr>';
                    });
            };

            var approveReport = function (reportId, button, citizenId) {
                confirmAction('{{ __('ui.missing_citizen_identities.approve_confirm') }}').then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    button.disabled = true;
                    button.textContent = '{{ __('ui.missing_citizen_identities.approving') }}';

                    fetch(approveUrlTemplate.replace('__REPORT__', reportId), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            confirm: true,
                            citizen_id: citizenId || null
                        })
                    })
                        .then(function (response) {
                            return response.json().then(function (payload) {
                                if (!response.ok) {
                                    throw payload;
                                }

                                return payload;
                            });
                        })
                        .then(function (payload) {
                            if (candidatesModalElement && window.bootstrap) {
                                bootstrap.Modal.getOrCreateInstance(candidatesModalElement).hide();
                            }

                            removeApprovedRows([reportId]);
                            loadCursor(cursorStack[cursorIndex]);
                            showToast(payload.message || '{{ __('ui.missing_citizen_identities.approved_success') }}', 'success');
                        })
                        .catch(function (payload) {
                            showToast(payload.message || '{{ __('ui.messages.unexpected_error') }}', 'error');
                            button.disabled = false;
                            button.textContent = citizenId ? '{{ __('ui.missing_citizen_identities.approve_this_candidate') }}' : '{{ __('ui.missing_citizen_identities.approve_match') }}';
                        });
                });
            };

            var bulkApprove = function (button) {
                var reportIds = selectedReportIds();

                if (reportIds.length === 0) {
                    return;
                }

                confirmAction('{{ __('ui.missing_citizen_identities.bulk_approve_confirm') }}').then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    button.disabled = true;
                    button.textContent = '{{ __('ui.missing_citizen_identities.approving') }}';

                    fetch(bulkApproveUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            report_ids: reportIds
                        })
                    })
                        .then(function (response) {
                            return response.json().then(function (payload) {
                                if (!response.ok) {
                                    throw payload;
                                }

                                return payload;
                            });
                        })
                        .then(function (payload) {
                            showToast(payload.message || '{{ __('ui.missing_citizen_identities.bulk_approved_done') }}', 'success');
                            removeApprovedRows(reportIds);
                            loadCursor(cursorStack[cursorIndex]);
                        })
                        .catch(function (payload) {
                            showToast(payload.message || '{{ __('ui.messages.unexpected_error') }}', 'error');
                        })
                        .finally(function () {
                            button.textContent = '{{ __('ui.missing_citizen_identities.approve_selected') }}';
                            updateBulkState();
                        });
                });
            };

            var bindEvents = function () {
                var search = document.querySelector('[data-kt-missing-citizens-filter="search"]');
                var unitObjectId = document.querySelector('[data-kt-missing-citizens-filter="unit-objectid"]');
                var searchTimer;

                Array.prototype.slice.call(document.querySelectorAll('[data-kt-missing-citizens-subject]')).forEach(function (button) {
                    button.addEventListener('click', function () {
                        currentIdentitySubject = button.getAttribute('data-kt-missing-citizens-subject') || 'owner';

                        Array.prototype.slice.call(document.querySelectorAll('[data-kt-missing-citizens-subject]')).forEach(function (subjectButton) {
                            var active = subjectButton === button;
                            subjectButton.classList.toggle('btn-primary', active);
                            subjectButton.classList.toggle('btn-light-primary', !active);
                        });

                        if (nameHeader) {
                            nameHeader.textContent = currentIdentitySubject === 'spouse'
                                ? '{{ __('ui.missing_citizen_identities.spouse_name') }}'
                                : '{{ __('ui.missing_citizen_identities.owner_name') }}';
                        }

                        if (idNumberHeader) {
                            idNumberHeader.textContent = currentIdentitySubject === 'spouse'
                                ? '{{ __('ui.missing_citizen_identities.spouse_id_number') }}'
                                : '{{ __('ui.missing_citizen_identities.id_number') }}';
                        }

                        updateOwnerContextVisibility();

                        cursorStack = [0];
                        cursorIndex = 0;
                        nextCursor = null;
                        loadCursor(0);
                    });
                });

                if (search) {
                    search.addEventListener('keyup', function (event) {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(function () {
                            currentSearch = event.target.value;
                            cursorStack = [0];
                            cursorIndex = 0;
                            nextCursor = null;
                            loadCursor(0);
                        }, 350);
                    });
                }

                if (unitObjectId) {
                    unitObjectId.addEventListener('input', function (event) {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(function () {
                            currentUnitObjectId = event.target.value;
                            currentUnitObjectIds = '';
                            updateUnitObjectIdsState();
                            cursorStack = [0];
                            cursorIndex = 0;
                            nextCursor = null;
                            loadCursor(0);
                        }, 350);
                    });
                }

                var refresh = document.querySelector('[data-kt-missing-citizens-action="refresh"]');
                var perPage = document.querySelector('[data-kt-missing-citizens-filter="per-page"]');
                var issueType = document.querySelector('[data-kt-missing-citizens-filter="issue-type"]');
                var nameMatchStatus = document.querySelector('[data-kt-missing-citizens-filter="name-match-status"]');
                var bulkApproveButton = document.querySelector('[data-kt-missing-citizens-action="bulk-approve"]');
                var selectAll = document.querySelector('[data-kt-missing-citizens-action="select-all"]');
                var selectAllVisible = document.querySelector('[data-kt-missing-citizens-action="select-all-visible"]');
                var documentsButton = document.querySelector('[data-kt-missing-citizens-action="show-documents"]');
                var hideDocumentsButton = document.querySelector('[data-kt-missing-citizens-action="hide-documents"]');
                var openUnitObjectIdsModal = document.querySelector('[data-kt-missing-citizens-action="open-unit-objectids-modal"]');
                var applyUnitObjectIds = document.querySelector('[data-kt-missing-citizens-action="apply-unit-objectids"]');
                var clearUnitObjectIds = document.querySelector('[data-kt-missing-citizens-action="clear-unit-objectids"]');

                if (refresh) {
                    refresh.addEventListener('click', function () {
                        loadCursor(cursorStack[cursorIndex]);
                    });
                }

                if (perPage) {
                    perPage.addEventListener('change', function (event) {
                        currentPerPage = Number(event.target.value || 100);
                        cursorStack = [0];
                        cursorIndex = 0;
                        nextCursor = null;
                        loadCursor(0);
                    });
                }

                if (issueType) {
                    issueType.addEventListener('change', function (event) {
                        currentIssueType = event.target.value || '';
                        cursorStack = [0];
                        cursorIndex = 0;
                        nextCursor = null;
                        loadCursor(0);
                    });
                }

                if (nameMatchStatus) {
                    nameMatchStatus.addEventListener('change', function (event) {
                        currentNameMatchStatus = event.target.value || '';
                        cursorStack = [0];
                        cursorIndex = 0;
                        nextCursor = null;
                        loadCursor(0);
                    });
                }

                if (bulkApproveButton) {
                    bulkApproveButton.addEventListener('click', function () {
                        bulkApprove(bulkApproveButton);
                    });
                }

                if (selectAll) {
                    selectAll.addEventListener('change', function (event) {
                        setVisibleRowsSelection(event.target.checked);
                    });
                }

                if (selectAllVisible) {
                    selectAllVisible.addEventListener('click', function () {
                        setVisibleRowsSelection(!allVisibleRowsSelected());
                    });
                }

                if (documentsButton) {
                    documentsButton.addEventListener('click', function () {
                        if (activeCandidateReportId) {
                            loadDocuments(activeCandidateReportId);
                        }
                    });
                }

                if (hideDocumentsButton) {
                    hideDocumentsButton.addEventListener('click', resetDocuments);
                }

                if (openUnitObjectIdsModal) {
                    openUnitObjectIdsModal.addEventListener('click', function () {
                        if (unitObjectIdsTextarea) {
                            unitObjectIdsTextarea.value = currentUnitObjectIds;
                            updateUnitObjectIdsState();
                        }

                        if (unitObjectIdsModalElement && window.bootstrap) {
                            bootstrap.Modal.getOrCreateInstance(unitObjectIdsModalElement).show();
                        }
                    });
                }

                if (unitObjectIdsTextarea) {
                    unitObjectIdsTextarea.addEventListener('input', function (event) {
                        var count = extractUnitObjectIds(event.target.value).length;

                        if (unitObjectIdsPreview) {
                            unitObjectIdsPreview.textContent = count + ' رقم';
                        }
                    });
                }

                if (applyUnitObjectIds) {
                    applyUnitObjectIds.addEventListener('click', function () {
                        currentUnitObjectIds = unitObjectIdsTextarea ? extractUnitObjectIds(unitObjectIdsTextarea.value).join('\n') : '';
                        currentUnitObjectId = '';

                        if (unitObjectId) {
                            unitObjectId.value = '';
                        }

                        updateUnitObjectIdsState();

                        if (unitObjectIdsModalElement && window.bootstrap) {
                            bootstrap.Modal.getOrCreateInstance(unitObjectIdsModalElement).hide();
                        }

                        cursorStack = [0];
                        cursorIndex = 0;
                        nextCursor = null;
                        loadCursor(0);
                    });
                }

                if (clearUnitObjectIds) {
                    clearUnitObjectIds.addEventListener('click', function () {
                        currentUnitObjectIds = '';

                        if (unitObjectIdsTextarea) {
                            unitObjectIdsTextarea.value = '';
                        }

                        updateUnitObjectIdsState();
                        cursorStack = [0];
                        cursorIndex = 0;
                        nextCursor = null;
                        loadCursor(0);
                    });
                }

                var previous = document.querySelector('[data-kt-missing-citizens-action="previous"]');

                if (previous) {
                    previous.addEventListener('click', function () {
                        cursorIndex = Math.max(0, cursorIndex - 1);
                        loadCursor(cursorStack[cursorIndex]);
                    });
                }

                var next = document.querySelector('[data-kt-missing-citizens-action="next"]');

                if (next) {
                    next.addEventListener('click', function () {
                        if (!hasMore || nextCursor === null) {
                            return;
                        }

                        cursorStack = cursorStack.slice(0, cursorIndex + 1);
                        cursorStack.push(nextCursor);
                        cursorIndex += 1;
                        loadCursor(nextCursor);
                    });
                }

                if (tbody) {
                    tbody.addEventListener('change', function (event) {
                        if (event.target.matches('[data-kt-missing-citizens-row-check]')) {
                            updateBulkState();
                        }
                    });

                    tbody.addEventListener('click', function (event) {
                        var button = event.target.closest('[data-kt-missing-citizens-action="approve-name"]');

                        if (button && !loading) {
                            approveReport(button.getAttribute('data-report-id'), button);

                            return;
                        }

                        var candidatesButton = event.target.closest('[data-kt-missing-citizens-action="show-candidates"]');

                        if (candidatesButton && !loading) {
                            showCandidates(candidatesButton.getAttribute('data-report-id'));

                            return;
                        }

                        var searchCitizensButton = event.target.closest('[data-kt-missing-citizens-action="search-citizens"]');

                        if (searchCitizensButton && !loading) {
                            showCitizenSearch(
                                searchCitizensButton.getAttribute('data-report-id'),
                                searchCitizensButton.getAttribute('data-owner-name')
                            );
                        }
                    });
                }

                if (manualSearch) {
                    manualSearch.addEventListener('keyup', function (event) {
                        if (event.target.value.trim() !== '') {
                            clearNamePartInputs();
                        }

                        triggerCitizenSearch();
                    });
                }

                namePartInputs.forEach(function (input) {
                    input.addEventListener('keyup', function () {
                        if (manualSearch) {
                            manualSearch.value = '';
                        }

                        triggerCitizenSearch();
                    });
                });

                Array.prototype.slice.call(document.querySelectorAll('[data-kt-missing-citizen-clear-input]')).forEach(function (button) {
                    button.addEventListener('click', function () {
                        var input = document.querySelector(button.getAttribute('data-kt-missing-citizen-clear-input'));

                        if (input) {
                            input.value = '';
                            input.focus();
                            triggerCitizenSearch();
                        }
                    });
                });

                Array.prototype.slice.call(document.querySelectorAll('[data-kt-missing-citizen-clear-nearest]')).forEach(function (button) {
                    button.addEventListener('click', function () {
                        var wrapper = button.closest('.input-group');
                        var input = wrapper ? wrapper.querySelector('input') : null;

                        if (input) {
                            input.value = '';
                            input.focus();
                            triggerCitizenSearch();
                        }
                    });
                });

                if (candidatesBody) {
                    candidatesBody.addEventListener('click', function (event) {
                        var button = event.target.closest('[data-kt-missing-citizens-action="approve-candidate"]');

                        if (!button || !activeCandidateReportId) {
                            return;
                        }

                        approveReport(activeCandidateReportId, button, button.getAttribute('data-citizen-id'));
                    });
                }
            };

            return {
                init: function () {
                    bindEvents();
                    updateOwnerContextVisibility();
                    updateUnitObjectIdsState();
                    loadCursor(0);
                }
            };
        }();

        KTUtil.onDOMContentLoaded(function () {
            KTMissingCitizenIdentities.init();
        });
    </script>
@endsection
