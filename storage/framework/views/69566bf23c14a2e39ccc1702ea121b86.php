<?php $__env->startSection('title', __('multilingual.area_manager_review.title')); ?>
<?php $__env->startSection('pageName', __('multilingual.area_manager_review.page_name')); ?>

<?php $__env->startSection('content'); ?>
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-flush shadow-sm">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <i class="ki-duotone ki-shield-search fs-1 me-3 text-primary">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="fw-bold m-0"><?php echo e(__('multilingual.area_manager_review.queue_title')); ?></h3>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-light-primary" id="refreshAreaManagerTable">
                            <i class="ki-duotone ki-arrows-circle fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <?php echo e(__('multilingual.area_manager_review.actions.refresh')); ?>

                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info d-flex flex-column gap-2 mb-0">
                        <div><span class="fw-bold"><?php echo e(__('multilingual.area_manager_review.region')); ?>:</span> <?php echo e($regionLabel); ?></div>
                        <div>
                            <span class="fw-bold"><?php echo e(__('multilingual.area_manager_review.allowed_municipalities')); ?>:</span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $municipalities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $municipality): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <span class="badge badge-light-primary me-1"><?php echo e($municipality); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <span class="badge badge-light-danger"><?php echo e(__('multilingual.area_manager_review.no_municipalities')); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card card-flush mb-7">
                <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                    <div class="card-title">
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <input type="text" id="areaManagerTableSearch"
                                class="form-control form-control-solid w-250px ps-13" placeholder="<?php echo e(__('multilingual.area_manager_review.search_placeholder')); ?>" />
                        </div>
                    </div>
                </div>

                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="areaManagerReviewTable">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th width="40">#</th>
                                    <th><?php echo e(__('multilingual.area_manager_review.columns.object_id')); ?></th>
                                    <th><?php echo e(__('multilingual.area_manager_review.columns.building_name')); ?></th>
                                    <th><?php echo e(__('multilingual.area_manager_review.columns.municipality')); ?></th>
                                    <th><?php echo e(__('multilingual.area_manager_review.columns.neighborhood')); ?></th>
                                    <th><?php echo e(__('multilingual.area_manager_review.columns.field_engineer')); ?></th>
                                    <th><?php echo e(__('multilingual.area_manager_review.columns.latest_status')); ?></th>
                                    <th><?php echo e(__('multilingual.area_manager_review.columns.status_date')); ?></th>
                                    <th class="text-end min-w-100px"><?php echo e(__('multilingual.area_manager_review.columns.actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        $(function () {
            let table = $('#areaManagerReviewTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: '<?php echo e(route('area-manager-review.data')); ?>',
                order: [[7, 'desc']],
                columns: [
                    {
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    { data: 'objectid', name: 'objectid' },
                    { data: 'building_name', name: 'building_name' },
                    { data: 'municipalitie', name: 'municipalitie' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'assignedto', name: 'assignedto' },
                    { data: 'latest_status_label', name: 'latest_status_label' },
                    {
                        data: 'latest_status_at',
                        name: 'latest_history.created_at',
                        searchable: false
                    },
                    {
                        data: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end'
                    }
                ]
            });

            $('#areaManagerReviewTable').on('draw.dt', function () {
                KTMenu.createInstances();
            });

            $('#refreshAreaManagerTable').on('click', function () {
                table.ajax.reload(null, false);
            });

            $('#areaManagerTableSearch').on('keyup', function () {
                table.search($(this).val()).draw();
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/areaManagerRejectedBuildings.blade.php ENDPATH**/ ?>