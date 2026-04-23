<?php $__env->startSection('title', __('ui.profile.title')); ?>
<?php $__env->startSection('pageName', __('ui.profile.title')); ?>

<?php $__env->startSection('content'); ?>
    <?php
        $user = auth()->user();
        $showSettings = session('status') === 'password-updated' || $errors->updatePassword->any();
    ?>

    <div class="card mb-5 mb-xl-10">
        <div class="card-body pt-9 pb-0">
            <div class="d-flex flex-wrap flex-sm-nowrap">
                <div class="me-7 mb-4">
                    <div class="symbol symbol-100px symbol-lg-160px symbol-fixed position-relative">
                        <img src="<?php echo e($user->avatar ? asset('storage/' . $user->avatar) : asset('assets/media/avatars/blank.png')); ?>"
                            alt="<?php echo e($user->name); ?>" class="w-100px h-100px rounded" style="object-fit: cover;">
                        <div class="position-absolute translate-middle bottom-0 start-100 mb-6 bg-success rounded-circle border border-4 border-body h-20px w-20px"></div>
                    </div>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                        <div class="d-flex flex-column min-w-0">
                            <div class="d-flex align-items-center mb-2 min-w-0">
                                <a href="#" class="text-gray-900 text-hover-primary fw-bold me-1 text-truncate fs-4 fs-md-2" style="max-width: 250px;">
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
                                    <?php echo e($user->getRoleNames()->first() ?? __('ui.profile.no_role')); ?>

                                </a>

                                <a href="#" class="d-flex align-items-center text-gray-400 text-hover-primary me-5 mb-2">
                                    <i class="ki-duotone ki-geolocation fs-4 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php echo e($user->address ?? '-'); ?>

                                </a>

                                <a href="#" class="d-flex align-items-center text-gray-400 text-hover-primary mb-2 text-truncate">
                                    <i class="ki-duotone ki-sms fs-4 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php echo e($user->email); ?>

                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                <li class="nav-item mt-2">
                    <a onclick="$(this).addClass('active');$('.setting').removeClass('active'); $('#kt_profile_details_view').fadeIn();$('#kt_profile_details_edit').fadeOut();"
                        class="public nav-link text-active-primary ms-0 me-10 py-5 <?php echo e($showSettings ? '' : 'active'); ?>" href="javascript:void(0)"><?php echo e(__('ui.profile.general')); ?></a>
                </li>

                <li class="nav-item mt-2">
                    <a onclick="$(this).addClass('active');$('.public').removeClass('active'); $('#kt_profile_details_view').fadeOut();$('#kt_profile_details_edit').fadeIn();"
                        class="setting nav-link text-active-primary ms-0 me-10 py-5 <?php echo e($showSettings ? 'active' : ''); ?>" href="javascript:void(0)"><?php echo e(__('ui.profile.settings')); ?></a>
                </li>
            </ul>
        </div>
    </div>

    <div class="card mb-5 mb-xl-10" id="kt_profile_details_view" style="<?php echo e($showSettings ? 'display: none;' : ''); ?>">
        <div class="card-header cursor-pointer">
            <div class="card-title m-0">
                <h3 class="fw-bold m-0"><?php echo e(__('ui.profile.details')); ?></h3>
            </div>

            <a href="javascript:void(0)"
                onclick="$('.setting').addClass('active');$('.public').removeClass('active'); $('#kt_profile_details_view').fadeOut();$('#kt_profile_details_edit').fadeIn();"
                class="btn btn-sm btn-primary align-self-center">
                <?php echo e(__('ui.profile.edit')); ?>

            </a>
        </div>

        <div class="card-body p-9">
            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted"><?php echo e(__('ui.profile.full_name')); ?></label>
                <div class="col-lg-8">
                    <span class="fw-bold fs-6 text-gray-800"><?php echo e($user->name); ?></span>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted"><?php echo e(__('ui.profile.role')); ?></label>
                <div class="col-lg-8 fv-row">
                    <span class="fw-semibold text-gray-800 fs-6">
                        <?php echo e($user->getRoleNames()->first() ?? __('ui.profile.no_role')); ?>

                    </span>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">
                    <?php echo e(__('ui.profile.phone')); ?>

                    <span class="ms-1" data-bs-toggle="tooltip" title="<?php echo e(__('ui.profile.phone_tooltip')); ?>">
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
                <label class="col-lg-4 fw-semibold text-muted"><?php echo e(__('ui.profile.email')); ?></label>
                <div class="col-lg-8">
                    <a href="#" class="fw-semibold fs-6 text-gray-800 text-hover-primary"><?php echo e($user->email); ?></a>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted"><?php echo e(__('ui.profile.address')); ?></label>
                <div class="col-lg-8">
                    <a href="#" class="fw-semibold fs-6 text-gray-800 text-hover-primary">
                        <?php echo e($user->address ?? '-'); ?>

                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-5 mb-xl-10" style="<?php echo e($showSettings ? '' : 'display: none;'); ?>" id="kt_profile_details_edit">
        <div class="card-header border-0 cursor-pointer" role="button" data-bs-toggle="collapse"
            data-bs-target="#kt_account_settings_profile_details" aria-expanded="true"
            aria-controls="kt_account_settings_profile_details">
            <div class="card-title m-0">
                <h3 class="fw-bold m-0"><?php echo e(__('ui.profile.details')); ?></h3>
            </div>
        </div>

        <div id="kt_account_settings_profile_details" class="collapse show">
            <form id="kt_account_profile_details_form" action="<?php echo e(route('profile.update')); ?>" method="POST"
                enctype="multipart/form-data" class="form">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <div class="card-body border-top p-9">
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6"><?php echo e(__('ui.profile.avatar')); ?></label>
                        <div class="col-lg-8">
                            <div class="image-input image-input-outline" data-kt-image-input="true"
                                style="background-image: url('<?php echo e(asset('assets/media/svg/avatars/blank.svg')); ?>')">
                                <div class="image-input-wrapper w-125px h-125px"
                                    style="background-image: url('<?php echo e($user->avatar ? asset('storage/' . $user->avatar) : asset('assets/media/avatars/300-1.jpg')); ?>')">
                                </div>

                                <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="change" data-bs-toggle="tooltip"
                                    title="<?php echo e(__('ui.profile.change_avatar')); ?>">
                                    <i class="ki-duotone ki-pencil fs-7">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <input type="file" name="avatar" accept=".png, .jpg, .jpeg" />
                                    <input type="hidden" name="avatar_remove" />
                                </label>

                                <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="cancel" data-bs-toggle="tooltip"
                                    title="<?php echo e(__('ui.profile.cancel_avatar')); ?>">
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>

                                <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="remove" data-bs-toggle="tooltip" title="<?php echo e(__('ui.profile.remove_avatar')); ?>">
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>
                            </div>

                            <div class="form-text"><?php echo e(__('ui.profile.allowed_types')); ?></div>
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label required fw-semibold fs-6"><?php echo e(__('ui.profile.full_name')); ?></label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="name" class="form-control form-control-lg form-control-solid"
                                placeholder="<?php echo e(__('ui.profile.full_name')); ?>" value="<?php echo e($user->name); ?>" />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6"><?php echo e(__('ui.profile.role')); ?></label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" class="form-control form-control-lg form-control-solid"
                                value="<?php echo e($user->getRoleNames()->first() ?? __('ui.profile.no_role')); ?>" readonly />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">
                            <span class="required"><?php echo e(__('ui.profile.phone')); ?></span>
                        </label>
                        <div class="col-lg-8 fv-row">
                            <input type="tel" name="phone" class="form-control form-control-lg form-control-solid"
                                placeholder="<?php echo e(__('ui.profile.phone')); ?>" value="<?php echo e($user->phone); ?>" />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6"><?php echo e(__('ui.profile.email')); ?></label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="email" class="form-control form-control-lg form-control-solid"
                                placeholder="<?php echo e(__('ui.profile.email')); ?>" value="<?php echo e($user->email); ?>" />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6"><?php echo e(__('ui.profile.address')); ?></label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="address" class="form-control form-control-lg form-control-solid"
                                placeholder="<?php echo e(__('ui.profile.address')); ?>" value="<?php echo e($user->address); ?>" />
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <button type="reset" class="btn btn-light btn-active-light-primary me-2"><?php echo e(__('ui.buttons.cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="kt_account_profile_details_submit">
                        <?php echo e(__('ui.buttons.save')); ?>

                    </button>
                </div>
            </form>

            <div class="separator mx-9"></div>

            <form action="<?php echo e(route('password.update')); ?>" method="POST" class="form">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <div class="card-body border-top p-9">
                    <div class="row mb-6">
                        <div class="col-lg-4">
                            <h4 class="fw-bold mb-1"><?php echo e(__('ui.profile.change_password_title')); ?></h4>
                            <div class="text-muted fs-7"><?php echo e(__('ui.profile.change_password_hint')); ?></div>
                        </div>
                        <div class="col-lg-8">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status') === 'password-updated'): ?>
                                <div class="alert alert-success d-flex align-items-center p-5 mb-6">
                                    <i class="ki-duotone ki-shield-tick fs-2hx text-success me-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                    <div class="d-flex flex-column">
                                        <span><?php echo e(__('ui.messages.password_updated')); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label required fw-semibold fs-6"><?php echo e(__('ui.profile.current_password')); ?></label>
                        <div class="col-lg-8 fv-row">
                            <input type="password" name="current_password" autocomplete="current-password"
                                class="form-control form-control-lg form-control-solid <?php if($errors->updatePassword->has('current_password')): ?> is-invalid <?php endif; ?>"
                                placeholder="<?php echo e(__('ui.profile.current_password')); ?>" />
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->updatePassword->has('current_password')): ?>
                                <div class="invalid-feedback d-block">
                                    <?php echo e($errors->updatePassword->first('current_password')); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label required fw-semibold fs-6"><?php echo e(__('ui.profile.new_password')); ?></label>
                        <div class="col-lg-8 fv-row">
                            <input type="password" name="new_password" autocomplete="new-password"
                                class="form-control form-control-lg form-control-solid <?php if($errors->updatePassword->has('new_password')): ?> is-invalid <?php endif; ?>"
                                placeholder="<?php echo e(__('ui.profile.new_password')); ?>" />
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->updatePassword->has('new_password')): ?>
                                <div class="invalid-feedback d-block">
                                    <?php echo e($errors->updatePassword->first('new_password')); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-0">
                        <label class="col-lg-4 col-form-label required fw-semibold fs-6"><?php echo e(__('ui.profile.new_password_confirmation')); ?></label>
                        <div class="col-lg-8 fv-row">
                            <input type="password" name="new_password_confirmation" autocomplete="new-password"
                                class="form-control form-control-lg form-control-solid <?php if($errors->updatePassword->has('new_password_confirmation')): ?> is-invalid <?php endif; ?>"
                                placeholder="<?php echo e(__('ui.profile.new_password_confirmation')); ?>" />
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->updatePassword->has('new_password_confirmation')): ?>
                                <div class="invalid-feedback d-block">
                                    <?php echo e($errors->updatePassword->first('new_password_confirmation')); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <button type="submit" class="btn btn-primary">
                        <?php echo e(__('ui.profile.change_password_action')); ?>

                    </button>
                </div>
            </form>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        "use strict";

        var profileTranslations = {
            nameRequired: <?php echo json_encode(__('validation.required', ['attribute' => __('validation.attributes.name')]), 512) ?>,
            emailRequired: <?php echo json_encode(__('validation.required', ['attribute' => __('validation.attributes.email')]), 512) ?>,
            emailInvalid: <?php echo json_encode(__('validation.email', ['attribute' => __('validation.attributes.email')]), 512) ?>,
            phoneRequired: <?php echo json_encode(__('validation.required', ['attribute' => __('validation.attributes.phone')]), 512) ?>,
            validationError: <?php echo json_encode(__('ui.messages.validation_error'), 15, 512) ?>,
            sessionExpired: <?php echo json_encode(__('ui.messages.session_expired'), 15, 512) ?>,
            success: <?php echo json_encode(__('ui.messages.profile_updated'), 15, 512) ?>,
            ok: <?php echo json_encode(__('ui.buttons.ok'), 15, 512) ?>,
            error: <?php echo json_encode(__('ui.messages.unexpected_error'), 15, 512) ?>,
        };

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
                                    message: profileTranslations.nameRequired
                                }
                            }
                        },
                        email: {
                            validators: {
                                notEmpty: {
                                    message: profileTranslations.emailRequired
                                },
                                emailAddress: {
                                    message: profileTranslations.emailInvalid
                                }
                            }
                        },
                        phone: {
                            validators: {
                                notEmpty: {
                                    message: profileTranslations.phoneRequired
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
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                                body: new FormData(form)
                            })
                                .then(async response => {
                                    const data = await response.json().catch(() => null);

                                    if (!response.ok) {
                                        let msg = profileTranslations.validationError;

                                        if (response.status === 419) {
                                            msg = profileTranslations.sessionExpired;
                                        } else if (data && data.message) {
                                            msg = data.message;
                                        }

                                        throw new Error(msg);
                                    }

                                    return data;
                                })
                                .then(() => {
                                    Swal.fire({
                                        text: profileTranslations.success,
                                        icon: "success",
                                        confirmButtonText: profileTranslations.ok,
                                        customClass: {
                                            confirmButton: "btn fw-bold btn-light-primary"
                                        }
                                    }).then(() => {
                                        location.reload();
                                    });
                                })
                                .catch(function (error) {
                                    Swal.fire({
                                        text: error.message || profileTranslations.error,
                                        icon: "error",
                                        confirmButtonText: profileTranslations.ok,
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