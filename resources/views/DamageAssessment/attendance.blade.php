@extends('layouts.app')

@section('title', 'Attendance')
@section('pageName', 'Attendance')

@section('content')

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">

<style>
    /* مهم جدًا: إجبار كل طبقات DataTables على LTR */
    .attendance-wrapper,
    .attendance-wrapper .dataTables_wrapper,
    .attendance-wrapper .dataTables_scroll,
    .attendance-wrapper .dataTables_scrollHead,
    .attendance-wrapper .dataTables_scrollBody,
    .attendance-wrapper .dataTables_scrollHeadInner,
    .attendance-wrapper table.dataTable,
    #attendanceTable {
        direction: ltr !important;
    }

    #attendanceTable thead tr,
    #attendanceTable tbody tr {
        direction: ltr !important;
    }

    #attendanceTable th,
    #attendanceTable td {
        white-space: nowrap;
        vertical-align: middle !important;
    }

    #attendanceTable thead th {
        font-weight: 700;
    }

    .col-name-ar {
        direction: rtl !important;
        text-align: right !important;
    }

    .col-name-en,
    .col-position {
        text-align: left !important;
    }

    .day-cell {
        width: 45px !important;
        min-width: 45px !important;
        max-width: 45px !important;
        text-align: center !important;
        padding: 4px !important;
    }

    .total-cell {
        font-weight: 700;
    }

    #attendanceTable tbody tr:hover {
        background-color: #f1faff !important;
    }

    /* تثبيت الأعمدة */
    .dtfc-fixed-left,
    .dtfc-fixed-start {
        background: #ffffff !important;
        z-index: 3 !important;
    }

    .dtfc-fixed-left::after,
    .dtfc-fixed-start::after {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 1px;
        height: 100%;
        background: #e4e6ef;
    }

    /* حتى لا ينعكس شريط التمرير */
    .attendance-wrapper .dataTables_scrollBody {
        overflow-x: auto !important;
    }
</style>

<div class="card card-flush mb-7">
    <div class="card-header align-items-center py-5 gap-2 gap-md-5">
        <div class="card-title">
            <h2 class="fw-bold">Attendance Sheet</h2>
        </div>

        <div class="card-toolbar">
            <div class="d-flex gap-2 me-3">
                <select id="month" class="form-select form-select-sm w-auto">
                    <option value="">Month</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ $m }}</option>
                    @endfor
                </select>

                <select id="year" class="form-select form-select-sm w-auto">
                    <option value="">Year</option>
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <button type="button" id="reloadTable" class="btn btn-sm btn-light-primary">
                <i class="ki-duotone ki-arrows-circle">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Reload
            </button>
        </div>
    </div>

    <div class="card-body">
        <div class="attendance-wrapper">
            <table id="attendanceTable" class="table table-bordered align-middle text-center fs-7 gy-3">
                <thead class="bg-light">
                    <tr class="fw-bold text-gray-800">
                        <th class="w-50px min-w-50px">#</th>
                        <th class="w-125px min-w-125px">Contract</th>
                        <th class="w-150px min-w-150px">Name EN</th>
                        <th class="w-150px min-w-150px">Name AR</th>
                        <th class="w-150px min-w-150px">Position</th>
                        <th class="w-125px min-w-125px">ID</th>
                        <th class="w-125px min-w-125px">Contact</th>
                        <th class="w-80px min-w-80px bg-warning">Total</th>

                        @for($i = 1; $i <= 31; $i++)
                            <th class="w-45px min-w-45px">{{ $i }}</th>
                        @endfor
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

@endsection

@section('script')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>

<script>
    let table;

    $(function () {
        table = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            scrollCollapse: true,
            autoWidth: false,
            paging: false,
            searching: false,
            ordering: false,
            info: false,

            ajax: {
                url: "{{ route('attendance.data') }}",
                type: "POST",
                data: function (d) {
                    d._token = "{{ csrf_token() }}";
                    d.month = $('#month').val();
                    d.year = $('#year').val();
                }
            },

            columns: [
                { data: 'DT_RowIndex', className: 'w-50px min-w-50px text-center' },
                { data: 'contract_date', className: 'w-125px min-w-125px text-center' },
                { data: 'name_en', className: 'w-150px min-w-150px text-nowrap col-name-en' },
                { data: 'name_ar', className: 'w-150px min-w-150px text-truncate col-name-ar' },
                { data: 'position', className: 'w-150px min-w-150px text-nowrap col-position' },
                { data: 'id_no', className: 'w-125px min-w-125px text-center' },
                { data: 'contact', className: 'w-125px min-w-125px text-center' },
                { data: 'total', className: 'w-80px min-w-80px text-center bg-warning total-cell' },

                @for($i = 1; $i <= 31; $i++)
                {
                    data: 'day_{{ $i }}',
                    defaultContent: '',
                    className: 'day-cell',
                    render: function (data) {
                        if (data == 1) {
                            return '<span class="badge badge-light-success w-30px">1</span>';
                        }

                        if (data == 0) {
                            return '<span class="badge badge-light-danger w-30px">0</span>';
                        }

                        return '';
                    }
                },
                @endfor
            ],

            fixedColumns: {
                leftColumns: 8
            },

            drawCallback: function () {
                $('.attendance-wrapper, .attendance-wrapper *').css('direction', 'ltr');
                $('.col-name-ar').css({
                    direction: 'rtl',
                    textAlign: 'right'
                });
            }
        });

        $('#reloadTable').on('click', function () {
            const btn = $(this);

            btn.attr('data-kt-indicator', 'on');
            btn.prop('disabled', true);

            table.ajax.reload(null, false);

            setTimeout(function () {
                btn.removeAttr('data-kt-indicator');
                btn.prop('disabled', false);
            }, 500);
        });

        $('#month, #year').on('change', function () {
            table.ajax.reload(null, false);
        });
    });
</script>
@endsection