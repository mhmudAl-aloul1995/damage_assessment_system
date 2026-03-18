@extends('layouts.app')
@section('title', 'الوحدات السكنية')
@section('pageName', 'الوحدات السكنية')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div style=" direction: rtl;; " class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h2 style="color: green;">Areas Productivity Report: {{ $startDate }} <span
                                class="text-gray-400">to</span>
                            {{ $endDate }}</h2>
                    </div>

                    <!-- Date Filter Form -->
                    <div class="card-toolbar">
                        <form action="{{ route('reports.commulative') }}" method="GET" id="filter_form">
                            <!-- Hidden inputs to store the values for the backend -->
                            <input type="hidden" name="start_date" id="start_date" value="{{ $startDate }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ $endDate }}">

                            <div class="d-flex align-items-center gap-3">
                                <!-- The jQuery Trigger Input -->
                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" placeholder="اختر نطاق التاريخ"
                                        id="kt_daterangepicker" readonly />
                                    <span class="input-group-text"><i class="ki-duotone ki-calendar fs-2"></i></span>
                                </div>

                                <button onclick="$(this).addClass('data-kt-indicator'); setTimeout(() => {$(this).removeClass('disabled');}, 2000);" type="submit"
                                    class="btn data-kt-indicator  btn-primary">فلتر</button>
                                <a onclick="$(this).addClass('data-kt-indicator'); setTimeout(() => {$(this).removeClass('disabled');}, 2000);" href="{{ route('reports.commulative.export', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                                    class="btn  btn-success">
                                    <i class="fa fa-file-excel"></i> تصدير ل إكسل
                                </a>

                            </div>
                        </form>
                    </div>

                </div>

                <div class="card-body py-4">
                    <table class="table table-rounded table-striped table-row-bordered gy-7" id="commulative_report">
                        <thead>
                            <tr class="fw-bolder fs-6 text-gray-800 text-uppercase">
                                <th>Sector</th>
                                <th>Governorate</th>
                                <th>Municipality</th>
                                <th>Area/Neighborhood</th>
                                <th class="text-center">Engineers</th>
                                <th class="text-center">TDA </th>
                                <th class="text-center">PDA</th>
                                <th class="text-center">CRA</th>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>

                            @php
                                $grandTotalEng = 0;
                                $grandTotalTDA = 0;
                                $grandTotalPDA = 0;
                                $grandTotalCRA = 0; // Added this missing initialization
                                $grandTotalAll = 0;
                            @endphp

                            @forelse ($commualtive as $val)
                                @php
                                    $rowTotal = ($val->tda_range ?? 0) + ($val->pda_range ?? 0) + ($val->cra_range ?? 0);
                                    $grandTotalEng += ($val->no_eng ?? 0);
                                    $grandTotalTDA += ($val->tda_range ?? 0);
                                    $grandTotalPDA += ($val->pda_range ?? 0);
                                    $grandTotalCRA += ($val->cra_range ?? 0); // Now it works
                                    $grandTotalAll += $rowTotal;
                                @endphp
                                <tr>
                                    <td>Housing</td>
                                    <td>{{ $val->governorate }}</td>
                                    <td>{{ $val->municipalitie }}</td>
                                    <td>{{ $val->neighborhood }}</td>
                                    <td class="text-center">{{ $val->no_eng }}</td>
                                    <td class="text-center">{{ $val->tda_range }}</td>
                                    <td class="text-center">{{ $val->pda_range }}</td>
                                    <td class="text-center">{{ $val->cra_range }}</td>
                                    <td class="text-center"><strong>{{ $rowTotal }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No data found for the selected dates.</td>
                                </tr>
                            @endforelse

                        </tbody>
                        <tfoot class="border-top-2">
                            <tr class="fw-bold bg-light">
                                <td colspan="4" class="text-end">Grand Totals:</td>
                                <td class="text-center">{{ $grandTotalEng }}</td>
                                <td class="text-center text-danger">{{ $grandTotalTDA }}</td>
                                <td class="text-center text-warning">{{ $grandTotalPDA }}</td>
                                <td class="text-center text-primary">{{ $grandTotalCRA }}</td>
                                <td class="text-center text-success fs-5">{{ $grandTotalAll }}</td>
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
            // Initialize picker
            $('#kt_daterangepicker').daterangepicker({
                startDate: moment("{{ $startDate }}"),
                endDate: moment("{{ $endDate }}"),
                ranges: {
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')]
                }
            }, function (start, end) {
                // Update inputs on change
                $('#kt_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });
        });


    </script>
@endsection