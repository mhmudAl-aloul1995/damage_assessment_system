@extends('layouts.app')

@section('title', __($title_key))
@section('pageName', __($title_key))

@section('content')
    @php
        $showHousingUnitsCount = false;
        $showLocationPies = in_array($type, [
            \App\Services\DamageAssessment\Reports\AreaProductivityReportService::TYPE_HOUSING_UNITS,
            \App\Services\DamageAssessment\Reports\AreaProductivityReportService::TYPE_PUBLIC_BUILDINGS,
            \App\Services\DamageAssessment\Reports\AreaProductivityReportService::TYPE_ROAD_FACILITIES,
        ], true);
        $locationPieCountLabel = match ($type) {
            \App\Services\DamageAssessment\Reports\AreaProductivityReportService::TYPE_PUBLIC_BUILDINGS => 'public buildings',
            \App\Services\DamageAssessment\Reports\AreaProductivityReportService::TYPE_ROAD_FACILITIES => 'road facilities',
            default => 'housing units',
        };
        $locationPieCharts = [];

        if ($showLocationPies) {
            foreach ($charts['location_pies'] as $municipalityNode) {
                $locationPieCharts[] = $municipalityNode['pie'];

                foreach ($municipalityNode['neighborhoods'] as $neighborhoodPie) {
                    $locationPieCharts[] = $neighborhoodPie;
                }
            }
        }
    @endphp

    <style>
        #area_productivity_table th,
        #area_productivity_table td {
            text-align: center !important;
            vertical-align: middle !important;
        }

        #area_productivity_table tfoot td {
            text-align: center !important;
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
            box-shadow: 0 .45rem 1.4rem rgba(15, 23, 42, .05);
        }

        .location-pie-section-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border: 0;
            border-bottom: 1px solid #edf0f5;
            background: #f9fbff;
            text-align: start;
            cursor: pointer;
        }

        .location-pie-section-toggle:hover {
            background: #f1f7ff;
        }

        .location-collapse-cue {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            color: #3699ff;
            font-size: .75rem;
            font-weight: 800;
        }

        .location-collapse-cue .when-open {
            display: none;
        }

        .location-pie-section-toggle[aria-expanded="true"] .when-open {
            display: inline;
        }

        .location-pie-section-toggle[aria-expanded="true"] .when-closed {
            display: none;
        }

        .location-collapse-icon {
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border: 1px solid #bcdcff;
            border-radius: 50%;
            background: #fff;
            color: #3699ff;
            box-shadow: 0 .25rem .75rem rgba(54, 153, 255, .18);
            transition: transform .2s ease, background-color .2s ease;
        }

        .location-collapse-icon::before {
            width: .55rem;
            height: .55rem;
            border-right: 2px solid currentColor;
            border-bottom: 2px solid currentColor;
            transform: rotate(45deg) translate(-1px, -1px);
            content: "";
        }

        .location-pie-section-toggle[aria-expanded="true"] .location-collapse-icon {
            background: #3699ff;
            color: #fff;
            transform: rotate(180deg);
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

        .location-primary-body {
            display: grid;
            grid-template-columns: minmax(300px, 400px) 1fr;
            gap: 1rem;
            padding: 1rem;
            align-items: start;
        }

        .location-municipality-title {
            color: #3f4254;
            font-size: .95rem;
            font-weight: 800;
        }

        .location-neighborhood-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: .85rem;
        }

        .location-pie-card {
            min-height: 280px;
            padding: .85rem;
            border: 1px solid #edf0f5;
            border-radius: .65rem;
            background: #fff;
            text-align: center;
        }

        .location-pie-card.primary {
            border-color: #cfe2ff;
            box-shadow: 0 .45rem 1.2rem rgba(15, 23, 42, .06);
        }

        .location-pie-card.neighborhood {
            min-height: 255px;
        }

        .location-pie-title {
            color: #181c32;
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .location-pie-card.primary .location-pie-title {
            font-size: 1.15rem;
        }

        .location-pie-meta {
            color: #7e8299;
            font-size: .76rem;
            font-weight: 700;
        }

        .location-pie-chart-wrap {
            position: relative;
            height: 185px;
            margin-top: .25rem;
        }

        .location-pie-card.primary .location-pie-chart-wrap {
            height: 210px;
        }

        .location-pie-chart {
            width: 100%;
            height: 100%;
        }

        .location-pie-inner-percent {
            position: absolute;
            z-index: 3;
            min-width: 44px;
            padding: .22rem .4rem;
            background: #fff;
            border: 1px solid #d8dde8;
            border-radius: .35rem;
            color: #181c32;
            font-size: .78rem;
            font-weight: 900;
            line-height: 1;
            box-shadow: 0 .35rem .8rem rgba(15, 23, 42, .16);
            pointer-events: none;
        }

        .location-pie-inner-percent.completed,
        .location-pie-inner-percent.totally-damaged {
            top: 46%;
            left: 58%;
            transform: translate(-50%, -50%);
        }

        .location-pie-inner-percent.not-completed,
        .location-pie-inner-percent.partially-damaged {
            top: 32%;
            left: 39%;
            transform: translate(-50%, -50%);
        }

        .location-pie-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .5rem;
            margin-top: .35rem;
        }

        .location-pie-summary-grid.two-items {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .location-pie-summary-item {
            display: flex;
            flex-direction: column;
            gap: .15rem;
            min-width: 0;
            padding: .45rem .5rem;
            border: 1px solid #edf0f5;
            border-radius: .45rem;
            background: #fcfcfd;
            text-align: start;
        }

        .location-pie-summary-label {
            color: #7e8299;
            font-size: .68rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .location-pie-summary-value {
            color: #181c32;
            font-size: .82rem;
            font-weight: 900;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .location-pie-summary-item.completed .location-pie-summary-value {
            color: #50cd89;
        }

        .location-pie-summary-item.not-completed .location-pie-summary-value,
        .location-pie-summary-item.totally-damaged .location-pie-summary-value {
            color: #f1416c;
        }

        .location-pie-summary-item.partially-damaged .location-pie-summary-value {
            color: #ffc700;
        }

        .location-pie-summary-item.neighborhoods .location-pie-summary-value {
            color: #3699ff;
        }

        @media (max-width: 575px) {
            .location-primary-body,
            .location-pie-summary-grid,
            .location-pie-summary-grid.two-items {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-6" style="direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }};">
                    <div class="card-title">
                        <h2 style="color: green;">
                            {{ __($title_key) }}: {{ $start_date }}
                            <span class="text-gray-400">{{ __('multilingual.area_productivity_reports.labels.to') }}</span>
                            {{ $end_date }}
                        </h2>
                    </div>

                    <div class="card-toolbar">
                        <form action="{{ route($route_name) }}" method="GET" id="filter_form" class="w-100">
                            <input type="hidden" name="start_date" id="start_date" value="{{ $start_date }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ $end_date }}">

                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <a href="{{ route($export_route_name, array_merge(request()->query(), ['start_date' => $start_date, 'end_date' => $end_date])) }}"
                                    class="btn btn-success">
                                    <i class="fa fa-file-excel"></i>
                                    {{ __('multilingual.area_productivity_reports.actions.export_excel') }}
                                </a>

                                <button type="submit" class="btn btn-primary">
                                    {{ __('multilingual.area_productivity_reports.actions.filter') }}
                                </button>

                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" value="{{ $date_range_label }}"
                                        placeholder="{{ __('multilingual.area_productivity_reports.filters.date_range') }}"
                                        id="kt_daterangepicker" readonly />
                                    <span class="input-group-text"><i class="ki-duotone ki-calendar fs-2"></i></span>
                                </div>

                                <button class="btn btn-light" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#area-productivity-advanced-filters" aria-expanded="false">
                                    {{ __('multilingual.area_productivity_reports.actions.advanced_filters') }}
                                </button>
                            </div>

                            <div class="collapse mt-5" id="area-productivity-advanced-filters">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <select name="governorate" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.governorate') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_governorates') }}</option>
                                            @foreach ($filter_options['governorates'] as $governorate)
                                                <option value="{{ $governorate }}" @selected($filters['governorate'] === $governorate)>{{ $governorate }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="municipalitie" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.municipality') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_municipalities') }}</option>
                                            @foreach ($filter_options['municipalities'] as $municipality)
                                                <option value="{{ $municipality }}" @selected($filters['municipalitie'] === $municipality)>{{ $municipality }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="neighborhood" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.neighborhood') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_neighborhoods') }}</option>
                                            @foreach ($filter_options['neighborhoods'] as $neighborhood)
                                                <option value="{{ $neighborhood }}" @selected($filters['neighborhood'] === $neighborhood)>{{ $neighborhood }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="zone_code" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.zone_code') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_zone_codes') }}</option>
                                            @foreach ($filter_options['zone_codes'] as $zoneCode)
                                                <option value="{{ $zoneCode }}" @selected($filters['zone_code'] === $zoneCode)>{{ $zoneCode }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="assignedto" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.assignedto') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_assignedto') }}</option>
                                            @foreach ($filter_options['assignedto'] as $assignedto)
                                                <option value="{{ $assignedto }}" @selected($filters['assignedto'] === $assignedto)>{{ $assignedto }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex gap-3">
                                        <button type="submit" class="btn btn-primary flex-fill">
                                            {{ __('multilingual.area_productivity_reports.actions.apply_filters') }}
                                        </button>
                                        <a href="{{ route($route_name) }}" class="btn btn-light flex-fill">
                                            {{ __('multilingual.area_productivity_reports.actions.reset') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($showLocationPies)
                    <div class="card-body pb-0 border-top">
                        <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bold" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="area-productivity-table-tab" type="button"
                                    data-bs-toggle="tab" data-bs-target="#area-productivity-table-pane" role="tab"
                                    aria-controls="area-productivity-table-pane" aria-selected="true">
                                    Report Table
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="area-productivity-location-charts-tab" type="button"
                                    data-bs-toggle="tab" data-bs-target="#area-productivity-location-charts-pane"
                                    role="tab" aria-controls="area-productivity-location-charts-pane" aria-selected="false">
                                    Location Pie Charts
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content">
                @endif

                @if ($showLocationPies)
                    <div class="tab-pane fade" id="area-productivity-location-charts-pane" role="tabpanel"
                        aria-labelledby="area-productivity-location-charts-tab">
                        <div class="card-body p-0">
                            <div class="px-8 pt-6">
                                <h3 class="fw-bold mb-1">Location Pie Charts</h3>
                                <div class="text-muted fs-7">Municipality and neighborhood charts for totally and partially damaged {{ $locationPieCountLabel }}.</div>
                            </div>

                            @if (count($charts['location_pies']))
                                <div class="location-pie-tree mt-5">
                                    @foreach ($charts['location_pies'] as $municipalityNode)
                                        @php
                                            $municipalityPie = $municipalityNode['pie'];
                                            $showNeighborhoodPies = count($municipalityNode['neighborhoods']) > 0;
                                        @endphp
                                        <div class="location-pie-section">
                                            <button class="location-pie-section-toggle" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#collapse_{{ $municipalityPie['id'] }}"
                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                                aria-controls="collapse_{{ $municipalityPie['id'] }}">
                                                <span>
                                                    <span class="location-pie-section-title d-block">{{ $municipalityPie['title'] }}</span>
                                                    <span class="location-pie-section-meta">
                                                        Municipality | {{ number_format($municipalityPie['items_count']) }} {{ $locationPieCountLabel }}
                                                        @if ($showNeighborhoodPies)
                                                            | {{ count($municipalityNode['neighborhoods']) }} neighborhoods
                                                        @endif
                                                    </span>
                                                    <span class="location-collapse-cue d-block mt-1">
                                                        <span class="when-closed">Click to expand</span>
                                                        <span class="when-open">Click to collapse</span>
                                                    </span>
                                                </span>
                                                <span class="location-collapse-icon" aria-hidden="true"></span>
                                            </button>

                                            <div id="collapse_{{ $municipalityPie['id'] }}"
                                                class="collapse location-pie-collapse {{ $loop->first ? 'show' : '' }}">
                                                <div class="location-primary-body">
                                                    @include('modules.damage-assessment.reports.partials.location_productivity_neighborhood', [
                                                        'pie' => $municipalityPie,
                                                        'variant' => 'primary',
                                                        'neighborhoodsCount' => $showNeighborhoodPies ? count($municipalityNode['neighborhoods']) : null,
                                                        'countLabel' => $locationPieCountLabel,
                                                        'firstMetricLabel' => 'Totally Damaged',
                                                        'secondMetricLabel' => 'Partially Damaged',
                                                        'firstMetricClass' => 'totally-damaged',
                                                        'secondMetricClass' => 'partially-damaged',
                                                    ])
                                                </div>

                                                @if ($showNeighborhoodPies)
                                                    <div class="p-4 pt-0">
                                                        <div class="location-municipality-title mb-3">
                                                            Neighborhoods under {{ $municipalityPie['title'] }}
                                                        </div>
                                                        <div class="location-neighborhood-grid">
                                                            @foreach ($municipalityNode['neighborhoods'] as $neighborhoodPie)
                                                                @include('modules.damage-assessment.reports.partials.location_productivity_neighborhood', [
                                                                    'pie' => $neighborhoodPie,
                                                                    'countLabel' => $locationPieCountLabel,
                                                                    'firstMetricLabel' => 'Totally Damaged',
                                                                    'secondMetricLabel' => 'Partially Damaged',
                                                                    'firstMetricClass' => 'totally-damaged',
                                                                    'secondMetricClass' => 'partially-damaged',
                                                                ])
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-10 text-center text-muted">No matching damaged {{ $locationPieCountLabel }}.</div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($showLocationPies)
                    <div class="tab-pane fade show active" id="area-productivity-table-pane" role="tabpanel"
                        aria-labelledby="area-productivity-table-tab">
                @endif
                    <div class="card-body py-4">
                        <table class="table table-rounded table-striped table-row-bordered gy-7 text-center align-middle" id="area_productivity_table">
                            <thead>
                                <tr class="fw-bolder fs-6 text-gray-800 text-uppercase">
                                    <th>{{ __('multilingual.area_productivity_reports.columns.total_count') }}</th>
                                    @if ($showHousingUnitsCount)
                                        <th>{{ __('multilingual.area_productivity_reports.columns.housing_units_count') }}</th>
                                    @endif
                                    <th>{{ __('multilingual.area_productivity_reports.columns.cra') }}</th>
                                    <th>{{ __('multilingual.area_productivity_reports.columns.pda') }}</th>
                                    <th>{{ __('multilingual.area_productivity_reports.columns.tda') }}</th>
                                    <th>{{ __('multilingual.area_productivity_reports.columns.engineers') }}</th>
                                    <th>{{ __('multilingual.area_productivity_reports.columns.neighborhood') }}</th>
                                    <th>{{ __('multilingual.area_productivity_reports.columns.municipality') }}</th>
                                    <th>{{ __('multilingual.area_productivity_reports.columns.governorate') }}</th>
                                    <th>{{ __('multilingual.area_productivity_reports.columns.sector') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td class="fw-bold">{{ $row->total_count }}</td>
                                        @if ($showHousingUnitsCount)
                                            <td>{{ $row->housing_units_count ?? 0 }}</td>
                                        @endif
                                        <td>{{ $row->cra_range }}</td>
                                        <td>{{ $row->pda_range }}</td>
                                        <td>{{ $row->tda_range }}</td>
                                        <td>{{ $row->no_eng }}</td>
                                        <td>{{ $row->neighborhood ?: __('multilingual.area_productivity_reports.labels.not_available') }}</td>
                                        <td>{{ $row->municipalitie ?: __('multilingual.area_productivity_reports.labels.not_available') }}</td>
                                        <td>{{ $row->governorate ?: __('multilingual.area_productivity_reports.labels.not_available') }}</td>
                                        <td>{{ __($sector_key) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $showHousingUnitsCount ? 10 : 9 }}" class="text-center text-muted">
                                            {{ __('multilingual.area_productivity_reports.labels.empty') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="border-top-2">
                                <tr class="fw-bold bg-light">
                                    <td class="text-success fs-5">{{ $summary['total_records'] }}</td>
                                    @if ($showHousingUnitsCount)
                                        <td>{{ $summary['housing_units_count'] }}</td>
                                    @endif
                                    <td class="text-primary">{{ $summary['cra'] }}</td>
                                    <td class="text-warning">{{ $summary['pda'] }}</td>
                                    <td class="text-danger">{{ $summary['tda'] }}</td>
                                    <td>{{ $summary['engineers'] }}</td>
                                    <td colspan="4" class="text-center">{{ __('multilingual.area_productivity_reports.labels.grand_totals') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @if ($showLocationPies)
                    </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(document).ready(function () {
            $('.area-report-select').select2({
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
                    @if (app()->getLocale() === 'ar')
                        'آخر 30 يوم': [moment().subtract(29, 'days'), moment()],
                        'هذا الشهر': [moment().startOf('month'), moment().endOf('month')]
                    @else
                        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                        'This Month': [moment().startOf('month'), moment().endOf('month')]
                    @endif
                }
            }, function (start, end) {
                $('#kt_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            $('#area_productivity_table').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [
                    {
                        targets: '_all',
                        className: 'text-center align-middle'
                    }
                ],
                language: {
                    url: @json(app()->getLocale() === 'ar' ? '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json' : '//cdn.datatables.net/plug-ins/1.13.4/i18n/en-GB.json')
                }
            });

                @if ($showLocationPies)
                const locationPieCharts = @json($locationPieCharts);
                const locationPieCountLabel = @json($locationPieCountLabel);
                const renderedLocationPieCharts = new Set();

                function renderLocationPieChart(pie) {
                    if (renderedLocationPieCharts.has(pie.id)) {
                        return;
                    }

                    const element = document.getElementById(pie.id);

                    if (!element || element.offsetParent === null) {
                        return;
                    }

                    renderedLocationPieCharts.add(pie.id);

                    new ApexCharts(element, {
                        series: pie.series,
                        chart: {
                            type: 'donut',
                            height: pie.level === 'municipality' ? 210 : 185,
                            toolbar: { show: false },
                            animations: { enabled: true }
                        },
                        labels: pie.labels,
                        colors: ['#F1416C', '#FFC700'],
                        legend: { show: false },
                        stroke: {
                            width: 3,
                            colors: ['#ffffff']
                        },
                        dataLabels: { enabled: false },
                        tooltip: {
                            y: {
                                formatter: function (value) {
                                    return value + ' ' + locationPieCountLabel;
                                }
                            }
                        },
                        plotOptions: {
                            pie: {
                                expandOnClick: false,
                                donut: { size: '62%' }
                            }
                        }
                    }).render();
                }

                function renderVisibleLocationPieCharts() {
                    locationPieCharts.forEach(renderLocationPieChart);
                }

                document.querySelectorAll('.location-pie-collapse').forEach(function (collapseElement) {
                    collapseElement.addEventListener('shown.bs.collapse', renderVisibleLocationPieCharts);
                });

                document.getElementById('area-productivity-location-charts-tab')
                    ?.addEventListener('shown.bs.tab', renderVisibleLocationPieCharts);
            @endif
        });
    </script>
@endsection
