@extends('layouts.app')

@section('title', 'CSO Damage Assessment')
@section('pageName', 'CSO Damage Assessment')

@section('content')
    <div class="row g-5 mb-5">
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Total Surveys</div>
                    <div class="fs-2hx fw-bold text-gray-900">{{ $summary['total_surveys'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Organizations</div>
                    <div class="fs-2hx fw-bold text-primary">{{ $summary['total_organizations'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Units</div>
                    <div class="fs-2hx fw-bold text-info">{{ $summary['total_units'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
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
                <h3 class="fw-bold m-0">CSO Filters</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-5">
                <div class="col-md-3">
                    <label class="form-label">Municipality</label>
                    <select id="filter_municipalitie" class="form-select form-select-solid cso-select2" data-placeholder="Select municipality" multiple>
                        @foreach ($filterOptions['municipalities'] as $municipality)
                            <option value="{{ $municipality }}">{{ $municipality }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Neighborhood</label>
                    <select id="filter_neighborhood" class="form-select form-select-solid cso-select2" data-placeholder="Select neighborhood" multiple>
                        @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                            <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Researcher</label>
                    <select id="filter_assignedto" class="form-select form-select-solid cso-select2" data-placeholder="Select researcher" multiple>
                        @foreach ($filterOptions['researchers'] as $researcher)
                            <option value="{{ $researcher }}">{{ $researcher }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Damage Status</label>
                    <select id="filter_building_damage_status" class="form-select form-select-solid cso-select2" data-placeholder="Select damage status" multiple>
                        @foreach ($filterOptions['damageStatuses'] as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Operational Status</label>
                    <select id="filter_operational_status" class="form-select form-select-solid cso-select2" data-placeholder="Select operational status" multiple>
                        @foreach ($filterOptions['operationalStatuses'] as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input id="filter_search" type="text" class="form-control form-control-solid" placeholder="Search organization, building, ObjectID">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input id="filter_from_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['min_creationdate'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input id="filter_to_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['max_creationdate'] }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">CSO Surveys</h3>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <a href="{{ route('cso-surveys.export-data') }}" class="btn btn-primary">صفحة التصدير</a>
                <button type="button" id="reset_filters" class="btn btn-light">Reset Filters</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="cso_surveys_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>Object ID</th>
                            <th>Organization</th>
                            <th>Building</th>
                            <th>Municipality</th>
                            <th>Neighborhood</th>
                            <th>Damage Status</th>
                            <th>Created At</th>
                            <th>Organizations</th>
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
            $('.cso-select2').each(function () {
                $(this).select2({
                    placeholder: $(this).data('placeholder') || 'Select an option',
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%'
                });
            });

            const queryParams = new URLSearchParams(window.location.search);

            const currentFilters = function () {
                return {
                    municipalitie: $('#filter_municipalitie').val() || queryParams.get('municipalitie'),
                    neighborhood: $('#filter_neighborhood').val() || queryParams.get('neighborhood'),
                    assignedto: $('#filter_assignedto').val() || queryParams.get('assignedto'),
                    building_damage_status: $('#filter_building_damage_status').val() || queryParams.get('building_damage_status'),
                    operational_status: $('#filter_operational_status').val() || queryParams.get('operational_status'),
                    from_date: $('#filter_from_date').val() || queryParams.get('from_date'),
                    to_date: $('#filter_to_date').val() || queryParams.get('to_date'),
                    q: $('#filter_search').val() || queryParams.get('q'),
                    damaged_only: queryParams.get('damaged_only'),
                    with_organizations: queryParams.get('with_organizations'),
                    with_units: queryParams.get('with_units'),
                };
            };

            const table = $('#cso_surveys_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('cso-surveys.data') }}',
                    data: function (d) {
                        const filters = currentFilters();

                        d.municipalitie = filters.municipalitie;
                        d.neighborhood = filters.neighborhood;
                        d.assignedto = filters.assignedto;
                        d.building_damage_status = filters.building_damage_status;
                        d.operational_status = filters.operational_status;
                        d.from_date = filters.from_date;
                        d.to_date = filters.to_date;
                        d.q = filters.q;
                        d.damaged_only = filters.damaged_only;
                        d.with_organizations = filters.with_organizations;
                        d.with_units = filters.with_units;
                    },
                    error: function (xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Could not load CSO surveys data');
                    }
                },
                order: [[0, 'desc']],
                columns: [
                    { data: 'objectid', name: 'objectid' },
                    { data: 'organization_name', name: 'organization_name' },
                    { data: 'building_name', name: 'building_name' },
                    { data: 'municipalitie', name: 'municipalitie' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'building_damage_status', name: 'building_damage_status', orderable: false, searchable: false },
                    { data: 'creationdate', name: 'creationdate' },
                    { data: 'organizations_count', name: 'organizations_count', searchable: false },
                    { data: 'units_count', name: 'units_count', searchable: false },
                    { data: 'assignedto', name: 'assignedto', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $('#filter_search').on('keyup', function () {
                table.search(this.value).draw();
            });

            $('#filter_municipalitie, #filter_neighborhood, #filter_assignedto, #filter_building_damage_status, #filter_operational_status, #filter_from_date, #filter_to_date').on('change', function () {
                table.draw();
            });

            $('#reset_filters').on('click', function () {
                $('#filter_search').val('');
                $('.cso-select2').val(null).trigger('change');
                $('#filter_from_date').val('');
                $('#filter_to_date').val('');
                table.search('').draw();
            });
        });
    </script>
@endsection
