@extends('layouts.app')
@section('title', __('multilingual.area_manager_review.title'))
@section('pageName', __('multilingual.area_manager_review.page_name'))

@section('content')
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-flush shadow-sm">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <i class="ki-duotone ki-shield-search fs-1 me-3 text-primary">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="fw-bold m-0">{{ __('multilingual.area_manager_review.queue_title') }}</h3>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-light-primary" id="refreshAreaManagerTable">
                            <i class="ki-duotone ki-arrows-circle fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            {{ __('multilingual.area_manager_review.actions.refresh') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info d-flex flex-column gap-2 mb-0">
                        <div><span class="fw-bold">{{ __('multilingual.area_manager_review.region') }}:</span> {{ $regionLabel }}</div>
                        <div>
                            <span class="fw-bold">{{ __('multilingual.area_manager_review.allowed_municipalities') }}:</span>
                            @forelse ($municipalities as $municipality)
                                <span class="badge badge-light-primary me-1">{{ $municipality }}</span>
                            @empty
                                <span class="badge badge-light-danger">{{ __('multilingual.area_manager_review.no_municipalities') }}</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card card-flush mb-7">
                <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                    <div class="card-title">
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <input type="text" id="areaManagerTableSearch"
                                class="form-control form-control-solid w-250px ps-13" placeholder="{{ __('multilingual.area_manager_review.search_placeholder') }}" />
                        </div>
                    </div>
                    <div class="card-toolbar">
                        <button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="collapse"
                            data-bs-target="#areaManagerAdvancedFilters" aria-expanded="true">
                            <i class="ki-duotone ki-filter fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            {{ __('multilingual.area_manager_review.filters.title') }}
                        </button>
                    </div>
                </div>

                <div class="card-body pt-0">
                    <div class="collapse show mb-7" id="areaManagerAdvancedFilters">
                        <div class="row g-4 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('multilingual.area_manager_review.columns.object_id') }}</label>
                                <input type="text" id="filter_objectid" class="form-control form-control-solid"
                                    placeholder="{{ __('multilingual.area_manager_review.filters.object_id') }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('multilingual.area_manager_review.columns.building_name') }}</label>
                                <input type="text" id="filter_building_name" class="form-control form-control-solid"
                                    placeholder="{{ __('multilingual.area_manager_review.filters.building_name') }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('multilingual.area_manager_review.columns.municipality') }}</label>
                                <select id="filter_municipalitie" class="form-select form-select-solid area-manager-filter-select"
                                    data-placeholder="{{ __('multilingual.area_manager_review.filters.all_municipalities') }}">
                                    <option value=""></option>
                                    @foreach ($filterOptions['municipalities'] as $municipality)
                                        <option value="{{ $municipality }}">{{ $municipality }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('multilingual.area_manager_review.columns.neighborhood') }}</label>
                                <select id="filter_neighborhood" class="form-select form-select-solid area-manager-filter-select"
                                    data-placeholder="{{ __('multilingual.area_manager_review.filters.all_neighborhoods') }}">
                                    <option value=""></option>
                                    @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                                        <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('multilingual.area_manager_review.columns.field_engineer') }}</label>
                                <select id="filter_assignedto" class="form-select form-select-solid area-manager-filter-select"
                                    data-placeholder="{{ __('multilingual.area_manager_review.filters.all_engineers') }}">
                                    <option value=""></option>
                                    @foreach ($filterOptions['field_engineers'] as $engineer)
                                        <option value="{{ $engineer }}">{{ $engineer }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('multilingual.area_manager_review.columns.latest_status') }}</label>
                                <select id="filter_latest_status" class="form-select form-select-solid area-manager-filter-select"
                                    data-placeholder="{{ __('multilingual.area_manager_review.filters.all_statuses') }}">
                                    <option value=""></option>
                                    @foreach ($filterOptions['statuses'] as $status)
                                        <option value="{{ $status['name'] }}">{{ $status['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('multilingual.area_manager_review.filters.from_date') }}</label>
                                <input type="text" id="filter_from_date" class="form-control form-control-solid area-manager-datepicker"
                                    placeholder="yyyy-mm-dd">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('multilingual.area_manager_review.filters.to_date') }}</label>
                                <input type="text" id="filter_to_date" class="form-control form-control-solid area-manager-datepicker"
                                    placeholder="yyyy-mm-dd">
                            </div>

                            <div class="col-md-3 d-flex gap-3">
                                <button type="button" class="btn btn-primary flex-fill" id="applyAreaManagerFilters">
                                    {{ __('multilingual.area_manager_review.filters.apply_filters') }}
                                </button>
                                <button type="button" class="btn btn-light flex-fill" id="resetAreaManagerFilters">
                                    {{ __('multilingual.area_manager_review.filters.reset') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="areaManagerReviewTable">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th width="40">#</th>
                                    <th>{{ __('multilingual.area_manager_review.columns.object_id') }}</th>
                                    <th>{{ __('multilingual.area_manager_review.columns.building_name') }}</th>
                                    <th>{{ __('multilingual.area_manager_review.columns.municipality') }}</th>
                                    <th>{{ __('multilingual.area_manager_review.columns.neighborhood') }}</th>
                                    <th>{{ __('multilingual.area_manager_review.columns.field_engineer') }}</th>
                                    <th>{{ __('multilingual.area_manager_review.columns.latest_status') }}</th>
                                    <th>{{ __('multilingual.area_manager_review.columns.status_date') }}</th>
                                    <th class="text-end min-w-100px">{{ __('multilingual.area_manager_review.columns.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(function () {
            $('.area-manager-filter-select').select2({
                allowClear: true,
                width: '100%'
            });

            let fromPicker = flatpickr("#filter_from_date", {
                dateFormat: "Y-m-d",
                allowInput: true,
                onChange: function (selectedDates) {
                    toPicker.set('minDate', selectedDates[0] || null);
                }
            });

            let toPicker = flatpickr("#filter_to_date", {
                dateFormat: "Y-m-d",
                allowInput: true,
                onChange: function (selectedDates) {
                    fromPicker.set('maxDate', selectedDates[0] || null);
                }
            });

            let table = $('#areaManagerReviewTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('area-manager-review.data') }}',
                    data: function (data) {
                        data.objectid = $('#filter_objectid').val();
                        data.building_name = $('#filter_building_name').val();
                        data.municipalitie = $('#filter_municipalitie').val();
                        data.neighborhood = $('#filter_neighborhood').val();
                        data.assignedto = $('#filter_assignedto').val();
                        data.latest_status = $('#filter_latest_status').val();
                        data.from_date = $('#filter_from_date').val();
                        data.to_date = $('#filter_to_date').val();
                    }
                },
                order: [[7, 'desc']],
                columns: [
                    {
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    { data: 'objectid', name: 'objectid' },
                    { data: 'building_name', name: 'building_name' },
                    { data: 'municipalitie', name: 'municipalitie' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'assignedto', name: 'assignedto' },
                    { data: 'latest_status_label', name: 'latest_status_label' },
                    {
                        data: 'latest_status_at',
                        name: 'latest_history.created_at',
                        searchable: false
                    },
                    {
                        data: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end'
                    }
                ]
            });

            $('#areaManagerReviewTable').on('draw.dt', function () {
                KTMenu.createInstances();
            });

            $('#refreshAreaManagerTable').on('click', function () {
                table.ajax.reload(null, false);
            });

            $('#areaManagerTableSearch').on('keyup', function () {
                table.search($(this).val()).draw();
            });

            $('#applyAreaManagerFilters').on('click', function () {
                table.ajax.reload();
            });

            $('#resetAreaManagerFilters').on('click', function () {
                $('#filter_objectid').val('');
                $('#filter_building_name').val('');
                $('.area-manager-filter-select').val(null).trigger('change');
                fromPicker.clear();
                toPicker.clear();
                table.search('');
                $('#areaManagerTableSearch').val('');
                table.ajax.reload();
            });
        });
    </script>
@endsection
