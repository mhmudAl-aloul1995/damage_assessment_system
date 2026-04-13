<?php $__env->startSection('title', 'Audit Dashboard'); ?>
<?php $__env->startSection('pageName', 'Audit Dashboard'); ?>

<?php $__env->startSection('content'); ?>
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-flush shadow-sm">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold m-0">Audit Dashboard</h3>
                    </div>
                    <div class="card-toolbar">
                        <form action="<?php echo e(route('audit.dashboard')); ?>" method="GET" id="audit_dashboard_filter_form">
                            <input type="hidden" name="start_date" id="start_date" value="<?php echo e($startDateValue); ?>">
                            <input type="hidden" name="end_date" id="end_date" value="<?php echo e($endDateValue); ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" placeholder="Select date range"
                                        id="audit_dashboard_daterangepicker" readonly />
                                    <span class="input-group-text"><i class="ki-duotone ki-calendar fs-2"></i></span>
                                </div>
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-md-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2">Total Buildings</span>
                    <span class="fs-2hx fw-bold text-gray-900"><?php echo e($summaryMetrics['total_buildings_count']); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2">Audited Buildings</span>
                    <span class="fs-2hx fw-bold text-primary"><?php echo e($summaryMetrics['audited_buildings_count']); ?></span>
                    <span class="text-muted"><?php echo e($summaryMetrics['audited_buildings_percentage']); ?>% of total</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2">Total Housing Units</span>
                    <span class="fs-2hx fw-bold text-gray-900"><?php echo e($summaryMetrics['total_housing_units_count']); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush h-md-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <span class="fs-6 text-muted mb-2">Audited Housing Units</span>
                    <span class="fs-2hx fw-bold text-success"><?php echo e($summaryMetrics['audited_housing_units_count']); ?></span>
                    <span class="text-muted"><?php echo e($summaryMetrics['audited_housing_units_percentage']); ?>% of total</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-lg-4">
            <div class="card card-flush shadow-sm h-md-100">
                <div class="card-header pt-6">
                    <h3 class="card-title">Buildings Engineering Status</h3>
                </div>
                <div class="card-body">
                    <div id="audit_buildings_status_chart" style="height: 340px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-flush shadow-sm h-md-100">
                <div class="card-header pt-6">
                    <h3 class="card-title">Housing Units Engineering Status</h3>
                </div>
                <div class="card-body">
                    <div id="audit_housing_status_chart" style="height: 340px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-flush shadow-sm h-md-100">
                <div class="card-header pt-6">
                    <h3 class="card-title">Audited vs Total</h3>
                </div>
                <div class="card-body">
                    <div id="audit_comparison_chart" style="height: 340px;"></div>
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
                    'Today': [moment(), moment()],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')]
                }
            }, function (start, end) {
                $('#audit_dashboard_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            const donutOptions = function (labels, series, colors) {
                return {
                    chart: {
                        type: 'donut',
                        height: 340,
                        toolbar: { show: false }
                    },
                    labels: labels,
                    series: series,
                    colors: colors,
                    legend: { position: 'bottom' },
                    dataLabels: { enabled: true },
                    stroke: { width: 0 }
                };
            };

            new ApexCharts(document.querySelector('#audit_buildings_status_chart'), donutOptions(
                <?php echo json_encode($chartData['building_status_labels'], 15, 512) ?>,
                <?php echo json_encode($chartData['building_status_series'], 15, 512) ?>,
                ['#7239EA', '#50CD89', '#F1416C', '#FFAD0F']
            )).render();

            new ApexCharts(document.querySelector('#audit_housing_status_chart'), donutOptions(
                <?php echo json_encode($chartData['housing_status_labels'], 15, 512) ?>,
                <?php echo json_encode($chartData['housing_status_series'], 15, 512) ?>,
                ['#009EF7', '#50CD89', '#F1416C', '#FFAD0F']
            )).render();

            new ApexCharts(document.querySelector('#audit_comparison_chart'), {
                chart: {
                    type: 'bar',
                    height: 340,
                    toolbar: { show: false }
                },
                series: [
                    {
                        name: 'Audited',
                        data: <?php echo json_encode($chartData['comparison_audited_series'], 15, 512) ?>
                    },
                    {
                        name: 'Total',
                        data: <?php echo json_encode($chartData['comparison_total_series'], 15, 512) ?>
                    }
                ],
                xaxis: {
                    categories: <?php echo json_encode($chartData['comparison_categories'], 15, 512) ?>
                },
                colors: ['#009EF7', '#E4E6EF'],
                dataLabels: {
                    enabled: true
                },
                legend: {
                    position: 'top'
                }
            }).render();
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/auditDashboard.blade.php ENDPATH**/ ?>