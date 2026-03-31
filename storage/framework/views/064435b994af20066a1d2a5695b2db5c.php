<?php $__env->startSection('title', 'الإستبيان'); ?>
<?php $__env->startSection('pageName', 'الإستبيان'); ?>

<?php $__env->startSection('content'); ?>
    <?php
        $user = auth()->user();
    ?>

    <div class="card mb-5 mb-xl-10">
        <div class="card-body pt-9 pb-0">
            <div class="d-flex flex-wrap flex-sm-nowrap">
                <div class="me-7 mb-4">
                    <div class="symbol symbol-100px symbol-lg-160px symbol-fixed position-relative">
                        <img src="<?php echo e($user->avatar ? asset('storage/' . $user->avatar) : asset('assets/media/avatars/blank.png')); ?>"
                            alt="<?php echo e($user->name); ?>" class="w-100px h-100px rounded" style="object-fit: cover;">
                        <div
                            class="position-absolute translate-middle bottom-0 start-100 mb-6 bg-success rounded-circle border border-4 border-body h-20px w-20px">
                        </div>
                    </div>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                        <div class="d-flex flex-column min-w-0">
                            <div class="d-flex align-items-center mb-2 min-w-0">
                                <a href="#" class="text-gray-900 text-hover-primary fw-bold me-1 text-truncate fs-4 fs-md-2"
                                    style="max-width: 250px;">
                                    <?php echo e($user->name); ?>

                                </a>

                                <a href="#">
                                    <i class="ki-duotone ki-verify fs-1 text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </a>
                            </div>

                            <div class="d-flex flex-wrap fw-semibold fs-6 mb-4 pe-2">
                                <a href="#" class="d-flex align-items-center text-gray-400 text-hover-primary me-5 mb-2">
                                    <i class="ki-duotone ki-profile-circle fs-4 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <?php echo e($user->getRoleNames()->first() ?? 'No Role'); ?>

                                </a>

                                <a href="#" class="d-flex align-items-center text-gray-400 text-hover-primary me-5 mb-2">
                                    <i class="ki-duotone ki-geolocation fs-4 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php echo e($user->address ?? '-'); ?>

                                </a>

                                <a href="#"
                                    class="d-flex align-items-center text-gray-400 text-hover-primary mb-2 text-truncate">
                                    <i class="ki-duotone ki-sms fs-4 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php echo e($user->email); ?>

                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap flex-stack">
                        <div class="d-flex flex-column flex-grow-1 pe-8">
                            <div class="d-flex flex-wrap"></div>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                <li class="nav-item mt-2">
                    <a onclick="$(this).addClass('active');$('.setting').removeClass('active'); $('#kt_profile_details_view').fadeIn();$('#kt_profile_details_edit').fadeOut();"
                        class="public nav-link text-active-primary ms-0 me-10 py-5 active" href="javascript:void(0)">عام</a>
                </li>

                <li class="nav-item mt-2">
                    <a onclick="$(this).addClass('active');$('.public').removeClass('active'); $('#kt_profile_details_view').fadeOut();$('#kt_profile_details_edit').fadeIn();"
                        class="setting nav-link text-active-primary ms-0 me-10 py-5" href="javascript:void(0)">الإعدادات</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="card mb-5 mb-xl-10" id="kt_profile_details_view">
        <div class="card-header cursor-pointer">
            <div class="card-title m-0">
                <h3 class="fw-bold m-0">تفاصيل الملف الشخصي</h3>
            </div>

            <a href="javascript:void(0)"
                onclick="$('.setting').addClass('active');$('.public').removeClass('active'); $('#kt_profile_details_view').fadeOut();$('#kt_profile_details_edit').fadeIn();"
                class="btn btn-sm btn-primary align-self-center">
                تعديل الملف الشخصي
            </a>
        </div>

        <div class="card-body p-9">
            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">إسم المستخدم</label>
                <div class="col-lg-8">
                    <span class="fw-bold fs-6 text-gray-800"><?php echo e($user->name); ?></span>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">الدور</label>
                <div class="col-lg-8 fv-row">
                    <span class="fw-semibold text-gray-800 fs-6">
                        <?php echo e($user->getRoleNames()->first() ?? 'No Role'); ?>

                    </span>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">
                    رقم الجوال
                    <span class="ms-1" data-bs-toggle="tooltip" title="Phone number must be active">
                        <i class="ki-duotone ki-information fs-7">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </span>
                </label>
                <div class="col-lg-8 d-flex align-items-center">
                    <span class="fw-bold fs-6 text-gray-800 me-2"><?php echo e($user->phone ?? '-'); ?></span>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">البريد الإلكتروني</label>
                <div class="col-lg-8">
                    <a href="#" class="fw-semibold fs-6 text-gray-800 text-hover-primary"><?php echo e($user->email); ?></a>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">العنوان</label>
                <div class="col-lg-8">
                    <a href="#" class="fw-semibold fs-6 text-gray-800 text-hover-primary">
                        <?php echo e($user->address ?? '-'); ?>

                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-5 mb-xl-10" style="display: none;" id="kt_profile_details_edit">
        <div class="card-header border-0 cursor-pointer" role="button" data-bs-toggle="collapse"
            data-bs-target="#kt_account_settings_profile_details" aria-expanded="true"
            aria-controls="kt_account_settings_profile_details">
            <div class="card-title m-0">
                <h3 class="fw-bold m-0">تفاصيل الملف الشخصي</h3>
            </div>
        </div>

        <div id="kt_account_settings_profile_details" class="collapse show">
            <form id="kt_account_profile_details_form" action="<?php echo e(route('profile.update')); ?>" method="POST"
                enctype="multipart/form-data" class="form">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <div class="card-body border-top p-9">
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">الصورة الشخصية</label>
                        <div class="col-lg-8">
                            <div class="image-input image-input-outline" data-kt-image-input="true"
                                style="background-image: url('<?php echo e(asset('assets/media/svg/avatars/blank.svg')); ?>')">
                                <div class="image-input-wrapper w-125px h-125px"
                                    style="background-image: url('<?php echo e($user->avatar ? asset('storage/' . $user->avatar) : asset('assets/media/avatars/300-1.jpg')); ?>')">
                                </div>

                                <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="change" data-bs-toggle="tooltip"
                                    title="تغيير الصورة الشخصية">
                                    <i class="ki-duotone ki-pencil fs-7">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <input type="file" name="avatar" accept=".png, .jpg, .jpeg" />
                                    <input type="hidden" name="avatar_remove" />
                                </label>

                                <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="cancel" data-bs-toggle="tooltip"
                                    title="إلغاء الصورة الشخصية">
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>

                                <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="remove" data-bs-toggle="tooltip" title="حذف الصورة الشخصية">
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>
                            </div>

                            <div class="form-text">أنواع الملفات المسموح بها: png, jpg, jpeg.</div>
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label required fw-semibold fs-6">الإسم بالكامل</label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="name" class="form-control form-control-lg form-control-solid"
                                placeholder="الاسم الكامل" value="<?php echo e($user->name); ?>" />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">الدور</label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" class="form-control form-control-lg form-control-solid"
                                value="<?php echo e($user->getRoleNames()->first() ?? 'No Role'); ?>" readonly />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">
                            <span class="required">رقم الجوال</span>
                        </label>
                        <div class="col-lg-8 fv-row">
                            <input type="tel" name="phone" class="form-control form-control-lg form-control-solid"
                                placeholder="رقم الجوال" value="<?php echo e($user->phone); ?>" />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">البريد الإلكتروني</label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="email" class="form-control form-control-lg form-control-solid"
                                placeholder="البريد الإلكتروني" value="<?php echo e($user->email); ?>" />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">العنوان</label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="address" class="form-control form-control-lg form-control-solid"
                                placeholder="العنوان" value="<?php echo e($user->address); ?>" />
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <button type="reset" class="btn btn-light btn-active-light-primary me-2">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="kt_account_profile_details_submit">
                        حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        "use strict";

        var KTAccountSettingsProfileDetails = function () {
            var form;
            var submitButton;
            var validation;

            var initValidation = function () {
                validation = FormValidation.formValidation(form, {
                    fields: {
                        name: {
                            validators: {
                                notEmpty: {
                                    message: 'الإسم مطلوب'
                                }
                            }
                        },
                        email: {
                            validators: {
                                notEmpty: {
                                    message: 'البريد الإلكتروني مطلوب'
                                },
                                emailAddress: {
                                    message: 'البريد الإلكتروني غير صحيح'
                                }
                            }
                        },
                        phone: {
                            validators: {
                                notEmpty: {
                                    message: 'رقم الجوال مطلوب'
                                }
                            }
                        }
                    },
                    plugins: {
                        trigger: new FormValidation.plugins.Trigger(),
                        submitButton: new FormValidation.plugins.SubmitButton(),
                        bootstrap: new FormValidation.plugins.Bootstrap5({
                            rowSelector: '.fv-row',
                            eleInvalidClass: '',
                            eleValidClass: ''
                        })
                    }
                });
            };

            var handleForm = function () {
                submitButton.addEventListener('click', function (e) {
                    e.preventDefault();

                    validation.validate().then(function (status) {
                        if (status === 'Valid') {
                            submitButton.setAttribute('data-kt-indicator', 'on');
                            submitButton.disabled = true;

                            fetch(form.getAttribute('action'), {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                                body: new FormData(form)
                            })
                                .then(async response => {
                                    const data = await response.json().catch(() => null);

                                    if (!response.ok) {
                                        let msg = 'عذراً، يبدو أنه تم اكتشاف بعض الأخطاء.';

                                        if (response.status === 419) {
                                            msg = 'انتهت صلاحية الجلسة، يرجى تحديث الصفحة.';
                                        } else if (data && data.message) {
                                            msg = data.message;
                                        }

                                        throw new Error(msg);
                                    }

                                    return data;
                                })
                                .then(() => {
                                    Swal.fire({
                                        text: "شكراً لك! لقد قمت بتحديث معلوماتك الأساسية",
                                        icon: "success",
                                        confirmButtonText: "حسناً، فهمت!",
                                        customClass: {
                                            confirmButton: "btn fw-bold btn-light-primary"
                                        }
                                    }).then(() => {
                                        location.reload();
                                    });
                                })
                                .catch(function (error) {
                                    Swal.fire({
                                        text: error.message ||
                                            "عذراً، يبدو أنه تم اكتشاف بعض الأخطاء.",
                                        icon: "error",
                                        confirmButtonText: "حسناً، فهمت!",
                                        customClass: {
                                            confirmButton: "btn fw-bold btn-light-primary"
                                        }
                                    });
                                })
                                .finally(() => {
                                    submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                                });
                        }
                    });
                });
            };

            return {
                init: function () {
                    form = document.getElementById('kt_account_profile_details_form');

                    if (!form) {
                        return;
                    }

                    submitButton = document.getElementById('kt_account_profile_details_submit');

                    initValidation();
                    handleForm();
                }
            };
        }();

        KTUtil.onDOMContentLoaded(function () {
            KTAccountSettingsProfileDetails.init();
        });
    </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/profile/edit.blade.php ENDPATH**/ ?>