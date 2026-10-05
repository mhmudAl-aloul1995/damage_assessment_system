@extends('layouts.app')

@section('title', 'Attendance')
@section('pageName', 'Attendance')

@section('content')
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css" />

    <style>
        .day-column {
            min-width: 48px !important;
            width: 48px !important;
            text-align: center;
            vertical-align: middle !important;
        }

        #kt_attendance_table th,
        #kt_attendance_table td {
            vertical-align: middle !important;
            text-align: center;
            white-space: nowrap;
        }

        #kt_attendance_table .dtfc-fixed-left {
            background-color: #ffffff !important;
            z-index: 3 !important;
        }

        #kt_attendance_table thead .dtfc-fixed-left {
            background-color: #f8f9fa !important;
            z-index: 4 !important;
        }

        .btn-attendance {
            width: 38px !important;
            height: 38px !important;
            min-width: 38px !important;
            min-height: 38px !important;
            padding: 0 !important;
            border-radius: 10px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 12px !important;
            line-height: 1 !important;
            font-weight: 700 !important;
            border: 0 !important;
        }

        .btn-attendance span {
            display: inline-block;
            line-height: 1;
        }

        .attendance-total {
            font-weight: 700;
        }

        .attendance-total.high {
            color: var(--kt-success);
        }

        .attendance-total.medium {
            color: var(--kt-warning);
        }

        .attendance-total.low {
            color: var(--kt-danger);
        }

        .table-responsive {
            overflow: hidden;
        }

        div.dt-scroll-body {
            border-bottom: 1px solid #e9ecef;
        }

        .badge {
            white-space: nowrap;
        }

        .employee-name-box {
            text-align: start;
            line-height: 1.3;
        }

        .employee-name-box .name-en {
            font-weight: 600;
            color: var(--kt-gray-900);
        }

        .employee-name-box .name-ar {
            font-size: 11px;
            color: var(--kt-gray-600);
        }

        .attendance-summary-bar {
            border: 1px solid #eef1f6;
            border-radius: 8px;
            background: #fbfcfe;
        }

        .attendance-summary-item {
            min-width: 105px;
            padding: 8px 12px;
            border-inline-start: 1px solid #eef1f6;
        }

        .attendance-summary-item:first-child {
            border-inline-start: 0;
        }

        .attendance-summary-item span {
            display: block;
            color: var(--kt-gray-600);
            font-size: 11px;
            font-weight: 600;
        }

        .attendance-summary-item strong {
            display: block;
            color: var(--kt-gray-900);
            font-size: 18px;
            line-height: 1.1;
        }

        .attendance-view-switch .btn {
            min-width: 86px;
        }

        .employee-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .employee-detail-item {
            border: 1px solid #eef1f6;
            border-radius: 8px;
            padding: 10px 12px;
            background: #fbfcfe;
        }

        .employee-detail-item span {
            display: block;
            color: var(--kt-gray-600);
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .employee-detail-item strong {
            color: var(--kt-gray-900);
            font-size: 13px;
            word-break: break-word;
        }

        .toolbar-filters {
            gap: 10px;
            flex-wrap: wrap;
        }

        .day-column {
            min-width: 48px !important;
        }

        .day-column .btn {
            width: 22px !important;
            height: 22px !important;
            padding: 0 !important;
            font-size: 12px !important;
        }

        .set-day-present,
        .set-day-absent {
            display: none !important;
        }

        .day-column .spinner-border {
            width: 12px !important;
            height: 12px !important;
            border-width: 2px !important;
        }

        .import-form-box {
            min-width: 320px;
        }
    </style>

    <div class="app-content flex-column-fluid">
        <div class="app-container container-xxl">

            <div class="card shadow-sm">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h2 class="fw-bold mb-0 d-flex align-items-center">
                            <i class="ki-duotone ki-calendar-8 fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            Attendance
                        </h2>
                    </div>

                    <div class="card-toolbar">
                        <div class="d-flex align-items-center toolbar-filters">
                            <input type="month" id="filter_month" class="form-control form-control-solid w-200px"
                                value="{{ date('Y-m') }}">

                            <select id="filter_contract" class="form-select form-select-solid w-150px">
                                <option value="">All Contracts</option>
                                <option value="phc">PHC</option>
                                <option value="undp">UNDP</option>
                                <option value="mopwh">MOPWH</option>
                                <option value="pef">PEF</option>
                            </select>
                            <select id="filter_region" class="form-select form-select-solid w-150px">
                                <option value="">All Regions</option>
                                <option value="north">North</option>
                                <option value="south">South</option>
                            </select>
                            <button class="btn btn-light-primary" id="btn_reload" type="button">
                                Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body pt-4 pb-0">
                    <!--   <form id="attendanceImportForm" enctype="multipart/form-data"
                                                class="d-flex align-items-center gap-3 flex-wrap import-form-box">
                                                @csrf
                                                <input type="file" name="file" accept=".xlsx,.xls" class="form-control form-control-solid w-250px"
                                                    required>
                                                <select name="region" class="form-select form-select-solid w-150px" required>
                                                    <option value="">Select Region</option>
                                                    <option value="north">North</option>
                                                    <option value="south">South</option>
                                                </select>
                                                <button type="submit" class="btn btn-primary" id="btn_import_excel">
                                                    <span class="indicator-label">Import Excel</span>
                                                    <span class="indicator-progress">
                                                        Importing...
                                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                                    </span>
                                                </button>

                                            </form>
                                        </div>
                                      <div class="w-100 mt-3" id="import_progress_wrapper" style="display:none;">
                                            <div class="progress h-20px">
                                                <div id="import_progress_bar" class="progress-bar progress-bar-striped progress-bar-animated"
                                                    role="progressbar" style="width: 0%">
                                                    0%
                                                </div>
                                            </div>
                                            <div class="text-muted mt-2" id="import_progress_text">Starting import...</div>
                                        </div> -->
                    <div class="card-body py-4">
                        <div class="attendance-summary-bar d-flex align-items-center justify-content-between flex-wrap gap-3 p-3 mb-4">
                            <div class="d-flex align-items-center flex-wrap gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <label for="selected_day" class="fw-semibold text-muted mb-0">Selected day</label>
                                    <select id="selected_day" class="form-select form-select-solid w-100px">
                                        @for($i = 1; $i <= 31; $i++)
                                            <option value="{{ $i }}">{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>

                                <div class="attendance-summary-item">
                                    <span>Present</span>
                                    <strong id="summary_present">0</strong>
                                </div>

                                <div class="attendance-summary-item">
                                    <span>Absent</span>
                                    <strong id="summary_absent">0</strong>
                                </div>

                                <div class="attendance-summary-item">
                                    <span>Unset</span>
                                    <strong id="summary_unset">0</strong>
                                </div>
                            </div>

                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <div class="btn-group attendance-view-switch" role="group" aria-label="Attendance view">
                                    <button type="button" class="btn btn-light-primary active" data-view-mode="month">Month</button>
                                    <button type="button" class="btn btn-light" data-view-mode="day">Day</button>
                                </div>

                                <button type="button" class="btn btn-light-success" id="btn_set_selected_present">
                                    Set day present
                                </button>

                                <button type="button" class="btn btn-light-danger" id="btn_set_selected_absent">
                                    Set day absent
                                </button>
                            </div>
                        </div>

                        <table class="table table-bordered align-middle fs-7 gy-2" id="kt_attendance_table">
                            <thead>
                                <tr class="text-center bg-light fw-bold">
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Details</th>
                                    <th>Total</th>

                                    @for($i = 1; $i <= 31; $i++)
                                        <th class="day-column">
                                            <span class="fw-bold">{{ $i }}</span>

                                                <button type="button"
                                                    class="btn btn-icon btn-light-success btn-sm set-day-present"
                                                    data-day="{{ $i }}" title="Set all present">
                                                    ✓
                                                </button>

                                                <button type="button"
                                                    class="btn btn-icon btn-light-danger btn-sm set-day-absent"
                                                    data-day="{{ $i }}" title="Set all absent">
                                                    ✕
                                                </button>
                                        </th>
                                    @endfor

                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <div class="modal fade" id="employeeDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1" id="employeeDetailsTitle">Employee details</h5>
                            <div class="text-muted fs-7" id="employeeDetailsSubtitle"></div>
                        </div>
                        <button type="button" class="btn btn-icon btn-sm btn-light" data-bs-dismiss="modal" aria-label="Close">
                            <i class="ki-duotone ki-cross fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="employee-detail-grid">
                            <div class="employee-detail-item">
                                <span>ID</span>
                                <strong id="employeeDetailId">-</strong>
                            </div>
                            <div class="employee-detail-item">
                                <span>Phone</span>
                                <strong id="employeeDetailPhone">-</strong>
                            </div>
                            <div class="employee-detail-item">
                                <span>Role</span>
                                <strong id="employeeDetailRole">-</strong>
                            </div>
                            <div class="employee-detail-item">
                                <span>Contract</span>
                                <strong id="employeeDetailContract">-</strong>
                            </div>
                            <div class="employee-detail-item">
                                <span>Region</span>
                                <strong id="employeeDetailRegion">-</strong>
                            </div>
                            <div class="employee-detail-item">
                                <span>Total present</span>
                                <strong id="employeeDetailTotal">0</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endsection

    @section('script')
        <script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>

        <script>
            $(document).ready(function () {

                function initTooltips() {
                    $('[data-bs-toggle="tooltip"]').each(function () {
                        let existing = bootstrap.Tooltip.getInstance(this);
                        if (existing) {
                            existing.dispose();
                        }
                        new bootstrap.Tooltip(this);
                    });
                }

                function setHeaderButtonLoading(btn, isLoading) {
                    if (isLoading) {
                        btn.data('old-html', btn.html());
                        btn.prop('disabled', true);
                        btn.html('<span class="spinner-border spinner-border-sm"></span>');
                    } else {
                        btn.prop('disabled', false);
                        if (btn.data('old-html')) {
                            btn.html(btn.data('old-html'));
                        }
                    }
                }

                function getDayColumnIndex(dayNumber) {
                    return 3 + parseInt(dayNumber);
                }

                function getSelectedDate() {
                    let monthVal = $('#filter_month').val();
                    let selectedDay = parseInt($('#selected_day').val()) || 1;

                    return monthVal + '-' + String(selectedDay).padStart(2, '0');
                }

                function syncSelectedDayOptions() {
                    let monthVal = $('#filter_month').val();
                    let parts = monthVal.split('-');
                    let daysInMonth = new Date(parseInt(parts[0]), parseInt(parts[1]), 0).getDate();
                    let selectedDay = Math.min(parseInt($('#selected_day').val()) || 1, daysInMonth);

                    $('#selected_day option').each(function () {
                        let optionDay = parseInt($(this).val());
                        $(this).prop('disabled', optionDay > daysInMonth).toggle(optionDay <= daysInMonth);
                    });

                    $('#selected_day').val(selectedDay);
                }

                function refreshSummary() {
                    $.ajax({
                        url: "{{ route('attendance.summary') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            date: getSelectedDate(),
                            contract_type: $('#filter_contract').val(),
                            region: $('#filter_region').val()
                        },
                        success: function (res) {
                            $('#summary_present').text(res.present ?? 0);
                            $('#summary_absent').text(res.absent ?? 0);
                            $('#summary_unset').text(res.unset ?? 0);
                        }
                    });
                }

                function applyViewMode(mode) {
                    let selectedDay = parseInt($('#selected_day').val()) || 1;

                    for (let day = 1; day <= 31; day++) {
                        table.column(getDayColumnIndex(day)).visible(mode === 'month' || day === selectedDay, false);
                    }

                    table.columns.adjust().draw(false);
                }

                function updateDayButtonsWithoutReload(dayNumber, newStatus, fullDate) {
                    let columnIndex = getDayColumnIndex(dayNumber);

                    $('#kt_attendance_table tbody tr').each(function () {
                        let row = $(this);
                        let dayCell = row.find('td').eq(columnIndex);
                        let btn = dayCell.find('.toggle-status');

                        if (!btn.length || btn.prop('disabled')) {
                            return;
                        }

                        let currentStatus = parseInt(btn.attr('data-status')) || 0;
                        let totalElement = row.find('.attendance-total');
                        let currentTotal = parseInt(totalElement.text()) || 0;

                        if (currentStatus === newStatus) {
                            return;
                        }

                        btn.attr('data-status', newStatus);
                        btn.attr('data-date', fullDate);

                        if (newStatus === 1) {
                            btn.removeClass('btn-light-danger btn-danger btn-light-secondary')
                                .addClass('btn-light-success')
                                .html('<span>✓</span>')
                                .attr('title', 'Present - ' + fullDate);

                            currentTotal = currentTotal + 1;
                        } else {
                            btn.removeClass('btn-light-success btn-success btn-light-secondary')
                                .addClass('btn-light-danger')
                                .html('<span>✕</span>')
                                .attr('title', 'Absent - ' + fullDate);

                            currentTotal = Math.max(0, currentTotal - 1);
                        }

                        let levelClass = 'low';
                        if (currentTotal >= 20) {
                            levelClass = 'high';
                        } else if (currentTotal >= 10) {
                            levelClass = 'medium';
                        }

                        totalElement
                            .removeClass('high medium low')
                            .addClass(levelClass)
                            .text(currentTotal);
                    });

                    initTooltips();
                }

                let currentViewMode = 'month';

                let today = new Date();
                let currentMonth = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
                if ($('#filter_month').val() === currentMonth) {
                    $('#selected_day').val(today.getDate());
                }

                syncSelectedDayOptions();

                let table = $('#kt_attendance_table').DataTable({
                    processing: true,
                    serverSide: true,
                    pageLength: 50,
                    searching: false,
                    ordering: false,
                    info: true,
                    autoWidth: false,
                    scrollX: true,
                    scrollCollapse: true,
                    fixedColumns: {
                        leftColumns: 4
                    },
                    ajax: {
                        url: "{{ route('attendance.data') }}",
                        type: "POST",
                        data: function (d) {
                            let val = $('#filter_month').val();
                            let parts = val.split('-');

                            d.year = parts[0];
                            d.month = parts[1];
                            d.contract_type = $('#filter_contract').val();
                            d.region = $('#filter_region').val();
                            d._token = "{{ csrf_token() }}";

                        }
                    },
                    drawCallback: function () {
                        initTooltips();
                        refreshSummary();
                    },
                    columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        className: 'text-center',
                        width: '50px',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name_en',
                        name: 'name_en',
                        width: '240px',
                        render: function (data, type, row) {
                            let nameEn = data ?? '-';
                            let nameAr = row.name ?? '';
                            return `
                                                                                    <div class="employee-name-box">
                                                                                        <div class="name-en">${nameEn}</div>
                                                                                        <div class="name-ar">${nameAr}</div>
                                                                                    </div>
                                                                                `;
                        }
                    },
                    {
                        data: null,
                        name: 'details',
                        className: 'text-center',
                        width: '80px',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            return `
                                <button type="button"
                                        class="btn btn-icon btn-light-info btn-sm show-employee-details"
                                        data-row="${encodeURIComponent(JSON.stringify(row))}"
                                        data-bs-toggle="tooltip"
                                        title="Show details">
                                    <i class="ki-duotone ki-eye fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </button>
                            `;
                        }
                    },
                    {
                        data: 'total',
                        name: 'total',
                        className: 'text-center',
                        width: '80px',
                        orderable: false,
                        searchable: false,
                        render: function (data) {
                            let total = parseInt(data) || 0;
                            let levelClass = 'low';

                            if (total >= 20) {
                                levelClass = 'high';
                            } else if (total >= 10) {
                                levelClass = 'medium';
                            }

                            return `<span class="attendance-total ${levelClass}">${total}</span>`;
                        }
                    },

                        @for ($i = 1; $i <= 31; $i++)
                                                                                                                                                                                    {
                                data: 'day_{{ $i }}',
                                name: 'day_{{ $i }}',
                                className: 'text-center day-column',
                                width: '55px',
                                orderable: false,
                                searchable: false,
                                render: function (data, type, row) {
                                    if (data === 'N/A') {
                                        return '<span class="text-muted">-</span>';
                                    }

                                    let monthVal = $('#filter_month').val();
                                    let date = monthVal + '-' + String({{ $i }}).padStart(2, '0');

                                    let today = new Date();
                                    today.setHours(0, 0, 0, 0);

                                    let cellDate = new Date(date);
                                    cellDate.setHours(0, 0, 0, 0);

                                    if (cellDate > today) {
                                        return `
                                                                                                                                                                                                    <button type="button"
                                                                                                                                                                                                            class="btn btn-light-secondary btn-attendance"
                                                                                                                                                                                                            disabled
                                                                                                                                                                                                            data-bs-toggle="tooltip"
                                                                                                                                                                                                            title="Future date">
                                                                                                                                                                                                        <span>-</span>
                                                                                                                                                                                                    </button>
                                                                                                                                                                                                `;
                                    }

                                    let status = parseInt(data) === 1 ? 1 : 0;
                                    let color = status === 1 ? 'btn-light-success' : 'btn-light-danger';
                                    let text = status === 1 ? '✓' : '✕';
                                    let title = (status === 1 ? 'Present' : 'Absent') + ' - ' + date;

                                    return `
                                                                                                                                                                                                <button type="button"
                                                                                                                                                                                                        class="btn ${color} btn-attendance toggle-status"
                                                                                                                                                                                                        data-user="${row.id}"
                                                                                                                                                                                                        data-date="${date}"
                                                                                                                                                                                                        data-status="${status}"
                                                                                                                                                                                                        data-bs-toggle="tooltip"
                                                                                                                                                                                                        title="${title}">
                                                                                                                                                                                                    <span>${text}</span>
                                                                                                                                                                                                </button>
                                                                                                                                                                                            `;
                                }
                            },
                        @endfor
                                                                    ]
                });

                table.on('init', function () {
                    applyViewMode(currentViewMode);
                    refreshSummary();
                });

                applyViewMode(currentViewMode);
                refreshSummary();

                $('#filter_month, #filter_contract, #filter_region').on('change', function () {
                    syncSelectedDayOptions();
                    applyViewMode(currentViewMode);
                    table.ajax.reload();
                    refreshSummary();
                });

                $('#btn_reload').on('click', function () {
                    table.ajax.reload(null, false);
                    refreshSummary();
                });

                $('#selected_day').on('change', function () {
                    applyViewMode(currentViewMode);
                    refreshSummary();
                });

                $('[data-view-mode]').on('click', function () {
                    currentViewMode = $(this).data('view-mode');
                    $('[data-view-mode]').removeClass('btn-light-primary active').addClass('btn-light');
                    $(this).removeClass('btn-light').addClass('btn-light-primary active');
                    applyViewMode(currentViewMode);
                });

                $(document).on('click', '.show-employee-details', function () {
                    let row = JSON.parse(decodeURIComponent($(this).attr('data-row')));
                    let roles = row.role && row.role.length ? row.role.join(', ') : '-';

                    $('#employeeDetailsTitle').text(row.name_en || '-');
                    $('#employeeDetailsSubtitle').text(row.name || '');
                    $('#employeeDetailId').text(row.id_no || '-');
                    $('#employeeDetailPhone').text(row.phone || '-');
                    $('#employeeDetailRole').text(roles);
                    $('#employeeDetailContract').text(row.contract_type || '-');
                    $('#employeeDetailRegion').text(row.region || '-');
                    $('#employeeDetailTotal').text(row.total || 0);

                    new bootstrap.Modal(document.getElementById('employeeDetailsModal')).show();
                });

                function setSelectedDayStatus(status, btn) {
                    if (btn.prop('disabled')) {
                        return;
                    }

                    let fullDate = getSelectedDate();
                    let selectedDay = parseInt($('#selected_day').val()) || 1;

                    let today = new Date();
                    today.setHours(0, 0, 0, 0);

                    let cellDate = new Date(fullDate);
                    cellDate.setHours(0, 0, 0, 0);

                    if (cellDate > today) {
                        toastr.error('Cannot set future date');
                        return;
                    }

                    setHeaderButtonLoading(btn, true);

                    $.ajax({
                        url: status === 1 ? "{{ route('attendance.set-day-present') }}" : "{{ route('attendance.set-day-absent') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            date: fullDate,
                            contract_type: $('#filter_contract').val(),
                            region: $('#filter_region').val()
                        },
                        success: function (res) {
                            setHeaderButtonLoading(btn, false);

                            if (res.success) {
                                table.ajax.reload(null, false);
                                refreshSummary();
                                toastr.success(res.message || 'Day updated');
                            } else {
                                toastr.error(res.message || 'Update failed');
                            }
                        },
                        error: function () {
                            setHeaderButtonLoading(btn, false);
                            toastr.error('Update failed');
                        }
                    });
                }

                $('#btn_set_selected_present').on('click', function () {
                    setSelectedDayStatus(1, $(this));
                });

                $('#btn_set_selected_absent').on('click', function () {
                    setSelectedDayStatus(0, $(this));
                });

                $(document).on('click', '.toggle-status', function () {
                    let btn = $(this);

                    if (btn.prop('disabled')) return;

                    let userId = btn.attr('data-user');
                    let date = btn.attr('data-date');
                    let currentStatus = parseInt(btn.attr('data-status')) || 0;
                    let newStatus = currentStatus === 1 ? 0 : 1;

                    let row = btn.closest('tr');
                    let totalElement = row.find('.attendance-total');
                    let currentTotal = parseInt(totalElement.text()) || 0;

                    btn.prop('disabled', true);

                    $.ajax({
                        url: "{{ route('attendance.store') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            user_id: userId,
                            date: date,
                            status: newStatus
                        },
                        success: function (res) {
                            btn.prop('disabled', false);

                            if (res.success) {
                                btn.attr('data-status', newStatus);

                                if (newStatus === 1) {
                                    btn.removeClass('btn-light-danger btn-danger btn-light-secondary')
                                        .addClass('btn-light-success')
                                        .html('<span>✓</span>')
                                        .attr('title', 'Present - ' + date);

                                    currentTotal = currentTotal + 1;
                                } else {
                                    btn.removeClass('btn-light-success btn-success btn-light-secondary')
                                        .addClass('btn-light-danger')
                                        .html('<span>✕</span>')
                                        .attr('title', 'Absent - ' + date);

                                    currentTotal = Math.max(0, currentTotal - 1);
                                }

                                let levelClass = 'low';
                                if (currentTotal >= 20) {
                                    levelClass = 'high';
                                } else if (currentTotal >= 10) {
                                    levelClass = 'medium';
                                }

                                totalElement
                                    .removeClass('high medium low')
                                    .addClass(levelClass)
                                    .text(currentTotal);

                                initTooltips();
                                refreshSummary();
                                toastr.success('تم التحديث');
                            } else {
                                toastr.error(res.message || 'حدث خطأ');
                            }
                        },
                        error: function () {
                            btn.prop('disabled', false);
                            toastr.error('فشل التحديث');
                        }
                    });
                });

                $(document).on('click', '.set-day-present', function () {
                    let btn = $(this);
                    if (btn.prop('disabled')) return;

                    let day = parseInt(btn.data('day'));
                    let monthVal = $('#filter_month').val();

                    if (!monthVal) {
                        toastr.error('Please select month');
                        return;
                    }

                    let fullDate = monthVal + '-' + String(day).padStart(2, '0');

                    let today = new Date();
                    today.setHours(0, 0, 0, 0);

                    let cellDate = new Date(fullDate);
                    cellDate.setHours(0, 0, 0, 0);

                    if (cellDate > today) {
                        toastr.error('Cannot set future date');
                        return;
                    }

                    setHeaderButtonLoading(btn, true);

                    $.ajax({
                        url: "{{ route('attendance.set-day-present') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            date: fullDate,
                            contract_type: $('#filter_contract').val(),
                            region: $('#filter_region').val()
                        },
                        success: function (res) {
                            setHeaderButtonLoading(btn, false);

                            if (res.success) {
                                updateDayButtonsWithoutReload(day, 1, fullDate);
                                refreshSummary();
                                toastr.success(res.message || 'All users set to present');
                            } else {
                                toastr.error(res.message || 'Update failed');
                            }
                        },
                        error: function () {
                            setHeaderButtonLoading(btn, false);
                            toastr.error('Update failed');
                        }
                    });
                });

                $(document).on('click', '.set-day-absent', function () {
                    let btn = $(this);
                    if (btn.prop('disabled')) return;

                    let day = parseInt(btn.data('day'));
                    let monthVal = $('#filter_month').val();

                    if (!monthVal) {
                        toastr.error('Please select month');
                        return;
                    }

                    let fullDate = monthVal + '-' + String(day).padStart(2, '0');

                    let today = new Date();
                    today.setHours(0, 0, 0, 0);

                    let cellDate = new Date(fullDate);
                    cellDate.setHours(0, 0, 0, 0);

                    if (cellDate > today) {
                        toastr.error('Cannot set future date');
                        return;
                    }

                    setHeaderButtonLoading(btn, true);

                    $.ajax({
                        url: "{{ route('attendance.set-day-absent') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            date: fullDate,
                            contract_type: $('#filter_contract').val(),
                            region: $('#filter_region').val()
                        },
                        success: function (res) {
                            setHeaderButtonLoading(btn, false);

                            if (res.success) {
                                updateDayButtonsWithoutReload(day, 0, fullDate);
                                refreshSummary();
                                toastr.success(res.message || 'All users set to absent');
                            } else {
                                toastr.error(res.message || 'Update failed');
                            }
                        },
                        error: function () {
                            setHeaderButtonLoading(btn, false);
                            toastr.error('Update failed');
                        }
                    });
                });

                $('#attendanceImportForm').on('submit', function (e) {
                    e.preventDefault();

                    let formData = new FormData(this);
                    let btn = $('#btn_import_excel');

                    btn.attr('data-kt-indicator', 'on').prop('disabled', true);
                    $('#import_progress_wrapper').show();
                    $('#import_progress_bar').css('width', '10%').text('10%');
                    $('#import_progress_text').text('Uploading file...');

                    $.ajax({
                        url: "{{ route('attendance.import') }}",
                        type: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,
                        xhr: function () {
                            let xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener("progress", function (evt) {
                                if (evt.lengthComputable) {
                                    let percent = Math.round((evt.loaded / evt.total) * 40);
                                    $('#import_progress_bar').css('width', percent + '%').text(percent + '%');
                                }
                            }, false);
                            return xhr;
                        },
                        success: function (res) {

                            console.log(res);

                            if (!res.success) {
                                btn.removeAttr('data-kt-indicator').prop('disabled', false);
                                toastr.error(res.message || 'Import failed');
                                return;
                            }

                            let logId = res.log_id;

                            if (!logId) {
                                btn.removeAttr('data-kt-indicator').prop('disabled', false);
                                toastr.error('log_id missing');
                                return;
                            }

                            $('#import_progress_text').text('Processing data...');

                            let interval = setInterval(function () {

                                $.get("{{ route('attendance.import.progress', ['log' => '__ID__']) }}".replace('__ID__', logId), function (progress) {

                                    let percent = progress.status === 'completed' ? 100 : 60;

                                    $('#import_progress_bar').css('width', percent + '%').text(percent + '%');

                                    $('#import_progress_text').text(
                                        'Processed: ' + progress.processed_rows +
                                        ' | Imported: ' + progress.imported_records +
                                        ' | Users: ' + progress.created_users
                                    );

                                    if (progress.status === 'completed') {
                                        clearInterval(interval);

                                        btn.removeAttr('data-kt-indicator').prop('disabled', false);

                                        toastr.success('Import completed');

                                        $('#attendanceImportForm')[0].reset();
                                        table.ajax.reload(null, false);
                                    }

                                    if (progress.status === 'failed') {
                                        clearInterval(interval);

                                        btn.removeAttr('data-kt-indicator').prop('disabled', false);

                                        toastr.error(progress.message || 'Import failed');
                                    }

                                });

                            }, 1000);
                        },
                        error: function (xhr) {
                            btn.removeAttr('data-kt-indicator').prop('disabled', false);
                            toastr.error(xhr.responseJSON?.message || 'Import failed');
                        }
                    });
                });

                initTooltips();

                $("#kt_app_sidebar_toggle").click()
            });

        </script>
    @endsection
