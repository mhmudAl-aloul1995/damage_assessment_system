<?php $__env->startSection('title', 'المستخدمين'); ?>
<?php $__env->startSection('pageName', 'المستخدمين'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <input
                    type="text"
                    data-kt-user-table-filter="search"
                    class="form-control form-control-solid w-250px ps-13"
                    placeholder="بحث مستخدم"
                />
            </div>
        </div>

        <div class="card-toolbar">
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-light-primary me-3" id="reload_users_table">
                    <i class="ki-duotone ki-arrows-circle fs-2"></i>
                    تحديث
                </button>

                <button
                    type="button"
                    class="btn btn-primary"
                    id="open_add_user_modal"
                >
                    <i class="ki-duotone ki-plus fs-2"></i>
                    إضافة مستخدم
                </button>
            </div>
        </div>
    </div>

    <div class="card-body py-4">
        <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_table_user">
            <thead>
                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                    <th class="w-10px pe-2"></th>
                    <th class="min-w-125px">إسم المستخدم</th>
                    <th class="min-w-125px">الإيميل</th>
                    <th class="min-w-125px">رقم الجوال</th>
                    <th class="min-w-125px">تاريخ الإنشاء</th>
                    <th class="text-end min-w-100px">إجراء</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 fw-semibold"></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="kt_modal_user" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_user_header">
                <h2 class="fw-bold" id="user_modal_title">إضافة مستخدم</h2>

                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <div class="modal-body px-5 my-7">
                <form id="kt_modal_user_form" class="form" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" id="user_id">

                    <div
                        class="d-flex flex-column scroll-y px-5 px-lg-10"
                        id="kt_modal_user_scroll"
                        data-kt-scroll="true"
                        data-kt-scroll-activate="true"
                        data-kt-scroll-max-height="auto"
                        data-kt-scroll-dependencies="#kt_modal_user_header"
                        data-kt-scroll-wrappers="#kt_modal_user_scroll"
                        data-kt-scroll-offset="300px"
                    >
                        <div class="fv-row mb-7">
                            <label class="d-block fw-semibold fs-6 mb-5">الصورة الشخصية</label>

                            <style>
                                .image-input-placeholder {
                                    background-image: url('<?php echo e(asset('assets/media/svg/files/blank-image.svg')); ?>');
                                }

                                [data-bs-theme="dark"] .image-input-placeholder {
                                    background-image: url('<?php echo e(asset('assets/media/svg/files/blank-image-dark.svg')); ?>');
                                }
                            </style>

                            <div class="image-input image-input-outline image-input-placeholder" data-kt-image-input="true">
                                <div class="image-input-wrapper w-125px h-125px" id="user_avatar_preview"></div>

                                <label
                                    class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="change"
                                    data-bs-toggle="tooltip"
                                    title="تغيير الصورة الشخصية"
                                >
                                    <i class="ki-duotone ki-pencil fs-7">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <input type="file" name="avatar" accept=".png, .jpg, .jpeg" />
                                    <input type="hidden" name="avatar_remove" />
                                </label>

                                <span
                                    class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="cancel"
                                    data-bs-toggle="tooltip"
                                    title="إلغاء الصورة"
                                >
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>

                                <span
                                    class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="remove"
                                    data-bs-toggle="tooltip"
                                    title="حذف الصورة"
                                >
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>
                            </div>

                            <div class="form-text">Allowed file types: png, jpg, jpeg.</div>
                        </div>

                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">الإسم كامل</label>
                            <input
                                type="text"
                                name="name"
                                class="form-control form-control-solid"
                                placeholder="الإسم كامل"
                            />
                        </div>

                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">الإيميل</label>
                            <input
                                type="email"
                                name="email"
                                class="form-control form-control-solid"
                                placeholder="example@domain.com"
                            />
                        </div>

                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">العنوان</label>
                            <input
                                type="text"
                                name="address"
                                class="form-control form-control-solid"
                                placeholder="العنوان"
                            />
                        </div>

                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">رقم الجوال</label>
                            <input
                                type="text"
                                name="phone"
                                class="form-control form-control-solid"
                                placeholder="رقم الجوال"
                            />
                        </div>

                        <div class="mb-5">
                            <label class="required fw-semibold fs-6 mb-5">الدور</label>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <div class="d-flex fv-row">
                                    <div class="form-check form-check-custom form-check-solid">
                                        <input
                                            class="form-check-input me-3"
                                            name="role"
                                            type="radio"
                                            value="<?php echo e($role->name); ?>"
                                            id="role_option_<?php echo e($role->id); ?>"
                                            <?php echo e($loop->first ? 'checked' : ''); ?>

                                        />
                                        <label class="form-check-label" for="role_option_<?php echo e($role->id); ?>">
                                            <div class="fw-bold text-gray-800"><?php echo e($role->name); ?></div>
                                        </label>
                                    </div>
                                </div>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->last): ?>
                                    <div class="separator separator-dashed my-5"></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>

                    <div class="text-center pt-10">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">
                            إلغاء
                        </button>

                        <button type="submit" class="btn btn-primary" id="save_user_btn">
                            <span class="indicator-label">حفظ</span>
                            <span class="indicator-progress">
                                إنتظر قليلاً...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
<script src="<?php echo e(asset('assets/js/widgets.bundle.js')); ?>"></script>

<script>
    let usersTable;

    function showSwalError(message) {
        Swal.fire({
            html: message,
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "حاول مجدداً",
            customClass: {
                confirmButton: "btn btn-danger"
            }
        });
    }

    function resetUserForm() {
        const form = document.getElementById('kt_modal_user_form');
        form.reset();

        $('#user_id').val('');
        $('#user_modal_title').text('إضافة مستخدم');
        $('#user_avatar_preview').css('background-image', 'none');

        const firstRole = $('#kt_modal_user_form input[name="role"]').first();
        if (firstRole.length) {
            firstRole.prop('checked', true);
        }
    }

    function openUserModalForCreate() {
        resetUserForm();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_user')).show();
    }

    function showUser(id) {
        $.ajax({
            url: "<?php echo e(url('user-management/user')); ?>/" + id + "/edit",
            type: "GET",
            success: function(response) {
                resetUserForm();

                const user = response.user;

                $('#user_modal_title').text('تعديل مستخدم');
                $('#user_id').val(user.id);
                $('#kt_modal_user_form input[name="name"]').val(user.name ?? '');
                $('#kt_modal_user_form input[name="email"]').val(user.email ?? '');
                $('#kt_modal_user_form input[name="phone"]').val(user.phone ?? '');
                $('#kt_modal_user_form input[name="address"]').val(user.address ?? '');

                if (response.role) {
                    $('#kt_modal_user_form input[name="role"][value="' + response.role + '"]').prop('checked', true);
                }

                if (user.avatar_url) {
                    $('#user_avatar_preview').css('background-image', 'url(' + user.avatar_url + ')');
                }

                bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_user')).show();
            },
            error: function(xhr) {
                console.log(xhr);
                showSwalError('فشل تحميل بيانات المستخدم');
            }
        });
    }

    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        usersTable = $('#kt_table_user').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: "<?php echo e(url('user-management/user/show')); ?>"
            },
            info: false,
            order: [],
            pageLength: 10,
            lengthChange: false,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json'
            },
            columns: [
                {
                    data: 'checkbox',
                    name: 'checkbox',
                    searchable: false,
                    orderable: false
                },
                {
                    data: 'name',
                    name: 'name'
                },
                {
                    data: 'email',
                    name: 'email'
                },
                {
                    data: 'phone',
                    name: 'phone'
                },
                {
                    data: 'created_at',
                    name: 'created_at'
                },
                {
                    data: 'action',
                    name: 'action',
                    searchable: false,
                    orderable: false
                }
            ],
            drawCallback: function() {
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
            }
        });

        $('[data-kt-user-table-filter="search"]').on('keyup', function() {
            usersTable.search(this.value).draw();
        });

        $('#reload_users_table').on('click', function() {
            usersTable.ajax.reload(null, false);
        });

        $('#open_add_user_modal').on('click', function() {
            openUserModalForCreate();
        });

        $('#kt_modal_user_form').on('submit', function(e) {
            e.preventDefault();

            let form = this;
            let formData = new FormData(form);
            let userId = $('#user_id').val();
            let submitButton = $('#save_user_btn');

            let url = userId
                ? "<?php echo e(url('user-management/user')); ?>/" + userId
                : "<?php echo e(route('users.store')); ?>";

            if (userId) {
                formData.append('_method', 'PUT');
            }

            submitButton.attr('data-kt-indicator', 'on').prop('disabled', true);

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    submitButton.removeAttr('data-kt-indicator').prop('disabled', false);

                    Swal.fire({
                        text: response.message ?? 'تم حفظ المستخدم بنجاح',
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: 'موافق',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    }).then(function() {
                        resetUserForm();
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_user')).hide();
                        usersTable.ajax.reload(null, false);
                    });
                },
                error: function(xhr) {
                    submitButton.removeAttr('data-kt-indicator').prop('disabled', false);

                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        let errors = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                        showSwalError(errors);
                        return;
                    }

                    showSwalError(xhr.responseJSON?.message ?? 'حدث خطأ غير متوقع');
                }
            });
        });
    });
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/UserManagement/users.blade.php ENDPATH**/ ?>