<?php $__env->startSection('title', 'الوحدات السكنية'); ?>
<?php $__env->startSection('pageName', 'الوحدات السكنية'); ?>

<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div style=" direction: rtl;; " class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h2 style="color: green;">Areas Productivity Report: <?php echo e($startDate); ?> <span
                                class="text-gray-400">to</span>
                            <?php echo e($endDate); ?></h2>
                    </div>

                    <!-- Date Filter Form -->
                    <div class="card-toolbar">
                        <form action="<?php echo e(route('reports.commulative')); ?>" method="GET" id="filter_form">
                            <!-- Hidden inputs to store the values for the backend -->
                            <input type="hidden" name="start_date" id="start_date" value="<?php echo e($startDate); ?>">
                            <input type="hidden" name="end_date" id="end_date" value="<?php echo e($endDate); ?>">

                            <div class="d-flex align-items-center gap-3">
                                <!-- The jQuery Trigger Input -->
                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" placeholder="اختر نطاق التاريخ"
                                        id="kt_daterangepicker" readonly />
                                    <span class="input-group-text"><i class="ki-duotone ki-calendar fs-2"></i></span>
                                </div>

                                <button onclick="$(this).addClass('data-kt-indicator'); setTimeout(() => {$(this).removeClass('disabled');}, 2000);" type="submit"
                                    class="btn data-kt-indicator  btn-primary">فلتر</button>
                                <a onclick="$(this).addClass('data-kt-indicator'); setTimeout(() => {$(this).removeClass('disabled');}, 2000);" href="<?php echo e(route('reports.commulative.export', ['start_date' => $startDate, 'end_date' => $endDate])); ?>"
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

                            <?php
                                $grandTotalEng = 0;
                                $grandTotalTDA = 0;
                                $grandTotalPDA = 0;
                                $grandTotalCRA = 0; // Added this missing initialization
                                $grandTotalAll = 0;
                            ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $commualtive; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <?php
                                    $rowTotal = ($val->tda_range ?? 0) + ($val->pda_range ?? 0) + ($val->cra_range ?? 0);
                                    $grandTotalEng += ($val->no_eng ?? 0);
                                    $grandTotalTDA += ($val->tda_range ?? 0);
                                    $grandTotalPDA += ($val->pda_range ?? 0);
                                    $grandTotalCRA += ($val->cra_range ?? 0); // Now it works
                                    $grandTotalAll += $rowTotal;
                                ?>
                                <tr>
                                    <td>Housing</td>
                                    <td><?php echo e($val->governorate); ?></td>
                                    <td><?php echo e($val->municipalitie); ?></td>
                                    <td><?php echo e($val->neighborhood); ?></td>
                                    <td class="text-center"><?php echo e($val->no_eng); ?></td>
                                    <td class="text-center"><?php echo e($val->tda_range); ?></td>
                                    <td class="text-center"><?php echo e($val->pda_range); ?></td>
                                    <td class="text-center"><?php echo e($val->cra_range); ?></td>
                                    <td class="text-center"><strong><?php echo e($rowTotal); ?></strong></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No data found for the selected dates.</td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        </tbody>
                        <tfoot class="border-top-2">
                            <tr class="fw-bold bg-light">
                                <td colspan="4" class="text-end">Grand Totals:</td>
                                <td class="text-center"><?php echo e($grandTotalEng); ?></td>
                                <td class="text-center text-danger"><?php echo e($grandTotalTDA); ?></td>
                                <td class="text-center text-warning"><?php echo e($grandTotalPDA); ?></td>
                                <td class="text-center text-primary"><?php echo e($grandTotalCRA); ?></td>
                                <td class="text-center text-success fs-5"><?php echo e($grandTotalAll); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('script'); ?>

    <script>
        $(document).ready(function () {
            // Initialize picker
            $('#kt_daterangepicker').daterangepicker({
                startDate: moment("<?php echo e($startDate); ?>"),
                endDate: moment("<?php echo e($endDate); ?>"),
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
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/Reports/commulatives.blade.php ENDPATH**/ ?>