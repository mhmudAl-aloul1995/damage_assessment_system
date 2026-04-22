@extends('layouts.app')
@section('title', 'Area Manager Review')
@section('pageName', 'Area Manager Review')

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
                        <h3 class="fw-bold m-0">Area Manager Review Queue</h3>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-light-primary" id="refreshAreaManagerTable">
                            <i class="ki-duotone ki-arrows-circle fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info d-flex flex-column gap-2 mb-0">
                        <div><span class="fw-bold">Region:</span> {{ $regionLabel }}</div>
                        <div>
                            <span class="fw-bold">Allowed municipalities:</span>
                            @forelse ($municipalities as $municipality)
                                <span class="badge badge-light-primary me-1">{{ $municipality }}</span>
                            @empty
                                <span class="badge badge-light-danger">No municipalities configured</span>
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
                                class="form-control form-control-solid w-250px ps-13" placeholder="Search buildings" />
                        </div>
                    </div>
                </div>

                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="areaManagerReviewTable">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th width="40">#</th>
                                    <th>Object ID</th>
                                    <th>Building Name</th>
                                    <th>Municipality</th>
                                    <th>Neighborhood</th>
                                    <th>Field Engineer</th>
                                    <th>Latest Status</th>
                                    <th>Status Date</th>
                                    <th class="text-end min-w-100px">Actions</th>
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
            let table = $('#areaManagerReviewTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('area-manager-review.data') }}',
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
                    { data: 'latest_status_at', name: 'latest_status_at' },
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
        });
    </script>
@endsection
