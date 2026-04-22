<?php $__env->startSection('title', 'Telegram Discovered Chats'); ?>
<?php $__env->startSection('pageName', 'Telegram Discovered Chats'); ?>

<?php $__env->startSection('content'); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success mb-5"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="card card-flush shadow-sm">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold">Discovered Telegram Groups</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="telegram_discovered_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>Chat ID</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Username</th>
                            <th>Last Message</th>
                            <th>Destination</th>
                            <th>Last Seen</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="promote_discovered_chat_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="promote_discovered_chat_form">
                    <?php echo csrf_field(); ?>
                    <div class="modal-header">
                        <h3 class="fw-bold m-0">Promote Discovered Chat</h3>
                        <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal">
                            <i class="ki-duotone ki-cross fs-1"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-5">
                            <label class="form-label">Destination Name</label>
                            <input type="text" name="name" id="promote_name" class="form-control form-control-solid">
                        </div>
                        <div class="mb-5">
                            <label class="form-label required">Scope</label>
                            <select name="scope_type" class="form-select form-select-solid" required>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $scopes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scope): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <option value="<?php echo e($scope); ?>"><?php echo e(str($scope)->title()); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Context ID</label>
                            <input type="number" name="context_id" class="form-control form-control-solid">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Promote</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const promoteModal = new bootstrap.Modal(document.getElementById('promote_discovered_chat_modal'));

            $('#telegram_discovered_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '<?php echo e(route('telegram.discovered.data')); ?>',
                order: [[6, 'desc']],
                columns: [
                    { data: 'chat_id', name: 'chat_id' },
                    { data: 'chat_type', name: 'chat_type' },
                    { data: 'title', name: 'title' },
                    { data: 'username', name: 'username' },
                    { data: 'last_message_text', name: 'last_message_text' },
                    { data: 'destination', name: 'destination', orderable: false, searchable: false },
                    { data: 'last_seen_at', name: 'last_seen_at' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $(document).on('click', '.promote-discovered-chat', function () {
                $('#promote_discovered_chat_form').attr('action', '<?php echo e(url('telegram/discovered-chats')); ?>/' + $(this).data('chat-id') + '/promote');
                $('#promote_name').val($(this).data('chat-title'));
                promoteModal.show();
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/Committee/Telegram/Discovered/index.blade.php ENDPATH**/ ?>