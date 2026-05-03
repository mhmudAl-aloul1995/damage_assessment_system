@extends('layouts.app')

@section('title', __('ui.permissions.title'))
@section('pageName', __('ui.permissions.title'))

@section('content')
<div class="card card-flush">
    <div class="card-header mt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1 me-5">
                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <input type="text" id="permission_search" class="form-control form-control-solid w-250px ps-13"
                    placeholder="{{ __('ui.permissions.search_placeholder') }}" />
            </div>
        </div>

        <div class="card-toolbar">
            <button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_add_permission">
                <i class="ki-duotone ki-plus-square fs-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                {{ __('ui.permissions.add_button') }}
            </button>
        </div>
    </div>

    <div class="card-body pt-0">
        <table class="table align-middle table-row-dashed fs-6 gy-5 mb-0" id="kt_permissions_table">
            <thead>
                <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                    <th>{{ __('ui.permissions.name') }}</th>
                    <th>{{ __('ui.permissions.assigned_to') }}</th>
                    <th>{{ __('ui.permissions.created_at') }}</th>
                    <th class="text-end">{{ __('ui.permissions.actions') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="kt_modal_add_permission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">{{ __('ui.permissions.add_title') }}</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>

            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_add_permission_form" class="form">
                    @csrf

                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-semibold form-label mb-2">
                            <span class="required">{{ __('ui.permissions.name_label') }}</span>
                        </label>

                        <input class="form-control form-control-solid" placeholder="{{ __('ui.permissions.name_placeholder') }}" name="name" />
                    </div>

                    <div class="text-center pt-15">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">{{ __('ui.buttons.discard') }}</button>
                        <button type="submit" class="btn btn-primary" id="add_permission_submit_btn">
                            <span class="indicator-label">{{ __('ui.buttons.submit') }}</span>
                            <span class="indicator-progress">{{ __('ui.auth.please_wait') }}
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="kt_modal_update_permission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">{{ __('ui.permissions.update_title') }}</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>

            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed mb-9 p-6">
                    <i class="ki-duotone ki-information fs-2tx text-warning me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="fw-semibold fs-6 text-gray-700">
                        <strong class="me-1">{{ __('ui.permissions.warning_title') }}</strong>
                        {{ __('ui.permissions.warning_text') }}
                    </div>
                </div>

                <form id="kt_modal_update_permission_form" class="form">
                    @csrf
                    <input type="hidden" id="edit_permission_id">

                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-semibold form-label mb-2">
                            <span class="required">{{ __('ui.permissions.name_label') }}</span>
                        </label>

                        <input class="form-control form-control-solid" placeholder="{{ __('ui.permissions.name_placeholder') }}" name="name" id="edit_permission_name" />
                    </div>

                    <div class="text-center pt-15">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">{{ __('ui.buttons.discard') }}</button>
                        <button type="submit" class="btn btn-primary" id="update_permission_submit_btn">
                            <span class="indicator-label">{{ __('ui.buttons.submit') }}</span>
                            <span class="indicator-progress">{{ __('ui.auth.please_wait') }}
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
<script>
    let permissionsTable;
    const permissionTranslations = {
        ok: @json(__('ui.buttons.ok')),
        error: @json(__('ui.messages.unexpected_error')),
        deleteConfirm: @json(__('ui.permissions.delete_confirm', ['name' => '__NAME__'])),
        yesDelete: @json(__('ui.buttons.yes_delete')),
        cancel: @json(__('ui.buttons.cancel')),
        dataTableLanguageUrl: @json(app()->getLocale() === 'ar' ? '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json' : '//cdn.datatables.net/plug-ins/1.13.4/i18n/en-GB.json'),
    };

    function showErrors(xhr) {
        if (xhr.responseJSON && xhr.responseJSON.errors) {
            Swal.fire({ html: Object.values(xhr.responseJSON.errors).flat().join('<br>'), icon: 'error', confirmButtonText: permissionTranslations.ok });
            return;
        }

        Swal.fire({
            text: xhr.responseJSON?.message ?? permissionTranslations.error,
            icon: 'error',
            confirmButtonText: permissionTranslations.ok
        });
    }

    $(document).ready(function () {
        permissionsTable = $('#kt_permissions_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('permissions.data') }}",
            info: false,
            pageLength: 10,
            lengthChange: false,
            order: [[0, 'asc']],
            language: {
                url: permissionTranslations.dataTableLanguageUrl
            },
            columns: [
                { data: 'name', name: 'name' },
                { data: 'assigned_to', name: 'assigned_to', orderable: false, searchable: false },
                { data: 'created_at', name: 'created_at' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            drawCallback: function () {
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
            }
        });

        $('#permission_search').on('keyup', function () {
            permissionsTable.search(this.value).draw();
        });

        $('#kt_modal_add_permission_form').on('submit', function (e) {
            e.preventDefault();
            let btn = $('#add_permission_submit_btn');
            btn.attr('data-kt-indicator', 'on').prop('disabled', true);

            $.ajax({
                url: "{{ route('permissions.store') }}",
                type: "POST",
                data: $(this).serialize(),
                success: function (response) {
                    $('#kt_modal_add_permission_form')[0].reset();
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_add_permission')).hide();
                    permissionsTable.ajax.reload(null, false);

                    Swal.fire({ text: response.message, icon: 'success', confirmButtonText: permissionTranslations.ok });
                },
                error: function (xhr) {
                    showErrors(xhr);
                },
                complete: function () {
                    btn.removeAttr('data-kt-indicator').prop('disabled', false);
                }
            });
        });

        $(document).on('click', '.btn-edit-permission', function () {
            let id = $(this).data('id');

            $.ajax({
                url: "{{ url('user-management/permissions') }}/" + id + "/edit",
                type: "GET",
                success: function (response) {
                    $('#edit_permission_id').val(response.permission.id);
                    $('#edit_permission_name').val(response.permission.name);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_update_permission')).show();
                },
                error: function (xhr) {
                    showErrors(xhr);
                }
            });
        });

        $('#kt_modal_update_permission_form').on('submit', function (e) {
            e.preventDefault();
            let id = $('#edit_permission_id').val();
            let btn = $('#update_permission_submit_btn');
            btn.attr('data-kt-indicator', 'on').prop('disabled', true);

            $.ajax({
                url: "{{ url('user-management/permissions') }}/" + id,
                type: "POST",
                data: $(this).serialize() + '&_method=PUT',
                success: function (response) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('kt_modal_update_permission')).hide();
                    permissionsTable.ajax.reload(null, false);
                    Swal.fire({ text: response.message, icon: 'success', confirmButtonText: permissionTranslations.ok });
                },
                error: function (xhr) {
                    showErrors(xhr);
                },
                complete: function () {
                    btn.removeAttr('data-kt-indicator').prop('disabled', false);
                }
            });
        });

        $(document).on('click', '.btn-delete-permission', function () {
            let id = $(this).data('id');
            let name = $(this).data('name');

            Swal.fire({
                text: permissionTranslations.deleteConfirm.replace('__NAME__', name),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: permissionTranslations.yesDelete,
                cancelButtonText: permissionTranslations.cancel
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                $.ajax({
                    url: "{{ url('user-management/permissions') }}/" + id,
                    type: "POST",
                    data: {
                        _method: 'DELETE',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function (response) {
                        permissionsTable.ajax.reload(null, false);
                        Swal.fire({ text: response.message, icon: 'success', confirmButtonText: permissionTranslations.ok });
                    },
                    error: function (xhr) {
                        showErrors(xhr);
                    }
                });
            });
        });
    });
</script>
@endsection
