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
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">{{ __('ui.missing_citizen_identities.table_title') }}</h3>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="position-relative">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-4"></i>
                    <input type="text" data-kt-missing-citizens-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="{{ __('ui.missing_citizen_identities.search_placeholder') }}">
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted fs-7">{{ __('ui.missing_citizen_identities.rows_per_page') }}</span>
                    <select class="form-select form-select-solid w-100px" data-kt-missing-citizens-filter="per-page">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <button type="button" class="btn btn-light-primary" data-kt-missing-citizens-action="refresh">
                    <i class="ki-duotone ki-arrows-circle fs-2"></i>
                    {{ __('ui.missing_citizen_identities.refresh') }}
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5 w-100" id="kt_table_missing_citizen_identities">
                    <thead>
                        <tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">
                            <th>{{ __('ui.missing_citizen_identities.owner_name') }}</th>
                            <th>{{ __('ui.missing_citizen_identities.id_number') }}</th>
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
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">{{ __('ui.missing_citizen_identities.candidates_title') }}</h3>
                    <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ki-duotone ki-cross fs-1"></i>
                    </button>
                </div>
                <div class="modal-body">
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
@endsection

@section('script')
    <script>
        var KTMissingCitizenIdentities = function () {
            var table = document.getElementById('kt_table_missing_citizen_identities');
            var tbody = table ? table.querySelector('tbody') : null;
            var pageInfo = document.getElementById('missing_citizens_page_info');
            var currentSearch = '';
            var hasMore = false;
            var loading = false;
            var currentPerPage = 25;
            var cursorStack = [0];
            var cursorIndex = 0;
            var nextCursor = null;
            var total = document.getElementById('missing_citizens_total');
            var approveUrlTemplate = @json(route('reports.missing-citizen-identities.approve-name-match', ['report' => '__REPORT__']));
            var candidatesUrlTemplate = @json(route('reports.missing-citizen-identities.name-candidates', ['report' => '__REPORT__']));
            var csrfToken = @json(csrf_token());
            var candidatesModalElement = document.getElementById('missing_citizen_candidates_modal');
            var candidatesBody = document.getElementById('missing_citizen_candidates_body');
            var activeCandidateReportId = null;

            var setButtonsState = function () {
                var previous = document.querySelector('[data-kt-missing-citizens-action="previous"]');
                var next = document.querySelector('[data-kt-missing-citizens-action="next"]');

                if (previous) {
                    previous.disabled = loading || cursorIndex <= 0;
                }

                if (next) {
                    next.disabled = loading || !hasMore;
                }
            };

            var escapeHtml = function (value) {
                return String(value || '-')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            var renderRows = function (rows) {
                if (!tbody) {
                    return;
                }

                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-10">{{ __('ui.missing_citizen_identities.empty_table') }}</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(function (row) {
                    var status = matchStatusBadge(row);
                    var matchedCitizen = row.matched_citizen_full_name
                        ? '<div class="fw-semibold">' + escapeHtml(row.matched_citizen_full_name) + '</div><span class="badge badge-light-success">' + escapeHtml(row.matched_citizen_id_card_no) + '</span>'
                        : '<span class="text-muted">-</span>';
                    var action = actionButton(row);

                    return '<tr>'
                        + '<td>' + escapeHtml(row.owner_name) + '</td>'
                        + '<td><span class="badge badge-light-danger">' + escapeHtml(row.id_number1) + '</span></td>'
                        + '<td>' + status + '</td>'
                        + '<td>' + matchedCitizen + '</td>'
                        + '<td class="text-end">' + action + '</td>'
                        + '</tr>';
                }).join('');
            };

            var actionButton = function (row) {
                if (row.can_approve_name_match) {
                    return '<button type="button" class="btn btn-sm btn-light-success" data-kt-missing-citizens-action="approve-name" data-report-id="' + escapeHtml(row.id) + '">{{ __('ui.missing_citizen_identities.approve_match') }}</button>';
                }

                if (row.can_show_name_candidates) {
                    return '<button type="button" class="btn btn-sm btn-light-warning" data-kt-missing-citizens-action="show-candidates" data-report-id="' + escapeHtml(row.id) + '">{{ __('ui.missing_citizen_identities.show_candidates') }}</button>';
                }

                return '<span class="text-muted">-</span>';
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

            var loadCursor = function (cursor) {
                if (loading) {
                    return;
                }

                loading = true;
                setButtonsState();

                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-10">{{ __('ui.missing_citizen_identities.loading') }}</td></tr>';
                }

                var params = new URLSearchParams({
                    after_id: cursor,
                    search: currentSearch,
                    per_page: currentPerPage
                });

                fetch("{{ route('reports.missing-citizen-identities.data') }}?" + params.toString(), {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then(function (response) {
                        return response.json();
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
                            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-10">{{ __('ui.messages.unexpected_error') }}</td></tr>';
                        }
                    })
                    .finally(function () {
                        loading = false;
                        setButtonsState();
                    });
            };

            var showCandidates = function (reportId) {
                activeCandidateReportId = reportId;

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

                        if (candidates.length === 0) {
                            candidatesBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-8">{{ __('ui.missing_citizen_identities.no_candidates') }}</td></tr>';
                            return;
                        }

                        candidatesBody.innerHTML = candidates.map(function (candidate) {
                            return '<tr>'
                                + '<td>' + escapeHtml(candidate.full_name) + '</td>'
                                + '<td><span class="badge badge-light-primary">' + escapeHtml(candidate.id_card_no) + '</span></td>'
                                + '<td class="text-end"><button type="button" class="btn btn-sm btn-light-success" data-kt-missing-citizens-action="approve-candidate" data-citizen-id="' + escapeHtml(candidate.id) + '">{{ __('ui.missing_citizen_identities.approve_this_candidate') }}</button></td>'
                                + '</tr>';
                        }).join('');
                    })
                    .catch(function (payload) {
                        if (candidatesBody) {
                            candidatesBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-8">' + escapeHtml(payload.message || '{{ __('ui.messages.unexpected_error') }}') + '</td></tr>';
                        }
                    });
            };

            var approveReport = function (reportId, button, citizenId) {
                if (!confirm('{{ __('ui.missing_citizen_identities.approve_confirm') }}')) {
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
                    .then(function () {
                        if (candidatesModalElement && window.bootstrap) {
                            bootstrap.Modal.getOrCreateInstance(candidatesModalElement).hide();
                        }

                        loadCursor(cursorStack[cursorIndex]);
                    })
                    .catch(function (payload) {
                        alert(payload.message || '{{ __('ui.messages.unexpected_error') }}');
                        button.disabled = false;
                        button.textContent = citizenId ? '{{ __('ui.missing_citizen_identities.approve_this_candidate') }}' : '{{ __('ui.missing_citizen_identities.approve_match') }}';
                    });
            };

            var bindEvents = function () {
                var search = document.querySelector('[data-kt-missing-citizens-filter="search"]');
                var searchTimer;

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

                var refresh = document.querySelector('[data-kt-missing-citizens-action="refresh"]');
                var perPage = document.querySelector('[data-kt-missing-citizens-filter="per-page"]');

                if (refresh) {
                    refresh.addEventListener('click', function () {
                        loadCursor(cursorStack[cursorIndex]);
                    });
                }

                if (perPage) {
                    perPage.addEventListener('change', function (event) {
                        currentPerPage = Number(event.target.value || 25);
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
                    tbody.addEventListener('click', function (event) {
                        var button = event.target.closest('[data-kt-missing-citizens-action="approve-name"]');

                        if (button && !loading) {
                            approveReport(button.getAttribute('data-report-id'), button);

                            return;
                        }

                        var candidatesButton = event.target.closest('[data-kt-missing-citizens-action="show-candidates"]');

                        if (candidatesButton && !loading) {
                            showCandidates(candidatesButton.getAttribute('data-report-id'));
                        }
                    });
                }

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
                    loadCursor(0);
                }
            };
        }();

        KTUtil.onDOMContentLoaded(function () {
            KTMissingCitizenIdentities.init();
        });
    </script>
@endsection
