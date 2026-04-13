<?php $__env->startSection('title', 'Daily Achievement'); ?>
<?php $__env->startSection('pageName', 'Daily Achievement'); ?>

<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div>
                            <h2 style="color: green;"><?php echo e($reportTitle); ?></h2>
                            <div class="text-muted fs-7">
                                From <?php echo e($startDateValue); ?> to <?php echo e($endDateValue); ?>

                            </div>
                        </div>
                    </div>

                    <div class="card-toolbar d-flex flex-wrap gap-3">
                        <ul class="nav nav-pills gap-2">
                            <li class="nav-item">
                                <a class="nav-link <?php echo e($activeTab === 'engineers' ? 'active' : ''); ?>" href="<?php echo e(route('reports.daily-achievement', ['tab' => 'engineers', 'start_date' => $startDateValue, 'end_date' => $endDateValue])); ?>">Engineers</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo e($activeTab === 'lawyers' ? 'active' : ''); ?>" href="<?php echo e(route('reports.daily-achievement', ['tab' => 'lawyers', 'start_date' => $startDateValue, 'end_date' => $endDateValue])); ?>">Lawyers</a>
                            </li>
                        </ul>

                        <form action="<?php echo e($reportRoute); ?>" method="GET" id="daily_achievement_form">
                            <input type="hidden" name="tab" id="tab" value="<?php echo e($activeTab); ?>">
                            <input type="hidden" name="start_date" id="start_date" value="<?php echo e($startDateValue); ?>">
                            <input type="hidden" name="end_date" id="end_date" value="<?php echo e($endDateValue); ?>">

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
                                        <span class="fs-2 fw-bold text-gray-900"><?php echo e($chartMetrics['buildings']['percentage']); ?>%</span>
                                        <span class="text-muted pt-1 fw-semibold fs-6">Audited buildings from total buildings</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div id="daily_achievement_buildings_chart" style="height: 320px;"></div>
                                    <div class="d-flex justify-content-center gap-10 flex-wrap mt-4">
                                        <div class="text-center">
                                            <div class="fs-3 fw-bold text-primary"><?php echo e($chartMetrics['buildings']['audited_count']); ?></div>
                                            <div class="text-muted">Audited</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="fs-3 fw-bold text-gray-700"><?php echo e($chartMetrics['buildings']['total_count']); ?></div>
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
                                        <span class="fs-2 fw-bold text-gray-900"><?php echo e($chartMetrics['housing_units']['percentage']); ?>%</span>
                                        <span class="text-muted pt-1 fw-semibold fs-6">Audited housing units from total housing units</span>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div id="daily_achievement_housing_units_chart" style="height: 320px;"></div>
                                    <div class="d-flex justify-content-center gap-10 flex-wrap mt-4">
                                        <div class="text-center">
                                            <div class="fs-3 fw-bold text-success"><?php echo e($chartMetrics['housing_units']['audited_count']); ?></div>
                                            <div class="text-muted">Audited</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="fs-3 fw-bold text-gray-700"><?php echo e($chartMetrics['housing_units']['total_count']); ?></div>
                                            <div class="text-muted">Total</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-5 mb-8">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $summaryCards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <div class="col-md-3">
                                <div class="border border-gray-300 border-dashed rounded p-6 text-center h-100 <?php echo e($card['class'] === 'primary' ? 'bg-light-primary' : ''); ?>">
                                    <div class="text-muted mb-2"><?php echo e($card['label']); ?></div>
                                    <div class="fs-2hx fw-bold text-<?php echo e($card['class']); ?>"><?php echo e($card['value']); ?></div>
                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-rounded table-striped table-row-bordered gy-7 align-middle">
                            <thead>
                                <tr class="fw-bolder fs-6 text-gray-800 text-uppercase">
                                    <th><?php echo e($tableTitle); ?></th>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $tableColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <th class="text-center"><?php echo e($column['label']); ?></th>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo e($row['name']); ?></td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $tableColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                            <td class="text-center text-<?php echo e($column['class']); ?> fw-bold <?php echo e($column['class'] === 'primary' ? 'fw-bolder' : ''); ?>"><?php echo e($row[$column['key']]); ?></td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    <tr>
                                        <td colspan="<?php echo e(count($tableColumns) + 1); ?>" class="text-center text-muted"><?php echo e($emptyMessage); ?></td>
                                    </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold bg-light">
                                    <td>Total</td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $tableColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <td class="text-center text-<?php echo e($column['class']); ?>"><?php echo e($totals[$column['key']]); ?></td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        $(document).ready(function () {
            $('#kt_daily_achievement_daterangepicker').daterangepicker({
                startDate: moment("<?php echo e($startDateValue); ?>"),
                endDate: moment("<?php echo e($endDateValue); ?>"),
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
                    <?php echo e($chartMetrics['buildings']['audited_count']); ?>,
                    <?php echo e($chartMetrics['buildings']['remaining_count']); ?>

                ], ['#009ef7', '#e4e6ef'])
            ).render();

            new ApexCharts(
                document.querySelector('#daily_achievement_housing_units_chart'),
                chartOptions([
                    <?php echo e($chartMetrics['housing_units']['audited_count']); ?>,
                    <?php echo e($chartMetrics['housing_units']['remaining_count']); ?>

                ], ['#50cd89', '#e4e6ef'])
            ).render();
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/Reports/daily_achievement.blade.php ENDPATH**/ ?>