<?php $__env->startSection('title', 'Public Building Survey'); ?>
<?php $__env->startSection('pageName', 'Public Building Survey'); ?>

<?php $__env->startSection('content'); ?>
<div class="card card-flush mb-7">
    <div class="card-header pt-7">
        <div class="card-title d-flex flex-column">
            <h2 class="mb-1"><?php echo e($survey->building_name ?? 'Public Building Survey'); ?></h2>
            <div class="text-muted">Object ID: <?php echo e($survey->objectid ?? '-'); ?></div>
        </div>
        <div class="card-toolbar">
            <a href="<?php echo e(route('public-buildings.index')); ?>" class="btn btn-sm btn-light">Back</a>
        </div>
    </div>

    <div class="card-body">
        <div class="row g-5 mb-8">
            <div class="col-md-3">
                <div class="border rounded p-4 h-100 bg-light-primary">
                    <div class="text-muted fs-7 mb-1">Municipality</div>
                    <div class="fw-bold fs-5"><?php echo e($survey->municipalitie ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-4 h-100 bg-light-success">
                    <div class="text-muted fs-7 mb-1">Neighborhood</div>
                    <div class="fw-bold fs-5"><?php echo e($survey->neighborhood ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-4 h-100 bg-light-warning">
                    <div class="text-muted fs-7 mb-1">Damage Status</div>
                    <div class="fw-bold fs-5"><?php echo e($survey->building_damage_status ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-4 h-100 bg-light-info">
                    <div class="text-muted fs-7 mb-1">Researcher</div>
                    <div class="fw-bold fs-5"><?php echo e($survey->assigned_to ?? '-'); ?></div>
                </div>
            </div>
        </div>

        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-8">
            <li class="nav-item">
                <a class="nav-link text-active-primary active" data-bs-toggle="tab" href="#tab_public_building_survey">
                    Survey
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-active-primary" data-bs-toggle="tab" href="#tab_public_building_units">
                    Units / Floors
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_public_building_survey" role="tabpanel">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <div class="card card-bordered shadow-sm mb-6">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title fw-bold"><?php echo e($section['title']); ?></h3>
                        </div>
                        <div class="card-body py-4">
                            <div class="table-responsive">
                                <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5">
                                    <thead>
                                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                            <th class="min-w-250px">Question</th>
                                            <th class="min-w-300px">Answer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $section['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                            <tr>
                                                <td class="fw-semibold text-gray-800"><?php echo e($row['question']); ?></td>
                                                <td class="text-gray-700"><?php echo e($row['answer']); ?></td>
                                            </tr>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <tr>
                                                <td colspan="2" class="text-center text-muted py-8">No data available.</td>
                                            </tr>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            <div class="tab-pane fade" id="tab_public_building_units" role="tabpanel">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $unitSections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <div class="card card-bordered shadow-sm mb-6">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title fw-bold"><?php echo e($section['title']); ?></h3>
                        </div>
                        <div class="card-body py-4">
                            <div class="table-responsive">
                                <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5">
                                    <thead>
                                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                            <th class="min-w-250px">Question</th>
                                            <th class="min-w-300px">Answer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $section['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                            <tr>
                                                <td class="fw-semibold text-gray-800"><?php echo e($row['question']); ?></td>
                                                <td class="text-gray-700"><?php echo e($row['answer']); ?></td>
                                            </tr>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="alert alert-secondary">No repeated units found for this survey.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/PublicBuilding/show.blade.php ENDPATH**/ ?>