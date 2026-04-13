@extends('layouts.app')
@section('title', 'Auditors Daily Achievement')
@section('pageName', 'Auditors Daily Achievement')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div>
                            <h2 style="color: green;">Daily Achievement Report For Auditing Engineers</h2>
                            <div class="text-muted fs-7">
                                From {{ $startDateValue }} to {{ $endDateValue }}
                            </div>
                        </div>
                    </div>

                    <div class="card-toolbar">
                        <form action="{{ route('reports.auditors-daily') }}" method="GET" id="auditors_daily_form">
                            <input type="hidden" name="start_date" id="start_date" value="{{ $startDateValue }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ $endDateValue }}">

                            <div class="d-flex align-items-center gap-3">
                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" placeholder="Select date range"
                                        id="kt_auditors_daterangepicker" readonly />
                                    <span class="input-group-text">
                                        <i class="ki-duotone ki-calendar fs-2"></i>
                                    </span>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body py-4">
                    <div class="row g-5 mb-8">
                        <div class="col-md-6">
                            <div class="card card-flush h-md-100 border border-gray-200">
                                <div class="card-header pt-6">
                                    <div class="card-title d-flex flex-column">
                                        <span class="fs-2 fw-bold text-gray-900">{{ $chartMetrics['buildings']['percentage'] }}%</span>
                                        <span class="text-muted pt-1 fw-semibold fs-6">Audited buildings from total buildings</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div id="audited_buildings_chart" style="height: 320px;"></div>
                                    <div class="d-flex justify-content-center gap-10 flex-wrap mt-4">
                                        <div class="text-center">
                                            <div class="fs-3 fw-bold text-primary">{{ $chartMetrics['buildings']['audited_count'] }}</div>
                                            <div class="text-muted">Audited</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="fs-3 fw-bold text-gray-700">{{ $chartMetrics['buildings']['total_count'] }}</div>
                                            <div class="text-muted">Total</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card card-flush h-md-100 border border-gray-200">
                                <div class="card-header pt-6">
                                    <div class="card-title d-flex flex-column">
                                        <span class="fs-2 fw-bold text-gray-900">{{ $chartMetrics['housing_units']['percentage'] }}%</span>
                                        <span class="text-muted pt-1 fw-semibold fs-6">Audited housing units from total housing units</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div id="audited_housing_units_chart" style="height: 320px;"></div>
                                    <div class="d-flex justify-content-center gap-10 flex-wrap mt-4">
                                        <div class="text-center">
                                            <div class="fs-3 fw-bold text-success">{{ $chartMetrics['housing_units']['audited_count'] }}</div>
                                            <div class="text-muted">Audited</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="fs-3 fw-bold text-gray-700">{{ $chartMetrics['housing_units']['total_count'] }}</div>
                                            <div class="text-muted">Total</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-5 mb-8">
                        <div class="col-md-3">
                            <div class="border border-gray-300 border-dashed rounded p-6 text-center h-100">
                                <div class="text-muted mb-2">Accepted</div>
                                <div class="fs-2hx fw-bold text-success">{{ $totals['accepted_count'] }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border border-gray-300 border-dashed rounded p-6 text-center h-100">
                                <div class="text-muted mb-2">Rejected</div>
                                <div class="fs-2hx fw-bold text-danger">{{ $totals['rejected_count'] }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border border-gray-300 border-dashed rounded p-6 text-center h-100">
                                <div class="text-muted mb-2">Need Review</div>
                                <div class="fs-2hx fw-bold text-warning">{{ $totals['need_review_count'] }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border border-gray-300 border-dashed rounded p-6 text-center h-100 bg-light-primary">
                                <div class="text-muted mb-2">Total</div>
                                <div class="fs-2hx fw-bold text-primary">{{ $totals['total_count'] }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-rounded table-striped table-row-bordered gy-7 align-middle">
                            <thead>
                                <tr class="fw-bolder fs-6 text-gray-800 text-uppercase">
                                    <th>Auditor Name</th>
                                    <th class="text-center">Accepted Units</th>
                                    <th class="text-center">Rejected Units</th>
                                    <th class="text-center">Need Review</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td class="fw-bold">{{ $row['name'] }}</td>
                                        <td class="text-center text-success fw-bold">{{ $row['accepted_count'] }}</td>
                                        <td class="text-center text-danger fw-bold">{{ $row['rejected_count'] }}</td>
                                        <td class="text-center text-warning fw-bold">{{ $row['need_review_count'] }}</td>
                                        <td class="text-center text-primary fw-bolder">{{ $row['total_count'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No auditors found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold bg-light">
                                    <td>Total</td>
                                    <td class="text-center text-success">{{ $totals['accepted_count'] }}</td>
                                    <td class="text-center text-danger">{{ $totals['rejected_count'] }}</td>
                                    <td class="text-center text-warning">{{ $totals['need_review_count'] }}</td>
                                    <td class="text-center text-primary">{{ $totals['total_count'] }}</td>
                                </tr>
                            </tfoot>
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
            $('#kt_auditors_daterangepicker').daterangepicker({
                startDate: moment("{{ $startDateValue }}"),
                endDate: moment("{{ $endDateValue }}"),
                ranges: {
                    'Today': [moment(), moment()],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')]
                }
            }, function (start, end) {
                $('#kt_auditors_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            const chartOptions = function (series, colors) {
                return {
                    series: series,
                    chart: {
                        type: 'donut',
                        height: 320,
                    },
                    labels: ['Audited', 'Remaining'],
                    colors: colors,
                    legend: {
                        position: 'bottom',
                    },
                    stroke: {
                        width: 0,
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function (value) {
                            return value.toFixed(1) + '%';
                        }
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '62%',
                            }
                        }
                    }
                };
            };

            new ApexCharts(
                document.querySelector('#audited_buildings_chart'),
                chartOptions([
                    {{ $chartMetrics['buildings']['audited_count'] }},
                    {{ $chartMetrics['buildings']['remaining_count'] }}
                ], ['#009ef7', '#e4e6ef'])
            ).render();

            new ApexCharts(
                document.querySelector('#audited_housing_units_chart'),
                chartOptions([
                    {{ $chartMetrics['housing_units']['audited_count'] }},
                    {{ $chartMetrics['housing_units']['remaining_count'] }}
                ], ['#50cd89', '#e4e6ef'])
            ).render();
        });
    </script>
@endsection
