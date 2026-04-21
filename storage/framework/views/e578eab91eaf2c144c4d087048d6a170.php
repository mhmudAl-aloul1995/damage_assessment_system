<?php $__env->startSection('title', __('ui.audit_dashboard.title')); ?>
<?php $__env->startSection('pageName', __('ui.audit_dashboard.title')); ?>

<?php $__env->startSection('content'); ?>
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-flush shadow-sm">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold m-0"><?php echo e(__('ui.audit_dashboard.title')); ?></h3>
                    </div>
                    <div class="card-toolbar">
                        <form action="<?php echo e(route('audit.dashboard')); ?>" method="GET" id="audit_dashboard_filter_form">
                            <input type="hidden" name="start_date" id="start_date" value="<?php echo e($startDateValue); ?>">
                            <input type="hidden" name="end_date" id="end_date" value="<?php echo e($endDateValue); ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" placeholder="<?php echo e(__('ui.audit_dashboard.select_date_range')); ?>"
                                        id="audit_dashboard_daterangepicker" readonly />
                                    <span class="input-group-text"><i class="ki-duotone ki-calendar fs-2"></i></span>
                                </div>
                                <button type="submit" class="btn btn-primary"><?php echo e(__('ui.audit_dashboard.filter')); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-md-6 col-xl-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2"><?php echo e(__('ui.audit_dashboard.total_buildings')); ?></span>
                    <span class="fs-2hx fw-bold text-gray-900"><?php echo e($summaryMetrics['total_buildings_count']); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2"><?php echo e(__('ui.audit_dashboard.total_housing_units')); ?></span>
                    <span class="fs-2hx fw-bold text-gray-900"><?php echo e($summaryMetrics['total_housing_units_count']); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2"><?php echo e(__('ui.audit_dashboard.engineer_audited_buildings')); ?></span>
                    <span class="fs-2hx fw-bold text-primary"><?php echo e($summaryMetrics['engineer']['audited_buildings_count']); ?></span>
                    <span class="text-muted"><?php echo e(__('ui.audit_dashboard.of_total', ['percentage' => $summaryMetrics['engineer']['audited_buildings_percentage']])); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2"><?php echo e(__('ui.audit_dashboard.lawyer_audited_buildings')); ?></span>
                    <span class="fs-2hx fw-bold text-info"><?php echo e($summaryMetrics['lawyer']['audited_buildings_count']); ?></span>
                    <span class="text-muted"><?php echo e(__('ui.audit_dashboard.of_total', ['percentage' => $summaryMetrics['lawyer']['audited_buildings_percentage']])); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-8">
        <div class="col-md-6 col-xl-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2"><?php echo e(__('ui.audit_dashboard.engineer_audited_housing_units')); ?></span>
                    <span class="fs-2hx fw-bold text-success"><?php echo e($summaryMetrics['engineer']['audited_housing_units_count']); ?></span>
                    <span class="text-muted"><?php echo e(__('ui.audit_dashboard.of_total', ['percentage' => $summaryMetrics['engineer']['audited_housing_units_percentage']])); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2"><?php echo e(__('ui.audit_dashboard.lawyer_audited_housing_units')); ?></span>
                    <span class="fs-2hx fw-bold text-warning"><?php echo e($summaryMetrics['lawyer']['audited_housing_units_count']); ?></span>
                    <span class="text-muted"><?php echo e(__('ui.audit_dashboard.of_total', ['percentage' => $summaryMetrics['lawyer']['audited_housing_units_percentage']])); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-12">
            <div class="card card-flush shadow-sm">
                <div class="card-header pt-6">
                    <h3 class="card-title"><?php echo e(__('ui.audit_dashboard.engineering_audit')); ?></h3>
                </div>
                <div class="card-body">
                    <div class="row g-5">
                        <div class="col-lg-4">
                            <div id="audit_engineer_buildings_status_chart" style="height: 340px;"></div>
                        </div>
                        <div class="col-lg-4">
                            <div id="audit_engineer_housing_status_chart" style="height: 340px;"></div>
                        </div>
                        <div class="col-lg-4">
                            <div id="audit_engineer_comparison_chart" style="height: 340px;"></div>
                        </div>
                        <div class="col-12">
                            <div class="border border-gray-200 rounded p-5">
                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                                    <div>
                                        <h4 class="fw-bold mb-1"><?php echo e(__('ui.audit_dashboard.cumulative_audited_housing_units')); ?></h4>
                                        <div class="text-muted"><?php echo e(__('ui.audit_dashboard.trend_starting_from', ['date' => $chartData['engineer']['daily_housing_achievement_start_date']])); ?></div>
                                    </div>
                                </div>
                                <div id="audit_engineer_daily_housing_achievement_chart" style="height: 360px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-12">
            <div class="card card-flush shadow-sm">
                <div class="card-header pt-6">
                    <h3 class="card-title"><?php echo e(__('ui.audit_dashboard.legal_audit')); ?></h3>
                </div>
                <div class="card-body">
                    <div class="row g-5">
                        <div class="col-lg-4">
                            <div id="audit_lawyer_buildings_status_chart" style="height: 340px;"></div>
                        </div>
                        <div class="col-lg-4">
                            <div id="audit_lawyer_housing_status_chart" style="height: 340px;"></div>
                        </div>
                        <div class="col-lg-4">
                            <div id="audit_lawyer_comparison_chart" style="height: 340px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#audit_dashboard_daterangepicker').daterangepicker({
                startDate: moment("<?php echo e($startDateValue); ?>"),
                endDate: moment("<?php echo e($endDateValue); ?>"),
                ranges: {
                    <?php echo json_encode(__('ui.audit_dashboard.today'), 15, 512) ?>: [moment(), moment()],
                    <?php echo json_encode(__('ui.audit_dashboard.last_7_days'), 15, 512) ?>: [moment().subtract(6, 'days'), moment()],
                    <?php echo json_encode(__('ui.audit_dashboard.last_30_days'), 15, 512) ?>: [moment().subtract(29, 'days'), moment()],
                    <?php echo json_encode(__('ui.audit_dashboard.this_month'), 15, 512) ?>: [moment().startOf('month'), moment().endOf('month')]
                }
            }, function (start, end) {
                $('#audit_dashboard_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            const exportTools = {
                download: true,
                selection: false,
                zoom: false,
                zoomin: false,
                zoomout: false,
                pan: false,
                reset: false,
            };

            const exportConfig = {
                csv: {
                    filename: undefined,
                    columnDelimiter: ',',
                    headerCategory: <?php echo json_encode(__('ui.audit_dashboard.category'), 15, 512) ?>,
                    headerValue: <?php echo json_encode(__('ui.audit_dashboard.value'), 15, 512) ?>
                },
                svg: {
                    filename: undefined,
                },
                png: {
                    filename: undefined,
                }
            };

            const donutOptions = function (labels, series, colors) {
                return {
                    chart: {
                        type: 'donut',
                        height: 340,
                        toolbar: {
                            show: true,
                            tools: exportTools,
                            export: exportConfig
                        }
                    },
                    labels: labels,
                    series: series,
                    colors: colors,
                    legend: { position: 'bottom' },
                    dataLabels: { enabled: true },
                    stroke: { width: 0 }
                };
            };

            const comparisonOptions = function (categories, auditedSeries, totalSeries, primaryColor) {
                return {
                    chart: {
                        type: 'bar',
                        height: 340,
                        toolbar: {
                            show: true,
                            tools: exportTools,
                            export: exportConfig
                        }
                    },
                    series: [
                        {
                            name: <?php echo json_encode(__('ui.audit_dashboard.audited'), 15, 512) ?>,
                            data: auditedSeries
                        },
                        {
                            name: <?php echo json_encode(__('ui.audit_dashboard.total'), 15, 512) ?>,
                            data: totalSeries
                        }
                    ],
                    xaxis: {
                        categories: categories
                    },
                    colors: [primaryColor, '#E4E6EF'],
                    dataLabels: {
                        enabled: true
                    },
                    legend: {
                        position: 'top'
                    }
                };
            };

            const lineOptions = function (categories, series, primaryColor) {
                return {
                    chart: {
                        type: 'line',
                        height: 360,
                        toolbar: {
                            show: true,
                            tools: exportTools,
                            export: exportConfig
                        },
                        zoom: { enabled: false }
                    },
                    series: [
                        {
                            name: <?php echo json_encode(__('ui.audit_dashboard.cumulative_audited_housing_units'), 15, 512) ?>,
                            data: series
                        }
                    ],
                    xaxis: {
                        categories: categories,
                        labels: {
                            rotate: -45,
                            hideOverlappingLabels: true
                        }
                    },
                    yaxis: {
                        min: 0,
                        forceNiceScale: true,
                        decimalsInFloat: 0
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 3
                    },
                    colors: [primaryColor],
                    markers: {
                        size: 4,
                        strokeWidth: 0
                    },
                    dataLabels: {
                        enabled: false
                    },
                    grid: {
                        borderColor: '#EFF2F5'
                    },
                    tooltip: {
                        y: {
                            formatter: function (value) {
                                return <?php echo json_encode(__('ui.audit_dashboard.cumulative_units', ['value' => '__VALUE__']), 512) ?>.replace('__VALUE__', value);
                            }
                        }
                    }
                };
            };

            new ApexCharts(document.querySelector('#audit_engineer_buildings_status_chart'), donutOptions(
                <?php echo json_encode($chartData['engineer']['building_status_labels'], 15, 512) ?>,
                <?php echo json_encode($chartData['engineer']['building_status_series'], 15, 512) ?>,
                ['#7239EA', '#50CD89', '#F1416C', '#FFAD0F']
            )).render();

            new ApexCharts(document.querySelector('#audit_engineer_housing_status_chart'), donutOptions(
                <?php echo json_encode($chartData['engineer']['housing_status_labels'], 15, 512) ?>,
                <?php echo json_encode($chartData['engineer']['housing_status_series'], 15, 512) ?>,
                ['#009EF7', '#50CD89', '#F1416C', '#FFAD0F']
            )).render();

            new ApexCharts(document.querySelector('#audit_engineer_comparison_chart'), comparisonOptions(
                <?php echo json_encode($chartData['engineer']['comparison_categories'], 15, 512) ?>,
                <?php echo json_encode($chartData['engineer']['comparison_audited_series'], 15, 512) ?>,
                <?php echo json_encode($chartData['engineer']['comparison_total_series'], 15, 512) ?>,
                '#009EF7'
            )).render();

            new ApexCharts(document.querySelector('#audit_engineer_daily_housing_achievement_chart'), lineOptions(
                <?php echo json_encode($chartData['engineer']['daily_housing_achievement_labels'], 15, 512) ?>,
                <?php echo json_encode($chartData['engineer']['daily_housing_achievement_series'], 15, 512) ?>,
                '#50CD89'
            )).render();

            new ApexCharts(document.querySelector('#audit_lawyer_buildings_status_chart'), donutOptions(
                <?php echo json_encode($chartData['lawyer']['building_status_labels'], 15, 512) ?>,
                <?php echo json_encode($chartData['lawyer']['building_status_series'], 15, 512) ?>,
                ['#3F4254', '#50CD89', '#FFAD0F']
            )).render();

            new ApexCharts(document.querySelector('#audit_lawyer_housing_status_chart'), donutOptions(
                <?php echo json_encode($chartData['lawyer']['housing_status_labels'], 15, 512) ?>,
                <?php echo json_encode($chartData['lawyer']['housing_status_series'], 15, 512) ?>,
                ['#3F4254', '#50CD89', '#FFAD0F']
            )).render();

            new ApexCharts(document.querySelector('#audit_lawyer_comparison_chart'), comparisonOptions(
                <?php echo json_encode($chartData['lawyer']['comparison_categories'], 15, 512) ?>,
                <?php echo json_encode($chartData['lawyer']['comparison_audited_series'], 15, 512) ?>,
                <?php echo json_encode($chartData['lawyer']['comparison_total_series'], 15, 512) ?>,
                '#7239EA'
            )).render();
        });
    </script>
<?php $__env->stopSection(); ?>



<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/auditDashboard.blade.php ENDPATH**/ ?>