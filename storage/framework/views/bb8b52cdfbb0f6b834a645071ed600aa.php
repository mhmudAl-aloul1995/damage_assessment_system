<?php $__env->startSection('title', 'Telegram Settings'); ?>
<?php $__env->startSection('pageName', 'Telegram Settings'); ?>

<?php $__env->startSection('content'); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success mb-5"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="card card-flush shadow-sm">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold">Telegram Bot Settings</h3>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo e(route('telegram.settings.update')); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="form-label">Bot Token</label>
                        <textarea name="bot_token" rows="3" class="form-control form-control-solid"><?php echo e(old('bot_token', $settings->bot_token)); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bot Username</label>
                        <input type="text" name="bot_username" class="form-control form-control-solid" value="<?php echo e(old('bot_username', $settings->bot_username)); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Webhook Secret</label>
                        <input type="text" name="webhook_secret" class="form-control form-control-solid" value="<?php echo e(old('webhook_secret', $settings->webhook_secret)); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Parse Mode</label>
                        <select name="parse_mode" class="form-select form-select-solid">
                            <option value="HTML" <?php if(old('parse_mode', $settings->parse_mode) === 'HTML'): echo 'selected'; endif; ?>>HTML</option>
                            <option value="Markdown" <?php if(old('parse_mode', $settings->parse_mode) === 'Markdown'): echo 'selected'; endif; ?>>Markdown</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" value="1" id="telegram_enabled" name="is_enabled" <?php if(old('is_enabled', $settings->is_enabled)): echo 'checked'; endif; ?>>
                            <label class="form-check-label" for="telegram_enabled">Enable Telegram integration</label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="alert alert-light-info mb-0">
                            Webhook URL:
                            <code><?php echo e(route('telegram.webhook', ['secret' => $settings->webhook_secret ?: 'set-secret'])); ?></code>
                        </div>
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/Committee/Telegram/Settings/index.blade.php ENDPATH**/ ?>