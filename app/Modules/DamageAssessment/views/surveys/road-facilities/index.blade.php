@extends('layouts.app')

@section('title', __('multilingual.road_facilities_page.title'))
@section('pageName', __('multilingual.road_facilities_page.title'))

@section('content')
    <div class="row g-5 mb-5">
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">{{ __('multilingual.road_facilities_page.total_surveys') }}</div>
                    <div class="fs-2hx fw-bold text-gray-900">{{ $summary['total_surveys'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">{{ __('multilingual.road_facilities_page.repeated_items') }}</div>
                    <div class="fs-2hx fw-bold text-primary">{{ $summary['total_items'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">{{ __('multilingual.road_facilities_page.damaged_roads') }}</div>
                    <div class="fs-2hx fw-bold text-danger">{{ $summary['damaged_roads'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold m-0">{{ __('multilingual.road_facilities_page.filters_title') }}</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-5">
                <div class="col-md-3">
                    <label class="form-label">{{ __('multilingual.road_facilities_page.municipality') }}</label>
                    <select id="filter_municipalitie" class="form-select form-select-solid road-select2" data-placeholder="{{ __('multilingual.road_facilities_page.select_municipality') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                        @foreach ($filterOptions['municipalities'] as $municipality)
                            <option value="{{ $municipality }}">{{ $municipality }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('multilingual.road_facilities_page.neighborhood') }}</label>
                    <select id="filter_neighborhood" class="form-select form-select-solid road-select2" data-placeholder="{{ __('multilingual.road_facilities_page.select_neighborhood') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                        @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                            <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach ($filterGroups as $groupName => $items)
                    <div class="col-md-3">
                        <label class="form-label">{{ str($groupName)->replace('_', ' ')->title() }}</label>
                        <select id="filter_{{ $groupName }}" class="form-select form-select-solid road-filter-select road-select2" data-filter-key="{{ $groupName }}" data-placeholder="{{ __('multilingual.road_facilities_page.select_filter', ['label' => str($groupName)->replace('_', ' ')->lower()]) }}" data-allow-clear="true" data-close-on-select="false" multiple>
                            @foreach ($items as $item)
                                <option value="{{ $item->name }}">{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-3">
                    <label class="form-label">{{ __('multilingual.road_facilities_page.researcher') }}</label>
                    <select id="filter_assignedto" class="form-select form-select-solid road-select2" data-placeholder="{{ __('multilingual.road_facilities_page.select_researcher') }}" data-allow-clear="true" data-close-on-select="false" multiple>
                        @foreach ($filterOptions['researchers'] as $researcher)
                            <option value="{{ $researcher }}">{{ $researcher }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('multilingual.road_facilities_page.search') }}</label>
                    <input id="filter_search" type="text" class="form-control form-control-solid" placeholder="{{ __('multilingual.road_facilities_page.search_placeholder') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('multilingual.road_facilities_page.from_date') }}</label>
                    <input id="filter_from_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['min_submissiondate'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('multilingual.road_facilities_page.to_date') }}</label>
                    <input id="filter_to_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['max_submissiondate'] }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">{{ __('multilingual.road_facilities_page.surveys_title') }}</h3>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-light-primary road-facilities-export" data-format="xlsx">{{ __('multilingual.road_facilities_page.export_excel') }}</button>
                <button type="button" class="btn btn-light-success road-facilities-export" data-format="csv">{{ __('multilingual.road_facilities_page.export_csv') }}</button>
                <button type="button" class="btn btn-light-danger road-facilities-export" data-format="pdf">{{ __('multilingual.road_facilities_page.export_pdf') }}</button>
                <button type="button" id="export_neighborhood_lengths" class="btn btn-light-info">{{ __('multilingual.road_facilities_page.export_neighborhood_lengths') }}</button>
                <button type="button" id="reset_filters" class="btn btn-light">{{ __('multilingual.road_facilities_page.reset_filters') }}</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="road_facilities_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>{{ __('multilingual.road_facilities_page.object_id') }}</th>
                            <th>{{ __('multilingual.road_facilities_page.road_name') }}</th>
                            <th>{{ __('multilingual.road_facilities_page.municipality') }}</th>
                            <th>{{ __('multilingual.road_facilities_page.neighborhood') }}</th>
                            <th>{{ __('multilingual.road_facilities_page.damage_level') }}</th>
                            <th>{{ __('multilingual.road_facilities_page.road_access') }}</th>
                            <th>{{ __('multilingual.road_facilities_page.submission_date') }}</th>
                            <th>{{ __('multilingual.road_facilities_page.linked_items') }}</th>
                            <th>{{ __('multilingual.road_facilities_page.researcher') }}</th>
                            <th class="text-end">{{ __('multilingual.road_facilities_page.actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const exportRouteTemplate = @json(route('road-facilities.export', ['format' => '__FORMAT__']));
            const neighborhoodLengthsExportRoute = @json(route('road-facilities.neighborhood-lengths.export'));

            $('.road-select2').each(function () {
                const placeholder = $(this).data('placeholder') || 'Select an option';
                const allowClear = String($(this).data('allow-clear')) === 'true';

                $(this).select2({
                    placeholder: placeholder,
                    allowClear: allowClear,
                    closeOnSelect: false,
                    width: '100%'
                });
            });

            const dynamicFilters = function () {
                const filters = {};

                $('.road-filter-select').each(function () {
                    const key = $(this).data('filter-key');
                    const value = $(this).val();

                    if (value && value.length !== 0) {
                        filters[key] = value;
                    }
                });

                return filters;
            };

            const currentFilters = function () {
                const queryParams = new URLSearchParams(window.location.search);
                const filters = dynamicFilters();

                queryParams.forEach(function (value, key) {
                    if (key.startsWith('filters[') && key.endsWith(']')) {
                        filters[key.slice(8, -1)] = value;
                    }
                });

                return {
                    municipalitie: $('#filter_municipalitie').val() || queryParams.get('municipalitie'),
                    neighborhood: $('#filter_neighborhood').val() || queryParams.get('neighborhood'),
                    assignedto: $('#filter_assignedto').val() || queryParams.get('assignedto'),
                    from_date: $('#filter_from_date').val() || queryParams.get('from_date'),
                    to_date: $('#filter_to_date').val() || queryParams.get('to_date'),
                    search: $('#filter_search').val() || queryParams.get('search'),
                    damaged_only: queryParams.get('damaged_only'),
                    with_items: queryParams.get('with_items'),
                    has_municipality: queryParams.get('has_municipality'),
                    has_neighborhood: queryParams.get('has_neighborhood'),
                    potholes_only: queryParams.get('potholes_only'),
                    obstacles_only: queryParams.get('obstacles_only'),
                    buried_bodies_only: queryParams.get('buried_bodies_only'),
                    uxo_only: queryParams.get('uxo_only'),
                    filters: filters,
                };
            };

            const table = $('#road_facilities_table').DataTable({
                processing: true,
                serverSide: true,
                language: {
                    processing: @json(__('multilingual.road_facilities_page.table.processing')),
                    search: @json(__('multilingual.road_facilities_page.table.search')),
                    lengthMenu: @json(__('multilingual.road_facilities_page.table.length_menu')),
                    info: @json(__('multilingual.road_facilities_page.table.info')),
                    infoEmpty: @json(__('multilingual.road_facilities_page.table.info_empty')),
                    infoFiltered: @json(__('multilingual.road_facilities_page.table.info_filtered')),
                    emptyTable: @json(__('multilingual.road_facilities_page.table.empty_table')),
                    zeroRecords: @json(__('multilingual.road_facilities_page.table.zero_records')),
                    paginate: {
                        first: @json(__('multilingual.road_facilities_page.table.paginate_first')),
                        last: @json(__('multilingual.road_facilities_page.table.paginate_last')),
                        next: @json(__('multilingual.road_facilities_page.table.paginate_next')),
                        previous: @json(__('multilingual.road_facilities_page.table.paginate_previous')),
                    },
                },
                ajax: {
                    url: '{{ route('road-facilities.data') }}',
                    data: function (d) {
                        const filters = currentFilters();
                        d.municipalitie = filters.municipalitie;
                        d.neighborhood = filters.neighborhood;
                        d.assignedto = filters.assignedto;
                        d.from_date = filters.from_date;
                        d.to_date = filters.to_date;
                        d.search = filters.search;
                        d.damaged_only = filters.damaged_only;
                        d.with_items = filters.with_items;
                        d.has_municipality = filters.has_municipality;
                        d.has_neighborhood = filters.has_neighborhood;
                        d.potholes_only = filters.potholes_only;
                        d.obstacles_only = filters.obstacles_only;
                        d.buried_bodies_only = filters.buried_bodies_only;
                        d.uxo_only = filters.uxo_only;
                        d.filters = filters.filters;
                    }
                },
                order: [[0, 'desc']],
                columns: [
                    { data: 'objectid', name: 'objectid' },
                    { data: 'str_name', name: 'str_name' },
                    { data: 'municipalitie', name: 'municipalitie' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'road_damage_level', name: 'road_damage_level', orderable: false, searchable: false },
                    { data: 'road_access', name: 'road_access' },
                    { data: 'submissiondate', name: 'submissiondate' },
                    { data: 'items_count', name: 'items_count', searchable: false },
                    { data: 'assignedto', name: 'assignedto', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $('#filter_search').on('keyup', function () {
                table.search(this.value).draw();
            });

            $('#filter_municipalitie, #filter_neighborhood, #filter_assignedto, #filter_from_date, #filter_to_date, .road-filter-select').on('change', function () {
                table.draw();
            });

            const buildExportQuery = function () {
                const filters = currentFilters();
                const query = new URLSearchParams();

                ['municipalitie', 'neighborhood', 'assignedto', 'from_date', 'to_date', 'search'].forEach(function (key) {
                    if (Array.isArray(filters[key])) {
                        filters[key].forEach(function (value) {
                            query.append(key + '[]', value);
                        });
                    } else if (filters[key]) {
                        query.set(key, filters[key]);
                    }
                });

                Object.entries(filters.filters).forEach(function (entry) {
                    if (Array.isArray(entry[1])) {
                        entry[1].forEach(function (value) {
                            query.append('filters[' + entry[0] + '][]', value);
                        });
                    } else {
                        query.append('filters[' + entry[0] + ']', entry[1]);
                    }
                });

                return query;
            };

            $('.road-facilities-export').on('click', function () {
                const format = $(this).data('format');
                const query = buildExportQuery();

                window.location.href = exportRouteTemplate.replace('__FORMAT__', format) + '?' + query.toString();
            });

            $('#export_neighborhood_lengths').on('click', function () {
                window.location.href = neighborhoodLengthsExportRoute + '?' + buildExportQuery().toString();
            });

            $('#reset_filters').on('click', function () {
                $('#filter_search').val('');
                $('#filter_municipalitie').val(null).trigger('change');
                $('#filter_neighborhood').val(null).trigger('change');
                $('#filter_assignedto').val(null).trigger('change');
                $('.road-filter-select').val(null).trigger('change');
                $('#filter_from_date').val('');
                $('#filter_to_date').val('');
                table.search('').draw();
            });
        });
    </script>
@endsection
