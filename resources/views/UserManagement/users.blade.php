@extends('layouts.app')

@section('title', __('ui.users.title'))
@section('pageName', __('ui.users.title'))

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <input type="text" data-kt-user-table-filter="search"
                        class="form-control form-control-solid w-250px ps-13" placeholder="{{ __('ui.users.search_placeholder') }}" />
                </div>
            </div>

            <div class="card-toolbar">
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-light-primary me-3" id="reload_users_table">
                        <i class="ki-duotone ki-arrows-circle fs-2"></i>
                        {{ __('ui.buttons.reload') }}
                    </button>

                    <button type="button" class="btn btn-primary" id="open_add_user_modal">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        {{ __('ui.buttons.add_user') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body py-4">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_table_user">
                <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th class="w-10px pe-2"></th>
                        <th class="min-w-150px">{{ __('ui.users.full_name') }}</th>
                        <th class="min-w-150px">{{ __('ui.users.name_en') }}</th>
                        <th class="min-w-150px">ArcGIS Username</th>
                        <th class="min-w-175px">{{ __('multilingual.user_management.telegram.connection') }}</th>
                        <th class="min-w-125px">{{ __('ui.users.email') }}</th>
                        <th class="min-w-125px">{{ __('ui.users.id_no') }}</th>
                        <th class="min-w-125px">{{ __('ui.users.contract_type') }}</th>
                        <th class="min-w-125px">{{ __('ui.users.phone') }}</th>
                        <th class="min-w-125px">{{ __('ui.users.created_at') }}</th>
                        <th class="text-end min-w-100px">{{ __('ui.users.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-semibold"></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="kt_modal_user" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-lg-800px mw-650px">
            <div class="modal-content">
                <div class="modal-header" id="kt_modal_user_header">
                    <h2 class="fw-bold" id="user_modal_title">{{ __('ui.users.create_title') }}</h2>

                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>

                <div class="modal-body px-5 my-7">
                    <form id="kt_modal_user_form" class="form" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="id" id="user_id">

                        <div class="d-flex flex-column scroll-y px-5 px-lg-10" id="kt_modal_user_scroll"
                            data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-max-height="auto"
                            data-kt-scroll-dependencies="#kt_modal_user_header"
                            data-kt-scroll-wrappers="#kt_modal_user_scroll" data-kt-scroll-offset="300px">
                            <div class="fv-row mb-7">
                                <label class="d-block fw-semibold fs-6 mb-5">{{ __('ui.users.avatar') }}</label>

                                <style>
                                    .image-input-placeholder {
                                        background-image: url('{{ asset('assets/media/svg/files/blank-image.svg') }}');
                                    }

                                    [data-bs-theme="dark"] .image-input-placeholder {
                                        background-image: url('{{ asset('assets/media/svg/files/blank-image-dark.svg') }}');
                                    }
                                </style>

                                <div class="image-input image-input-outline image-input-placeholder" data-kt-image-input="true">
                                    <div class="image-input-wrapper w-125px h-125px" id="user_avatar_preview"></div>

                                    <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                        data-kt-image-input-action="change" data-bs-toggle="tooltip"
                                        title="{{ __('ui.users.change_avatar') }}">
                                        <i class="ki-duotone ki-pencil fs-7">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <input type="file" name="avatar" accept=".png, .jpg, .jpeg" />
                                        <input type="hidden" name="avatar_remove" />
                                    </label>

                                    <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                        data-kt-image-input-action="cancel" data-bs-toggle="tooltip" title="{{ __('ui.users.cancel_avatar') }}">
                                        <i class="ki-duotone ki-cross fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </span>

                                    <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                        data-kt-image-input-action="remove" data-bs-toggle="tooltip" title="{{ __('ui.users.remove_avatar') }}">
                                        <i class="ki-duotone ki-cross fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </span>
                                </div>

                                <div class="form-text">{{ __('ui.users.allowed_types') }}</div>
                            </div>

                            <div class="fv-row mb-7">
                                <label class="required fw-semibold fs-6 mb-2">{{ __('ui.users.full_name') }}</label>
                                <input type="text" name="name" class="form-control form-control-solid"
                                    placeholder="{{ __('ui.users.full_name') }}" />
                            </div>

                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">{{ __('ui.users.name_en') }}</label>
                                <input type="text" name="name_en" class="form-control form-control-solid"
                                    placeholder="{{ __('ui.users.name_en') }}" />
                            </div>

                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">ArcGIS Username</label>
                                <input type="text" name="username_arcgis" class="form-control form-control-solid"
                                    placeholder="ArcGIS assignedto username" />
                            </div>

                            <div class="fv-row mb-7">
                                <label class="required fw-semibold fs-6 mb-2">{{ __('ui.users.email') }}</label>
                                <input type="email" name="email" class="form-control form-control-solid"
                                    placeholder="example@domain.com" />
                            </div>

                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">{{ __('ui.users.id_no') }}</label>
                                <input type="text" name="id_no" class="form-control form-control-solid"
                                    placeholder="{{ __('ui.users.id_no') }}" />
                            </div>

                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">{{ __('ui.users.contract_type') }}</label>
                                <select name="contract_type" class="form-select form-select-solid">
                                    <option value="">{{ __('ui.options.select_contract') }}</option>
                                    <option value="phc">PHC</option>
                                    <option value="undp">UNDP</option>
                                    <option value="mopwh">MOPWH</option>
                                    <option value="pef">PEF</option>
                                </select>
                            </div>

                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">{{ __('ui.users.address') }}</label>
                                <input type="text" name="address" class="form-control form-control-solid"
                                    placeholder="{{ __('ui.users.address') }}" />
                            </div>

                            <div class="fv-row mb-7">
                                <label class="required fw-semibold fs-6 mb-2">{{ __('ui.users.phone') }}</label>
                                <input type="text" name="phone" class="form-control form-control-solid"
                                    placeholder="{{ __('ui.users.phone') }}" />
                            </div>

                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">{{ __('ui.users.region') }}</label>
                                <select name="region" class="form-select form-select-solid">
                                    <option value="">{{ __('ui.regions.select') }}</option>
                                    <option value="north">{{ __('ui.regions.north') }}</option>
                                    <option value="south">{{ __('ui.regions.south') }}</option>
                                </select>
                            </div>

                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">{{ __('ui.users.send_password') }}</label>
                                <select name="send_password" class="form-select form-select-solid">
                                    <option value="">{{ __('ui.options.send_password') }}</option>
                                    <option value="yes">{{ __('ui.options.yes') }}</option>
                                    <option value="no">{{ __('ui.options.no') }}</option>
                                </select>
                            </div>

                            <div class="mb-5">
                                <label class="required fw-semibold fs-6 mb-2">{{ __('ui.users.roles') }}</label>

                                <select name="roles[]" id="roles_select" class="form-select form-select-solid"
                                    data-control="select2" data-placeholder="{{ __('ui.users.select_roles') }}" multiple>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}">
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="text-center pt-10">
                            <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">
                                {{ __('ui.buttons.cancel') }}
                            </button>

                            <button type="submit" class="btn btn-primary" id="save_user_btn">
                                <span class="indicator-label">{{ __('ui.buttons.save') }}</span>
                                <span class="indicator-progress">
                                    {{ __('ui.auth.please_wait') }}
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('assets/js/widgets.bundle.js') }}"></script>

    <script>
        const userTranslations = {
            createTitle: @json(__('ui.users.create_title')),
            editTitle: @json(__('ui.users.edit_title')),
            ok: @json(__('ui.buttons.ok')),
            error: @json(__('ui.messages.unexpected_error')),
            retry: @json(__('ui.buttons.try_again')),
            loadFailed: @json(__('ui.users.load_failed')),
            saved: @json(__('ui.users.saved')),
            telegramLinkReady: @json(__('multilingual.user_management.telegram.link_ready')),
            telegramLinkButton: @json(__('multilingual.user_management.telegram.generate_link')),
            telegramCopyLink: @json(__('multilingual.telegram_integrations.actions.copy_link')),
            telegramCopySuccess: @json(__('multilingual.telegram_integrations.messages.link_copied')),
            telegramCopyFailed: @json(__('multilingual.telegram_integrations.messages.link_copy_failed')),
            telegramOpenDestination: @json(__('multilingual.user_management.telegram.open_destination')),
            telegramNotConfigured: @json(__('multilingual.user_management.telegram.link_not_available')),
            dataTableLanguageUrl: @json(app()->getLocale() === 'ar' ? '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json' : '//cdn.datatables.net/plug-ins/1.13.4/i18n/en-GB.json'),
        };

        $('#roles_select').select2({
            placeholder: @json(__('ui.users.select_roles')),
            allowClear: true,
            width: '100%'
        });

        let usersTable;

        function showSwalError(message) {
            Swal.fire({
                html: message,
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: userTranslations.retry,
                customClass: {
                    confirmButton: "btn btn-danger"
                }
            });
        }

        function resetUserForm() {
            const form = document.getElementById('kt_modal_user_form');
            form.reset();

            $('#user_id').val('');
            $('#user_modal_title').text(userTranslations.createTitle);
            $('#user_avatar_preview').css('background-image', 'none');
        }

        function openUserModalForCreate() {
            resetUserForm();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_user')).show();
            $('#roles_select').val(null).trigger('change');
        }

        function showUser(id) {
            $.ajax({
                url: "{{ url('user-management/user') }}/" + id + "/edit",
                type: "GET",
                success: function (response) {
                    resetUserForm();

                    const user = response.user;
                    $('#roles_select').val(null).trigger('change');
                    $('#user_modal_title').text(userTranslations.editTitle);
                    $('#user_id').val(user.id);
                    $('#kt_modal_user_form input[name="name"]').val(user.name ?? '');
                    $('#kt_modal_user_form input[name="name_en"]').val(user.name_en ?? '');
                    $('#kt_modal_user_form input[name="username_arcgis"]').val(user.username_arcgis ?? '');
                    $('#kt_modal_user_form input[name="email"]').val(user.email ?? '');
                    $('#kt_modal_user_form input[name="id_no"]').val(user.id_no ?? '');
                    $('#kt_modal_user_form select[name="contract_type"]').val(user.contract_type ?? '');
                    $('#kt_modal_user_form input[name="phone"]').val(user.phone ?? '');
                    $('#kt_modal_user_form input[name="address"]').val(user.address ?? '');
                    $('#kt_modal_user_form select[name="region"]').val(user.region ?? '');

                    if (response.roles && response.roles.length > 0) {
                        $('#roles_select').val(response.roles).trigger('change');
                    }

                    if (user.avatar_url) {
                        $('#user_avatar_preview').css('background-image', 'url(' + user.avatar_url + ')');
                    }

                    bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_user')).show();
                },
                error: function () {
                    showSwalError(userTranslations.loadFailed);
                }
            });
        }

        function generateTelegramLink(id) {
            $.ajax({
                url: "{{ url('user-management/user') }}/" + id + "/telegram-link",
                type: "POST",
                success: function (response) {
                    const shareableLink = response.shareable_link ?? '';
                    const linkHtml = shareableLink
                        ? `<div class="mt-4">
                                <label class="form-label fw-semibold">${userTranslations.telegramLinkButton}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-solid" id="telegram_shareable_link" value="${shareableLink}" readonly>
                                    <button type="button" class="btn btn-primary" onclick="copyTelegramLink()">${userTranslations.telegramCopyLink}</button>
                                </div>
                            </div>`
                        : `<div class="alert alert-warning mt-4 mb-0">${userTranslations.telegramNotConfigured}</div>`;

                    Swal.fire({
                        title: response.message ?? userTranslations.telegramLinkReady,
                        html: `
                            <div class="text-start">
                                <div class="mb-3">
                                    <span class="badge badge-light-info">${response.status_label ?? ''}</span>
                                </div>
                                ${linkHtml}
                                <div class="mt-4">
                                    <a href="${response.destination_url}" class="btn btn-light-primary">${userTranslations.telegramOpenDestination}</a>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: userTranslations.ok,
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });

                    usersTable.ajax.reload(null, false);
                },
                error: function (xhr) {
                    showSwalError(xhr.responseJSON?.message ?? userTranslations.error);
                }
            });
        }

        function copyTelegramLink() {
            const input = document.getElementById('telegram_shareable_link');

            if (!input) {
                showSwalError(userTranslations.telegramNotConfigured);
                return;
            }

            navigator.clipboard.writeText(input.value)
                .then(() => {
                    Swal.fire({
                        text: userTranslations.telegramCopySuccess,
                        icon: 'success',
                        buttonsStyling: false,
                        confirmButtonText: userTranslations.ok,
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                })
                .catch(() => {
                    showSwalError(userTranslations.telegramCopyFailed);
                });
        }

        $(document).ready(function () {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            usersTable = $('#kt_table_user').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "{{ url('user-management/user/show') }}"
                },
                info: false,
                order: [],
                pageLength: 10,
                lengthChange: false,
                language: {
                    url: userTranslations.dataTableLanguageUrl
                },
                columns: [
                    { data: 'checkbox', name: 'checkbox', searchable: false, orderable: false },
                    { data: 'name', name: 'name' },
                    { data: 'name_en', name: 'name_en' },
                    { data: 'username_arcgis', name: 'username_arcgis' },
                    { data: 'telegram_destination', name: 'telegram_destination', searchable: false, orderable: false },
                    { data: 'email', name: 'email' },
                    { data: 'id_no', name: 'id_no' },
                    { data: 'contract_type', name: 'contract_type' },
                    { data: 'phone', name: 'phone' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'action', name: 'action', searchable: false, orderable: false }
                ],
                drawCallback: function () {
                    if (typeof KTMenu !== 'undefined') {
                        KTMenu.createInstances();
                    }
                }
            });

            $('[data-kt-user-table-filter="search"]').on('keyup', function () {
                usersTable.search(this.value).draw();
            });

            $('#reload_users_table').on('click', function () {
                usersTable.ajax.reload(null, false);
            });

            $('#open_add_user_modal').on('click', function () {
                openUserModalForCreate();
            });

            $('#kt_modal_user_form').on('submit', function (e) {
                e.preventDefault();

                let form = this;
                let formData = new FormData(form);
                let userId = $('#user_id').val();
                let submitButton = $('#save_user_btn');

                let url = userId
                    ? "{{ url('user-management/user') }}/" + userId
                    : "{{ route('users.store') }}";

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
                    success: function (response) {
                        submitButton.removeAttr('data-kt-indicator').prop('disabled', false);

                        Swal.fire({
                            text: response.message ?? userTranslations.saved,
                            icon: 'success',
                            buttonsStyling: false,
                            confirmButtonText: userTranslations.ok,
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            }
                        }).then(function () {
                            resetUserForm();
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_user')).hide();
                            usersTable.ajax.reload(null, false);
                        });
                    },
                    error: function (xhr) {
                        submitButton.removeAttr('data-kt-indicator').prop('disabled', false);

                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            let errors = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                            showSwalError(errors);
                            return;
                        }

                        showSwalError(xhr.responseJSON?.message ?? userTranslations.error);
                    }
                });
            });
        });
    </script>
@endsection
