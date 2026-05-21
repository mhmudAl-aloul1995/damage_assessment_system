@extends('layouts.app')

@section('title', 'HLP Audit Report')
@section('pageName', 'HLP Audit Report')

@section('content')
    <style>
        #hlp_audit_table th,
        #hlp_audit_table td {
            text-align: center !important;
            vertical-align: middle !important;
        }
    </style>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-6" style="direction: rtl;">
                    <div class="card-title">
                        <h2 style="color: green;">
                            HLP Audit Report: {{ $start_date }}
                            <span class="text-gray-400">to</span>
                            {{ $end_date }}
                        </h2>
                    </div>

                    <div class="card-toolbar">
                        <form action="{{ route('reports.hlp-audit') }}" method="GET" id="filter_form" class="w-100">
                            <input type="hidden" name="start_date" id="start_date" value="{{ $start_date }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ $end_date }}">

                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <a href="{{ route('reports.hlp-audit.export', array_merge(request()->query(), ['start_date' => $start_date, 'end_date' => $end_date])) }}"
                                    class="btn btn-success">
                                    <i class="fa fa-file-excel"></i>
                                    تصدير إلى إكسل
                                </a>

                                <button type="submit" class="btn btn-primary">
                                    فلتر
                                </button>

                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" value="{{ $date_range_label }}"
                                        placeholder="اختر نطاق التاريخ" id="kt_daterangepicker" readonly />
                                    <span class="input-group-text"><i class="ki-duotone ki-calendar fs-2"></i></span>
                                </div>

                                <button class="btn btn-light" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#hlp-audit-advanced-filters" aria-expanded="false">
                                    فلاتر إضافية
                                </button>
                            </div>

                            <div class="collapse mt-5" id="hlp-audit-advanced-filters">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <select name="governorate" class="form-select form-select-solid hlp-report-select"
                                            data-placeholder="المحافظة">
                                            <option value="">كل المحافظات</option>
                                            @foreach ($filter_options['governorates'] as $governorate)
                                                <option value="{{ $governorate }}" @selected($filters['governorate'] === $governorate)>{{ $governorate }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <select name="neighborhood" class="form-select form-select-solid hlp-report-select"
                                            data-placeholder="الحي">
                                            <option value="">كل الأحياء</option>
                                            @foreach ($filter_options['neighborhoods'] as $neighborhood)
                                                <option value="{{ $neighborhood }}" @selected($filters['neighborhood'] === $neighborhood)>{{ $neighborhood }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-4 d-flex gap-3">
                                        <button type="submit" class="btn btn-primary flex-fill">
                                            تطبيق
                                        </button>
                                        <a href="{{ route('reports.hlp-audit') }}" class="btn btn-light flex-fill">
                                            تفريغ
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body py-4">
                    <table class="table table-rounded table-striped table-row-bordered gy-7 text-center align-middle" id="hlp_audit_table">
                        <thead>
                            <tr class="fw-bolder fs-6 text-gray-800 text-uppercase">
                                <th>المحافظة</th>
                                <th>الحي</th>
                                <th>HLP Buildings</th>
                                <th>HLP Housings</th>
                            </tr>
                            <tr class="fw-bold fs-7 text-gray-700">
                                <th>إسم المحافظة</th>
                                <th>إسم الحي</th>
                                <th>عدد المباني المدققة من قبل المحامي</th>
                                <th>عدد الوحد السكنية المدققة من قبل المحامي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>{{ $row->governorate }}</td>
                                    <td>{{ $row->neighborhood }}</td>
                                    <td class="fw-bold">{{ $row->hlp_buildings }}</td>
                                    <td class="fw-bold">{{ $row->hlp_housings }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        لا توجد بيانات ضمن الفلاتر المحددة.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-top-2">
                            <tr class="fw-bold bg-light">
                                <td colspan="2" class="text-center">Grand Totals</td>
                                <td class="text-primary">{{ $summary['hlp_buildings'] }}</td>
                                <td class="text-success">{{ $summary['hlp_housings'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(document).ready(function () {
            $('.hlp-report-select').select2({
                allowClear: true,
                width: '100%'
            });

            $('#kt_daterangepicker').daterangepicker({
                startDate: moment(@json($start_date)),
                endDate: moment(@json($end_date)),
                locale: {
                    format: 'MM/DD/YYYY'
                },
                ranges: {
                    'آخر 30 يوم': [moment().subtract(29, 'days'), moment()],
                    'هذا الشهر': [moment().startOf('month'), moment().endOf('month')]
                }
            }, function (start, end) {
                $('#kt_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            $('#hlp_audit_table').DataTable({
                pageLength: 25,
                order: [[2, 'desc']],
                orderCellsTop: true,
                columnDefs: [
                    {
                        targets: '_all',
                        className: 'text-center align-middle'
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json'
                }
            });
        });
    </script>
@endsection
