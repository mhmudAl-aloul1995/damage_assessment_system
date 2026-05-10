@extends('layouts.app')

@section('title', 'Building Productivity Report')
@section('pageName', 'Building Productivity Report')

@section('content')
    <style>
        .neighborhood-pie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            border-top: 2px solid #1f1f1f;
            border-right: 2px solid #1f1f1f;
            background: #fff;
        }

        .location-pie-tree {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            padding: 1.25rem;
            background: #f8fafc;
        }

        .location-pie-section {
            border: 1px solid #d8dde8;
            border-radius: .75rem;
            background: #fff;
            overflow: hidden;
        }

        .location-pie-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #edf0f5;
            background: #f9fbff;
        }

        .location-pie-section-title {
            margin: 0;
            color: #181c32;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .location-pie-section-meta {
            color: #7e8299;
            font-size: .8rem;
            font-weight: 700;
        }

        .location-pie-level {
            padding: 1rem;
        }

        .location-pie-level.municipality {
            padding-inline-start: 2rem;
            background: #fcfcfd;
        }

        .location-pie-level.neighborhood {
            padding-inline-start: 3rem;
            background: #fff;
        }

        .location-pie-level-title {
            margin: .25rem 0 .75rem;
            color: #3f4254;
            font-size: .95rem;
            font-weight: 800;
        }

        .neighborhood-pie-card {
            min-height: 335px;
            padding: 12px 12px 14px;
            text-align: center;
            border-left: 2px solid #1f1f1f;
            border-bottom: 2px solid #1f1f1f;
            background: #fff;
        }

        .neighborhood-pie-title {
            color: #3f4254;
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: .15rem;
        }

        .neighborhood-pie-subtitle {
            min-height: 18px;
            color: #7e8299;
            font-size: .78rem;
            font-weight: 700;
        }

        .neighborhood-pie-chart {
            height: 235px;
            margin-top: .25rem;
        }

        .neighborhood-pie-chart-wrap {
            position: relative;
            height: 235px;
            margin-top: .25rem;
        }

        .neighborhood-pie-chart-wrap .neighborhood-pie-chart {
            height: 235px;
            margin-top: 0;
        }

        .neighborhood-pie-inner-percent {
            position: absolute;
            z-index: 3;
            min-width: 48px;
            padding: .25rem .45rem;
            background: #fff;
            border: 1px solid #d8dde8;
            border-radius: .35rem;
            color: #181c32;
            font-weight: 900;
            line-height: 1;
            box-shadow: 0 .35rem .8rem rgba(15, 23, 42, .16);
            pointer-events: none;
        }

        .neighborhood-pie-inner-percent.completed {
            top: 46%;
            left: 58%;
            transform: translate(-50%, -50%);
        }

        .neighborhood-pie-inner-percent.not-completed {
            top: 32%;
            left: 39%;
            transform: translate(-50%, -50%);
        }

        .neighborhood-pie-percent-row {
            display: flex;
            justify-content: center;
            gap: .65rem;
            flex-wrap: wrap;
            margin-top: -.35rem;
            margin-bottom: .5rem;
        }

        .neighborhood-pie-percent {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            min-width: 76px;
            justify-content: center;
            padding: .35rem .6rem;
            border-radius: .45rem;
            color: #fff;
            font-weight: 800;
            box-shadow: 0 .35rem .8rem rgba(15, 23, 42, .18);
        }

        .neighborhood-pie-percent.completed {
            background: #6ead47;
        }

        .neighborhood-pie-percent.not-completed {
            background: #e96c73;
        }

        .neighborhood-pie-legend {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            padding: .45rem .9rem;
            background: #fafafa;
            color: #3f4254;
            font-size: .92rem;
            line-height: 1.2;
        }

        .neighborhood-pie-legend-item {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            white-space: nowrap;
        }

        .neighborhood-pie-dot {
            width: 10px;
            height: 10px;
            display: inline-block;
        }

        .neighborhood-pie-dot.completed {
            background: #8cc36b;
        }

        .neighborhood-pie-dot.not-completed {
            background: #ff8f95;
        }

        @media (max-width: 575px) {
            .neighborhood-pie-grid {
                grid-template-columns: 1fr;
            }

            .neighborhood-pie-card {
                min-height: 310px;
            }
        }
    </style>

    @php
        $formatPercent = fn (float $value): string => number_format($value * 100, 2) . '%';
        $dateRangeLabel = $filters['from_date'] && $filters['to_date']
            ? $filters['from_date'] . ' to ' . $filters['to_date']
            : '';
    @endphp

    <div class="card card-flush shadow-sm mb-6">
        <div class="card-header pt-6">
            <div class="card-title">
                <div>
                    <h2 class="fw-bold mb-1">Building Productivity Report</h2>
                    <div class="text-muted fs-7">
                        Date field: <span class="fw-bold">{{ $dateField }}</span>
                        | Completed statuses: <span class="fw-bold">{{ implode(', ', $completedStatuses) }}</span>
                    </div>
                </div>
            </div>

            <div class="card-toolbar">
                <a href="{{ $exportRoute }}" class="btn btn-light-success">
                    <i class="ki-duotone ki-file-down fs-3"><span class="path1"></span><span class="path2"></span></i>
                    Export Excel With Chart Data
                </a>
            </div>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ $reportRoute }}" id="buildingProductivityFilterForm">
                <input type="hidden" name="from_date" id="from_date" value="{{ $filters['from_date'] }}">
                <input type="hidden" name="to_date" id="to_date" value="{{ $filters['to_date'] }}">

                <div class="row g-4 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Date Range</label>
                        <input type="text" id="date_range" class="form-control form-control-solid"
                            placeholder="Select date range" value="{{ $dateRangeLabel }}" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Gov / Locale</label>
                        <select name="gov" class="form-select form-select-solid report-select"
                            data-placeholder="All Gov">
                            <option value="">All Gov</option>
                            @foreach ($filterOptions['governorates'] as $gov)
                                <option value="{{ $gov }}" @selected($filters['gov'] === $gov)>{{ $gov }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Neighborhood</label>
                        <select name="neighborhood" class="form-select form-select-solid report-select"
                            data-placeholder="All Neighborhoods">
                            <option value="">All Neighborhoods</option>
                            @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                                <option value="{{ $neighborhood }}" @selected($filters['neighborhood'] === $neighborhood)>{{ $neighborhood }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">Apply Filter</button>
                        <a href="{{ $reportRoute }}" class="btn btn-light flex-fill">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-5 mb-6">
        <div class="col-md-3">
            <div class="border border-gray-300 border-dashed rounded p-6 bg-light-primary h-100">
                <div class="text-muted fw-semibold mb-2">Buildings Count</div>
                <div class="fs-2hx fw-bold text-primary">{{ number_format($summary['buildings_count']) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border border-gray-300 border-dashed rounded p-6 bg-light-success h-100">
                <div class="text-muted fw-semibold mb-2">Completed</div>
                <div class="fs-2hx fw-bold text-success">{{ number_format($summary['completed']) }}</div>
                <div class="text-muted">{{ $formatPercent($summary['completed_percent']) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border border-gray-300 border-dashed rounded p-6 bg-light-danger h-100">
                <div class="text-muted fw-semibold mb-2">Not Completed</div>
                <div class="fs-2hx fw-bold text-danger">{{ number_format($summary['not_completed']) }}</div>
                <div class="text-muted">{{ $formatPercent($summary['not_completed_percent']) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border border-gray-300 border-dashed rounded p-6 bg-light-info h-100">
                <div class="text-muted fw-semibold mb-2">Gov / Neighborhoods</div>
                <div class="fs-2hx fw-bold text-info">{{ number_format($summary['areas_count']) }}</div>
                <div class="text-muted">{{ number_format($summary['neighborhoods_count']) }} neighborhoods</div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-6">
        <div class="col-lg-4">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">Completion Distribution</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div id="building_productivity_completion_chart" style="height: 320px;"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">Completed vs Not Completed by Gov</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div id="building_productivity_gov_chart" style="height: 320px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm mb-6">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold mb-0">Top Neighborhoods Completed %</h3>
            </div>
        </div>
        <div class="card-body">
            <div id="building_productivity_neighborhood_chart" style="height: 340px;"></div>
        </div>
    </div>

    <div class="card card-flush shadow-sm mb-6">
        <div class="card-header pt-6">
            <div class="card-title">
                <div>
                    <h3 class="fw-bold mb-0">Every Neighborhood Productivity</h3>
                    <div class="text-muted fs-7">Same neighborhood rows as the Excel report, grouped by Gov / Name.</div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div id="building_productivity_all_neighborhoods_chart"
                style="height: {{ $charts['all_neighborhoods']['height'] }}px;"></div>
        </div>
    </div>

    <div class="card card-flush shadow-sm mb-6">
        <div class="card-header pt-6">
            <div class="card-title">
                <div>
                    <h3 class="fw-bold mb-0">Location Pie Charts</h3>
                    <div class="text-muted fs-7">Governorate, municipality, and neighborhood charts.</div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if (count($charts['location_pies']))
                <div class="location-pie-tree">
                    @foreach ($charts['location_pies'] as $governorateNode)
                        @php($governoratePie = $governorateNode['pie'])
                        <div class="location-pie-section">
                            <div class="location-pie-section-header">
                                <h4 class="location-pie-section-title">{{ $governoratePie['title'] }}</h4>
                                <span class="location-pie-section-meta">
                                    Governorate | {{ number_format($governoratePie['buildings_count']) }} buildings
                                </span>
                            </div>

                            <div class="location-pie-level">
                                <div class="neighborhood-pie-grid">
                                    @include('DamageAssessment.Reports.partials.location_productivity_pie', ['pie' => $governoratePie])
                                </div>
                            </div>

                            @foreach ($governorateNode['municipalities'] as $municipalityNode)
                                @php($municipalityPie = $municipalityNode['pie'])
                                <div class="location-pie-level municipality">
                                    <div class="location-pie-level-title">Municipality: {{ $municipalityPie['title'] }}</div>
                                    <div class="neighborhood-pie-grid">
                                        @include('DamageAssessment.Reports.partials.location_productivity_pie', ['pie' => $municipalityPie])
                                    </div>
                                </div>

                                <div class="location-pie-level neighborhood">
                                    <div class="location-pie-level-title">Neighborhoods under {{ $municipalityPie['title'] }}</div>
                                    <div class="neighborhood-pie-grid">
                                        @foreach ($municipalityNode['neighborhoods'] as $neighborhoodPie)
                                            @include('DamageAssessment.Reports.partials.location_productivity_pie', ['pie' => $neighborhoodPie])
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-10 text-center text-muted">No matching data.</div>
            @endif
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold mb-0">Report Table</h3>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-rounded table-row-bordered table-striped text-center align-middle gy-4" id="building_productivity_table">
                    <thead>
                        <tr class="fw-bold fs-6 text-gray-800 text-uppercase bg-light">
                            <th class="text-center">Gov</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Completed</th>
                            <th class="text-center">Not Completed</th>
                            <th class="text-center">Buildings Count</th>
                            <th class="text-center">Completed %</th>
                            <th class="text-center">Not Completed %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr @class([
                                'fw-bold bg-light-success' => $row['row_type'] === 'gov_total',
                            ])>
                                <td>{{ $row['gov'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-center text-success fw-bold">{{ number_format($row['completed']) }}</td>
                                <td class="text-center text-danger fw-bold">{{ number_format($row['not_completed']) }}</td>
                                <td class="text-center fw-bold">{{ number_format($row['buildings_count']) }}</td>
                                <td class="text-center">{{ $formatPercent($row['completed_percent']) }}</td>
                                <td class="text-center">{{ $formatPercent($row['not_completed_percent']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center text-muted">No matching data.</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold bg-light-primary fs-6">
                            <td class="text-center">{{ $grandTotal['gov'] }}</td>
                            <td class="text-center">{{ $grandTotal['name'] }}</td>
                            <td class="text-center text-success">{{ number_format($grandTotal['completed']) }}</td>
                            <td class="text-center text-danger">{{ number_format($grandTotal['not_completed']) }}</td>
                            <td class="text-center">{{ number_format($grandTotal['buildings_count']) }}</td>
                            <td class="text-center">{{ $formatPercent($grandTotal['completed_percent']) }}</td>
                            <td class="text-center">{{ $formatPercent($grandTotal['not_completed_percent']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(function () {
            $('.report-select').select2({
                width: '100%',
                allowClear: true
            });

            const fromDate = $('#from_date');
            const toDate = $('#to_date');

            flatpickr('#date_range', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                defaultDate: [
                    @if ($filters['from_date'])
                        @json($filters['from_date'])
                    @else
                        null
                    @endif,
                    @if ($filters['to_date'])
                        @json($filters['to_date'])
                    @else
                        null
                    @endif
                ].filter(Boolean),
                onChange: function (selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        fromDate.val(instance.formatDate(selectedDates[0], 'Y-m-d'));
                        toDate.val(instance.formatDate(selectedDates[1], 'Y-m-d'));
                    }
                },
                onClose: function (selectedDates, dateStr, instance) {
                    if (selectedDates.length === 0) {
                        fromDate.val('');
                        toDate.val('');
                    }
                }
            });

            $('#building_productivity_table').DataTable({
                pageLength: 25,
                order: [],
                language: {
                    url: @json(app()->getLocale() === 'ar' ? '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json' : '//cdn.datatables.net/plug-ins/1.13.4/i18n/en-GB.json')
                }
            });

            new ApexCharts(document.querySelector('#building_productivity_completion_chart'), {
                series: @json($charts['completion_donut']['series']),
                chart: { type: 'donut', height: 320, toolbar: { show: true } },
                labels: @json($charts['completion_donut']['labels']),
                colors: @json($charts['completion_donut']['colors']),
                legend: { position: 'bottom' },
                stroke: { width: 0 },
                plotOptions: { pie: { donut: { size: '62%' } } }
            }).render();

            new ApexCharts(document.querySelector('#building_productivity_gov_chart'), {
                series: [
                    { name: 'Completed', data: @json($charts['gov_bar']['completed']) },
                    { name: 'Not Completed', data: @json($charts['gov_bar']['not_completed']) }
                ],
                chart: { type: 'bar', height: 320, stacked: true, toolbar: { show: true } },
                colors: ['#50CD89', '#F1416C'],
                plotOptions: { bar: { borderRadius: 4, horizontal: false } },
                dataLabels: { enabled: false },
                xaxis: { categories: @json($charts['gov_bar']['labels']) },
                yaxis: { title: { text: 'Buildings Count' } },
                legend: { position: 'top' },
                grid: { borderColor: '#eff2f5' }
            }).render();

            new ApexCharts(document.querySelector('#building_productivity_neighborhood_chart'), {
                series: [{ name: 'Completed %', data: @json($charts['neighborhood_percent']['series']) }],
                chart: { type: 'bar', height: 340, toolbar: { show: true } },
                colors: ['#009EF7'],
                plotOptions: { bar: { borderRadius: 4, horizontal: true } },
                dataLabels: {
                    enabled: true,
                    formatter: function (value) {
                        return value + '%';
                    }
                },
                xaxis: {
                    categories: @json($charts['neighborhood_percent']['labels']),
                    max: 100,
                    labels: {
                        formatter: function (value) {
                            return value + '%';
                        }
                    }
                },
                grid: { borderColor: '#eff2f5' }
            }).render();

            new ApexCharts(document.querySelector('#building_productivity_all_neighborhoods_chart'), {
                series: [
                    { name: 'Completed', data: @json($charts['all_neighborhoods']['completed']) },
                    { name: 'Not Completed', data: @json($charts['all_neighborhoods']['not_completed']) }
                ],
                chart: {
                    type: 'bar',
                    height: @json($charts['all_neighborhoods']['height']),
                    stacked: true,
                    toolbar: { show: true }
                },
                colors: ['#50CD89', '#F1416C'],
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        dataLabels: {
                            total: {
                                enabled: true,
                                style: {
                                    fontSize: '12px',
                                    fontWeight: 700
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: @json($charts['all_neighborhoods']['labels']),
                    title: { text: 'Buildings Count' }
                },
                yaxis: {
                    labels: {
                        style: { fontSize: '12px' }
                    }
                },
                legend: { position: 'top' },
                grid: { borderColor: '#eff2f5' }
            }).render();

            @foreach ($charts['location_pies'] as $governorateNode)
                @include('DamageAssessment.Reports.partials.location_productivity_pie_script', ['pie' => $governorateNode['pie']])

                @foreach ($governorateNode['municipalities'] as $municipalityNode)
                    @include('DamageAssessment.Reports.partials.location_productivity_pie_script', ['pie' => $municipalityNode['pie']])

                    @foreach ($municipalityNode['neighborhoods'] as $neighborhoodPie)
                        @include('DamageAssessment.Reports.partials.location_productivity_pie_script', ['pie' => $neighborhoodPie])
                    @endforeach
                @endforeach
            @endforeach
        });
    </script>
@endsection
