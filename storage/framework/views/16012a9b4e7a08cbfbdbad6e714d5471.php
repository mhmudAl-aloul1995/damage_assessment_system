<?php $__env->startSection('title', 'Attendance Dashboard'); ?>
<?php $__env->startSection('pageName', 'Attendance Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="app-content flex-column-fluid">
    <div class="app-container container-xxl">

        <div class="card mb-7">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h2 class="fw-bold">Attendance Dashboard</h2>
                </div>

                <div class="card-toolbar">
                    <form method="GET" action="<?php echo e(route('attendance.dashboard')); ?>" class="d-flex align-items-center gap-3">
                        <input
                            type="month"
                            name="month_picker"
                            id="month_picker"
                            class="form-control form-control-solid w-200px"
                            value="<?php echo e($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT)); ?>"
                        >

                        <input type="hidden" name="month" id="month_value" value="<?php echo e($month); ?>">
                        <input type="hidden" name="year" id="year_value" value="<?php echo e($year); ?>">

                        <button type="submit" class="btn btn-light-primary">
                            Filter
                        </button>

                        <a href="<?php echo e(route('attendance.export-monthly-report', ['month' => $month, 'year' => $year])); ?>"
                           class="btn btn-success">
                            Export Monthly Report
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-md-6 col-xl-3">
                <div class="card card-flush h-md-100">
                    <div class="card-header pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2hx fw-bold text-dark me-2 lh-1"><?php echo e($totalUsers); ?></span>
                            <span class="text-gray-500 pt-1 fw-semibold fs-6">Total Users</span>
                        </div>
                    </div>
                    <div class="card-body pt-2 pb-4 d-flex align-items-center">
                        <i class="ki-duotone ki-profile-user fs-3x text-primary">
                            <span class="path1"></span><span class="path2"></span>
                            <span class="path3"></span><span class="path4"></span>
                        </i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card card-flush h-md-100">
                    <div class="card-header pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2hx fw-bold text-success me-2 lh-1"><?php echo e($todayPresent); ?></span>
                            <span class="text-gray-500 pt-1 fw-semibold fs-6">Present Today</span>
                        </div>
                    </div>
                    <div class="card-body pt-2 pb-4 d-flex align-items-center">
                        <i class="ki-duotone ki-check-circle fs-3x text-success">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card card-flush h-md-100">
                    <div class="card-header pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2hx fw-bold text-danger me-2 lh-1"><?php echo e($todayAbsent); ?></span>
                            <span class="text-gray-500 pt-1 fw-semibold fs-6">Absent Today</span>
                        </div>
                    </div>
                    <div class="card-body pt-2 pb-4 d-flex align-items-center">
                        <i class="ki-duotone ki-cross-circle fs-3x text-danger">
                            <span class="path1"></span><span class="path2"></span>
                        </i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card card-flush h-md-100">
                    <div class="card-header pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2hx fw-bold text-primary me-2 lh-1"><?php echo e($attendanceRate); ?>%</span>
                            <span class="text-gray-500 pt-1 fw-semibold fs-6">Attendance Rate Today</span>
                        </div>
                    </div>
                    <div class="card-body pt-2 pb-4 d-flex align-items-center">
                        <i class="ki-duotone ki-chart-simple fs-3x text-primary">
                            <span class="path1"></span><span class="path2"></span>
                            <span class="path3"></span><span class="path4"></span>
                        </i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-xl-6">
                <div class="card card-flush h-md-100">
                    <div class="card-header pt-7">
                        <div class="card-title">
                            <h3 class="card-label fw-bold text-dark">Monthly Present</h3>
                        </div>
                    </div>
                    <div class="card-body pt-6">
                        <div class="d-flex align-items-center">
                            <span class="fs-3x fw-bold text-success me-3"><?php echo e($monthPresent); ?></span>
                            <span class="text-gray-500 fw-semibold">present records in selected month</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card card-flush h-md-100">
                    <div class="card-header pt-7">
                        <div class="card-title">
                            <h3 class="card-label fw-bold text-dark">Monthly Absent</h3>
                        </div>
                    </div>
                    <div class="card-body pt-6">
                        <div class="d-flex align-items-center">
                            <span class="fs-3x fw-bold text-danger me-3"><?php echo e($monthAbsent); ?></span>
                            <span class="text-gray-500 fw-semibold">absent records in selected month</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-xl-12">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <div class="card-title">
                            <h3 class="card-label fw-bold text-dark">Daily Summary</h3>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Day</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $monthlySummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <tr>
                                            <td><?php echo e($item->day_number); ?></td>
                                            <td class="text-success fw-bold"><?php echo e($item->present_count); ?></td>
                                            <td class="text-danger fw-bold"><?php echo e($item->absent_count); ?></td>
                                        </tr>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No data found</td>
                                        </tr>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-10">
            <div class="col-xl-6">
                <div class="card card-flush h-xl-100">
                    <div class="card-header pt-7">
                        <div class="card-title">
                            <h3 class="card-label fw-bold text-dark">Top Employees</h3>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Name</th>
                                        <th>Present Days</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topEmployees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <tr>
                                            <td><?php echo e($user->name_en ?? $user->name); ?></td>
                                            <td class="text-success fw-bold"><?php echo e($user->total_present); ?></td>
                                        </tr>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">No data found</td>
                                        </tr>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card card-flush h-xl-100">
                    <div class="card-header pt-7">
                        <div class="card-title">
                            <h3 class="card-label fw-bold text-dark">Low Employees</h3>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Name</th>
                                        <th>Present Days</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $lowEmployees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <tr>
                                            <td><?php echo e($user->name_en ?? $user->name); ?></td>
                                            <td class="text-danger fw-bold"><?php echo e($user->total_present); ?></td>
                                        </tr>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">No data found</td>
                                        </tr>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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
    const monthPicker = document.getElementById('month_picker');
    const monthValue = document.getElementById('month_value');
    const yearValue = document.getElementById('year_value');

    monthPicker.addEventListener('change', function () {
        const parts = this.value.split('-');
        yearValue.value = parts[0];
        monthValue.value = parts[1];
    });
});
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/UserManagement/attendance_dashboard.blade.php ENDPATH**/ ?>