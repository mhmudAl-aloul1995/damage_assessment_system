@extends('layouts.app')

@section('title', __('ui.profile.title'))
@section('pageName', __('ui.profile.title'))

@section('content')
    @php
        $user = auth()->user();
    @endphp

    <div class="card mb-5 mb-xl-10">
        <div class="card-body pt-9 pb-0">
            <div class="d-flex flex-wrap flex-sm-nowrap">
                <div class="me-7 mb-4">
                    <div class="symbol symbol-100px symbol-lg-160px symbol-fixed position-relative">
                        <img src="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('assets/media/avatars/blank.png') }}"
                            alt="{{ $user->name }}" class="w-100px h-100px rounded" style="object-fit: cover;">
                        <div class="position-absolute translate-middle bottom-0 start-100 mb-6 bg-success rounded-circle border border-4 border-body h-20px w-20px"></div>
                    </div>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                        <div class="d-flex flex-column min-w-0">
                            <div class="d-flex align-items-center mb-2 min-w-0">
                                <a href="#" class="text-gray-900 text-hover-primary fw-bold me-1 text-truncate fs-4 fs-md-2" style="max-width: 250px;">
                                    {{ $user->name }}
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
                                    {{ $user->getRoleNames()->first() ?? __('ui.profile.no_role') }}
                                </a>

                                <a href="#" class="d-flex align-items-center text-gray-400 text-hover-primary me-5 mb-2">
                                    <i class="ki-duotone ki-geolocation fs-4 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    {{ $user->address ?? '-' }}
                                </a>

                                <a href="#" class="d-flex align-items-center text-gray-400 text-hover-primary mb-2 text-truncate">
                                    <i class="ki-duotone ki-sms fs-4 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    {{ $user->email }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                <li class="nav-item mt-2">
                    <a onclick="$(this).addClass('active');$('.setting').removeClass('active'); $('#kt_profile_details_view').fadeIn();$('#kt_profile_details_edit').fadeOut();"
                        class="public nav-link text-active-primary ms-0 me-10 py-5 active" href="javascript:void(0)">{{ __('ui.profile.general') }}</a>
                </li>

                <li class="nav-item mt-2">
                    <a onclick="$(this).addClass('active');$('.public').removeClass('active'); $('#kt_profile_details_view').fadeOut();$('#kt_profile_details_edit').fadeIn();"
                        class="setting nav-link text-active-primary ms-0 me-10 py-5" href="javascript:void(0)">{{ __('ui.profile.settings') }}</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="card mb-5 mb-xl-10" id="kt_profile_details_view">
        <div class="card-header cursor-pointer">
            <div class="card-title m-0">
                <h3 class="fw-bold m-0">{{ __('ui.profile.details') }}</h3>
            </div>

            <a href="javascript:void(0)"
                onclick="$('.setting').addClass('active');$('.public').removeClass('active'); $('#kt_profile_details_view').fadeOut();$('#kt_profile_details_edit').fadeIn();"
                class="btn btn-sm btn-primary align-self-center">
                {{ __('ui.profile.edit') }}
            </a>
        </div>

        <div class="card-body p-9">
            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">{{ __('ui.profile.full_name') }}</label>
                <div class="col-lg-8">
                    <span class="fw-bold fs-6 text-gray-800">{{ $user->name }}</span>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">{{ __('ui.profile.role') }}</label>
                <div class="col-lg-8 fv-row">
                    <span class="fw-semibold text-gray-800 fs-6">
                        {{ $user->getRoleNames()->first() ?? __('ui.profile.no_role') }}
                    </span>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">
                    {{ __('ui.profile.phone') }}
                    <span class="ms-1" data-bs-toggle="tooltip" title="{{ __('ui.profile.phone_tooltip') }}">
                        <i class="ki-duotone ki-information fs-7">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </span>
                </label>
                <div class="col-lg-8 d-flex align-items-center">
                    <span class="fw-bold fs-6 text-gray-800 me-2">{{ $user->phone ?? '-' }}</span>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">{{ __('ui.profile.email') }}</label>
                <div class="col-lg-8">
                    <a href="#" class="fw-semibold fs-6 text-gray-800 text-hover-primary">{{ $user->email }}</a>
                </div>
            </div>

            <div class="row mb-7">
                <label class="col-lg-4 fw-semibold text-muted">{{ __('ui.profile.address') }}</label>
                <div class="col-lg-8">
                    <a href="#" class="fw-semibold fs-6 text-gray-800 text-hover-primary">
                        {{ $user->address ?? '-' }}
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
                <h3 class="fw-bold m-0">{{ __('ui.profile.details') }}</h3>
            </div>
        </div>

        <div id="kt_account_settings_profile_details" class="collapse show">
            <form id="kt_account_profile_details_form" action="{{ route('profile.update') }}" method="POST"
                enctype="multipart/form-data" class="form">
                @csrf
                @method('PUT')

                <div class="card-body border-top p-9">
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">{{ __('ui.profile.avatar') }}</label>
                        <div class="col-lg-8">
                            <div class="image-input image-input-outline" data-kt-image-input="true"
                                style="background-image: url('{{ asset('assets/media/svg/avatars/blank.svg') }}')">
                                <div class="image-input-wrapper w-125px h-125px"
                                    style="background-image: url('{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('assets/media/avatars/300-1.jpg') }}')">
                                </div>

                                <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="change" data-bs-toggle="tooltip"
                                    title="{{ __('ui.profile.change_avatar') }}">
                                    <i class="ki-duotone ki-pencil fs-7">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <input type="file" name="avatar" accept=".png, .jpg, .jpeg" />
                                    <input type="hidden" name="avatar_remove" />
                                </label>

                                <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="cancel" data-bs-toggle="tooltip"
                                    title="{{ __('ui.profile.cancel_avatar') }}">
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>

                                <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                    data-kt-image-input-action="remove" data-bs-toggle="tooltip" title="{{ __('ui.profile.remove_avatar') }}">
                                    <i class="ki-duotone ki-cross fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>
                            </div>

                            <div class="form-text">{{ __('ui.profile.allowed_types') }}</div>
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label required fw-semibold fs-6">{{ __('ui.profile.full_name') }}</label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="name" class="form-control form-control-lg form-control-solid"
                                placeholder="{{ __('ui.profile.full_name') }}" value="{{ $user->name }}" />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">{{ __('ui.profile.role') }}</label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" class="form-control form-control-lg form-control-solid"
                                value="{{ $user->getRoleNames()->first() ?? __('ui.profile.no_role') }}" readonly />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">
                            <span class="required">{{ __('ui.profile.phone') }}</span>
                        </label>
                        <div class="col-lg-8 fv-row">
                            <input type="tel" name="phone" class="form-control form-control-lg form-control-solid"
                                placeholder="{{ __('ui.profile.phone') }}" value="{{ $user->phone }}" />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">{{ __('ui.profile.email') }}</label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="email" class="form-control form-control-lg form-control-solid"
                                placeholder="{{ __('ui.profile.email') }}" value="{{ $user->email }}" />
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">{{ __('ui.profile.address') }}</label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="address" class="form-control form-control-lg form-control-solid"
                                placeholder="{{ __('ui.profile.address') }}" value="{{ $user->address }}" />
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <button type="reset" class="btn btn-light btn-active-light-primary me-2">{{ __('ui.buttons.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="kt_account_profile_details_submit">
                        {{ __('ui.buttons.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script>
        "use strict";

        var profileTranslations = {
            nameRequired: @json(__('validation.required', ['attribute' => __('validation.attributes.name')])),
            emailRequired: @json(__('validation.required', ['attribute' => __('validation.attributes.email')])),
            emailInvalid: @json(__('validation.email', ['attribute' => __('validation.attributes.email')])),
            phoneRequired: @json(__('validation.required', ['attribute' => __('validation.attributes.phone')])),
            validationError: @json(__('ui.messages.validation_error')),
            sessionExpired: @json(__('ui.messages.session_expired')),
            success: @json(__('ui.messages.profile_updated')),
            ok: @json(__('ui.buttons.ok')),
            error: @json(__('ui.messages.unexpected_error')),
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
@endsection
