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
                        <div class="fs-2x fw-bold text-danger">{{ __('ui.missing_citizen_identities.fast_mode') }}</div>
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
@endsection

@section('script')
    <script>
        var KTMissingCitizenIdentities = function () {
            var table = document.getElementById('kt_table_missing_citizen_identities');
            var tbody = table ? table.querySelector('tbody') : null;
            var pageInfo = document.getElementById('missing_citizens_page_info');
            var currentPage = 1;
            var currentSearch = '';
            var hasMore = false;
            var loading = false;

            var setButtonsState = function () {
                var previous = document.querySelector('[data-kt-missing-citizens-action="previous"]');
                var next = document.querySelector('[data-kt-missing-citizens-action="next"]');

                if (previous) {
                    previous.disabled = loading || currentPage <= 1;
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
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-10">{{ __('ui.missing_citizen_identities.empty_table') }}</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(function (row) {
                    return '<tr><td>' + escapeHtml(row.owner_name) + '</td><td><span class="badge badge-light-danger">' + escapeHtml(row.id_number1) + '</span></td></tr>';
                }).join('');
            };

            var loadPage = function (page) {
                if (loading) {
                    return;
                }

                loading = true;
                currentPage = page;
                setButtonsState();

                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-10">{{ __('ui.missing_citizen_identities.loading') }}</td></tr>';
                }

                var params = new URLSearchParams({
                    page: currentPage,
                    search: currentSearch
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
                        renderRows(payload.data || []);

                        if (pageInfo) {
                            pageInfo.textContent = '{{ __('ui.missing_citizen_identities.page') }} ' + currentPage;
                        }
                    })
                    .catch(function () {
                        if (tbody) {
                            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-10">{{ __('ui.messages.unexpected_error') }}</td></tr>';
                        }
                    })
                    .finally(function () {
                        loading = false;
                        setButtonsState();
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
                            loadPage(1);
                        }, 350);
                    });
                }

                var refresh = document.querySelector('[data-kt-missing-citizens-action="refresh"]');

                if (refresh) {
                    refresh.addEventListener('click', function () {
                        loadPage(currentPage);
                    });
                }

                var previous = document.querySelector('[data-kt-missing-citizens-action="previous"]');

                if (previous) {
                    previous.addEventListener('click', function () {
                        loadPage(Math.max(1, currentPage - 1));
                    });
                }

                var next = document.querySelector('[data-kt-missing-citizens-action="next"]');

                if (next) {
                    next.addEventListener('click', function () {
                        loadPage(currentPage + 1);
                    });
                }
            };

            return {
                init: function () {
                    bindEvents();
                    loadPage(1);
                }
            };
        }();

        KTUtil.onDOMContentLoaded(function () {
            KTMissingCitizenIdentities.init();
        });
    </script>
@endsection
