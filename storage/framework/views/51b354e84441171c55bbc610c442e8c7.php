<?php $__env->startSection('content'); ?>
<div id="kt_app_content_container" class="app-container container-xxl">
    <div id="roles_cards_wrapper" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-5 g-xl-9">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <?php
                $rolePermissions = $role->permissions->pluck('name');
                $visiblePermissions = $rolePermissions->take(5);
                $remainingCount = $rolePermissions->count() - $visiblePermissions->count();
            ?>

            <div class="col-md-4 role-card-item" id="role-card-<?php echo e($role->id); ?>" data-role-id="<?php echo e($role->id); ?>">
                <div class="card card-flush h-md-100">
                    <div class="card-header">
                        <div class="card-title">
                            <h2><?php echo e($role->name); ?></h2>
                        </div>
                    </div>

                    <div class="card-body pt-1">
                        <div class="fw-bold text-gray-600 mb-5">
                            Total users with this role: <?php echo e($role->users_count); ?>

                        </div>

                        <div class="d-flex flex-column text-gray-600">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $visiblePermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <div class="d-flex align-items-center py-2">
                                    <span class="bullet bg-primary me-3"></span>
                                    <?php echo e($permission); ?>

                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <div class="d-flex align-items-center py-2">
                                    <span class="bullet bg-secondary me-3"></span>
                                    <em>No permissions assigned</em>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($remainingCount > 0): ?>
                                <div class="d-flex align-items-center py-2">
                                    <span class="bullet bg-primary me-3"></span>
                                    <em>and <?php echo e($remainingCount); ?> more...</em>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="card-footer flex-wrap pt-0">
                        <button
                            type="button"
                            class="btn btn-light btn-active-light-primary my-1 me-2 btn-edit-role"
                            data-id="<?php echo e($role->id); ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#kt_modal_update_role"
                        >
                            Edit Role
                        </button>

                        <button
                            type="button"
                            class="btn btn-light btn-active-danger my-1 btn-delete-role"
                            data-id="<?php echo e($role->id); ?>"
                            data-name="<?php echo e($role->name); ?>"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

        <div class="col-md-4" id="add-role-card">
            <div class="card h-md-100">
                <div class="card-body d-flex flex-center">
                    <button
                        type="button"
                        class="btn btn-clear d-flex flex-column flex-center"
                        data-bs-toggle="modal"
                        data-bs-target="#kt_modal_add_role"
                    >
                        <img src="<?php echo e(asset('assets/media/illustrations/sketchy-1/4.png')); ?>" alt="" class="mw-100 mh-150px mb-7" />
                        <div class="fw-bold fs-3 text-gray-600 text-hover-primary">Add New Role</div>
                    </button>
                </div>
            </div>
        </div>

    </div>

    
    <div class="modal fade" id="kt_modal_add_role" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-750px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bold">Add a Role</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>

                <div class="modal-body scroll-y mx-lg-5 my-7">
                    <form id="kt_modal_add_role_form" class="form">
                        <?php echo csrf_field(); ?>

                        <div class="d-flex flex-column scroll-y me-n7 pe-7">
                            <div class="fv-row mb-10">
                                <label class="fs-5 fw-bold form-label mb-2">
                                    <span class="required">Role name</span>
                                </label>
                                <input class="form-control form-control-solid" placeholder="Enter a role name" name="name" />
                            </div>

                            <div class="fv-row">
                                <label class="fs-5 fw-bold form-label mb-2">Role Permissions</label>

                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                                        <tbody class="text-gray-600 fw-semibold">
                                            <tr>
                                                <td class="text-gray-800">All Permissions</td>
                                                <td>
                                                    <label class="form-check form-check-custom form-check-solid me-9">
                                                        <input class="form-check-input select-all-permissions" type="checkbox" />
                                                        <span class="form-check-label">Select all</span>
                                                    </label>
                                                </td>
                                            </tr>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <tr>
                                                    <td class="text-gray-800"><?php echo e($permission->name); ?></td>
                                                    <td>
                                                        <label class="form-check form-check-sm form-check-custom form-check-solid">
                                                            <input
                                                                class="form-check-input permission-checkbox"
                                                                type="checkbox"
                                                                name="permissions[]"
                                                                value="<?php echo e($permission->name); ?>"
                                                            />
                                                            <span class="form-check-label">Allow</span>
                                                        </label>
                                                    </td>
                                                </tr>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="text-center pt-15">
                            <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Discard</button>
                            <button type="submit" class="btn btn-primary" id="add_role_submit_btn">
                                <span class="indicator-label">Submit</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    
    <div class="modal fade" id="kt_modal_update_role" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-750px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bold">Update Role</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>

                <div class="modal-body scroll-y mx-5 my-7">
                    <form id="kt_modal_update_role_form" class="form">
                        <?php echo csrf_field(); ?>

                        <input type="hidden" id="edit_role_id">

                        <div class="d-flex flex-column scroll-y me-n7 pe-7">
                            <div class="fv-row mb-10">
                                <label class="fs-5 fw-bold form-label mb-2">
                                    <span class="required">Role name</span>
                                </label>
                                <input
                                    class="form-control form-control-solid"
                                    placeholder="Enter a role name"
                                    name="name"
                                    id="edit_role_name"
                                />
                            </div>

                            <div class="fv-row">
                                <label class="fs-5 fw-bold form-label mb-2">Role Permissions</label>

                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                                        <tbody class="text-gray-600 fw-semibold">
                                            <tr>
                                                <td class="text-gray-800">All Permissions</td>
                                                <td>
                                                    <label class="form-check form-check-custom form-check-solid me-9">
                                                        <input class="form-check-input select-all-permissions-edit" type="checkbox" />
                                                        <span class="form-check-label">Select all</span>
                                                    </label>
                                                </td>
                                            </tr>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                <tr>
                                                    <td class="text-gray-800"><?php echo e($permission->name); ?></td>
                                                    <td>
                                                        <label class="form-check form-check-sm form-check-custom form-check-solid">
                                                            <input
                                                                class="form-check-input edit-permission-checkbox"
                                                                type="checkbox"
                                                                name="permissions[]"
                                                                value="<?php echo e($permission->name); ?>"
                                                            />
                                                            <span class="form-check-label">Allow</span>
                                                        </label>
                                                    </td>
                                                </tr>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="text-center pt-15">
                            <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Discard</button>
                            <button type="submit" class="btn btn-primary" id="update_role_submit_btn">
                                <span class="indicator-label">Update</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
<script>
$(document).ready(function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    function showErrors(xhr) {
        console.log(xhr);

        if (xhr.responseJSON && xhr.responseJSON.errors) {
            let errors = Object.values(xhr.responseJSON.errors).flat().join('\n');
            alert(errors);
            return;
        }

        if (xhr.responseJSON && xhr.responseJSON.message) {
            alert(xhr.responseJSON.message);
            return;
        }

        alert('Something went wrong');
    }

    function escapeHtml(text) {
        return $('<div>').text(text ?? '').html();
    }

    function buildRoleCard(role) {
        let permissionsHtml = '';

        if (role.permissions && role.permissions.length > 0) {
            let visiblePermissions = role.permissions.slice(0, 5);
            let remainingCount = role.permissions.length - visiblePermissions.length;

            visiblePermissions.forEach(function(permission) {
                permissionsHtml += `
                    <div class="d-flex align-items-center py-2">
                        <span class="bullet bg-primary me-3"></span>
                        ${escapeHtml(permission)}
                    </div>
                `;
            });

            if (remainingCount > 0) {
                permissionsHtml += `
                    <div class="d-flex align-items-center py-2">
                        <span class="bullet bg-primary me-3"></span>
                        <em>and ${remainingCount} more...</em>
                    </div>
                `;
            }
        } else {
            permissionsHtml = `
                <div class="d-flex align-items-center py-2">
                    <span class="bullet bg-secondary me-3"></span>
                    <em>No permissions assigned</em>
                </div>
            `;
        }

        return `
            <div class="col-md-4 role-card-item" id="role-card-${role.id}" data-role-id="${role.id}">
                <div class="card card-flush h-md-100">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>${escapeHtml(role.name)}</h2>
                        </div>
                    </div>

                    <div class="card-body pt-1">
                        <div class="fw-bold text-gray-600 mb-5">
                            Total users with this role: ${role.users_count ?? 0}
                        </div>

                        <div class="d-flex flex-column text-gray-600">
                            ${permissionsHtml}
                        </div>
                    </div>

                    <div class="card-footer flex-wrap pt-0">
                        <button
                            type="button"
                            class="btn btn-light btn-active-light-primary my-1 me-2 btn-edit-role"
                            data-id="${role.id}"
                            data-bs-toggle="modal"
                            data-bs-target="#kt_modal_update_role"
                        >
                            Edit Role
                        </button>

                        <button
                            type="button"
                            class="btn btn-light btn-active-danger my-1 btn-delete-role"
                            data-id="${role.id}"
                            data-name="${escapeHtml(role.name)}"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    $('#kt_modal_add_role_form').on('submit', function(e) {
        e.preventDefault();

        let form = $(this);
        let btn = $('#add_role_submit_btn');

        btn.prop('disabled', true);

        $.ajax({
            url: "<?php echo e(route('roles.store')); ?>",
            type: "POST",
            data: form.serialize(),
            success: function(response) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_add_role')).hide();
                form[0].reset();
                $('.select-all-permissions').prop('checked', false);

                let newCardHtml = buildRoleCard(response.role);
                $('#add-role-card').before(newCardHtml);
            },
            error: function(xhr) {
                showErrors(xhr);
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.btn-edit-role', function() {
        let roleId = $(this).data('id');

        $('#kt_modal_update_role_form')[0].reset();
        $('.edit-permission-checkbox').prop('checked', false);
        $('.select-all-permissions-edit').prop('checked', false);

        $.ajax({
            url: "<?php echo e(url('/user-management/roles')); ?>/" + roleId + "/edit",
            type: 'GET',
            success: function(response) {
                $('#edit_role_id').val(response.role.id);
                $('#edit_role_name').val(response.role.name);

                let permissions = response.role.permissions ?? [];

                $('.edit-permission-checkbox').each(function() {
                    $(this).prop('checked', permissions.includes($(this).val()));
                });
            },
            error: function(xhr) {
                showErrors(xhr);
            }
        });
    });

    $('#kt_modal_update_role_form').on('submit', function(e) {
        e.preventDefault();

        let roleId = $('#edit_role_id').val();
        let btn = $('#update_role_submit_btn');

        btn.prop('disabled', true);

        $.ajax({
            url: "<?php echo e(url('/user-management/roles')); ?>/" + roleId,
            type: 'POST',
            data: $(this).serialize() + '&_method=PUT',
            success: function(response) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_update_role')).hide();

                let updatedCardHtml = buildRoleCard(response.role);
                $('#role-card-' + response.role.id).replaceWith(updatedCardHtml);
            },
            error: function(xhr) {
                showErrors(xhr);
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.btn-delete-role', function() {
        let roleId = $(this).data('id');
        let roleName = $(this).data('name');

        if (!confirm('Are you sure you want to delete role: ' + roleName + ' ?')) {
            return;
        }

        $.ajax({
            url: "<?php echo e(url('/user-management/roles')); ?>/" + roleId,
            type: 'POST',
            data: {
                _method: 'DELETE'
            },
            success: function() {
                $('#role-card-' + roleId).remove();
            },
            error: function(xhr) {
                showErrors(xhr);
            }
        });
    });

    $(document).on('change', '.select-all-permissions', function() {
        $('#kt_modal_add_role .permission-checkbox').prop('checked', $(this).is(':checked'));
    });

    $(document).on('change', '.select-all-permissions-edit', function() {
        $('#kt_modal_update_role .edit-permission-checkbox').prop('checked', $(this).is(':checked'));
    });
});
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/UserManagement/roles.blade.php ENDPATH**/ ?>