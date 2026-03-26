@extends('layouts.app')

@section('title', 'Attendance')
@section('pageName', 'Attendance')

@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
<style>

/* 🔥 LTR FIX */
.dataTables_wrapper,
.dataTables_scroll,
.dataTables_scrollHead,
.dataTables_scrollBody {
    direction: ltr !important;
}

/* الجدول */
#attendanceTable th,
#attendanceTable td {
    white-space: nowrap;
    vertical-align: middle;
}

/* العربي */
.col-name-ar {
    direction: rtl;
    text-align: right;
}

/* hover */
#attendanceTable tbody tr:hover {
    background-color: #f1faff;
}

/* الأيام */
.day-cell {
    width: 45px;
}

/* fixed columns fix */
.dtfc-fixed-left {
    background: #fff !important;
    z-index: 2;
}

/* total */
.total-cell {
    font-weight: bold;
}

</style>

<div class="card card-flush mb-7">

    <!-- HEADER -->
    <div class="card-header align-items-center py-5">

        <div class="card-title">
            <h2 class="fw-bold">Attendance Sheet</h2>
        </div>

        <div class="card-toolbar">

            <div class="d-flex gap-2 me-3">
                <select id="month" class="form-select form-select-sm w-auto">
                    <option value="">Month</option>
                    @for($m=1; $m<=12; $m++)
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

            <button id="reloadTable" class="btn btn-sm btn-light-primary">
                Reload
            </button>

        </div>
    </div>

    <!-- BODY -->
    <div class="card-body">

        <div class="attendance-wrapper">

            <table id="attendanceTable"
                class="table table-bordered align-middle text-center fs-7">

                <thead class="bg-light">
                    <tr>

                        <th class="w-50px">#</th>
                        <th class="w-125px">Contract</th>
                        <th class="w-150px">Name EN</th>
                        <th class="w-150px">Name AR</th>
                        <th class="w-150px">Position</th>
                        <th class="w-125px">ID</th>
                        <th class="w-125px">Contact</th>
                        <th class="w-80px bg-warning">Total</th>

                        @for($i=1; $i<=31; $i++)
                            <th class="w-45px">{{ $i }}</th>
                        @endfor

                    </tr>
                </thead>

            </table>

        </div>

    </div>

</div>

@endsection



@section('script')
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>

<script>

let table;

$(function () {

    table = $('#attendanceTable').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        scrollCollapse: true,
        paging: false,
        searching: false,
        ordering: false,
        info: false,
        autoWidth: false,

        ajax: {
            url: "{{ route('attendance.data') }}",
            type: "POST",
            data: function (d) {
                d._token = "{{ csrf_token() }}";
                d.month = $('#month').val();
                d.year  = $('#year').val();
            }
        },

        columns: [

            { data: 'DT_RowIndex', className: 'w-50px text-center' },
            { data: 'contract_date', className: 'w-125px text-center' },
            { data: 'name_en', className: 'w-150px text-start' },
            { data: 'name_ar', className: 'w-150px col-name-ar' },
            { data: 'position', className: 'w-150px text-start' },
            { data: 'id_no', className: 'w-125px text-center' },
            { data: 'contact', className: 'w-125px text-center' },
            { data: 'total', className: 'w-80px bg-warning total-cell' },

            @for($i=1; $i<=31; $i++)
            {
                data: 'day_{{ $i }}',
                className: 'day-cell text-center',
                render: function(data) {

                    if (data == 1) {
                        return '<span class="badge badge-light-success">1</span>';
                    }

                    if (data == 0) {
                        return '<span class="badge badge-light-danger">0</span>';
                    }

                    return '';
                }
            },
            @endfor

        ],

        /* 🔥 هنا السحر */
        fixedColumns: {
            leftColumns: 8
        }

    });

    // reload
    $('#reloadTable').on('click', function () {
        table.ajax.reload(null, false);
    });

    // filters
    $('#month, #year').on('change', function () {
        table.ajax.reload(null, false);
    });

});
</script>
@endsection