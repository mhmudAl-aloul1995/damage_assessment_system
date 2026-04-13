@extends('layouts.app')
@section('title', 'Daily Achievement')
@section('pageName', 'Daily Achievement')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div>
                            <h2 style="color: green;">{{ $reportTitle }}</h2>
                            <div class="text-muted fs-7">
                                From {{ $startDateValue }} to {{ $endDateValue }}
                            </div>
                        </div>
                    </div>

                    <div class="card-toolbar d-flex flex-wrap gap-3">
                        <ul class="nav nav-pills gap-2">
                            <li class="nav-item">
                                <a class="nav-link {{ $activeTab === 'engineers' ? 'active' : '' }}" href="{{ route('reports.daily-achievement', ['tab' => 'engineers', 'start_date' => $startDateValue, 'end_date' => $endDateValue]) }}">Engineers</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $activeTab === 'lawyers' ? 'active' : '' }}" href="{{ route('reports.daily-achievement', ['tab' => 'lawyers', 'start_date' => $startDateValue, 'end_date' => $endDateValue]) }}">Lawyers</a>
                            </li>
                        </ul>

                        <form action="{{ $reportRoute }}" method="GET" id="daily_achievement_form">
                            <input type="hidden" name="tab" id="tab" value="{{ $activeTab }}">
                            <input type="hidden" name="start_date" id="start_date" value="{{ $startDateValue }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ $endDateValue }}">

                            <div class="d-flex align-items-center gap-3">
                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" placeholder="Select date range" id="kt_daily_achievement_daterangepicker" readonly />
                                    <span class="input-group-text">
                                        <i class="ki-duotone ki-calendar fs-2"></i>
                                    </span>
                                </div>

                                <button type="submit" class="btn btn-primary">Filter</button>
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
                                    <div id="daily_achievement_buildings_chart" style="height: 320px;"></div>
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
                                    <div id="daily_achievement_housing_units_chart" style="height: 320px;"></div>
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
                        @foreach ($summaryCards as $card)
                            <div class="col-md-3">
                                <div class="border border-gray-300 border-dashed rounded p-6 text-center h-100 {{ $card['class'] === 'primary' ? 'bg-light-primary' : '' }}">
                                    <div class="text-muted mb-2">{{ $card['label'] }}</div>
                                    <div class="fs-2hx fw-bold text-{{ $card['class'] }}">{{ $card['value'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="table-responsive">
                        <table class="table table-rounded table-striped table-row-bordered gy-7 align-middle">
                            <thead>
                                <tr class="fw-bolder fs-6 text-gray-800 text-uppercase">
                                    <th>{{ $tableTitle }}</th>
                                    @foreach ($tableColumns as $column)
                                        <th class="text-center">{{ $column['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td class="fw-bold">{{ $row['name'] }}</td>
                                        @foreach ($tableColumns as $column)
                                            <td class="text-center text-{{ $column['class'] }} fw-bold {{ $column['class'] === 'primary' ? 'fw-bolder' : '' }}">{{ $row[$column['key']] }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($tableColumns) + 1 }}" class="text-center text-muted">{{ $emptyMessage }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold bg-light">
                                    <td>Total</td>
                                    @foreach ($tableColumns as $column)
                                        <td class="text-center text-{{ $column['class'] }}">{{ $totals[$column['key']] }}</td>
                                    @endforeach
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
            $('#kt_daily_achievement_daterangepicker').daterangepicker({
                startDate: moment("{{ $startDateValue }}"),
                endDate: moment("{{ $endDateValue }}"),
                ranges: {
                    'Today': [moment(), moment()],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')]
                }
            }, function (start, end) {
                $('#kt_daily_achievement_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
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
                document.querySelector('#daily_achievement_buildings_chart'),
                chartOptions([
                    {{ $chartMetrics['buildings']['audited_count'] }},
                    {{ $chartMetrics['buildings']['remaining_count'] }}
                ], ['#009ef7', '#e4e6ef'])
            ).render();

            new ApexCharts(
                document.querySelector('#daily_achievement_housing_units_chart'),
                chartOptions([
                    {{ $chartMetrics['housing_units']['audited_count'] }},
                    {{ $chartMetrics['housing_units']['remaining_count'] }}
                ], ['#50cd89', '#e4e6ef'])
            ).render();
        });
    </script>
@endsection
