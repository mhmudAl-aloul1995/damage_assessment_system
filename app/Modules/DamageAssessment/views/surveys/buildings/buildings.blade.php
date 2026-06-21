@extends('layouts.app')
@section('title', __('ui.buildings_page.title'))
@section('pageName', __('ui.buildings_page.title'))

@section('content')
    <div class="row g-5 mb-5">
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-2">{{ __('ui.buildings_page.total_buildings') }}</div>
                    <div class="fs-2x fw-bold text-gray-900">{{ $buildingSummary['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-2">{{ __('ui.buildings_page.fully_damaged') }}</div>
                    <div class="fs-2x fw-bold text-danger">{{ $buildingSummary['fully_damaged'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-2">{{ __('ui.buildings_page.partially_damaged') }}</div>
                    <div class="fs-2x fw-bold text-warning">{{ $buildingSummary['partially_damaged'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-2">{{ __('ui.buildings_page.committee_review') }}</div>
                    <div class="fs-2x fw-bold text-primary">{{ $buildingSummary['committee_review'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">{{ __('ui.buildings_page.filters_title') }}</h3>
            </div>
            <button class="btn btn-light-primary" type="button" data-bs-toggle="collapse" data-bs-target="#advanced_building_filters" aria-expanded="false" aria-controls="advanced_building_filters">
                <i class="ki-duotone ki-filter fs-2"></i>
                {{ __('ui.buildings_page.advanced_filters') }}
            </button>
        </div>
        <form id="filter_buliding_form" class="form" data-kt-Building-table-filter="form" action="#">
            <div class="card-body">
                <div class="row g-5 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.buildings_page.quick_search') }}</label>
                        <input type="text" data-kt-Building-table-filter="search" class="form-control form-control-solid" placeholder="{{ __('ui.buildings_page.quick_search_placeholder') }}" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.buildings_page.municipality') }}</label>
                        <select name="filters[municipalitie][]" class="form-select form-select-solid building-filter-control" data-control="select2" data-placeholder="{{ __('ui.buildings_page.select_municipality') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                            @foreach ($municip as $value)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.buildings_page.neighborhood') }}</label>
                        <select name="filters[neighborhood][]" class="form-select form-select-solid building-filter-control" data-control="select2" data-placeholder="{{ __('ui.buildings_page.select_neighborhood') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                            @foreach ($neighborhoods as $value)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.buildings_page.researcher_name') }}</label>
                        <select name="filters[assignedto][]" class="form-select form-select-solid building-filter-control" data-control="select2" data-placeholder="{{ __('ui.buildings_page.select_researcher') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                            @foreach ($engineers as $value)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.buildings_page.damage_status') }}</label>
                        <select name="filters[building_damage_status][]" class="form-select form-select-solid building-filter-control" data-control="select2" data-placeholder="{{ __('ui.buildings_page.select_damage_status') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                            @foreach (($groupedFilters['building_damage_status'] ?? collect()) as $option)
                                <option value="{{ $option->name }}">{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.buildings_page.owner_id') }}</label>
                        <input type="text" name="filters[owner_id]" class="form-control form-control-solid building-filter-control" placeholder="{{ __('ui.buildings_page.owner_id_placeholder') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.buildings_page.building_name') }}</label>
                        <input type="text" name="filters[building_name]" class="form-control form-control-solid building-filter-control" placeholder="{{ __('ui.buildings_page.building_name') }}">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1" data-kt-Building-table-filter="filter">
                            <span class="indicator-label">{{ __('ui.buildings_page.search') }}</span>
                            <span class="indicator-progress">{{ __('ui.buildings_page.please_wait') }}
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <button type="reset" class="btn btn-light" data-kt-Buildings-filter-action="reset">{{ __('ui.buildings_page.reset') }}</button>
                    </div>
                </div>

                <div id="advanced_building_filters" class="collapse mt-8">
                    <div class="separator separator-dashed mb-6"></div>
                    <div class="accordion" id="building_filter_sections">
                        @foreach ($buildingFilterSections as $sectionIndex => $section)
                            <div class="accordion-item border border-gray-200 rounded mb-3">
                                <h2 class="accordion-header" id="building_filter_heading_{{ $sectionIndex }}">
                                    <button class="accordion-button fs-6 fw-semibold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#building_filter_panel_{{ $sectionIndex }}" aria-expanded="false" aria-controls="building_filter_panel_{{ $sectionIndex }}">
                                        {{ $section['title'] }}
                                    </button>
                                </h2>
                                <div id="building_filter_panel_{{ $sectionIndex }}" class="accordion-collapse collapse" aria-labelledby="building_filter_heading_{{ $sectionIndex }}" data-bs-parent="#building_filter_sections">
                                    <div class="accordion-body">
                                        <div class="row g-5">
                                            @foreach ($section['filters'] as $filter)
                                                <div class="col-md-3">
                                                    <label class="form-label">{{ $filter['label'] }}</label>
                                                    <select name="filters[{{ $filter['field'] }}][]" class="form-select form-select-solid building-filter-control" data-control="select2" data-placeholder="{{ __('ui.buildings_page.select_filter', ['label' => $filter['label']]) }}" data-allow-clear="true" data-close-on-select="false" multiple>
                                                        @foreach ($filter['options'] as $option)
                                                            <option value="{{ $option->name }}">{{ $option->label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="row g-5 mt-2">
                        @foreach ([
                            'floor_nos' => __('ui.buildings_page.floor_count'),
                            'units_nos' => __('ui.buildings_page.units_count'),
                            'damaged_units_nos' => __('ui.buildings_page.damaged_units_count'),
                        ] as $field => $label)
                            <div class="col-md-4">
                                <label class="form-label">{{ $label }}</label>
                                <div class="d-flex gap-2">
                                    <input type="number" name="filters[{{ $field }}_from]" class="form-control form-control-solid building-filter-control" placeholder="{{ __('ui.buildings_page.from') }}">
                                    <input type="number" name="filters[{{ $field }}_to]" class="form-control form-control-solid building-filter-control" placeholder="{{ __('ui.buildings_page.to') }}">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div id="building_active_filters" class="d-flex flex-wrap gap-2 mt-6"></div>
            </div>
        </form>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">{{ __('ui.buildings_page.table_title') }}</h3>
            </div>
            <div class="d-flex justify-content-end gap-2 flex-wrap" data-kt-Building-table-toolbar="base">
                <button type="button" class="btn btn-light-primary" data-kt-Building-table-action="refresh">
                    <i class="ki-duotone ki-arrows-circle fs-2"></i>
                    {{ __('ui.buildings_page.refresh') }}
                </button>
                <button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_export_buildings">
                    <i class="ki-duotone ki-exit-up fs-2"></i>
                    {{ __('ui.buildings_page.export') }}
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5 w-100" id="kt_table_Building">
                    <thead>
                        <tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">
                            <th class="min-w-80px">{{ __('ui.buildings_page.building_number') }}</th>
                            <th class="min-w-150px">{{ __('ui.buildings_page.building_name') }}</th>
                            <th class="min-w-150px">{{ __('ui.buildings_page.owner_name') }}</th>
                            <th class="min-w-120px">{{ __('ui.buildings_page.survey_status') }}</th>
                            <th class="min-w-130px">{{ __('ui.buildings_page.damage_status') }}</th>
                            <th class="min-w-100px">{{ __('ui.buildings_page.municipality') }}</th>
                            <th class="min-w-100px">{{ __('ui.buildings_page.district') }}</th>
                            <th class="min-w-90px">{{ __('ui.buildings_page.zone_number') }}</th>
                            <th class="min-w-90px">{{ __('ui.buildings_page.units_count') }}</th>
                            <th class="min-w-90px">{{ __('ui.buildings_page.damaged_units_count') }}</th>
                            <th class="min-w-120px">{{ __('ui.buildings_page.risk_summary') }}</th>
                            <th class="min-w-120px">{{ __('ui.buildings_page.updated_at') }}</th>
                            <th class="text-end min-w-100px">{{ __('ui.buildings_page.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="kt_modal_export_buildings" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bold">{{ __('ui.buildings_page.export_buildings') }}</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-kt-userss-modal-action="close">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <form id="kt_modal_export_buildings_form" class="form" action="#">
                        <input type="hidden" name="_method" value="get">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <div class="fv-row mb-10">
                            <label class="fs-6 fw-semibold form-label mb-2">{{ __('ui.buildings_page.select_columns') }}</label>
                            <select multiple data-allow-clear="true" data-close-on-select="false" name="building_columns[]" data-control="select2" data-placeholder="{{ __('ui.buildings_page.select_columns') }}" data-hide-search="false" class="form-select form-select-solid fw-bold">
                                <option value=""></option>
                                @foreach ($assessments as $value)
                                    @if (Schema::hasColumn('buildings', $value->name))
                                        <option value="{{ $value->name }}">{{ $value->label . ' ' . $value->hint }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="fv-row mb-10">
                            <label class="required fs-6 fw-semibold form-label mb-2">{{ __('ui.buildings_page.export_format') }}:</label>
                            <select name="format" data-control="select2" data-placeholder="{{ __('ui.buildings_page.export_format') }}" data-hide-search="false" class="form-select form-select-solid fw-bold">
                                <option></option>
                                <option value="XLSX">Excel</option>
                                <option value="pdf">PDF</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        <div class="text-center">
                            <button type="reset" class="btn btn-light me-3" data-kt-buildings-modal-action="close">{{ __('ui.buildings_page.cancel') }}</button>
                            <button type="submit" class="btn btn-primary" data-kt-buildings-modal-action="submit">
                                <span class="indicator-label">{{ __('ui.buildings_page.export') }}</span>
                                <span class="indicator-progress">{{ __('ui.buildings_page.please_wait') }}
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        var url_phc = "{{ url('') }}";
        var post_export_url = "{{ url('damage-assessment/export_building') }}";
    </script>
    <script src="{{ url('') }}/assets/js/custom/DamageAssessment/export-buildings.js"></script>

    <script>
        var KTBuildingsList = function () {
            var table = document.getElementById('kt_table_Building');
            var datatable;
            const filterForm = document.querySelector('[data-kt-Building-table-filter="form"]');
            const initialQueryParams = new URLSearchParams(window.location.search);

            const setFilterValue = function (field, value) {
                if (!filterForm || !value) {
                    return;
                }

                const fieldSelector = `[name="filters[${field}]"], [name="filters[${field}][]"]`;
                const input = filterForm.querySelector(fieldSelector);

                if (!input) {
                    return;
                }

                if (input.tagName === 'SELECT' && input.multiple) {
                    $(input).val(Array.isArray(value) ? value : [value]).trigger('change');
                    return;
                }

                input.value = value;
            };

            const applyInitialFilters = function () {
                initialQueryParams.forEach((value, key) => {
                    if (key === 'search') {
                        return;
                    }

                    const directMatch = key.match(/^filters\[(.+)]$/);
                    const arrayMatch = key.match(/^filters\[(.+)]\[]$/);

                    if (directMatch || arrayMatch) {
                        setFilterValue((directMatch || arrayMatch)[1], value);
                        return;
                    }

                    setFilterValue(key, value);
                });
            };

            const filterPayload = function () {
                const payload = {};

                if (!filterForm) {
                    return payload;
                }

                const formData = new FormData(filterForm);

                formData.forEach((value, key) => {
                    const multiMatch = key.match(/^filters\[(.+)]\[]$/);
                    const scalarMatch = key.match(/^filters\[(.+)]$/);

                    if (multiMatch) {
                        const field = multiMatch[1];
                        payload[field] = payload[field] || [];

                        if (value !== '') {
                            payload[field].push(value);
                        }
                    }

                    if (scalarMatch && value !== '') {
                        payload[scalarMatch[1]] = value;
                    }
                });

                return payload;
            };

            const activeFilterChips = function () {
                const container = document.getElementById('building_active_filters');

                if (!container || !filterForm) {
                    return;
                }

                container.innerHTML = '';

                $(filterForm).find('.building-filter-control').each(function () {
                    const value = $(this).val();
                    const label = $(this).closest('.col-md-3, .col-md-4').find('label').first().text().trim();
                    const values = Array.isArray(value) ? value : (value ? [value] : []);

                    values.filter(Boolean).forEach(function (item) {
                        const selectedLabel = $(this).find('option').filter(function () {
                            return $(this).val() === item;
                        }).text() || item;
                        const chip = document.createElement('span');
                        chip.className = 'badge badge-light-primary';
                        chip.textContent = label + ': ' + selectedLabel;
                        container.appendChild(chip);
                    }, this);
                });
            };

            var initBuildingTable = function () {
                if (!table) {
                    return;
                }

                datatable = $(table).DataTable({
                    serverSide: true,
                    processing: true,
                    pageLength: 10,
                    order: [[11, 'desc']],
                    ajax: {
                        url: "{{ url('damage-assessment/building/show') }}",
                        data: function (d) {
                            d.filters = filterPayload();
                        },
                    },
                    columns: [
                        { data: 'objectid', name: 'objectid' },
                        { data: 'building_name', name: 'building_name' },
                        { data: 'owner_name', name: 'owner_name' },
                        { data: 'field_status', name: 'field_status' },
                        { data: 'building_damage_status', name: 'building_damage_status' },
                        { data: 'municipalitie', name: 'municipalitie' },
                        { data: 'neighborhood', name: 'neighborhood' },
                        { data: 'zone_code', name: 'zone_code' },
                        { data: 'units_nos', name: 'units_nos' },
                        { data: 'damaged_units_nos', name: 'damaged_units_nos' },
                        { data: 'risk_summary', name: 'risk_summary', orderable: false, searchable: false },
                        { data: 'editdate', name: 'editdate' },
                        { data: 'action', responsivePriority: -1, className: 'text-end', orderable: false, searchable: false },
                    ],
                });

                datatable.on('draw', function () {
                    KTMenu.createInstances();
                    activeFilterChips();
                });

                const initialSearch = initialQueryParams.get('search');

                if (initialSearch) {
                    const searchInput = document.querySelector('[data-kt-Building-table-filter="search"]');

                    if (searchInput) {
                        searchInput.value = initialSearch;
                    }

                    datatable.search(initialSearch).draw();
                }
            };

            var handleSearchDatatable = () => {
                const filterSearch = document.querySelector('[data-kt-Building-table-filter="search"]');

                if (!filterSearch) {
                    return;
                }

                filterSearch.addEventListener('keyup', function (e) {
                    datatable.search(e.target.value).draw();
                });
            };

            var handleFilterDatatable = () => {
                if (!filterForm) {
                    return;
                }

                filterForm.addEventListener('submit', function (event) {
                    event.preventDefault();

                    const filterButton = filterForm.querySelector('[data-kt-Building-table-filter="filter"]');
                    filterButton.setAttribute('data-kt-indicator', 'on');
                    filterButton.disabled = true;

                    datatable.ajax.reload(() => {
                        filterButton.removeAttribute('data-kt-indicator');
                        filterButton.disabled = false;
                        activeFilterChips();
                    }, true);
                });

                $(filterForm).find('select').on('change', activeFilterChips);
            };

            var handleResetForm = () => {
                const resetButton = document.querySelector('[data-kt-Buildings-filter-action="reset"]');

                if (!resetButton) {
                    return;
                }

                resetButton.addEventListener('click', function () {
                    $(filterForm).find('select').val('').trigger('change');
                    $(filterForm).find('input').val('');

                    datatable.search('').ajax.reload();
                    activeFilterChips();
                });
            };

            var handleRefresh = () => {
                const refreshButton = document.querySelector('[data-kt-Building-table-action="refresh"]');

                if (!refreshButton) {
                    return;
                }

                refreshButton.addEventListener('click', function () {
                    datatable.ajax.reload(null, false);
                });
            };

            return {
                init: function () {
                    applyInitialFilters();
                    initBuildingTable();
                    handleSearchDatatable();
                    handleFilterDatatable();
                    handleResetForm();
                    handleRefresh();
                    activeFilterChips();
                }
            };
        }();

        KTUtil.onDOMContentLoaded(function () {
            KTBuildingsList.init();
        });
    </script>
@endsection
