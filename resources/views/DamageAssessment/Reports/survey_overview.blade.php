@extends('layouts.app')
@section('title', $reportTitle)
@section('pageName', $reportTitle)

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div>
                            <h2 style="color: green;">{{ $reportTitle }}</h2>
                            <div class="text-muted fs-7">{{ $reportSubtitle }}</div>
                            <div class="text-muted fs-7">From {{ $startDateValue }} to {{ $endDateValue }}</div>
                        </div>
                    </div>

                    <div class="card-toolbar">
                        <form action="{{ $reportRoute }}" method="GET">
                            <input type="hidden" name="start_date" id="start_date" value="{{ $startDateValue }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ $endDateValue }}">

                            <div class="d-flex align-items-center gap-3">
                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" placeholder="Select date range" id="kt_survey_report_daterangepicker" readonly />
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
                        @foreach ($summaryCards as $card)
                            <div class="col-md-3">
                                <div class="border border-gray-300 border-dashed rounded p-6 text-center h-100 {{ $card['class'] === 'primary' ? 'bg-light-primary' : '' }}">
                                    <div class="text-muted mb-2">{{ $card['label'] }}</div>
                                    <div class="fs-2hx fw-bold text-{{ $card['class'] }}">{{ $card['value'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="row g-5 mb-8">
                        <div class="col-md-6">
                            <div class="card card-flush h-md-100 border border-gray-200">
                                <div class="card-header pt-6">
                                    <div class="card-title d-flex flex-column">
                                        <span class="fs-2 fw-bold text-gray-900">{{ $primaryChart['title'] }}</span>
                                        <span class="text-muted pt-1 fw-semibold fs-6">Distribution summary</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div id="{{ $primaryChart['selector'] }}" style="height: 320px;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card card-flush h-md-100 border border-gray-200">
                                <div class="card-header pt-6">
                                    <div class="card-title d-flex flex-column">
                                        <span class="fs-2 fw-bold text-gray-900">{{ $secondaryChart['title'] }}</span>
                                        <span class="text-muted pt-1 fw-semibold fs-6">Grouped summary</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div id="{{ $secondaryChart['selector'] }}" style="height: 320px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-5 mb-8">
                        <div class="col-md-12">
                            <div class="card card-flush h-md-100 border border-gray-200">
                                <div class="card-header pt-6">
                                    <div class="card-title d-flex flex-column">
                                        <span class="fs-2 fw-bold text-gray-900">{{ $curveChart['title'] }}</span>
                                        <span class="text-muted pt-1 fw-semibold fs-6">Daily curve within the selected date range</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div id="{{ $curveChart['selector'] }}" style="height: 340px;"></div>
                                </div>
                            </div>
                        </div>
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
                                            <td class="text-center text-{{ $column['class'] }} fw-bold">{{ $row[$column['key']] }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($tableColumns) + 1 }}" class="text-center text-muted">{{ $emptyMessage }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
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
            $('#kt_survey_report_daterangepicker').daterangepicker({
                startDate: moment("{{ $startDateValue }}"),
                endDate: moment("{{ $endDateValue }}"),
                ranges: {
                    'Today': [moment(), moment()],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')]
                }
            }, function (start, end) {
                $('#kt_survey_report_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            $('#kt_survey_report_daterangepicker').val(moment("{{ $startDateValue }}").format('MM/DD/YYYY') + ' - ' + moment("{{ $endDateValue }}").format('MM/DD/YYYY'));

            new ApexCharts(document.querySelector('#{{ $primaryChart['selector'] }}'), {
                series: @json($primaryChart['series']),
                chart: { type: 'donut', height: 320, toolbar: { show: true } },
                labels: @json($primaryChart['labels']),
                colors: @json($primaryChart['colors']),
                legend: { position: 'bottom' },
                stroke: { width: 0 },
                dataLabels: { enabled: true },
                plotOptions: { pie: { donut: { size: '62%' } } }
            }).render();

            new ApexCharts(document.querySelector('#{{ $secondaryChart['selector'] }}'), {
                series: [{ name: 'Count', data: @json($secondaryChart['series']) }],
                chart: { type: 'bar', height: 320, toolbar: { show: true } },
                plotOptions: { bar: { borderRadius: 4, horizontal: false } },
                dataLabels: { enabled: false },
                colors: @json($secondaryChart['colors']),
                xaxis: { categories: @json($secondaryChart['labels']) },
                yaxis: { title: { text: 'Count' } }
            }).render();

            new ApexCharts(document.querySelector('#{{ $curveChart['selector'] }}'), {
                series: [{ name: 'Daily Count', data: @json($curveChart['series']) }],
                chart: { type: 'line', height: 340, toolbar: { show: true } },
                stroke: { curve: 'smooth', width: 4 },
                colors: ['{{ $curveChart['color'] }}'],
                dataLabels: { enabled: false },
                markers: { size: 4 },
                xaxis: { categories: @json($curveChart['labels']) },
                yaxis: { title: { text: 'Daily Count' } },
                grid: { borderColor: '#eff2f5' }
            }).render();
        });
    </script>
@endsection