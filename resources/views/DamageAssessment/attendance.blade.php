@extends('layouts.app')

@section('title', 'Attendance')
@section('pageName', 'Attendance')

@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css"/>

<style>
    .day-column {
        min-width: 55px !important;
        width: 55px !important;
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

    .toolbar-filters {
        gap: 10px;
        flex-wrap: wrap;
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
                        <input
                            type="month"
                            id="filter_month"
                            class="form-control form-control-solid w-200px"
                            value="{{ date('Y-m') }}"
                        >

                        <select id="filter_role" class="form-select form-select-solid w-175px">
                            <option value="">All Roles</option>
                            <option value="Engineering Auditor">Engineering Auditor</option>
                            <option value="Legal Auditor">Legal Auditor</option>
                            <option value="Auditing Supervisor">Auditing Supervisor</option>
                            <option value="system manager">System Manager</option>
                            <option value="area manager">Area Manager</option>
                            <option value="team leader">Team Leader</option>
                        </select>

                        <select id="filter_contract" class="form-select form-select-solid w-150px">
                            <option value="">All Contracts</option>
                            <option value="phc">PHC</option>
                            <option value="undp">UNDP</option>
                            <option value="mpwh">MPWH</option>
                            <option value="pef">PEF</option>
                        </select>

                        <button class="btn btn-light-primary" id="btn_reload" type="button">
                            Reload
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body py-4">
                <table class="table table-bordered align-middle fs-7 gy-2" id="kt_attendance_table">
                    <thead>
                        <tr class="text-center bg-light fw-bold">
                            <th>#</th>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Contract</th>
                            <th>Total</th>

                            @for($i = 1; $i <= 31; $i++)
                                <th class="day-column">{{ $i }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
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
            leftColumns: 7
        },
        ajax: {
            url: "{{ route('attendance.data') }}",
            type: "POST",
            data: function (d) {
                let val = $('#filter_month').val();
                let parts = val.split('-');

                d.year = parts[0];
                d.month = parts[1];
                d.role = $('#filter_role').val();
                d.contract_type = $('#filter_contract').val();
                d._token = "{{ csrf_token() }}";
            }
        },
        drawCallback: function () {
            initTooltips();
        },
        columns: [
            {
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
                data: 'id_no',
                name: 'id_no',
                className: 'text-center',
                width: '120px',
                render: function (data) {
                    return data ?? '-';
                }
            },
            {
                data: 'phone',
                name: 'phone',
                className: 'text-center',
                width: '130px',
                render: function(data){
                    return data ?? '-';
                }
            },
            {
                data: 'role',
                name: 'role',
                className: 'text-center',
                width: '150px',
                render: function(data){
                    if (!data) return '-';
                    return `<span class="badge badge-light-success">${data}</span>`;
                }
            },
            {
                data: 'contract_type',
                name: 'contract_type',
                className: 'text-center',
                width: '120px',
                render: function(data){
                    if (!data) return '-';
                    return `<span class="badge badge-light-primary text-uppercase">${data}</span>`;
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

    $('#filter_month, #filter_role, #filter_contract').on('change', function () {
        table.ajax.reload();
    });

    $('#btn_reload').on('click', function () {
        table.ajax.reload(null, false);
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

    initTooltips();
});
</script>
@endsection