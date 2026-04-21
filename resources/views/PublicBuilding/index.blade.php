@extends('layouts.app')

@section('title', 'Public Buildings')
@section('pageName', 'Public Buildings')

@section('content')
    <div class="row g-5 mb-5">
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Total Surveys</div>
                    <div class="fs-2hx fw-bold text-gray-900">{{ $summary['total_surveys'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Repeated Units</div>
                    <div class="fs-2hx fw-bold text-primary">{{ $summary['total_units'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Damaged Buildings</div>
                    <div class="fs-2hx fw-bold text-danger">{{ $summary['damaged_buildings'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold m-0">Public Building Filters</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-5">
                <div class="col-md-3">
                    <label class="form-label">Municipality</label>
                    <select id="filter_municipalitie" class="form-select form-select-solid public-building-select2" data-placeholder="Select municipality" data-allow-clear="true">
                        <option value=""></option>
                        @foreach ($filterOptions['municipalities'] as $municipality)
                            <option value="{{ $municipality }}">{{ $municipality }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Neighborhood</label>
                    <select id="filter_neighborhood" class="form-select form-select-solid public-building-select2" data-placeholder="Select neighborhood" data-allow-clear="true">
                        <option value=""></option>
                        @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                            <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach ($filterGroups as $groupName => $items)
                    <div class="col-md-3">
                        <label class="form-label">{{ str($groupName)->replace('_', ' ')->title() }}</label>
                        <select id="filter_{{ $groupName }}" class="form-select form-select-solid public-building-filter-select public-building-select2" data-filter-key="{{ $groupName }}" data-placeholder="Select {{ str($groupName)->replace('_', ' ')->lower() }}" data-allow-clear="true">
                            <option value=""></option>
                            @foreach ($items as $item)
                                <option value="{{ $item->name }}">{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-3">
                    <label class="form-label">Researcher</label>
                    <select id="filter_assigned_to" class="form-select form-select-solid public-building-select2" data-placeholder="Select researcher" data-allow-clear="true">
                        <option value=""></option>
                        @foreach ($filterOptions['researchers'] as $researcher)
                            <option value="{{ $researcher }}">{{ $researcher }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input id="filter_search" type="text" class="form-control form-control-solid" placeholder="Building, municipality, objectid...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input id="filter_from_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['min_damage_date'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input id="filter_to_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['max_damage_date'] }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">Public Building Surveys</h3>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-light-primary public-buildings-export" data-format="xlsx">Export Excel</button>
                <button type="button" class="btn btn-light-success public-buildings-export" data-format="csv">Export CSV</button>
                <button type="button" class="btn btn-light-danger public-buildings-export" data-format="pdf">Export PDF</button>
                <button type="button" id="reset_filters" class="btn btn-light">Reset Filters</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="public_buildings_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>Object ID</th>
                            <th>Building Name</th>
                            <th>Municipality</th>
                            <th>Neighborhood</th>
                            <th>Damage Status</th>
                            <th>Date Of Damage</th>
                            <th>Units</th>
                            <th>Researcher</th>
                            <th class="text-end">Actions</th>
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
            const exportRouteTemplate = @json(route('public-buildings.export', ['format' => '__FORMAT__']));

            $('.public-building-select2').each(function () {
                const placeholder = $(this).data('placeholder') || 'Select an option';
                const allowClear = String($(this).data('allow-clear')) === 'true';

                $(this).select2({
                    placeholder: placeholder,
                    allowClear: allowClear,
                    width: '100%'
                });
            });

            const initialQueryParams = new URLSearchParams(window.location.search);

            ['municipalitie', 'neighborhood', 'assigned_to'].forEach(function (key) {
                const value = initialQueryParams.get(key);

                if (value) {
                    $('#filter_' + key).val(value).trigger('change');
                }
            });

            $('.public-building-filter-select').each(function () {
                const key = $(this).data('filter-key');
                const value = initialQueryParams.get('filters[' + key + ']');

                if (value) {
                    $(this).val(value).trigger('change');
                }
            });

            $('#filter_search').val(initialQueryParams.get('search') || '');
            $('#filter_from_date').val(initialQueryParams.get('from_date') || $('#filter_from_date').val());
            $('#filter_to_date').val(initialQueryParams.get('to_date') || $('#filter_to_date').val());

            const dynamicFilters = function () {
                const filters = {};

                $('.public-building-filter-select').each(function () {
                    const key = $(this).data('filter-key');
                    const value = $(this).val();

                    if (value) {
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
                    assigned_to: $('#filter_assigned_to').val() || queryParams.get('assigned_to'),
                    from_date: $('#filter_from_date').val() || queryParams.get('from_date'),
                    to_date: $('#filter_to_date').val() || queryParams.get('to_date'),
                    search: $('#filter_search').val() || queryParams.get('search'),
                    damaged_only: queryParams.get('damaged_only'),
                    with_units: queryParams.get('with_units'),
                    has_municipality: queryParams.get('has_municipality'),
                    has_neighborhood: queryParams.get('has_neighborhood'),
                    has_assigned_to: queryParams.get('has_assigned_to'),
                    occupied_only: queryParams.get('occupied_only'),
                    bodies_only: queryParams.get('bodies_only'),
                    uxo_only: queryParams.get('uxo_only'),
                    filters: filters,
                };
            };

            const table = $('#public_buildings_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('public-buildings.data') }}',
                    data: function (d) {
                        const filters = currentFilters();
                        d.municipalitie = filters.municipalitie;
                        d.neighborhood = filters.neighborhood;
                        d.assigned_to = filters.assigned_to;
                        d.from_date = filters.from_date;
                        d.to_date = filters.to_date;
                        d.search = filters.search;
                        d.damaged_only = filters.damaged_only;
                        d.with_units = filters.with_units;
                        d.has_municipality = filters.has_municipality;
                        d.has_neighborhood = filters.has_neighborhood;
                        d.has_assigned_to = filters.has_assigned_to;
                        d.occupied_only = filters.occupied_only;
                        d.bodies_only = filters.bodies_only;
                        d.uxo_only = filters.uxo_only;
                        d.filters = filters.filters;
                    }
                },
                order: [[0, 'desc']],
                columns: [
                    { data: 'objectid', name: 'objectid' },
                    { data: 'building_name', name: 'building_name' },
                    { data: 'municipalitie', name: 'municipalitie' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'building_damage_status', name: 'building_damage_status', orderable: false, searchable: false },
                    { data: 'date_of_damage', name: 'date_of_damage' },
                    { data: 'units_count', name: 'units_count', searchable: false },
                    { data: 'assigned_to', name: 'assigned_to' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $('#filter_search').on('keyup', function () {
                table.search(this.value).draw();
            });

            $('#filter_municipalitie, #filter_neighborhood, #filter_assigned_to, #filter_from_date, #filter_to_date, .public-building-filter-select').on('change', function () {
                table.draw();
            });

            $('.public-buildings-export').on('click', function () {
                const format = $(this).data('format');
                const filters = currentFilters();
                const query = new URLSearchParams();

                ['municipalitie', 'neighborhood', 'assigned_to', 'from_date', 'to_date', 'search'].forEach(function (key) {
                    if (filters[key]) {
                        query.set(key, filters[key]);
                    }
                });

                Object.entries(filters.filters).forEach(function (entry) {
                    query.append('filters[' + entry[0] + ']', entry[1]);
                });

                window.location.href = exportRouteTemplate.replace('__FORMAT__', format) + '?' + query.toString();
            });

            $('#reset_filters').on('click', function () {
                $('#filter_search').val('');
                $('#filter_municipalitie').val(null).trigger('change');
                $('#filter_neighborhood').val(null).trigger('change');
                $('#filter_assigned_to').val(null).trigger('change');
                $('.public-building-filter-select').val(null).trigger('change');
                $('#filter_from_date').val('');
                $('#filter_to_date').val('');
                table.search('').draw();
            });
        });
    </script>
@endsection
