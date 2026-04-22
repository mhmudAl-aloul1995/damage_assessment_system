@extends('layouts.app')

@section('title', __('multilingual.telegram_integrations.title'))
@section('pageName', __('multilingual.telegram_integrations.page_name'))

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning mb-5">{{ session('warning') }}</div>
    @endif

    <div class="row g-5 mb-5">
        <div class="col-md-3">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-2">{{ __('multilingual.telegram_integrations.stats.total') }}</div>
                    <div class="fs-2hx fw-bold text-primary">{{ $counts['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-2">{{ __('multilingual.telegram_integrations.stats.connected') }}</div>
                    <div class="fs-2hx fw-bold text-success">{{ $counts['connected'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-2">{{ __('multilingual.telegram_integrations.stats.pending') }}</div>
                    <div class="fs-2hx fw-bold text-info">{{ $counts['pending'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-2">{{ __('multilingual.telegram_integrations.stats.failed') }}</div>
                    <div class="fs-2hx fw-bold text-danger">{{ $counts['failed'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-5">
        <div>
            <div class="fw-bold mb-1">{{ __('multilingual.telegram_integrations.group_note_title') }}</div>
            <div class="text-muted fs-7">{{ __('multilingual.telegram_integrations.group_note_body') }}</div>
        </div>
        <div class="text-muted fs-8">
            {{ __('multilingual.telegram_integrations.webhook_endpoint') }}:
            <code>{{ route('telegram.webhook', ['secret' => config('services.telegram.webhook_secret', 'set-secret')]) }}</code>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center">
            <div class="card-title">
                <h3 class="fw-bold m-0">{{ __('multilingual.telegram_integrations.page_name') }}</h3>
            </div>
            @if ($canManageTelegramIntegrations)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#telegram_integration_modal">
                    {{ __('multilingual.telegram_integrations.actions.create') }}
                </button>
            @endif
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="telegram_integrations_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>{{ __('multilingual.telegram_integrations.columns.name') }}</th>
                            <th>{{ __('multilingual.telegram_integrations.columns.type') }}</th>
                            <th>{{ __('multilingual.telegram_integrations.columns.status') }}</th>
                            <th>{{ __('multilingual.telegram_integrations.columns.app_user') }}</th>
                            <th>{{ __('multilingual.telegram_integrations.columns.chat_id') }}</th>
                            <th>{{ __('multilingual.telegram_integrations.columns.telegram') }}</th>
                            <th>{{ __('multilingual.telegram_integrations.columns.linked_by') }}</th>
                            <th>{{ __('multilingual.telegram_integrations.columns.link') }}</th>
                            <th>{{ __('multilingual.telegram_integrations.columns.updated_at') }}</th>
                            <th class="text-end">{{ __('multilingual.telegram_integrations.columns.actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    @if ($canManageTelegramIntegrations)
        <div class="modal fade" id="telegram_integration_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered mw-700px">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="fw-bold m-0">{{ __('multilingual.telegram_integrations.modal.title') }}</h3>
                        <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal">
                            <i class="ki-duotone ki-cross fs-1"></i>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('telegram-integrations.store') }}">
                        @csrf
                        <div class="modal-body py-10 px-lg-17">
                            <div class="row g-5">
                                <div class="col-md-6">
                                    <label class="form-label required">{{ __('multilingual.telegram_integrations.modal.name') }}</label>
                                    <input type="text" name="name" class="form-control form-control-solid" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">{{ __('multilingual.telegram_integrations.modal.type') }}</label>
                                    <select name="type" class="form-select form-select-solid" required>
                                        <option value="user">{{ __('multilingual.telegram_integrations.types.user') }}</option>
                                        <option value="group">{{ __('multilingual.telegram_integrations.types.group') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">{{ __('multilingual.telegram_integrations.modal.field_engineer_user') }}</label>
                                    <select name="user_id" class="form-select form-select-solid">
                                        <option value="">{{ __('multilingual.telegram_integrations.modal.unlinked_user') }}</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">
                                                {{ $user->name }}{{ $user->username_arcgis ? ' - '.$user->username_arcgis : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">{{ __('multilingual.telegram_integrations.modal.user_help') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('ui.buttons.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('multilingual.telegram_integrations.actions.generate_link') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#telegram_integrations_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('telegram-integrations.data') }}',
                order: [[8, 'desc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'type', name: 'type' },
                    { data: 'status', name: 'status' },
                    { data: 'app_user', name: 'user.name', orderable: false },
                    { data: 'telegram_chat_id', name: 'telegram_chat_id', defaultContent: '-' },
                    { data: 'target_name', name: 'telegram_username', orderable: false, searchable: false },
                    { data: 'linked_by', name: 'linked_by', defaultContent: '-' },
                    { data: 'shareable_link', name: 'shareable_link', orderable: false, searchable: false },
                    { data: 'updated_at', name: 'updated_at' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $(document).on('click', '.telegram-copy-link', async function () {
                const link = $(this).data('link');

                if (!link || link === '-') {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(link);
                    toastr.success(@json(__('multilingual.telegram_integrations.messages.link_copied')));
                } catch (error) {
                    toastr.error(@json(__('multilingual.telegram_integrations.messages.link_copy_failed')));
                }
            });
        });
    </script>
@endsection
