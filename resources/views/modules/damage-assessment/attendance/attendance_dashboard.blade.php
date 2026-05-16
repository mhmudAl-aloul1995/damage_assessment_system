@extends('layouts.app')

@section('title', __('multilingual.attendance_dashboard.title'))
@section('pageName', __('multilingual.attendance_dashboard.page_name'))

@section('content')
    <div class="app-content flex-column-fluid">
        <div class="app-container container-xxl">

            <div class="card mb-7">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h2 class="fw-bold">{{ __('multilingual.attendance_dashboard.title') }}</h2>
                    </div>

                    <div class="card-toolbar">
                        <form method="GET" action="{{ route('attendance.dashboard') }}"
                            class="d-flex align-items-center gap-3">
                            <input type="month" name="month_picker" id="month_picker"
                                class="form-control form-control-solid w-200px"
                                value="{{ ($year ?? now()->format('Y')) . '-' . str_pad(($month ?? now()->format('m')), 2, '0', STR_PAD_LEFT) }}">

                            <input type="hidden" name="month" id="month_value" value="{{ $month ?? now()->format('m') }}">
                            <input type="hidden" name="year" id="year_value" value="{{ $year ?? now()->format('Y') }}">

                            <button type="submit" class="btn btn-light-primary">
                                {{ __('multilingual.attendance_dashboard.filter') }}
                            </button>

                            <a href="{{ route('attendance.export-monthly-report', ['month' => $month, 'year' => $year]) }}"
                                class="btn btn-success">
                                {{ __('multilingual.attendance_dashboard.export_monthly_report') }}
                            </a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-md-6 col-xl-3">
                    <div class="card card-flush h-md-100">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-dark me-2 lh-1">{{ $totalUsers }}</span>
                                <span class="text-gray-500 pt-1 fw-semibold fs-6">{{ __('multilingual.attendance_dashboard.total_users') }}</span>
                            </div>
                        </div>
                        <div class="card-body pt-2 pb-4 d-flex align-items-center">
                            <i class="ki-duotone ki-profile-user fs-3x text-primary">
                                <span class="path1"></span><span class="path2"></span>
                                <span class="path3"></span><span class="path4"></span>
                            </i>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card card-flush h-md-100">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-success me-2 lh-1">{{ $todayPresent }}</span>
                                <span class="text-gray-500 pt-1 fw-semibold fs-6">{{ __('multilingual.attendance_dashboard.present_today') }}</span>
                            </div>
                        </div>
                        <div class="card-body pt-2 pb-4 d-flex align-items-center">
                            <i class="ki-duotone ki-check-circle fs-3x text-success">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card card-flush h-md-100">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-danger me-2 lh-1">{{ $todayAbsent }}</span>
                                <span class="text-gray-500 pt-1 fw-semibold fs-6">{{ __('multilingual.attendance_dashboard.absent_today') }}</span>
                            </div>
                        </div>
                        <div class="card-body pt-2 pb-4 d-flex align-items-center">
                            <i class="ki-duotone ki-cross-circle fs-3x text-danger">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card card-flush h-md-100">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-primary me-2 lh-1">{{ $attendanceRate }}%</span>
                                <span class="text-gray-500 pt-1 fw-semibold fs-6">{{ __('multilingual.attendance_dashboard.attendance_rate_today') }}</span>
                            </div>
                        </div>
                        <div class="card-body pt-2 pb-4 d-flex align-items-center">
                            <i class="ki-duotone ki-chart-simple fs-3x text-primary">
                                <span class="path1"></span><span class="path2"></span>
                                <span class="path3"></span><span class="path4"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-xl-6">
                    <div class="card card-flush h-md-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <h3 class="card-label fw-bold text-dark">{{ __('multilingual.attendance_dashboard.monthly_present') }}</h3>
                            </div>
                        </div>
                        <div class="card-body pt-6">
                            <div class="d-flex align-items-center">
                                <span class="fs-3x fw-bold text-success me-3">{{ $monthPresent }}</span>
                                <span class="text-gray-500 fw-semibold">{{ __('multilingual.attendance_dashboard.present_records_selected_month') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card card-flush h-md-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <h3 class="card-label fw-bold text-dark">{{ __('multilingual.attendance_dashboard.monthly_absent') }}</h3>
                            </div>
                        </div>
                        <div class="card-body pt-6">
                            <div class="d-flex align-items-center">
                                <span class="fs-3x fw-bold text-danger me-3">{{ $monthAbsent }}</span>
                                <span class="text-gray-500 fw-semibold">{{ __('multilingual.attendance_dashboard.absent_records_selected_month') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-xl-12">
                    <div class="card card-flush">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <h3 class="card-label fw-bold text-dark">{{ __('multilingual.attendance_dashboard.daily_attendance_chart') }}</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="daily_attendance_chart" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <h3 class="card-label fw-bold text-dark">{{ __('multilingual.attendance_dashboard.attendance_by_contract') }}</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="contract_attendance_chart" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <h3 class="card-label fw-bold text-dark">{{ __('multilingual.attendance_dashboard.attendance_by_role') }}</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="role_attendance_chart" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-5 g-xl-10">
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <h3 class="card-label fw-bold text-dark">{{ __('multilingual.attendance_dashboard.top_employees') }}</h3>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th>{{ __('multilingual.attendance_dashboard.columns.name') }}</th>
                                            <th>{{ __('multilingual.attendance_dashboard.columns.present_days') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($topEmployees as $user)
                                            <tr>
                                                <td>{{ $user->name_en ?? $user->name }}</td>
                                                <td class="text-success fw-bold">{{ $user->total_present }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">{{ __('multilingual.attendance_dashboard.no_data') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <h3 class="card-label fw-bold text-dark">{{ __('multilingual.attendance_dashboard.low_employees') }}</h3>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th>{{ __('multilingual.attendance_dashboard.columns.name') }}</th>
                                            <th>{{ __('multilingual.attendance_dashboard.columns.present_days') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($lowEmployees as $user)
                                            <tr>
                                                <td>{{ $user->name_en ?? $user->name }}</td>
                                                <td class="text-danger fw-bold">{{ $user->total_present }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">{{ __('multilingual.attendance_dashboard.no_data') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('script')
    

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const monthPicker = document.getElementById('month_picker');
            const monthValue = document.getElementById('month_value');
            const yearValue = document.getElementById('year_value');

            monthPicker.addEventListener('change', function () {
                const parts = this.value.split('-');
                yearValue.value = parts[0];
                monthValue.value = parts[1];
            });
            const dailyCategories = @json($dailyChartCategories);
            const dailyPresent = @json($dailyPresentSeries);
            const dailyAbsent = @json($dailyAbsentSeries);

            const contractCategories = @json($contractChartCategories);
            const contractSeries = @json($contractChartSeries);

            const roleCategories = @json($roleChartCategories);
            const roleSeries = @json($roleChartSeries);

            const dailyChart = new ApexCharts(document.querySelector("#daily_attendance_chart"), {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false }
                },
                series: [
                    { name: @json(__('multilingual.attendance_dashboard.present')), data: dailyPresent },
                    { name: @json(__('multilingual.attendance_dashboard.absent')), data: dailyAbsent }
                ],
                xaxis: {
                    categories: dailyCategories
                },
                dataLabels: { enabled: false },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['transparent']
                },
                legend: {
                    position: 'top'
                }
            });
            dailyChart.render();

            const contractChart = new ApexCharts(document.querySelector("#contract_attendance_chart"), {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false }
                },
                series: [{
                    name: @json(__('multilingual.attendance_dashboard.attendance_rate_percent')),
                    data: contractSeries
                }],
                xaxis: {
                    categories: contractCategories
                },
                dataLabels: {
                    enabled: true
                }
            });
            contractChart.render();

            const roleChart = new ApexCharts(document.querySelector("#role_attendance_chart"), {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false }
                },
                series: [{
                    name: @json(__('multilingual.attendance_dashboard.attendance_rate_percent')),
                    data: roleSeries
                }],
                xaxis: {
                    categories: roleCategories
                },
                dataLabels: {
                    enabled: true
                }
            });
            roleChart.render();
        });
    </script>
@endsection
