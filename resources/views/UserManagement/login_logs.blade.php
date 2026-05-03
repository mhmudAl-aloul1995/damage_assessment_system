@extends('layouts.app')

@section('title', 'Login Logs')
@section('pageName', 'Login Logs')

@section('content')
    <div class="app-content flex-column-fluid">
        <div class="app-container container-xxl">

            <div class="card mb-5">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">
                            <i class="ki-duotone ki-shield-tick fs-2 me-2"></i>
                            Login Logs
                        </h3>
                    </div>

                    <div class="card-toolbar">
                        <button type="button" id="btn_reload_table" class="btn btn-light-primary btn-sm me-2">
                            <i class="ki-duotone ki-arrows-circle fs-3"></i>
                            Reload
                        </button>

                        <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse"
                            data-bs-target="#loginLogsFilters">
                            <i class="ki-duotone ki-filter fs-3"></i>
                            Filters
                        </button>
                    </div>
                </div>

                <div class="card-body pt-0">

                    <div class="collapse show mb-5" id="loginLogsFilters">
                        <form id="filter_form" class="row g-4 align-items-end">

                            <div class="col-md-3">
                                <label class="form-label">User</label>
                                <select id="filter_user_id" class="form-select form-select-sm" data-control="select2"
                                    data-placeholder="All Users">
                                    <option value="">All Users</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">
                                            {{ $user->name }} - {{ $user->email ?? $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select id="filter_status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="success">Success</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">IP Address</label>
                                <input type="text" id="filter_ip_address" class="form-control form-control-sm"
                                    placeholder="IP">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="text" id="filter_from_date"
                                    class="form-control form-control-sm flatpickr-input" data-date-format="yyyy-mm-dd">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="text" id="filter_to_date" class="form-control form-control-sm flatpickr-input"
                                    data-date-format="yyyy-mm-dd">
                            </div>

                            <div class="col-md-1 d-flex gap-2">
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

                    <div class="table-responsive">
                        <table id="kt_datatable_login_logs"
                            class="table table-row-bordered table-row-gray-300 align-middle gy-4">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>name</th>
                                    <th>Role</th>
                                    <th>IP</th>
                                    <th>Status</th>
                                    <th>Login At</th>
                                    <th>Logout At</th>
                                    <th>Duration</th>
                                    <th>Browser</th>
                                    <th>Security</th>
                                    <th>Device</th>
                                    <th>Failed IP Attempts</th>
                                    <th>Suspicious Reason</th>
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

            let table = $('#kt_datatable_login_logs').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[0, 'desc']],

                ajax: {
                    url: "{{ route('login-logs.data') }}",
                    data: function (d) {
                        d.user_id = $('#filter_user_id').val();
                        d.status = $('#filter_status').val();
                        d.ip_address = $('#filter_ip_address').val();
                        d.from_date = $('#filter_from_date').val();
                        d.to_date = $('#filter_to_date').val();
                    }
                },

                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'user_name', name: 'name' },
                    { data: 'email', name: 'email', defaultContent: '-' },
                    { data: 'name', name: 'name', defaultContent: '-' },
                    { data: 'role', name: 'role', defaultContent: '-' },
                    { data: 'ip_address', name: 'ip_address', defaultContent: '-' },
                    { data: 'status_badge', name: 'is_success', orderable: false, searchable: false },
                    { data: 'login_at', name: 'logged_in_at' },
                    { data: 'logout_at', name: 'logged_out_at' },
                    { data: 'duration', name: 'duration', orderable: false, searchable: false },
                    { data: 'browser', name: 'user_agent', orderable: false },
                    { data: 'security_status', name: 'is_suspicious', orderable: false, searchable: false },
                    { data: 'device_info', name: 'device', orderable: false, searchable: false },
                    { data: 'failed_attempts_from_ip', name: 'failed_attempts_from_ip' },
                    { data: 'suspicious_reason_badge', name: 'suspicious_reason', orderable: false },
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
                }
            });

            $('#btn_filter').on('click', function () {
                table.ajax.reload(null, false);
            });

            $('#btn_reload_table').on('click', function () {
                table.ajax.reload(null, false);
            });

            $('#btn_reset').on('click', function () {
                $('#filter_user_id').val('').trigger('change');
                $('#filter_status').val('');
                $('#filter_ip_address').val('');
                $('#filter_from_date').val('');
                $('#filter_to_date').val('');

                table.ajax.reload(null, false);
            });

        });
    </script>
@endsection