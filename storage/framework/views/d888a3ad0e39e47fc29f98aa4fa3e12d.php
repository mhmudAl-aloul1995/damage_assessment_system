<?php $__env->startSection('title', __($title_key)); ?>
<?php $__env->startSection('pageName', __($title_key)); ?>

<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-6" style="direction: <?php echo e(app()->getLocale() === 'ar' ? 'rtl' : 'ltr'); ?>;">
                    <div class="card-title">
                        <h2 style="color: green;">
                            <?php echo e(__($title_key)); ?>: <?php echo e($start_date); ?>

                            <span class="text-gray-400"><?php echo e(__('multilingual.area_productivity_reports.labels.to')); ?></span>
                            <?php echo e($end_date); ?>

                        </h2>
                    </div>

                    <div class="card-toolbar">
                        <form action="<?php echo e(route($route_name)); ?>" method="GET" id="filter_form" class="w-100">
                            <input type="hidden" name="start_date" id="start_date" value="<?php echo e($start_date); ?>">
                            <input type="hidden" name="end_date" id="end_date" value="<?php echo e($end_date); ?>">

                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <a href="<?php echo e(route($export_route_name, array_merge(request()->query(), ['start_date' => $start_date, 'end_date' => $end_date]))); ?>"
                                    class="btn btn-success">
                                    <i class="fa fa-file-excel"></i>
                                    <?php echo e(__('multilingual.area_productivity_reports.actions.export_excel')); ?>

                                </a>

                                <button type="submit" class="btn btn-primary">
                                    <?php echo e(__('multilingual.area_productivity_reports.actions.filter')); ?>

                                </button>

                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" value="<?php echo e($date_range_label); ?>"
                                        placeholder="<?php echo e(__('multilingual.area_productivity_reports.filters.date_range')); ?>"
                                        id="kt_daterangepicker" readonly />
                                    <span class="input-group-text"><i class="ki-duotone ki-calendar fs-2"></i></span>
                                </div>

                                <button class="btn btn-light" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#area-productivity-advanced-filters" aria-expanded="false">
                                    <?php echo e(__('multilingual.area_productivity_reports.actions.advanced_filters')); ?>

                                </button>
                            </div>

                            <div class="collapse mt-5" id="area-productivity-advanced-filters">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <select name="governorate" class="form-select form-select-solid area-report-select"
                                            data-placeholder="<?php echo e(__('multilingual.area_productivity_reports.filters.governorate')); ?>">
                                            <option value=""><?php echo e(__('multilingual.area_productivity_reports.filters.all_governorates')); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filter_options['governorates']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $governorate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <option value="<?php echo e($governorate); ?>" <?php if($filters['governorate'] === $governorate): echo 'selected'; endif; ?>><?php echo e($governorate); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="municipalitie" class="form-select form-select-solid area-report-select"
                                            data-placeholder="<?php echo e(__('multilingual.area_productivity_reports.filters.municipality')); ?>">
                                            <option value=""><?php echo e(__('multilingual.area_productivity_reports.filters.all_municipalities')); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filter_options['municipalities']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $municipality): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <option value="<?php echo e($municipality); ?>" <?php if($filters['municipalitie'] === $municipality): echo 'selected'; endif; ?>><?php echo e($municipality); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="neighborhood" class="form-select form-select-solid area-report-select"
                                            data-placeholder="<?php echo e(__('multilingual.area_productivity_reports.filters.neighborhood')); ?>">
                                            <option value=""><?php echo e(__('multilingual.area_productivity_reports.filters.all_neighborhoods')); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filter_options['neighborhoods']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $neighborhood): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <option value="<?php echo e($neighborhood); ?>" <?php if($filters['neighborhood'] === $neighborhood): echo 'selected'; endif; ?>><?php echo e($neighborhood); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="zone_code" class="form-select form-select-solid area-report-select"
                                            data-placeholder="<?php echo e(__('multilingual.area_productivity_reports.filters.zone_code')); ?>">
                                            <option value=""><?php echo e(__('multilingual.area_productivity_reports.filters.all_zone_codes')); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filter_options['zone_codes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $zoneCode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <option value="<?php echo e($zoneCode); ?>" <?php if($filters['zone_code'] === $zoneCode): echo 'selected'; endif; ?>><?php echo e($zoneCode); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="assignedto" class="form-select form-select-solid area-report-select"
                                            data-placeholder="<?php echo e(__('multilingual.area_productivity_reports.filters.assignedto')); ?>">
                                            <option value=""><?php echo e(__('multilingual.area_productivity_reports.filters.all_assignedto')); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filter_options['assignedto']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignedto): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <option value="<?php echo e($assignedto); ?>" <?php if($filters['assignedto'] === $assignedto): echo 'selected'; endif; ?>><?php echo e($assignedto); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex gap-3">
                                        <button type="submit" class="btn btn-primary flex-fill">
                                            <?php echo e(__('multilingual.area_productivity_reports.actions.apply_filters')); ?>

                                        </button>
                                        <a href="<?php echo e(route($route_name)); ?>" class="btn btn-light flex-fill">
                                            <?php echo e(__('multilingual.area_productivity_reports.actions.reset')); ?>

                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body py-4">
                    <table class="table table-rounded table-striped table-row-bordered gy-7" id="area_productivity_table">
                        <thead>
                            <tr class="fw-bolder fs-6 text-gray-800 text-uppercase">
                                <th><?php echo e(__('multilingual.area_productivity_reports.columns.total_count')); ?></th>
                                <th><?php echo e(__('multilingual.area_productivity_reports.columns.cra')); ?></th>
                                <th><?php echo e(__('multilingual.area_productivity_reports.columns.pda')); ?></th>
                                <th><?php echo e(__('multilingual.area_productivity_reports.columns.tda')); ?></th>
                                <th><?php echo e(__('multilingual.area_productivity_reports.columns.engineers')); ?></th>
                                <th><?php echo e(__('multilingual.area_productivity_reports.columns.neighborhood')); ?></th>
                                <th><?php echo e(__('multilingual.area_productivity_reports.columns.municipality')); ?></th>
                                <th><?php echo e(__('multilingual.area_productivity_reports.columns.governorate')); ?></th>
                                <th><?php echo e(__('multilingual.area_productivity_reports.columns.sector')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <tr>
                                    <td class="fw-bold"><?php echo e($row->total_count); ?></td>
                                    <td><?php echo e($row->cra_range); ?></td>
                                    <td><?php echo e($row->pda_range); ?></td>
                                    <td><?php echo e($row->tda_range); ?></td>
                                    <td><?php echo e($row->no_eng); ?></td>
                                    <td><?php echo e($row->neighborhood ?: __('multilingual.area_productivity_reports.labels.not_available')); ?></td>
                                    <td><?php echo e($row->municipalitie ?: __('multilingual.area_productivity_reports.labels.not_available')); ?></td>
                                    <td><?php echo e($row->governorate ?: __('multilingual.area_productivity_reports.labels.not_available')); ?></td>
                                    <td><?php echo e(__($sector_key)); ?></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        <?php echo e(__('multilingual.area_productivity_reports.labels.empty')); ?>

                                    </td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                        <tfoot class="border-top-2">
                            <tr class="fw-bold bg-light">
                                <td class="text-success fs-5"><?php echo e($summary['total_records']); ?></td>
                                <td class="text-primary"><?php echo e($summary['cra']); ?></td>
                                <td class="text-warning"><?php echo e($summary['pda']); ?></td>
                                <td class="text-danger"><?php echo e($summary['tda']); ?></td>
                                <td><?php echo e($summary['engineers']); ?></td>
                                <td colspan="4" class="text-end">
                                    <?php echo e(__('multilingual.area_productivity_reports.labels.grand_totals')); ?>

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
        $(document).ready(function () {
            $('.area-report-select').select2({
                allowClear: true,
                width: '100%'
            });

            $('#kt_daterangepicker').daterangepicker({
                startDate: moment(<?php echo json_encode($start_date, 15, 512) ?>),
                endDate: moment(<?php echo json_encode($end_date, 15, 512) ?>),
                locale: {
                    format: 'MM/DD/YYYY'
                },
                ranges: {
                    <?php if(app()->getLocale() === 'ar'): ?>
                        'آخر 30 يوم': [moment().subtract(29, 'days'), moment()],
                        'هذا الشهر': [moment().startOf('month'), moment().endOf('month')]
                    <?php else: ?>
                        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                        'This Month': [moment().startOf('month'), moment().endOf('month')]
                    <?php endif; ?>
                }
            }, function (start, end) {
                $('#kt_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            $('#area_productivity_table').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    url: <?php echo json_encode(app()->getLocale() === 'ar' ? '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json' : '//cdn.datatables.net/plug-ins/1.13.4/i18n/en-GB.json', 15, 512) ?>
                }
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/Reports/area_productivity.blade.php ENDPATH**/ ?>