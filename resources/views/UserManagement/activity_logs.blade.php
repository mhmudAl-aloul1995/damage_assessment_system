@extends('layouts.app')

@section('title', 'Activity Logs')
@section('pageName', 'Activity Logs')

@section('content')
    <style>
        .activity-logs-table-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        #kt_datatable_activity_logs {
            width: 100% !important;
            min-width: 1500px;
            white-space: nowrap;
        }

        #kt_datatable_activity_logs th,
        #kt_datatable_activity_logs td {
            vertical-align: middle;
            white-space: nowrap;
        }

        #kt_datatable_activity_logs td {
            font-size: 13px;
        }
    </style>

    <div class="app-content flex-column-fluid">
        <div class="app-container container-xxl">
            <div class="card mb-5">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">
                            <i class="ki-duotone ki-chart-line-up fs-2 me-2"></i>
                            Activity Logs
                        </h3>
                    </div>

                    <div class="card-toolbar">
                        <button type="button" id="btn_reload_table" class="btn btn-light-primary btn-sm me-2">
                            <i class="ki-duotone ki-arrows-circle fs-3"></i>
                            Reload
                        </button>

                        <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse"
                            data-bs-target="#activityLogsFilters">
                            <i class="ki-duotone ki-filter fs-3"></i>
                            Filters
                        </button>
                    </div>
                </div>

                <div class="card-body pt-0">
                    <div class="collapse show mb-5" id="activityLogsFilters">
                        <form id="filter_form" class="row g-4 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">User</label>
                                <select id="filter_user_id" class="form-select form-select-sm" data-control="select2"
                                    data-placeholder="All Users">
                                    <option value="">All Users</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">
                                            {{ $user->name }} - {{ $user->email ?? $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Activity</label>
                                <select id="filter_action_type" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="page_visit">Page Visit</option>
                                    <option value="action">Action</option>
                                </select>
                            </div>

                            <div class="col-md-1">
                                <label class="form-label">Method</label>
                                <select id="filter_method" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="GET">GET</option>
                                    <option value="POST">POST</option>
                                    <option value="PUT">PUT</option>
                                    <option value="PATCH">PATCH</option>
                                    <option value="DELETE">DELETE</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">URL</label>
                                <input type="text" id="filter_url" class="form-control form-control-sm"
                                    placeholder="/damage-assessment">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="text" id="filter_from_date"
                                    class="form-control form-control-sm flatpickr-input" data-date-format="yyyy-mm-dd">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="text" id="filter_to_date"
                                    class="form-control form-control-sm flatpickr-input" data-date-format="yyyy-mm-dd">
                            </div>

                            <div class="col-md-1">
                                <button type="button" id="btn_filter" class="btn btn-primary btn-sm w-100">
                                    Search
                                </button>
                            </div>

                            <div class="col-md-1">
                                <button type="button" id="btn_reset" class="btn btn-light-danger btn-sm w-100">
                                    Reset
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive activity-logs-table-wrapper">
                        <table id="kt_datatable_activity_logs"
                            class="table table-row-bordered table-row-gray-300 align-middle gy-3 nowrap w-100">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Activity</th>
                                    <th>Method</th>
                                    <th>URL</th>
                                    <th>Route</th>
                                    <th>Description</th>
                                    <th>IP</th>
                                    <th>Status</th>
                                    <th>Browser</th>
                                    <th>Occurred At</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(document).ready(function () {
            if ($.fn.select2) {
                $('[data-control="select2"]').select2({
                    width: '100%'
                });
            }

            if (typeof flatpickr !== 'undefined') {
                $('#filter_from_date, #filter_to_date').flatpickr({
                    dateFormat: 'Y-m-d',
                    allowInput: true
                });
            }

            let table = $('#kt_datatable_activity_logs').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                autoWidth: false,
                responsive: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[0, 'desc']],

                ajax: {
                    url: "{{ route('user-activity-logs.data') }}",
                    data: function (d) {
                        d.user_id = $('#filter_user_id').val();
                        d.action_type = $('#filter_action_type').val();
                        d.method = $('#filter_method').val();
                        d.url = $('#filter_url').val();
                        d.from_date = $('#filter_from_date').val();
                        d.to_date = $('#filter_to_date').val();
                    }
                },

                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'user_name', name: 'user_name' },
                    { data: 'user_email', name: 'user_email', defaultContent: '-' },
                    { data: 'action_badge', name: 'action_type', orderable: false, searchable: false },
                    { data: 'method_badge', name: 'method', orderable: false, searchable: false },
                    { data: 'url_label', name: 'url' },
                    { data: 'route_name', name: 'route_name', defaultContent: '-' },
                    { data: 'description', name: 'description', defaultContent: '-' },
                    { data: 'ip_address', name: 'ip_address', defaultContent: '-' },
                    { data: 'status_code', name: 'status_code', defaultContent: '-' },
                    { data: 'browser', name: 'user_agent', orderable: false },
                    { data: 'occurred_at_formatted', name: 'occurred_at' },
                ],

                language: {
                    processing: "Loading...",
                    search: "Search:",
                    lengthMenu: "Show _MENU_",
                    info: "Showing _START_ to _END_ of _TOTAL_",
                    infoEmpty: "No records",
                    zeroRecords: "No matching records found",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
            });

            $('#btn_filter, #btn_reload_table').on('click', function () {
                table.ajax.reload(null, false);
            });

            $('#btn_reset').on('click', function () {
                $('#filter_user_id').val('').trigger('change');
                $('#filter_action_type').val('');
                $('#filter_method').val('');
                $('#filter_url').val('');
                $('#filter_from_date').val('');
                $('#filter_to_date').val('');

                table.ajax.reload(null, false);
            });

            setTimeout(function () {
                table.columns.adjust();
            }, 300);

            $('button[data-bs-toggle="collapse"]').on('shown.bs.collapse hidden.bs.collapse', function () {
                table.columns.adjust();
            });
        });
    </script>
@endsection
