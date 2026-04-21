<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Road Facilities Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .heading { margin-bottom: 16px; }
        .heading h1 { margin: 0 0 6px; font-size: 20px; }
        .meta { color: #6b7280; font-size: 11px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; }
        .filters { margin-bottom: 16px; }
        .filters span { display: inline-block; margin-right: 12px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="heading">
        <h1>Road Facilities Surveys</h1>
        <div class="meta">Generated at <?php echo e(now()->format('Y-m-d H:i')); ?></div>
    </div>

    <div class="filters">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($filters['municipalitie'])): ?><span><strong>Municipality:</strong> <?php echo e($filters['municipalitie']); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($filters['road_damage_level'])): ?><span><strong>Damage Level:</strong> <?php echo e($filters['road_damage_level']); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($filters['assigned_to'])): ?><span><strong>Researcher:</strong> <?php echo e($filters['assigned_to']); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($filters['from_date'])): ?><span><strong>From:</strong> <?php echo e($filters['from_date']); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($filters['to_date'])): ?><span><strong>To:</strong> <?php echo e($filters['to_date']); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Object ID</th>
                <th>Road Name</th>
                <th>Municipality</th>
                <th>Neighborhood</th>
                <th>Damage Level</th>
                <th>Road Access</th>
                <th>Submission Date</th>
                <th>Items</th>
                <th>Researcher</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $surveys; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $survey): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <tr>
                    <td><?php echo e($survey->objectid); ?></td>
                    <td><?php echo e($survey->str_name); ?></td>
                    <td><?php echo e($survey->municipalitie); ?></td>
                    <td><?php echo e($survey->neighborhood); ?></td>
                    <td><?php echo e($survey->road_damage_level); ?></td>
                    <td><?php echo e($survey->road_access); ?></td>
                    <td><?php echo e($survey->submission_date?->format('Y-m-d H:i')); ?></td>
                    <td><?php echo e($survey->items_count); ?></td>
                    <td><?php echo e($survey->assigned_to); ?></td>
                </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <tr>
                    <td colspan="9">No surveys found for the selected filters.</td>
                </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php /**PATH D:\myProjects\phc\resources\views/RoadFacility/export_pdf.blade.php ENDPATH**/ ?>