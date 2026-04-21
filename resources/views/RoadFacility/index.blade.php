@extends('layouts.app')

@section('title', 'Road Facilities')
@section('pageName', 'Road Facilities')

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
                    <div class="text-muted fs-6 mb-2">Repeated Items</div>
                    <div class="fs-2hx fw-bold text-primary">{{ $summary['total_items'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Damaged Roads</div>
                    <div class="fs-2hx fw-bold text-danger">{{ $summary['damaged_roads'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold m-0">Road Facilities Filters</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-5">
                <div class="col-md-3">
                    <label class="form-label">Municipality</label>
                    <select id="filter_municipalitie" class="form-select form-select-solid road-select2" data-placeholder="Select municipality" data-allow-clear="true">
                        <option value=""></option>
                        @foreach ($filterOptions['municipalities'] as $municipality)
                            <option value="{{ $municipality }}">{{ $municipality }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Neighborhood</label>
                    <select id="filter_neighborhood" class="form-select form-select-solid road-select2" data-placeholder="Select neighborhood" data-allow-clear="true">
                        <option value=""></option>
                        @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                            <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach ($filterGroups as $groupName => $items)
                    <div class="col-md-3">
                        <label class="form-label">{{ str($groupName)->replace('_', ' ')->title() }}</label>
                        <select id="filter_{{ $groupName }}" class="form-select form-select-solid road-filter-select road-select2" data-filter-key="{{ $groupName }}" data-placeholder="Select {{ str($groupName)->replace('_', ' ')->lower() }}" data-allow-clear="true">
                            <option value=""></option>
                            @foreach ($items as $item)
                                <option value="{{ $item->name }}">{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-3">
                    <label class="form-label">Researcher</label>
                    <select id="filter_assigned_to" class="form-select form-select-solid road-select2" data-placeholder="Select researcher" data-allow-clear="true">
                        <option value=""></option>
                        @foreach ($filterOptions['researchers'] as $researcher)
                            <option value="{{ $researcher }}">{{ $researcher }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input id="filter_search" type="text" class="form-control form-control-solid" placeholder="Road, municipality, objectid...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input id="filter_from_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['min_submission_date'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input id="filter_to_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['max_submission_date'] }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">Road Facilities Surveys</h3>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-light-primary road-facilities-export" data-format="xlsx">Export Excel</button>
                <button type="button" class="btn btn-light-success road-facilities-export" data-format="csv">Export CSV</button>
                <button type="button" class="btn btn-light-danger road-facilities-export" data-format="pdf">Export PDF</button>
                <button type="button" id="reset_filters" class="btn btn-light">Reset Filters</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="road_facilities_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>Object ID</th>
                            <th>Road Name</th>
                            <th>Municipality</th>
                            <th>Neighborhood</th>
                            <th>Damage Level</th>
                            <th>Road Access</th>
                            <th>Submission Date</th>
                            <th>Items</th>
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
            const exportRouteTemplate = @json(route('road-facilities.export', ['format' => '__FORMAT__']));

            $('.road-select2').each(function () {
                const placeholder = $(this).data('placeholder') || 'Select an option';
                const allowClear = String($(this).data('allow-clear')) === 'true';

                $(this).select2({
                    placeholder: placeholder,
                    allowClear: allowClear,
                    width: '100%'
                });
            });

            const dynamicFilters = function () {
                const filters = {};

                $('.road-filter-select').each(function () {
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
                ajax: {
                    url: '{{ route('road-facilities.data') }}',
                    data: function (d) {
                        const filters = currentFilters();
                        d.municipalitie = filters.municipalitie;
                        d.neighborhood = filters.neighborhood;
                        d.assigned_to = filters.assigned_to;
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
                    { data: 'submission_date', name: 'submission_date' },
                    { data: 'items_count', name: 'items_count', searchable: false },
                    { data: 'assigned_to', name: 'assigned_to' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $('#filter_search').on('keyup', function () {
                table.search(this.value).draw();
            });

            $('#filter_municipalitie, #filter_neighborhood, #filter_assigned_to, #filter_from_date, #filter_to_date, .road-filter-select').on('change', function () {
                table.draw();
            });

            $('.road-facilities-export').on('click', function () {
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
                $('.road-filter-select').val(null).trigger('change');
                $('#filter_from_date').val('');
                $('#filter_to_date').val('');
                table.search('').draw();
            });
        });
    </script>
@endsection
