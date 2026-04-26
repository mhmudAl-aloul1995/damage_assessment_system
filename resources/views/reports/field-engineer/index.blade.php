@extends('layouts.app')

@section('title', __('multilingual.field_engineer_report.title'))
@section('pageName', __('multilingual.field_engineer_report.page_name'))

@php
    $isArabic = app()->getLocale() === 'ar';
    $currentTab = request('tab', 'buildings');
    $summaryCards = [
        ['key' => 'total_buildings', 'class' => 'primary'],
        ['key' => 'total_housing_units', 'class' => 'info'],
        ['key' => 'damaged_buildings', 'class' => 'danger'],
        ['key' => 'damaged_housing_units', 'class' => 'warning'],
        ['key' => 'building_edits', 'class' => 'success'],
        ['key' => 'housing_edits', 'class' => 'dark'],
        ['key' => 'accepted_statuses', 'class' => 'success'],
        ['key' => 'rejected_statuses', 'class' => 'danger'],
        ['key' => 'need_review_statuses', 'class' => 'warning'],
        ['key' => 'last_updated_at', 'class' => 'secondary', 'isDate' => true],
        ['key' => 'completion_rate', 'class' => 'primary', 'isPercent' => true],
        ['key' => 'completed_buildings', 'class' => 'success'],
        ['key' => 'not_completed_buildings', 'class' => 'danger'],
    ];
@endphp

@section('content')
    <style>
        .field-engineer-report .stats-card {
            border: 1px dashed #d9dee7;
            border-radius: 1rem;
            padding: 1.25rem;
            height: 100%;
            background: #fff;
        }

        .field-engineer-report .stats-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .field-engineer-report .toolbar-actions .btn {
            min-width: 120px;
        }

        .field-engineer-report .loading-box {
            display: none;
            align-items: center;
            gap: 10px;
            color: #0d6efd;
            font-weight: 600;
        }

        .field-engineer-report .loading-box.is-active {
            display: inline-flex;
        }

        .field-engineer-report .error-box {
            display: none;
        }

        .field-engineer-report .error-box.is-active {
            display: block;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .field-engineer-report .print-target,
            .field-engineer-report .print-target * {
                visibility: visible;
            }

            .field-engineer-report .print-target {
                position: absolute;
                inset: 0;
                width: 100%;
                padding: 20px;
            }

            .field-engineer-report .nav,
            .field-engineer-report .toolbar-actions,
            .field-engineer-report .card-header,
            .field-engineer-report .dataTables_length,
            .field-engineer-report .dataTables_filter,
            .field-engineer-report .dataTables_paginate,
            .field-engineer-report .dataTables_info,
            .field-engineer-report .loading-box,
            .field-engineer-report .error-box {
                display: none !important;
            }
        }
    </style>

    <div class="field-engineer-report">
        <div class="card mb-7">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-5">
                <div>
                    <div class="text-muted fs-7 mb-2">
                        {{ __('menu.reports.title') }} / {{ __('multilingual.field_engineer_report.page_name') }}
                    </div>
                    <h2 class="mb-2">{{ __('multilingual.field_engineer_report.title') }}</h2>
                    <div class="text-muted fs-6">{{ __('multilingual.field_engineer_report.subtitle') }}</div>
                    <div class="mt-3 text-gray-700 fw-semibold">
                        {{ __('multilingual.field_engineer_report.results_for') }}:
                        <span class="badge badge-light-primary fs-7">
                            {{ $filters['assignedto'] ?: __('multilingual.field_engineer_report.no_engineer_selected') }}
                        </span>
                    </div>
                    <div id="fieldEngineerLoadingState" class="loading-box mt-3">
                        <span class="spinner-border spinner-border-sm"></span>
                        <span>Loading data...</span>
                    </div>
                    <div id="fieldEngineerErrorState" class="alert alert-danger error-box mt-3 mb-0"></div>
                </div>

                <div class="toolbar-actions d-flex flex-wrap gap-3">
                    <a href="{{ route('reports.field-engineer.index', request()->query()) }}" class="btn btn-light-primary">
                        <i class="ki-duotone ki-arrows-circle fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        {{ __('multilingual.field_engineer_report.actions.refresh') }}
                    </a>

                    <button type="button" class="btn btn-light-success export-tab-btn" data-format="xlsx">
                        <i class="ki-duotone ki-file-down fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        {{ __('multilingual.field_engineer_report.actions.export_excel') }}
                    </button>

                    <button type="button" class="btn btn-light-info export-tab-btn" data-format="csv">
                        <i class="ki-duotone ki-file-down fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        {{ __('multilingual.field_engineer_report.actions.export_csv') }}
                    </button>

                    <button type="button" class="btn btn-light-dark" id="printActiveTab">
                        <i class="ki-duotone ki-printer fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        {{ __('multilingual.field_engineer_report.actions.print') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="card mb-7">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="mb-0">{{ __('multilingual.field_engineer_report.filters_title') }}</h3>
                </div>
                <div class="card-toolbar">
                    <button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="collapse"
                        data-bs-target="#fieldEngineerFilters" aria-expanded="true" id="toggleFieldEngineerFilters">
                        <i class="fas fa-chevron-down me-1"></i>
                        {{ __('multilingual.field_engineer_report.actions.hide_filters') }}
                    </button>
                </div>
            </div>

            <div class="collapse show" id="fieldEngineerFilters">
                <div class="card-body pt-2">
                    <form method="GET" action="{{ route('reports.field-engineer.index') }}" id="fieldEngineerFiltersForm">
                        <div class="row g-5">
                            <div class="col-md-4">
                                <label
                                    class="form-label">{{ __('multilingual.field_engineer_report.filters.assignedto') }}</label>
                                <select name="assignedto" class="form-select form-select-solid report-select2"
                                    data-placeholder="{{ __('multilingual.field_engineer_report.select_placeholder') }}">
                                    <option value="">{{ __('multilingual.field_engineer_report.all_options') }}</option>
                                    @foreach ($filterOptions['engineers'] as $engineer)
                                        <option value="{{ $engineer }}" @selected($filters['assignedto'] === $engineer)>
                                            {{ $engineer }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label
                                    class="form-label">{{ __('multilingual.field_engineer_report.filters.municipalitie') }}</label>
                                <select name="municipalitie" class="form-select form-select-solid report-select2"
                                    data-placeholder="{{ __('multilingual.field_engineer_report.select_placeholder') }}">
                                    <option value="">{{ __('multilingual.field_engineer_report.all_options') }}</option>
                                    @foreach ($filterOptions['municipalities'] as $municipality)
                                        <option value="{{ $municipality }}"
                                            @selected($filters['municipalitie'] === $municipality)>{{ $municipality }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label
                                    class="form-label">{{ __('multilingual.field_engineer_report.filters.neighborhood') }}</label>
                                <select name="neighborhood" class="form-select form-select-solid report-select2"
                                    data-placeholder="{{ __('multilingual.field_engineer_report.select_placeholder') }}">
                                    <option value="">{{ __('multilingual.field_engineer_report.all_options') }}</option>
                                    @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                                        <option value="{{ $neighborhood }}" @selected($filters['neighborhood'] === $neighborhood)>
                                            {{ $neighborhood }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label
                                    class="form-label">{{ __('multilingual.field_engineer_report.filters.building_damage_status') }}</label>
                                <select name="building_damage_status" class="form-select form-select-solid report-select2"
                                    data-placeholder="{{ __('multilingual.field_engineer_report.select_placeholder') }}">
                                    <option value="">{{ __('multilingual.field_engineer_report.all_options') }}</option>
                                    @foreach ($filterOptions['building_damage_statuses'] as $status)
                                        <option value="{{ $status }}" @selected($filters['building_damage_status'] === $status)>
                                            {{ $status }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label
                                    class="form-label">{{ __('multilingual.field_engineer_report.filters.engineer_status') }}</label>
                                <select name="engineer_status" class="form-select form-select-solid report-select2"
                                    data-placeholder="{{ __('multilingual.field_engineer_report.select_placeholder') }}">
                                    <option value="">{{ __('multilingual.field_engineer_report.all_options') }}</option>
                                    @foreach ($filterOptions['engineer_statuses'] as $status)
                                        <option value="{{ $status['name'] }}"
                                            @selected($filters['engineer_status'] === $status['name'])>{{ $status['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label
                                    class="form-label">{{ __('multilingual.field_engineer_report.filters.legal_status') }}</label>
                                <select name="legal_status" class="form-select form-select-solid report-select2"
                                    data-placeholder="{{ __('multilingual.field_engineer_report.select_placeholder') }}">
                                    <option value="">{{ __('multilingual.field_engineer_report.all_options') }}</option>
                                    @foreach ($filterOptions['legal_statuses'] as $status)
                                        <option value="{{ $status['name'] }}"
                                            @selected($filters['legal_status'] === $status['name'])>{{ $status['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label
                                    class="form-label">{{ __('multilingual.field_engineer_report.filters.final_status') }}</label>
                                <select name="final_status" class="form-select form-select-solid report-select2"
                                    data-placeholder="{{ __('multilingual.field_engineer_report.select_placeholder') }}">
                                    <option value="">{{ __('multilingual.field_engineer_report.all_options') }}</option>
                                    @foreach ($filterOptions['final_statuses'] as $status)
                                        <option value="{{ $status['name'] }}"
                                            @selected($filters['final_status'] === $status['name'])>{{ $status['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">
                                    {{ __('multilingual.field_engineer_report.filters.from_date') }}
                                </label>
                                <input type="text" name="from_date " placeholder="yyyy-mm-dd"
                                    value="{{ $filters['from_date'] }}" class="form-control datepicker form-control-solid">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">
                                    {{ __('multilingual.field_engineer_report.filters.to_date') }}
                                </label>
                                <input type="text" name="to_date" placeholder="yyyy-mm-dd"
                                    value="{{ $filters['to_date'] }}" class="form-control datepicker form-control-solid">
                            </div>

                            <div class="col-md-8">
                                <label
                                    class="form-label">{{ __('multilingual.field_engineer_report.filters.search') }}</label>
                                <input type="text" name="search" value="{{ $filters['search'] }}"
                                    placeholder="{{ __('multilingual.field_engineer_report.search_placeholder') }}"
                                    class="form-control form-control-solid">
                            </div>

                            <div class="col-md-4 d-flex align-items-end gap-3">
                                <button type="submit" class="btn btn-primary flex-fill">Search</button>
                                <button type="button" class="btn btn-light flex-fill"
                                    id="resetFieldEngineerFilters">Reset</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row g-5 mb-7">
            @foreach ($summaryCards as $card)
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="text-muted fw-semibold fs-7 mb-3">
                            {{ __("multilingual.field_engineer_report.stats.{$card['key']}") }}
                        </div>
                        <div class="value text-{{ $card['class'] }}" data-summary-key="{{ $card['key'] }}">
                            @php
                                $summaryValue = $summary[$card['key']] ?? null;
                            @endphp
                            @if (!empty($card['isDate']))
                                {{ $summaryValue ?: '-' }}
                            @elseif (!empty($card['isPercent']))
                                {{ number_format((float) ($summaryValue ?? 0), 1) }}%
                            @else
                                {{ number_format((float) ($summaryValue ?? 0)) }}
                            @endif
                        </div>
                        @if ($card['key'] === 'completion_rate')
                            <div class="progress h-8px mt-4">
                                <div class="progress-bar bg-primary" role="progressbar" data-summary-progress="{{ $card['key'] }}"
                                    style="width: {{ min(100, (float) ($summary['completion_rate'] ?? 0)) }}%;"></div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 mb-5">
                    @foreach (['buildings', 'housing_units', 'edits', 'status_history', 'assignments'] as $tab)
                        <li class="nav-item">
                            <a class="nav-link {{ $currentTab === $tab ? 'active' : '' }}" data-bs-toggle="tab"
                                href="#tab-{{ $tab }}" data-tab="{{ $tab }}">
                                {{ __("multilingual.field_engineer_report.tabs.{$tab}") }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade {{ $currentTab === 'buildings' ? 'show active' : '' }}" id="tab-buildings">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100"
                                id="fieldEngineerBuildingsTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th>{{ __('multilingual.field_engineer_report.columns.object_id') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.globalid') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.assignedto') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.municipality') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.neighborhood') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.parcel_number') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.building_use') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.building_damage_status') }}
                                        </th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.creationdate') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.last_update') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.final_status') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $currentTab === 'housing_units' ? 'show active' : '' }}"
                        id="tab-housing_units">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100"
                                id="fieldEngineerHousingTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th>{{ __('multilingual.field_engineer_report.columns.object_id') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.parentglobalid') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.building_number') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.unit_use') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.damage_status') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.occupant_status') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.creationdate') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $currentTab === 'edits' ? 'show active' : '' }}" id="tab-edits">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100"
                                id="fieldEngineerEditsTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th>{{ __('multilingual.field_engineer_report.columns.type') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.globalid') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.field_name') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.old_value') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.new_value') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.updated_by') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.updated_at') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $currentTab === 'status_history' ? 'show active' : '' }}"
                        id="tab-status_history">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100"
                                id="fieldEngineerStatusHistoryTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th>{{ __('multilingual.field_engineer_report.columns.type') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.item_number') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.status') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.changed_by') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.changed_at') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $currentTab === 'assignments' ? 'show active' : '' }}"
                        id="tab-assignments">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100"
                                id="fieldEngineerAssignmentsTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th>{{ __('multilingual.field_engineer_report.columns.building_id') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.assigned_user') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.assigned_by') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.assigned_date') }}</th>
                                        <th>{{ __('multilingual.field_engineer_report.columns.notes') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>

        document.addEventListener('DOMContentLoaded', function () {
            const localeIsArabic = @json($isArabic);
            const currentTabInputValue = @json($currentTab);
            const initialSummary = @json($summary);
            const filtersForm = document.getElementById('fieldEngineerFiltersForm');
            const loadingState = document.getElementById('fieldEngineerLoadingState');
            const errorState = document.getElementById('fieldEngineerErrorState');
            const resetFiltersButton = document.getElementById('resetFieldEngineerFilters');
            const tables = {};

            $.fn.dataTable.ext.errMode = 'none';

            $('.report-select2').select2({
                allowClear: true,
                width: '100%',
                dir: localeIsArabic ? 'rtl' : 'ltr',
            });
            flatpickr('.datepicker', {
                dateFormat: 'Y-m-d',
                allowInput: true,
                locale: localeIsArabic ? 'ar' : 'en',
            });
          
            const dataTablesLanguageUrl = localeIsArabic
                ? '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json'
                : '//cdn.datatables.net/plug-ins/1.13.4/i18n/en-GB.json';

            const toggleButton = document.getElementById('toggleFieldEngineerFilters');
            const collapseElement = document.getElementById('fieldEngineerFilters');

            collapseElement.addEventListener('shown.bs.collapse', function () {
                toggleButton.innerHTML = '<i class="fas fa-chevron-down me-1"></i> {{ __('multilingual.field_engineer_report.actions.hide_filters') }}';
            });

            collapseElement.addEventListener('hidden.bs.collapse', function () {
                toggleButton.innerHTML = '<i class="fas fa-chevron-left me-1"></i> {{ __('multilingual.field_engineer_report.actions.show_filters') }}';
            });

            function setLoadingState(isLoading) {
                if (!loadingState) {
                    return;
                }

                loadingState.classList.toggle('is-active', isLoading);
            }

            function clearErrorState() {
                if (!errorState) {
                    return;
                }

                errorState.classList.remove('is-active');
                errorState.textContent = '';
            }

            function showError(message) {
                if (!errorState) {
                    return;
                }

                errorState.textContent = message;
                errorState.classList.add('is-active');
            }

            function filterPayload() {
                return $(filtersForm).serializeArray().reduce(function (carry, item) {
                    carry[item.name] = item.value;
                    return carry;
                }, {});
            }

            function hasAssignedEngineer() {
                const payload = filterPayload();

                return Boolean((payload.assignedto || '').trim());
            }

            function updatePageUrl() {
                const payload = filterPayload();
                const params = new URLSearchParams();

                Object.entries(payload).forEach(function ([key, value]) {
                    if (value !== null && value !== undefined && String(value).trim() !== '') {
                        params.set(key, value);
                    }
                });

                const activeTab = $('.nav-link.active').data('tab') || currentTabInputValue;

                if (activeTab) {
                    params.set('tab', activeTab);
                }

                const nextUrl = params.toString()
                    ? window.location.pathname + '?' + params.toString()
                    : window.location.pathname;

                window.history.replaceState({}, '', nextUrl);
            }

            function reloadInitializedTables() {
                Object.values(tables).forEach(function (table) {
                    table.ajax.reload(null, true);
                });
            }

            function buildExportUrl(format) {
                const activeTab = $('.nav-link.active').data('tab') || currentTabInputValue;
                const params = new URLSearchParams(filterPayload());
                params.set('tab', activeTab);

                return "{{ route('reports.field-engineer.export', ['tab' => '__TAB__', 'format' => '__FORMAT__']) }}"
                    .replace('__TAB__', activeTab)
                    .replace('__FORMAT__', format) + '?' + params.toString();
            }

            function renderSummary(summary) {
                Object.entries(summary).forEach(function ([key, value]) {
                    const summaryElement = document.querySelector('[data-summary-key="' + key + '"]');
                    if (!summaryElement) {
                        return;
                    }

                    if (key === 'last_updated_at') {
                        summaryElement.textContent = value ? value : '-';
                        return;
                    }

                    if (key === 'completion_rate') {
                        summaryElement.textContent = Number(value).toFixed(1) + '%';
                        const progressElement = document.querySelector('[data-summary-progress="' + key + '"]');
                        if (progressElement) {
                            progressElement.style.width = Math.min(100, Number(value)) + '%';
                        }
                        return;
                    }

                    summaryElement.textContent = Number(value || 0).toLocaleString();
                });
            }

            function fetchStats() {
                renderSummary(initialSummary || {});

                if (!hasAssignedEngineer()) {
                    return;
                }

                clearErrorState();
                setLoadingState(true);

                $.ajax({
                    url: "{{ route('reports.field-engineer.stats') }}",
                    method: 'GET',
                    data: filterPayload(),
                    success: function (response) {
                        renderSummary(response.summary || {});
                    },
                    error: function (xhr) {
                        console.log(xhr.responseText || xhr);
                        showError('Error loading stats. Check console.');
                    },
                    complete: function () {
                        setLoadingState(false);
                    }
                });
            }

            function initializeDataTable(key, selector, ajaxUrl, columns) {
                if (tables[key]) {
                    return tables[key];
                }

                tables[key] = $(selector).DataTable({
                    processing: true,
                    serverSide: true,
                    deferRender: true,
                    responsive: true,
                    searchDelay: 800,
                    pageLength: 25,
                    order: [[0, 'desc']],
                    ajax: {
                        url: ajaxUrl,
                        data: function (data) {
                            Object.assign(data, filterPayload());
                        },
                        beforeSend: function () {
                            clearErrorState();
                            setLoadingState(true);
                        },
                        complete: function () {
                            setLoadingState(false);
                        },
                        error: function (xhr) {
                            console.log(xhr.responseText || xhr);
                            showError('Error loading data. Check console.');
                        }
                    },
                    columns: columns,
                    language: {
                        url: dataTablesLanguageUrl
                    }
                });

                $(selector).on('error.dt', function (event, settings, techNote, message) {
                    console.log(message);
                    showError('Error loading data. Check console.');
                    setLoadingState(false);
                });

                return tables[key];
            }

            const tabTables = {
                buildings: function () {
                    return initializeDataTable('buildings', '#fieldEngineerBuildingsTable', "{{ url('reports/field-engineer/buildings') }}", [
                        { data: 'objectid', name: 'buildings.objectid' },
                        { data: 'globalid', name: 'buildings.globalid' },
                        { data: 'assignedto', name: 'buildings.assignedto' },
                        { data: 'municipalitie', name: 'municipalitie' },
                        { data: 'neighborhood', name: 'neighborhood' },
                        { data: 'parcel_no1', name: 'buildings.parcel_no1' },
                        { data: 'building_use', name: 'building_use' },
                        { data: 'building_damage_status', name: 'building_damage_status' },
                        { data: 'creationdate', name: 'buildings.creationdate' },
                        { data: 'editdate', name: 'buildings.editdate' },
                        { data: 'final_status_label', name: 'final_status_label', orderable: false, searchable: false },
                    ]);
                },
                housing_units: function () {
                    return initializeDataTable('housing_units', '#fieldEngineerHousingTable', "{{ url('reports/field-engineer/housing-units') }}", [
                        { data: 'objectid', name: 'housing_units.objectid' },
                        { data: 'parentglobalid', name: 'housing_units.parentglobalid' },
                        { data: 'building_objectid', name: 'building_objectid' },
                        { data: 'housing_unit_type', name: 'housing_unit_type' },
                        { data: 'unit_damage_status', name: 'unit_damage_status' },
                        { data: 'occupied', name: 'occupied' },
                        { data: 'creationdate', name: 'housing_units.creationdate' },
                    ]);
                },
                edits: function () {
                    return initializeDataTable('edits', '#fieldEngineerEditsTable', "{{ url('reports/field-engineer/edits') }}", [
                        { data: 'source_type', name: 'source_type' },
                        { data: 'global_id', name: 'edit_assessments.global_id' },
                        { data: 'field_name', name: 'edit_assessments.field_name' },
                        { data: 'old_value', name: 'old_value', orderable: false },
                        { data: 'new_value', name: 'new_value' },
                        { data: 'updated_by', name: 'updated_by' },
                        { data: 'updated_at', name: 'edit_assessments.updated_at' },
                    ]);
                },
                status_history: function () {
                    return initializeDataTable('status_history', '#fieldEngineerStatusHistoryTable', "{{ url('reports/field-engineer/status-history') }}", [
                        { data: 'item_type', name: 'item_type' },
                        { data: 'item_number', name: 'item_number' },
                        { data: 'status_label', name: 'status_label', orderable: false, searchable: false },
                        { data: 'changed_by', name: 'changed_by' },
                        { data: 'created_at', name: 'created_at' },
                    ]);
                },
                assignments: function () {
                    return initializeDataTable('assignments', '#fieldEngineerAssignmentsTable', "{{ url('reports/field-engineer/assignments') }}", [
                        { data: 'building_id', name: 'assigned_assessment_users.building_id' },
                        { data: 'assigned_user', name: 'assigned_user' },
                        { data: 'assigned_by', name: 'assigned_by' },
                        { data: 'assigned_date', name: 'assigned_date' },
                        { data: 'notes', name: 'notes', orderable: false, searchable: false },
                    ]);
                },
            };

            renderSummary(initialSummary || {});
            fetchStats();
            tabTables.buildings();

            $(filtersForm).on('submit', function (event) {
                event.preventDefault();
                clearErrorState();
                updatePageUrl();
                fetchStats();

                if (!tables.buildings) {
                    tabTables.buildings();
                }

                reloadInitializedTables();
            });

            $(resetFiltersButton).on('click', function () {
                filtersForm.reset();
                $('.report-select2').val(null).trigger('change');
                clearErrorState();
                updatePageUrl();
                fetchStats();

                if (!tables.buildings) {
                    tabTables.buildings();
                }

                reloadInitializedTables();
            });

            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (event) {
                const tab = $(event.target).data('tab');

                if (tabTables[tab]) {
                    tabTables[tab]();
                }

                updatePageUrl();
            });

            if (currentTabInputValue !== 'buildings' && tabTables[currentTabInputValue]) {
                tabTables[currentTabInputValue]();
            }

            $('.export-tab-btn').on('click', function () {
                window.location.href = buildExportUrl($(this).data('format'));
            });

            $('#printActiveTab').on('click', function () {
                window.print();
            });
        });
    </script>
@endsection