<div class="row g-6 g-xl-9" id="engineers-container">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $engineers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>

        <div class="col-md-6 col-xl-4 assessment-card" data-status="<?php echo e($value->field_status); ?>">
            <a href="<?php echo e(url('assessment/' . $value->globalid)); ?>" class="card border-hover-primary h-100 shadow-sm">

                <div class="card-header border-0 pt-9">

                    <div class="card-title m-0">
                        <div class="symbol symbol-50px w-50px bg-light">
                            <img src="<?php echo e(url('')); ?>/assets/media/svg/brand-logos/plurk.svg" class="p-3" />
                        </div>
                    </div>

                    <div class="card-toolbar">

                        <span
                            class="badge badge-light-<?php echo e($value->field_status == 'COMPLETED' ? 'success' : 'info'); ?> fw-bold me-auto px-4 py-3">
                            <?php echo e($value->field_status); ?>

                        </span>

                        <span
                            class="badge badge-light-<?php echo e($value->building_damage_status == 'partially_damaged' ? 'info' : 'danger'); ?> fw-bold me-auto px-4 py-3">
                            <?php echo e($building_damage_ststus[$value->building_damage_status] ?? 'غير محدد'); ?>

                        </span>

                    </div>

                </div>

                <div class="card-body p-9">

                    <div class="fs-3 fw-bold text-dark">
                        <?php echo e($value->building_name ?? $value->owner_name ?? 'غير مدرج'); ?>

                    </div>

                    <p class="text-gray-400 fw-semibold fs-5 mt-1 mb-7">
                        رقم المبنى : #<?php echo e($value->objectid); ?>

                    </p>

                    <div class="d-flex flex-wrap mb-5">

                        <div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-7 mb-3">
                            <div class="fs-6 text-gray-800 fw-bold">
                                <?php echo e($value->date_of_damage ?? 'غير محدد'); ?>

                            </div>
                            <div class="fw-semibold text-gray-400">تاريخ الضرر</div>
                        </div>

                        <div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-2 mb-3">
                            <div class="fs-6 text-gray-800 fw-bold"><?php echo e($value->damaged_units_nos); ?></div>
                            <div class="fw-semibold text-gray-400">عدد الوحدات المتضررة</div>
                        </div>

                    </div>

                    <div class="symbol-group symbol-hover">

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $value->housing_unit; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hou): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>

                            <div class="symbol symbol-40px symbol-circle" data-bs-toggle="tooltip"
                                title="<?php echo e($hou->q_13_3_1_first_name); ?> <?php echo e($hou->q_13_3_4_last_name__family); ?>">

                                <span class="symbol-label bg-success text-inverse-primary fw-bold">
                                    <?php echo e($hou->objectid); ?>

                                </span>

                            </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                    </div>

                </div>

            </a>
        </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

</div>
<style>
    .skeleton {
        background: linear-gradient(90deg,
                #eeeeee 25%,
                #dddddd 37%,
                #eeeeee 63%);
        background-size: 400% 100%;
        animation: skeleton-loading 1.4s ease infinite;
        border-radius: 6px;
    }

    @keyframes skeleton-loading {
        0% {
            background-position: 100% 50%;
        }

        100% {
            background-position: 0 50%;
        }
    }

    .skeleton-avatar {
        width: 50px;
        height: 50px;
    }

    .skeleton-title {
        width: 80%;
        height: 20px;
    }

    .skeleton-text {
        width: 100%;
        height: 14px;
    }
</style>
<div class="d-flex flex-stack flex-wrap pt-10">

    <div class="fs-6 fw-semibold text-gray-700">
        عرض <?php echo e($engineers->firstItem()); ?> إلى <?php echo e($engineers->lastItem()); ?> من أصل <?php echo e($engineers->total()); ?>

    </div>

    <?php echo $engineers->links('pagination::bootstrap-5'); ?>


</div>
<div id="skeleton-loader" class="d-none">

    <div class="row g-6 g-xl-9">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < 6; $i++): ?>

            <div class="col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm p-6">

                    <div class="skeleton skeleton-avatar mb-4"></div>
                    <div class="skeleton skeleton-title mb-3"></div>
                    <div class="skeleton skeleton-text mb-2"></div>
                    <div class="skeleton skeleton-text"></div>

                </div>
            </div>

        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>

</div><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/partials/engineers_cards.blade.php ENDPATH**/ ?>