@extends('layouts.app')

@section('title', __('ui.artisan_commands.title'))
@section('pageName', __('ui.artisan_commands.title'))

@section('content')

<div class="container-xxl">
    <div class="card card-flush shadow-sm">
        <div class="card-header align-items-center py-5">
            <div class="card-title">
                <div>
                    <h3 class="fw-bold m-0">{{ __('ui.artisan_commands.title') }}</h3>
                    <div class="text-muted fs-7 mt-2">{{ __('ui.artisan_commands.subtitle') }}</div>
                </div>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="alert alert-warning d-flex align-items-center p-5 mb-7">
                <i class="ki-duotone ki-shield-tick fs-2hx text-warning me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fw-bold">{{ __('ui.artisan_commands.safety_title') }}</span>
                    <span>{{ __('ui.artisan_commands.safety_text') }}</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5 w-100" id="artisan_commands_table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>{{ __('ui.artisan_commands.command') }}</th>
                            <th>{{ __('ui.artisan_commands.description') }}</th>
                            <th>{{ __('ui.artisan_commands.parameters') }}</th>
                            <th>{{ __('ui.artisan_commands.class') }}</th>
                            <th class="text-end">{{ __('ui.artisan_commands.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($commands as $command)
                            <tr>
                                <td>
                                    <code class="fs-7" dir="ltr">{{ $command['full_command'] }}</code>
                                </td>
                                <td class="min-w-250px">
                                    <div class="fw-semibold">{{ $command['description'] }}</div>
                                    @if($command['disabled_reason'])
                                        <div class="text-muted fs-8 mt-1">{{ $command['disabled_reason'] }}</div>
                                    @endif
                                </td>
                                <td class="min-w-200px">
                                    @forelse($command['arguments'] as $argument)
                                        <span class="badge {{ $argument['required'] ? 'badge-light-danger' : 'badge-light' }} me-1 mb-1">
                                            {{ $argument['name'] }}
                                        </span>
                                    @empty
                                        <span class="badge badge-light-success">{{ __('ui.artisan_commands.no_required_input') }}</span>
                                    @endforelse

                                    @foreach($command['options'] as $option)
                                        <span class="badge badge-light-primary me-1 mb-1">--{{ $option['name'] }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    <span class="text-gray-700">{{ $command['class'] }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <button type="button"
                                            class="btn btn-icon btn-light btn-sm"
                                            title="{{ __('ui.artisan_commands.copy') }}"
                                            data-copy-command="{{ $command['full_command'] }}">
                                            <i class="ki-duotone ki-copy fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>

                                        <button type="button"
                                            class="btn btn-icon btn-primary btn-sm"
                                            title="{{ $command['can_run'] ? __('ui.artisan_commands.run_background') : $command['disabled_reason'] }}"
                                            data-run-command="{{ $command['name'] }}"
                                            @disabled(! $command['can_run'])>
                                            <i class="ki-duotone ki-to-right fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
$(document).ready(function () {
    $('#artisan_commands_table').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[0, 'asc']],
        language: {
            search: @json(__('ui.artisan_commands.search')),
            lengthMenu: @json(__('ui.artisan_commands.length_menu')),
            info: @json(__('ui.artisan_commands.info')),
            paginate: {
                previous: @json(__('ui.artisan_commands.previous')),
                next: @json(__('ui.artisan_commands.next')),
            },
        },
    });

    $('[data-copy-command]').on('click', function () {
        const command = $(this).data('copy-command');

        navigator.clipboard.writeText(command).then(function () {
            toastr.success(@json(__('ui.artisan_commands.copied')));
        }).catch(function () {
            toastr.error(@json(__('ui.artisan_commands.copy_failed')));
        });
    });

    $('[data-run-command]').on('click', function () {
        const button = $(this);
        const command = button.data('run-command');

        if (button.prop('disabled') || !command) {
            return;
        }

        Swal.fire({
            title: @json(__('ui.artisan_commands.confirm_title')),
            text: @json(__('ui.artisan_commands.confirm_text')),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: @json(__('ui.artisan_commands.confirm_button')),
            cancelButtonText: @json(__('ui.buttons.cancel')),
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-light',
            },
            buttonsStyling: false,
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            button.prop('disabled', true);

            $.ajax({
                url: @json(route('admin.artisan-commands.run')),
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                data: {
                    command: command,
                },
            }).done(function (response) {
                toastr.success(response.message || @json(__('ui.artisan_commands.run_started_generic')));
            }).fail(function (xhr) {
                toastr.error(xhr.responseJSON?.message || @json(__('ui.artisan_commands.run_failed')));
            }).always(function () {
                button.prop('disabled', false);
            });
        });
    });
});
</script>
@endsection
