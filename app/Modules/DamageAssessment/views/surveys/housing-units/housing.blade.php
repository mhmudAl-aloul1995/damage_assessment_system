@extends('layouts.app')
@section('title', __('ui.housing_page.title'))
@section('pageName', __('ui.housing_page.title'))

@section('content')
    <div class="row g-5 mb-5">
        @foreach ([
            ['total', 'total_units', 'text-gray-900'],
            ['fully_damaged', 'fully_damaged', 'text-danger'],
            ['partially_damaged', 'partially_damaged', 'text-warning'],
            ['committee_review', 'committee_review', 'text-primary'],
        ] as [$key, $label, $color])
            <div class="col-md-3">
                <div class="card card-flush border border-gray-200 h-100">
                    <div class="card-body">
                        <div class="text-muted fs-7 mb-2">{{ __('ui.housing_page.' . $label) }}</div>
                        <div class="fs-2x fw-bold {{ $color }}">{{ $housingSummary[$key] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">{{ __('ui.housing_page.filters_title') }}</h3>
            </div>
            <button class="btn btn-light-primary" type="button" data-bs-toggle="collapse" data-bs-target="#advanced_housing_filters" aria-expanded="false" aria-controls="advanced_housing_filters">
                <i class="ki-duotone ki-filter fs-2"></i>
                {{ __('ui.housing_page.advanced_filters') }}
            </button>
        </div>
        <form id="filter_housing_form" class="form" data-kt-Housing-table-filter="form" action="#">
            <div class="card-body">
                <div class="row g-5 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.quick_search') }}</label>
                        <input type="text" data-kt-Housing-table-filter="search" class="form-control form-control-solid" placeholder="{{ __('ui.housing_page.quick_search_placeholder') }}" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.municipality') }}</label>
                        <select name="filters[municipalitie][]" class="form-select form-select-solid housing-filter-control" data-control="select2" data-placeholder="{{ __('ui.housing_page.select_municipality') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                            @foreach ($municip as $value)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.neighborhood') }}</label>
                        <select name="filters[neighborhood][]" class="form-select form-select-solid housing-filter-control" data-control="select2" data-placeholder="{{ __('ui.housing_page.select_neighborhood') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                            @foreach ($neighborhoods as $value)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.researcher') }}</label>
                        <select name="filters[assignedto][]" class="form-select form-select-solid housing-filter-control" data-control="select2" data-placeholder="{{ __('ui.buildings_page.select_researcher') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                            @foreach ($engineers as $value)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.damage_status') }}</label>
                        <select name="filters[unit_damage_status][]" class="form-select form-select-solid housing-filter-control" data-control="select2" data-placeholder="{{ __('ui.housing_page.select_damage_status') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                            @foreach (($groupedFilters['unit_damage_status'] ?? collect()) as $option)
                                <option value="{{ $option->name }}">{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.first_name') }}</label>
                        <input type="text" name="filters[q_9_3_1_first_name]" class="form-control form-control-solid housing-filter-control" placeholder="{{ __('ui.housing_page.first_name') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.last_name') }}</label>
                        <input type="text" name="filters[q_9_3_4_last_name]" class="form-control form-control-solid housing-filter-control" placeholder="{{ __('ui.housing_page.last_name') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.owner_id') }}</label>
                        <input type="text" name="filters[id_number1]" class="form-control form-control-solid housing-filter-control" placeholder="{{ __('ui.housing_page.owner_id_placeholder') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.object_id') }}</label>
                        <input type="text" name="filters[objectid]" class="form-control form-control-solid housing-filter-control" placeholder="{{ __('ui.housing_page.object_id_placeholder') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.submission_date_from') }}</label>
                        <input type="text" name="filters[submission_date_from]" class="form-control form-control-solid housing-filter-control housing-date-filter" placeholder="yyyy-mm-dd">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('ui.housing_page.submission_date_to') }}</label>
                        <input type="text" name="filters[submission_date_to]" class="form-control form-control-solid housing-filter-control housing-date-filter" placeholder="yyyy-mm-dd">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاريخ الحفظ من</label>
                        <input type="text" name="filters[end_from]" class="form-control form-control-solid housing-filter-control housing-date-filter" placeholder="yyyy-mm-dd">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاريخ الحفظ إلى</label>
                        <input type="text" name="filters[end_to]" class="form-control form-control-solid housing-filter-control housing-date-filter" placeholder="yyyy-mm-dd">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1" data-kt-Housing-table-filter="filter">
                            <span class="indicator-label">{{ __('ui.housing_page.search') }}</span>
                            <span class="indicator-progress">{{ __('ui.housing_page.please_wait') }} <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                        <button type="reset" class="btn btn-light" data-kt-housing-filter-action="reset">{{ __('ui.housing_page.reset') }}</button>
                    </div>
                </div>

                <div id="advanced_housing_filters" class="collapse mt-8">
                    <div class="separator separator-dashed mb-6"></div>
                    <div class="accordion" id="housing_filter_sections">
                        @foreach ($housingFilterSections as $sectionIndex => $section)
                            <div class="accordion-item border border-gray-200 rounded mb-3">
                                <h2 class="accordion-header" id="housing_filter_heading_{{ $sectionIndex }}">
                                    <button class="accordion-button fs-6 fw-semibold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#housing_filter_panel_{{ $sectionIndex }}" aria-expanded="false" aria-controls="housing_filter_panel_{{ $sectionIndex }}">
                                        {{ $section['title'] }}
                                    </button>
                                </h2>
                                <div id="housing_filter_panel_{{ $sectionIndex }}" class="accordion-collapse collapse" aria-labelledby="housing_filter_heading_{{ $sectionIndex }}" data-bs-parent="#housing_filter_sections">
                                    <div class="accordion-body">
                                        <div class="row g-5">
                                            @foreach ($section['filters'] as $filter)
                                                <div class="col-md-3">
                                                    <label class="form-label">{{ $filter['label'] }}</label>
                                                    <select name="filters[{{ $filter['field'] }}][]" class="form-select form-select-solid housing-filter-control" data-control="select2" data-placeholder="{{ __('ui.housing_page.select_filter', ['label' => $filter['label']]) }}" data-allow-clear="true" data-close-on-select="false" multiple>
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
                            'floor_number' => __('ui.housing_page.floor_number'),
                            'damaged_area_m2' => __('ui.housing_page.damaged_area'),
                            'number_of_rooms' => __('ui.housing_page.room_count'),
                            'age' => __('ui.housing_page.age'),
                        ] as $field => $label)
                            <div class="col-md-3">
                                <label class="form-label">{{ $label }}</label>
                                <div class="d-flex gap-2">
                                    <input type="number" name="filters[{{ $field }}_from]" class="form-control form-control-solid housing-filter-control" placeholder="{{ __('ui.housing_page.from') }}">
                                    <input type="number" name="filters[{{ $field }}_to]" class="form-control form-control-solid housing-filter-control" placeholder="{{ __('ui.housing_page.to') }}">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div id="housing_active_filters" class="d-flex flex-wrap gap-2 mt-6"></div>
            </div>
        </form>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title"><h3 class="fw-bold m-0">{{ __('ui.housing_page.table_title') }}</h3></div>
            <div class="d-flex justify-content-end gap-2 flex-wrap">
                <button type="button" class="btn btn-light-primary" data-kt-Housing-table-action="refresh"><i class="ki-duotone ki-arrows-circle fs-2"></i>{{ __('ui.housing_page.refresh') }}</button>
                <button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_export_housing"><i class="ki-duotone ki-exit-up fs-2"></i>{{ __('ui.housing_page.export') }}</button>
            </div>
        </div>
        <div class="card-body"><div class="table-responsive">
            <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5 w-100" id="kt_table_Housing">
                <thead><tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">
                    <th>{{ __('ui.housing_page.researcher') }}</th><th>{{ __('ui.housing_page.building_number') }}</th><th>{{ __('ui.housing_page.unit_number') }}</th><th>{{ __('ui.housing_page.unit_owner') }}</th><th>{{ __('ui.housing_page.damage_status') }}</th><th>{{ __('ui.housing_page.floor_number') }}</th><th>{{ __('ui.housing_page.damaged_area') }}</th><th>{{ __('ui.housing_page.municipality') }}</th><th>{{ __('ui.housing_page.neighborhood') }}</th><th>{{ __('ui.housing_page.support_summary') }}</th><th>{{ __('ui.housing_page.updated_at') }}</th><th class="text-end">{{ __('ui.housing_page.action') }}</th>
                </tr></thead>
                <tbody class="text-gray-600 fw-semibold"></tbody>
            </table>
        </div></div>
    </div>

    <div class="modal fade" id="kt_modal_export_housing" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered mw-650px"><div class="modal-content">
        <div class="modal-header"><h2 class="fw-bold">{{ __('ui.housing_page.export_housing') }}</h2><div class="btn btn-icon btn-sm btn-active-icon-primary" data-kt-housing-modal-action="close"><i class="ki-duotone ki-cross fs-1"></i></div></div>
        <div class="modal-body scroll-y mx-5 mx-xl-15 my-7"><form id="kt_modal_export_housing_form" class="form" action="#">
            <input type="hidden" name="_method" value="get"><input type="hidden" name="_token" value="{{ csrf_token() }}">
            <div class="fv-row mb-10"><label class="fs-6 fw-semibold form-label mb-2">{{ __('ui.housing_page.select_columns') }}</label><select multiple data-allow-clear="true" data-close-on-select="false" name="housing_columns[]" data-control="select2" data-placeholder="{{ __('ui.housing_page.select_columns') }}" class="form-select form-select-solid fw-bold"><option value=""></option>@foreach ($assessments as $value) @if (Schema::hasColumn('housing_units', $value->name)) <option value="{{ $value->name }}">{{ $value->hint ?: $value->label }}</option> @endif @endforeach</select></div>
            <div class="fv-row mb-10"><label class="required fs-6 fw-semibold form-label mb-2">{{ __('ui.housing_page.export_format') }}</label><select name="format" data-control="select2" data-placeholder="{{ __('ui.housing_page.export_format') }}" class="form-select form-select-solid fw-bold"><option></option><option value="XLSX">Excel</option><option value="pdf">PDF</option><option value="csv">CSV</option></select></div>
            <div class="text-center"><button type="reset" class="btn btn-light me-3" data-kt-housing-modal-action="close">{{ __('ui.housing_page.cancel') }}</button><button type="submit" class="btn btn-primary" data-kt-housing-modal-action="submit"><span class="indicator-label">{{ __('ui.housing_page.export') }}</span><span class="indicator-progress">{{ __('ui.housing_page.please_wait') }} <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span></button></div>
        </form></div>
    </div></div></div>
@endsection

@section('script')
    <script>var url_phc = "{{ url('') }}"; var post_export_url = "{{ url('damage-assessment/export_housings') }}";</script>
    <script src="{{ url('') }}/assets/js/custom/DamageAssessment/export-housings.js"></script>
    <script>
        var KTHousingList = function () {
            var table = document.getElementById('kt_table_Housing');
            var datatable;
            const filterForm = document.querySelector('[data-kt-Housing-table-filter="form"]');
            const initialQueryParams = new URLSearchParams(window.location.search);
            const parentGlobalId = @json($globalid);

            const setFilterValue = function (field, value) {
                if (!filterForm || !value) return;
                const input = filterForm.querySelector(`[name="filters[${field}]"], [name="filters[${field}][]"]`);
                if (!input) return;
                if (input.tagName === 'SELECT' && input.multiple) { $(input).val(Array.isArray(value) ? value : [value]).trigger('change'); return; }
                input.value = value;
            };
            const applyInitialFilters = function () {
                initialQueryParams.forEach((value, key) => {
                    if (key === 'search') return;
                    const match = key.match(/^filters\[(.+?)](?:\[])?$/);
                    setFilterValue(match ? match[1] : key, value);
                });
            };
            const filterPayload = function () {
                const payload = {};
                if (!filterForm) return payload;
                new FormData(filterForm).forEach((value, key) => {
                    const multi = key.match(/^filters\[(.+)]\[]$/); const scalar = key.match(/^filters\[(.+)]$/);
                    if (multi && value !== '') { payload[multi[1]] = payload[multi[1]] || []; payload[multi[1]].push(value); }
                    if (scalar && value !== '') payload[scalar[1]] = value;
                });
                return payload;
            };
            const activeFilterChips = function () {
                const container = document.getElementById('housing_active_filters'); if (!container || !filterForm) return; container.innerHTML = '';
                $(filterForm).find('.housing-filter-control').each(function () {
                    const value = $(this).val(); const label = $(this).closest('.col-md-3, .col-md-4').find('label').first().text().trim(); const values = Array.isArray(value) ? value : (value ? [value] : []);
                    values.filter(Boolean).forEach(function (item) { const selectedLabel = $(this).find('option').filter(function () { return $(this).val() === item; }).text() || item; const chip = document.createElement('span'); chip.className = 'badge badge-light-primary'; chip.textContent = label + ': ' + selectedLabel; container.appendChild(chip); }, this);
                });
            };
            const initHousingTable = function () {
                if (!table) return;
                datatable = $(table).DataTable({ serverSide: true, processing: true, pageLength: 10, order: [[10, 'desc']], ajax: { url: "{{ url('damage-assessment/housing/show') }}", data: function (d) { d.filters = filterPayload(); d.parentglobalid = parentGlobalId; } }, columns: [
                    { data: 'assignedto', name: 'assignedto', orderable: false }, { data: 'building_objectid', name: 'building_objectid', orderable: false }, { data: 'housing_unit_number', name: 'housing_unit_number' }, { data: 'full_name', name: 'unit_owner', orderable: false }, { data: 'unit_damage_status', name: 'unit_damage_status' }, { data: 'floor_number', name: 'floor_number' }, { data: 'damaged_area_m2', name: 'damaged_area_m2' }, { data: 'municipalitie', name: 'municipalitie' }, { data: 'neighborhood', name: 'neighborhood' }, { data: 'support_summary', name: 'support_summary', orderable: false, searchable: false }, { data: 'editdate', name: 'editdate' }, { data: 'action', name: 'action', className: 'text-end', orderable: false, searchable: false }
                ] });
                datatable.on('draw', function () { KTMenu.createInstances(); activeFilterChips(); });
                const initialSearch = initialQueryParams.get('search'); if (initialSearch) { const input = document.querySelector('[data-kt-Housing-table-filter="search"]'); if (input) input.value = initialSearch; datatable.search(initialSearch).draw(); }
            };
            const bindEvents = function () {
                if (typeof flatpickr !== 'undefined') { flatpickr('.housing-date-filter', { dateFormat: 'Y-m-d', allowInput: true }); }
                const search = document.querySelector('[data-kt-Housing-table-filter="search"]'); if (search) search.addEventListener('keyup', function (event) { datatable.search(event.target.value).draw(); });
                if (filterForm) { filterForm.addEventListener('submit', function (event) { event.preventDefault(); const button = filterForm.querySelector('[data-kt-Housing-table-filter="filter"]'); button.setAttribute('data-kt-indicator', 'on'); button.disabled = true; datatable.ajax.reload(function () { button.removeAttribute('data-kt-indicator'); button.disabled = false; activeFilterChips(); }, true); }); $(filterForm).find('select').on('change', activeFilterChips); }
                const reset = document.querySelector('[data-kt-housing-filter-action="reset"]'); if (reset) reset.addEventListener('click', function () { $(filterForm).find('select').val('').trigger('change'); $(filterForm).find('input').val(''); datatable.search('').ajax.reload(); activeFilterChips(); });
                const refresh = document.querySelector('[data-kt-Housing-table-action="refresh"]'); if (refresh) refresh.addEventListener('click', function () { datatable.ajax.reload(null, false); });
            };
            return { init: function () { applyInitialFilters(); initHousingTable(); bindEvents(); activeFilterChips(); } };
        }();
        KTUtil.onDOMContentLoaded(function () { KTHousingList.init(); });
    </script>
@endsection
