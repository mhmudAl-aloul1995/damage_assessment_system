<?php $__env->startSection('title', 'Telegram Destination'); ?>
<?php $__env->startSection('pageName', 'Telegram Destination'); ?>

<?php $__env->startSection('content'); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success mb-5"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('warning')): ?>
        <div class="alert alert-warning mb-5"><?php echo e(session('warning')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="row g-5">
        <div class="col-lg-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold">Destination Summary</h3></div>
                </div>
                <div class="card-body">
                    <div class="mb-4"><div class="text-muted fs-7">Name</div><div class="fw-bold"><?php echo e($destination->name); ?></div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Type</div><div class="fw-bold"><?php echo e($destination->type); ?></div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Scope</div><div class="fw-bold"><?php echo e($destination->scope_type); ?></div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Status</div><div class="fw-bold"><?php echo e($destination->status); ?></div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Chat ID</div><div class="fw-bold"><?php echo e($destination->chat_id ?: '-'); ?></div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Telegram Username</div><div class="fw-bold"><?php echo e($destination->telegram_username ?: '-'); ?></div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Linked At</div><div class="fw-bold"><?php echo e(optional($destination->linked_at)->format('Y-m-d H:i') ?: '-'); ?></div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Related Model</div><div class="fw-bold"><?php echo e($destination->relatedModel?->name ?? $destination->related_model_type ?? '-'); ?></div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Shareable Link</div><div class="fw-bold" style="word-break: break-all;"><?php echo e($shareableLink ?: '-'); ?></div></div>

                    <div class="d-flex flex-wrap gap-2 mt-6">
                        <form method="POST" action="<?php echo e(route('telegram.destinations.regenerate-link', $destination)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-light-primary btn-sm">Regenerate Link</button>
                        </form>
                        <form method="POST" action="<?php echo e(route('telegram.destinations.refresh', $destination)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-light-info btn-sm">Refresh Status</button>
                        </form>
                        <form method="POST" action="<?php echo e(route('telegram.destinations.unlink', $destination)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-light-warning btn-sm">Unlink</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-flush border border-gray-200">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold">Destination Preferences</h3></div>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo e(route('telegram.destinations.preferences.update', $destination)); ?>">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PUT'); ?>

                        <?php
                            $preferences = $destination->preferences;
                        ?>

                        <div class="row g-5">
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_new_records" value="1" <?php if($preferences?->notify_new_records): echo 'checked'; endif; ?> />
                                    <span class="form-check-label">Notify new records</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_errors" value="1" <?php if($preferences?->notify_errors): echo 'checked'; endif; ?> />
                                    <span class="form-check-label">Notify errors</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_status_changes" value="1" <?php if($preferences?->notify_status_changes): echo 'checked'; endif; ?> />
                                    <span class="form-check-label">Notify status changes</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_reports" value="1" <?php if($preferences?->notify_reports): echo 'checked'; endif; ?> />
                                    <span class="form-check-label">Notify reports</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_broadcasts" value="1" <?php if($preferences?->notify_broadcasts): echo 'checked'; endif; ?> />
                                    <span class="form-check-label">Notify broadcasts</span>
                                </label>
                            </div>
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">Save Preferences</button>
                            </div>
                        </div>
                    </form>

                    <div class="separator my-8"></div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-1">Danger Zone</h4>
                            <div class="text-muted fs-7">Disable or permanently delete this destination.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" action="<?php echo e(route('telegram.destinations.disable', $destination)); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn btn-light-warning">Disable</button>
                            </form>
                            <form method="POST" action="<?php echo e(route('telegram.destinations.destroy', $destination)); ?>">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('DELETE'); ?>
                                <button type="submit" class="btn btn-light-danger" onclick="return confirm('Delete this destination?')">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/Committee/Telegram/Destinations/show.blade.php ENDPATH**/ ?>