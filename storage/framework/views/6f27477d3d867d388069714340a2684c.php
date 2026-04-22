<?php $__env->startSection('title', 'Telegram Destinations'); ?>
<?php $__env->startSection('pageName', 'Telegram Destinations'); ?>

<?php $__env->startSection('content'); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success mb-5"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold">Create Telegram Destination</h3>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo e(route('telegram.destinations.store')); ?>">
                <?php echo csrf_field(); ?>
                <div class="row g-5">
                    <div class="col-md-3">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" class="form-control form-control-solid" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Type</label>
                        <select name="type" class="form-select form-select-solid" required>
                            <option value="user">User</option>
                            <option value="group">Group</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Scope</label>
                        <select name="scope_type" class="form-select form-select-solid" required>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $scopes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scope): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($scope); ?>"><?php echo e(str($scope)->title()); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Related User</label>
                        <select name="user_id" class="form-select form-select-solid">
                            <option value="">No related user</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($user->id); ?>"><?php echo e($user->name); ?><?php echo e($user->username_arcgis ? ' - '.$user->username_arcgis : ''); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Context ID</label>
                        <input type="number" name="context_id" class="form-control form-control-solid">
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">Create Destination</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold">Telegram Destinations</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="telegram_destinations_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>Name</th>
                            <th>Type</th>
                            <th>Scope</th>
                            <th>Status</th>
                            <th>Chat ID</th>
                            <th>Telegram</th>
                            <th>Shareable Link</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#telegram_destinations_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '<?php echo e(route('telegram.destinations.data')); ?>',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'type', name: 'type' },
                    { data: 'scope_type', name: 'scope_type' },
                    { data: 'link_status', name: 'status' },
                    { data: 'chat_id', name: 'chat_id', defaultContent: '-' },
                    { data: 'telegram_username', name: 'telegram_username', defaultContent: '-' },
                    { data: 'shareable_link', name: 'shareable_link', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/Committee/Telegram/Destinations/index.blade.php ENDPATH**/ ?>