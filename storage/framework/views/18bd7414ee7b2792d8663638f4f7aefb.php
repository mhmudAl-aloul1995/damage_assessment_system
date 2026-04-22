<?php
    $recordName = $recordType === 'housing-unit'
        ? ($decisionable->housing_unit_number ?: $decisionable->full_name ?: $decisionable->objectid)
        : ($decisionable->building_name ?: $decisionable->objectid);
?>

<?php $__env->startSection('title', __('multilingual.committee_decision_show.title')); ?>
<?php $__env->startSection('pageName', __('multilingual.committee_decision_show.page_name')); ?>

<?php $__env->startSection('content'); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success mb-5"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="row g-5 mb-5">
        <div class="col-lg-4">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold m-0"><?php echo e(__('multilingual.committee_decision_show.summary_title')); ?></h3></div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.record_type')); ?></div>
                        <div class="fw-bold"><?php echo e($recordType === 'housing-unit' ? __('multilingual.committee_decision_show.housing_unit') : __('multilingual.committee_decision_show.building')); ?></div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.record_number')); ?></div>
                        <div class="fw-bold"><?php echo e($decisionable->objectid ?? '-'); ?></div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.name')); ?></div>
                        <div class="fw-bold"><?php echo e($recordName); ?></div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.building')); ?></div>
                        <div class="fw-bold"><?php echo e($building?->building_name ?? '-'); ?></div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.field_engineer')); ?></div>
                        <div class="fw-bold"><?php echo e($building?->assignedto ?? '-'); ?></div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.decision_status')); ?></div>
                        <div class="fw-bold"><?php echo e($statusLabels[$decision->status] ?? $decision->status); ?></div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.committee_decision')); ?></div>
                        <div class="fw-bold"><?php echo e($decisionTypes[$decision->decision_type] ?? '-'); ?></div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.action')); ?></div>
                        <div class="fw-bold"><?php echo e($decision->action_text ?: '-'); ?></div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.required_signatures')); ?></div>
                        <div class="fw-bold">
                            <?php echo e($decision->signatures->filter(fn ($signature) => $signature->committeeMember?->is_required && $signature->committeeMember?->is_active)->where('status', 'approved')->count()); ?>

                            /
                            <?php echo e($decision->signatures->filter(fn ($signature) => $signature->committeeMember?->is_required && $signature->committeeMember?->is_active)->count()); ?>

                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.arcgis_status')); ?></div>
                        <div class="fw-bold"><?php echo e($decision->arcgis_sync_status ?: 'pending'); ?></div>
                    </div>
                    <div>
                        <div class="text-muted fs-7"><?php echo e(__('multilingual.committee_decision_show.telegram_status')); ?></div>
                        <div class="fw-bold"><?php echo e($decision->telegram_status ?: 'pending'); ?></div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRetryTelegram && $decision->isCompleted() && $decision->telegram_status !== 'sent'): ?>
                        <div class="mt-6">
                            <form method="POST" action="<?php echo e(route('committee-decisions.retry-telegram', $decision)); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn btn-light-success btn-sm"><?php echo e(__('multilingual.committee_decision_show.retry_telegram')); ?></button>
                            </form>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-flush border border-gray-200 mb-5">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold m-0"><?php echo e(__('multilingual.committee_decision_show.form_title')); ?></h3></div>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo e(route('committee-decisions.update', $decision)); ?>">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PUT'); ?>

                        <div class="row g-5">
                            <div class="col-md-6">
                                <label class="form-label required"><?php echo e(__('multilingual.committee_decision_show.decision_type')); ?></label>
                                <select name="decision_type" class="form-select form-select-solid" <?php echo e($canManageContent ? '' : 'disabled'); ?>>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $decisionTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <option value="<?php echo e($value); ?>" <?php if(old('decision_type', $decision->decision_type) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required"><?php echo e(__('multilingual.committee_decision_show.decision_date')); ?></label>
                                <input type="date" name="decision_date" class="form-control form-control-solid"
                                    value="<?php echo e(old('decision_date', optional($decision->decision_date)->format('Y-m-d') ?: now()->format('Y-m-d'))); ?>"
                                    <?php echo e($canManageContent ? '' : 'disabled'); ?>>
                            </div>
                            <div class="col-12">
                                <label class="form-label required"><?php echo e(__('multilingual.committee_decision_show.decision_text')); ?></label>
                                <textarea name="decision_text" rows="5" class="form-control form-control-solid" <?php echo e($canManageContent ? '' : 'disabled'); ?>><?php echo e(old('decision_text', $decision->decision_text)); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?php echo e(__('multilingual.committee_decision_show.action_text')); ?></label>
                                <textarea name="action_text" rows="3" class="form-control form-control-solid" <?php echo e($canManageContent ? '' : 'disabled'); ?>><?php echo e(old('action_text', $decision->action_text)); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?php echo e(__('multilingual.committee_decision_show.notes')); ?></label>
                                <textarea name="notes" rows="3" class="form-control form-control-solid" <?php echo e($canManageContent ? '' : 'disabled'); ?>><?php echo e(old('notes', $decision->notes)); ?></textarea>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageContent): ?>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary"><?php echo e(__('multilingual.committee_decision_show.save_decision')); ?></button>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-flush border border-gray-200">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold m-0"><?php echo e(__('multilingual.committee_decision_show.signatures_title')); ?></h3></div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th><?php echo e(__('multilingual.committee_decision_show.columns.member')); ?></th>
                                    <th><?php echo e(__('multilingual.committee_decision_show.columns.title')); ?></th>
                                    <th><?php echo e(__('multilingual.committee_decision_show.columns.required')); ?></th>
                                    <th><?php echo e(__('multilingual.committee_decision_show.columns.status')); ?></th>
                                    <th><?php echo e(__('multilingual.committee_decision_show.columns.notes')); ?></th>
                                    <th><?php echo e(__('multilingual.committee_decision_show.columns.signed_at')); ?></th>
                                    <th><?php echo e(__('multilingual.committee_decision_show.columns.user')); ?></th>
                                    <th><?php echo e(__('multilingual.committee_decision_show.columns.action')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $decision->signatures->sortBy(fn ($signature) => $signature->committeeMember->sort_order ?? 0); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $signature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <?php
                                        $isLinkedToCurrentUser = ! $signature->committeeMember?->user_id || $signature->committeeMember?->user_id === auth()->id();
                                        $signatureLocked = $decision->isCompleted();
                                        $signatureReason = null;

                                        if (! $canSign) {
                                            $signatureReason = __('multilingual.committee_decision_show.reasons.no_permission');
                                        } elseif (! $signature->committeeMember?->is_active) {
                                            $signatureReason = __('multilingual.committee_decision_show.reasons.member_inactive');
                                        } elseif (! $isLinkedToCurrentUser) {
                                            $signatureReason = __('multilingual.committee_decision_show.reasons.linked_to_other_user');
                                        } elseif ($signatureLocked) {
                                            $signatureReason = __('multilingual.committee_decision_show.reasons.decision_completed');
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo e($signature->committeeMember?->name); ?></td>
                                        <td><?php echo e($signature->committeeMember?->title ?: '-'); ?></td>
                                        <td>
                                            <span class="badge badge-light-<?php echo e($signature->committeeMember?->is_required ? 'primary' : 'secondary'); ?>">
                                                <?php echo e($signature->committeeMember?->is_required ? __('multilingual.committee_decision_show.required_badge') : __('multilingual.committee_decision_show.optional_badge')); ?>

                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-<?php echo e($signature->status === 'approved' ? 'success' : ($signature->status === 'rejected' ? 'danger' : 'warning')); ?>">
                                                <?php echo e(__('multilingual.committee_decision_show.signature_statuses.'.$signature->status)); ?>

                                            </span>
                                        </td>
                                        <td><?php echo e($signature->notes ?: '-'); ?></td>
                                        <td><?php echo e(optional($signature->signed_at)->format('Y-m-d H:i') ?: '-'); ?></td>
                                        <td><?php echo e($signature->signedByUser?->name ?: '-'); ?></td>
                                        <td>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $signatureReason): ?>
                                                <form method="POST" action="<?php echo e(route('committee-decisions.sign', $decision)); ?>" class="d-flex gap-2 flex-wrap">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="committee_member_id" value="<?php echo e($signature->committee_member_id); ?>">
                                                    <select name="status" class="form-select form-select-sm form-select-solid w-125px">
                                                        <option value="approved"><?php echo e(__('multilingual.committee_decision_show.signature_statuses.approved')); ?></option>
                                                        <option value="rejected"><?php echo e(__('multilingual.committee_decision_show.signature_statuses.rejected')); ?></option>
                                                        <option value="pending"><?php echo e(__('multilingual.committee_decision_show.signature_statuses.pending')); ?></option>
                                                    </select>
                                                    <input type="text" name="notes" class="form-control form-control-sm form-control-solid w-175px" placeholder="<?php echo e(__('multilingual.committee_decision_show.columns.notes')); ?>">
                                                    <button type="submit" class="btn btn-light-primary btn-sm"><?php echo e(__('multilingual.committee_decision_show.submit_signature')); ?></button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo e($signatureReason); ?></span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/Committee/Decisions/show.blade.php ENDPATH**/ ?>