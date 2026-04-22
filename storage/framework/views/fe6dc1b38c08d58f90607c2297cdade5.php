<?php $__env->startSection('title', 'الوحدات السكنية'); ?>
<?php $__env->startSection('pageName', 'الوحدات السكنية'); ?>


<?php $__env->startSection('content'); ?>
    <style>



    </style>
    <div class="row">
        <div class="col-md-12 ">
            <div class="card">

                <!--begin::Card header-->
                <div class="card-header border-0 pt-6">
                    <!--begin::Card title-->
                    <div class="card-title">
                        <h2 style=" color: green; " class="">Productivity Rates TDA-PDA Report</h3>

                    </div>
                    <!--begin::Card title-->
                    <!--begin::Card toolbar-->
                    <div class="card-toolbar flex-row-fluid justify-content-end gap-5">
                        <!--begin::Flatpickr-->
                        <div class="input-group w-250px">
                            <input class="form-control form-control-solid rounded rounded-end-0" placeholder="تاريخ من إلى"
                                id="kt_ecommerce_sales_flatpickr" />
                            <button class="btn btn-icon btn-light" id="kt_ecommerce_sales_flatpickr_clear">
                                <i class="ki-duotone ki-cross fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                        </div>
                        <!--end::Flatpickr-->

                        <!--begin::Add product-->
                        <button id="export_btn" class="btn btn-primary">
                            تصدير تقرير</button>
                        <!--end::Add product-->
                    </div>
                    <!--end::Card toolbar-->
                </div>
                <!--end::Card header-->
                <!--begin::Card body-->
                <style>
                    /* Keeps the first few columns visible while scrolling right */
                    .sticky-col {
                        position: sticky;
                        left: 0;
                        background-color: white !important;
                        z-index: 2;
                        border-right: 2px solid #ebedf3;
                    }

                    /* Offset for the second sticky column */
                    .sticky-col-2 {
                        left: 50px;
                    }

                    .sticky-col-3 {
                        left: 150px;
                    }
                </style>

                <div class="card-body py-4">
                    <!-- .table-responsive enables horizontal scrolling -->

                    <div class="table-responsive">
                        <table class="table table-bordered table-stripped  text-center align-middle">
                            <thead style="  background-color: #ffc107;"> 
                                <tr>
                                    <th>Day</th>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $period; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <th style="width:10px;" colspan="3"> <?php echo e($date->format('Y-m-d D')); ?> </th>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    <th> </th>

                                </tr>
                                <tr>
                                    <th> ENG.Name</th>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $period; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <th scope="col">TDA</th>
                                        <th scope="col">PDA</th>
                                        <th scope="col">Total</th>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    <th> Total </th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    // Initialize an array to hold totals for each date
                                    $columnTotals = [];
                                    $grandTotal = 0;
                                ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $assignedto; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <tr>
                                        <td class="bg-warning"><?php echo e($val); ?></td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $period; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                            <?php
                                                $dateStr = $date->format('Y-m-d');
                                                $dayData = $stats[$val]['daily_breakdown'][$dateStr] ?? null;

                                                $pda = $dayData[0]['pda'] ?? 0;
                                                $tda = $dayData[0]['tda'] ?? 0;
                                                $rowDayTotal = $pda + $tda;

                                                // Add to vertical column totals
                                                $columnTotals[$dateStr]['pda'] = ($columnTotals[$dateStr]['pda'] ?? 0) + $pda;
                                                $columnTotals[$dateStr]['tda'] = ($columnTotals[$dateStr]['tda'] ?? 0) + $tda;
                                                $columnTotals[$dateStr]['total'] = ($columnTotals[$dateStr]['total'] ?? 0) + $rowDayTotal;
                                            ?>
                                            <td class="text-white bg-danger-active"><?php echo e($pda); ?></td>
                                            <td class="text-white bg-success-active"><?php echo e($tda); ?></td>
                                            <td class="text-white bg-primary-active"><?php echo e($rowDayTotal); ?></td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                        <td style="background-color: gray;" class="text-white">
                                            <b>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($stats[$val])): ?>
                                                    <?php $engTotal = $stats[$val]['engineer_total']; ?>
                                                    <?php echo e($engTotal); ?>

                                                    <?php $grandTotal += $engTotal; ?>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </b>
                                        </td>
                                    </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tbody>

                           <tfoot>
    <tr style="background-color: #ffc107; font-weight: bold;">
        <td>الإجمالي (Total)</td>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $period; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <?php $dateStr = $date->format('Y-m-d'); ?>
            <td><?php echo e($columnTotals[$dateStr]['pda'] ?? 0); ?></td>
            <td><?php echo e($columnTotals[$dateStr]['tda'] ?? 0); ?></td>
            <td><?php echo e($columnTotals[$dateStr]['total'] ?? 0); ?></td>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <td class="bg-info text-white">
            <b><?php echo e($grandTotal); ?></b>
        </td>
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

            "use strict";

            // Class definition
            var kTExportProductivity = function () {
                // Shared variables
                var table;
                var flatpickr;
                var minDate, maxDate;
                var minformatted, maxformatted
                const export_btn = document.getElementById('export_btn');

                // Handle flatpickr --- more info: https://flatpickr.js.org/events/
                var handleFlatpickr = (selectedDates, dateStr, instance) => {
                    minDate = selectedDates[0] ? new Date(selectedDates[0]) : null;
                    maxDate = selectedDates[1] ? new Date(selectedDates[1]) : null;

                    if (selectedDates.length > 0) {
                        // Manually format a date using instance.formatDate
                        minformatted = instance.formatDate(selectedDates[0], "Y-m-d");
                        maxformatted = instance.formatDate(selectedDates[1], "Y-m-d");
                    }
                }


                var initFlatpickr = () => {
                    const element = document.querySelector('#kt_ecommerce_sales_flatpickr');
                    flatpickr = $(element).flatpickr({
                        altInput: true,
                        altFormat: "Y-m-d",
                        dateFormat: "Y-m-d",
                        mode: "range",
                        onChange: function (selectedDates, dateStr, instance) {
                            handleFlatpickr(selectedDates, dateStr, instance);


                            if (minDate && maxDate) {
                                var millisecondsInDay = 1000 * 60 * 60 * 24;
                                // Calculate the difference and round down to a whole number
                                var days = Math.floor((minDate.getTime() - maxDate.getTime()) / millisecondsInDay);

                                window.location.href = "<?php echo e(url('reports/productivity')); ?>?" + "minDate=" + minformatted + '&maxDate=' + maxformatted


                            }


                        },
                    });
                }






                // Handle clear flatpickr
                var handleClearFlatpickr = () => {
                    const clearButton = document.querySelector('#kt_ecommerce_sales_flatpickr_clear');
                    clearButton.addEventListener('click', e => {
                        flatpickr.clear();
                    });
                }
                function getUrlParameter(name) {
                    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
                    var results = regex.exec(location.search);
                    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
                };
                export_btn.addEventListener('click', function (e) {
                    $(this).addClass('disabled');
    
setTimeout(() => {
  $(this).removeClass('disabled');
}, 2000);
                    window.location.href = "<?php echo e(url('export_productivity')); ?>?" + "minDate=" + getUrlParameter('minDate') + '&maxDate=' + getUrlParameter('maxDate')
                })


                // Public methods
                return {
                    init: function () {

                        initFlatpickr();
                        handleClearFlatpickr();
                    }
                };
            }();

            // On document ready
            KTUtil.onDOMContentLoaded(function () {
                kTExportProductivity.init();
            });

        </script>
    <?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/Reports/productivity.blade.php ENDPATH**/ ?>