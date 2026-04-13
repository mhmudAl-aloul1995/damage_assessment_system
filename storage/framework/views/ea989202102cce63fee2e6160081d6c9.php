<div class="row g-6 g-xl-9" id="engineers-container">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $engineers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
        <div class="col-md-6 col-xl-4 assessment-card">
            <!-- أضفت تأثير التحويم وshadow أنعم -->
            <a href="<?php echo e(url('assessment/' . $value->globalid)); ?>" 
               class="card border-hover-primary h-100 shadow-sm transition-3d" 
               style="text-decoration: none; transition: all 0.3s ease;">

                <div class="card-header border-0 pt-7">
                    <div class="card-title m-0">
                        <!-- أيقونة تعبر عن "مبنى" بدلاً من شعار عشوائي -->
                        <div class="symbol symbol-45px w-45px bg-light-primary rounded-3">
                            <i class="ki-duotone ki-home-2 fs-2x text-primary p-3">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                        </div>
                    </div>

                    <div class="card-toolbar d-flex gap-2">
                        <!-- حالة الإستبيان -->
                        <span class="badge badge-light-<?php echo e($value->field_status == 'COMPLETED' ? 'success' : 'warning'); ?> fw-bold px-3 py-2">
                            <?php echo e($value->field_status == 'COMPLETED' ? 'مكتمل' : 'قيد التدقيق'); ?>

                        </span>

                        <!-- حالة الضرر بلون يعبر عن الخطورة -->
                        <?php
                            $damageClass = match($value->building_damage_status) {
                                'fully_damaged' => 'danger',
                                'partially_damaged' => 'warning',
                                'minor_damage' => 'primary',
                                default => 'secondary'
                            };
                        ?>
                        <span class="badge badge-<?php echo e($damageClass); ?> fw-bold px-3 py-2">
                            <?php echo e($building_damage_ststus[$value->building_damage_status] ?? 'غير محدد'); ?>

                        </span>
                    </div>
                </div>

                <div class="card-body p-9">
                    <!-- اسم المالك أو المبنى بشكل بارز -->
                    <div class="fs-3 fw-bolder text-gray-900 mb-1">
                        <?php echo e($value->building_name ?? $value->owner_name ?? 'بدون اسم'); ?>

                    </div>

                    <div class="text-gray-500 fw-bold fs-6 mb-6">
                        <span class="text-primary"> • رقم المبنى</span>#<?php echo e($value->objectid); ?>

                    </div>

                    <!-- تفاصيل سريعة منقطة -->
                    <div class="d-flex flex-wrap mb-7">
                        <div class="border border-gray-300 border-dashed rounded min-w-100px py-2 px-3 me-3 mb-2">
                            <div class="fw-semibold text-gray-400 fs-8">تاريخ الضرر</div>
                            <div class="fs-7 text-gray-800 fw-bolder"><?php echo e($value->date_of_damage ?? '-'); ?></div>
                        </div>

                        <div class="border border-gray-300 border-dashed rounded min-w-100px py-2 px-3 mb-2">
                            <div class="fw-semibold text-gray-400 fs-8">الوحدات المتضررة</div>
                            <div class="fs-7 text-gray-800 fw-bolder text-center"><?php echo e($value->damaged_units_nos); ?></div>
                        </div>
                    </div>

                    <!-- عرض الوحدات السكنية بشكل احترافي -->
                    <div class="d-flex align-items-center">
                        <div class="symbol-group symbol-hover mb-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $value->housing_unit->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hou): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <div class="symbol symbol-35px symbol-circle" 
                                     data-bs-toggle="tooltip" 
                                     title="<?php echo e($hou->q_9_3_1_first_name); ?> <?php echo e($hou->q_13_3_4_last_name__family); ?>">
                                    <span class="symbol-label bg-light-success text-success fw-bold fs-8 border border-white">
                                        <?php echo e(substr($hou->q_9_3_1_first_name, 0, 2)); ?>

                                    </span>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($value->housing_unit->count() > 4): ?>
                                <div class="symbol symbol-35px symbol-circle">
                                    <span class="symbol-label bg-light-dark text-gray-800 fw-bold fs-8">
                                        +<?php echo e($value->housing_unit->count() - 4); ?>

                                    </span>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="text-gray-400 fw-bold fs-8 ms-3">وحدات</div>
                    </div>
                </div>
            </a>
        </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</div>

<style>
    /* تحسين شكل البطاقة عند التحويم */
    .transition-3d:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    
    /* تحسين الخطوط والتناسق */
    .assessment-card .badge { font-size: 0.75rem; text-transform: capitalize; }
    
    /* تخصيص السكيلتون ليكون مطابقاً للبطاقة الجديدة */
    .skeleton-box { height: 180px; width: 100%; border-radius: 12px; }
</style>

<div class="d-flex flex-stack flex-wrap pt-10">
    <div class="fs-6 fw-bold text-gray-600">
        عرض <span class="text-gray-900"><?php echo e($engineers->firstItem()); ?></span> إلى <span class="text-gray-900"><?php echo e($engineers->lastItem()); ?></span> من أصل <span class="text-primary"><?php echo e($engineers->total()); ?></span>
    </div>
    <div class="pagination-modern">
        <?php echo $engineers->links('pagination::bootstrap-5'); ?>

    </div>
</div>
<?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/partials/engineers_cards.blade.php ENDPATH**/ ?>