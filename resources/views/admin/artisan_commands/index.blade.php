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
            @php
                $commandsByName = $commands->keyBy('name');
            @endphp

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
    const commandsByName = @json($commandsByName);
    const commandOptionGuides = {
        'arcgis:upload-audited': {
            'buildings-limit': {
                label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.buildings_limit.label')),
                help: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.buildings_limit.help')),
                placeholder: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.buildings_limit.placeholder')),
                type: 'number',
            },
            'only': {
                label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.only.label')),
                help: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.only.help')),
                type: 'select',
                choices: [
                    {
                        value: 'buildings',
                        label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.only.buildings')),
                    },
                    {
                        value: 'units',
                        label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.only.units')),
                    },
                ],
            },
            'changed-since': {
                label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.changed_since.label')),
                help: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.changed_since.help')),
                placeholder: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.changed_since.placeholder')),
                type: 'datetime-local',
            },
            'only-audit-edits': {
                label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.only_audit_edits.label')),
                help: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.only_audit_edits.help')),
            },
            'skip-counts': {
                label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.skip_counts.label')),
                help: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.skip_counts.help')),
            },
            'without-attachments': {
                label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.without_attachments.label')),
                help: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.without_attachments.help')),
            },
            'attachments-only': {
                label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.attachments_only.label')),
                help: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.attachments_only.help')),
            },
            'refresh-cache': {
                label: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.refresh_cache.label')),
                help: @json(__('ui.artisan_commands.guides.arcgis_upload_audited.refresh_cache.help')),
            },
        },
    };

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

    $(document).on('click', '[data-copy-command]', function () {
        const command = $(this).data('copy-command');

        navigator.clipboard.writeText(command).then(function () {
            toastr.success(@json(__('ui.artisan_commands.copied')));
        }).catch(function () {
            toastr.error(@json(__('ui.artisan_commands.copy_failed')));
        });
    });

    $(document).on('click', '[data-run-command]', function () {
        const button = $(this);
        const command = button.data('run-command');
        const commandDefinition = commandsByName[command];

        if (button.prop('disabled') || !command || !commandDefinition) {
            return;
        }

        let formHtml = '';

        try {
            formHtml = buildCommandForm(commandDefinition);
        } catch (error) {
            console.error(error);
            toastr.error(@json(__('ui.artisan_commands.modal_failed')));

            return;
        }

        Swal.fire({
            title: @json(__('ui.artisan_commands.confirm_title')),
            html: formHtml,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: @json(__('ui.artisan_commands.confirm_button')),
            cancelButtonText: @json(__('ui.buttons.cancel')),
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-light',
            },
            buttonsStyling: false,
            focusConfirm: false,
            didOpen: function () {
                const form = document.getElementById('artisan-command-run-form');
                const preview = document.getElementById('artisan-command-preview');
                const updatePreview = function () {
                    preview.textContent = buildPreviewCommand(commandDefinition, form);
                };

                form.querySelectorAll('input, select').forEach(function (input) {
                    input.addEventListener('input', updatePreview);
                    input.addEventListener('change', updatePreview);
                });

                updatePreview();
            },
            preConfirm: function () {
                const form = document.getElementById('artisan-command-run-form');
                const payload = {
                    arguments: {},
                    options: {},
                };
                let hasValidationError = false;

                commandDefinition.arguments.forEach(function (argument) {
                    const input = form.querySelector(`[name="argument:${argument.name}"]`);

                    if (input && input.value.trim() !== '') {
                        payload.arguments[argument.name] = input.value.trim();
                    }

                    if (argument.required && (!input || input.value.trim() === '')) {
                        hasValidationError = true;
                        Swal.showValidationMessage(@json(__('ui.artisan_commands.required_field')));
                    }
                });

                if (hasValidationError) {
                    return false;
                }

                commandDefinition.options.forEach(function (option) {
                    const input = form.querySelector(`[name="option:${option.name}"]`);

                    if (!input) {
                        return;
                    }

                    if (!option.accepts_value) {
                        payload.options[option.name] = input.checked ? '1' : '';
                        return;
                    }

                    if (input.value.trim() !== '') {
                        payload.options[option.name] = input.value.trim();
                    }
                });

                return payload;
            },
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
                    arguments: result.value.arguments,
                    options: result.value.options,
                },
            }).done(function (response) {
                toastr.success(response.message || @json(__('ui.artisan_commands.run_started_generic')));

                if (response.status_url) {
                    openRunMonitor(response.status_url, response.preview || command);
                }
            }).fail(function (xhr) {
                toastr.error(xhr.responseJSON?.message || @json(__('ui.artisan_commands.run_failed')));
            }).always(function () {
                button.prop('disabled', false);
            });
        });
    });

    function openRunMonitor(statusUrl, previewCommand) {
        let monitorTimer = null;

        Swal.fire({
            title: @json(__('ui.artisan_commands.monitor_title')),
            html: `
                <div class="text-start">
                    <div class="alert alert-light-primary py-3 px-4 mb-4">
                        <code dir="ltr">${escapeHtml(previewCommand)}</code>
                    </div>
                    <div class="alert alert-light-info py-3 px-4 mb-4">
                        ${escapeHtml(@json(__('ui.artisan_commands.monitor_verbose_hint')))}
                    </div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="spinner-border spinner-border-sm text-primary" id="artisan-run-spinner"></span>
                        <span class="badge badge-light-primary" id="artisan-run-status">${escapeHtml(@json(__('ui.artisan_commands.status_running')))}</span>
                    </div>
                    <pre id="artisan-run-output" class="bg-dark text-white rounded p-4 text-start" dir="ltr" style="min-height: 260px; max-height: 420px; overflow: auto; white-space: pre-wrap;"></pre>
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: @json(__('ui.buttons.close')),
            customClass: {
                confirmButton: 'btn btn-light',
            },
            buttonsStyling: false,
            didOpen: function () {
                const statusBadge = document.getElementById('artisan-run-status');
                const output = document.getElementById('artisan-run-output');
                const spinner = document.getElementById('artisan-run-spinner');

                const refreshStatus = function () {
                    $.get(statusUrl).done(function (response) {
                        statusBadge.textContent = statusLabel(response.status);
                        statusBadge.className = `badge ${statusClass(response.status)}`;
                        output.textContent = response.output || @json(__('ui.artisan_commands.no_output_yet'));
                        output.scrollTop = output.scrollHeight;

                        if (response.status !== 'running') {
                            clearInterval(monitorTimer);
                            spinner.classList.add('d-none');
                        }
                    }).fail(function () {
                        statusBadge.textContent = @json(__('ui.artisan_commands.status_unknown'));
                        statusBadge.className = 'badge badge-light-danger';
                    });
                };

                refreshStatus();
                monitorTimer = setInterval(refreshStatus, 2000);
            },
            willClose: function () {
                if (monitorTimer) {
                    clearInterval(monitorTimer);
                }
            },
        });
    }

    function statusLabel(status) {
        if (status === 'success') {
            return @json(__('ui.artisan_commands.status_success'));
        }

        if (status === 'failed') {
            return @json(__('ui.artisan_commands.status_failed'));
        }

        return @json(__('ui.artisan_commands.status_running'));
    }

    function statusClass(status) {
        if (status === 'success') {
            return 'badge-light-success';
        }

        if (status === 'failed') {
            return 'badge-light-danger';
        }

        return 'badge-light-primary';
    }

    function buildCommandForm(commandDefinition) {
        let html = `
            <div class="text-start">
                <div class="alert alert-light-primary py-3 px-4 mb-5">
                    <code dir="ltr">${escapeHtml(commandDefinition.full_command)}</code>
                </div>
                <div class="alert alert-light-info py-3 px-4 mb-5">
                    ${escapeHtml(@json(__('ui.artisan_commands.inputs_hint')))}
                </div>
                <form id="artisan-command-run-form">
        `;

        if (commandDefinition.arguments.length > 0) {
            html += `<div class="fw-bold mb-3">${escapeHtml(@json(__('ui.artisan_commands.arguments_title')))}</div>`;

            commandDefinition.arguments.forEach(function (argument) {
                html += `
                    <div class="mb-4">
                        <label class="form-label">
                            ${escapeHtml(argument.name)}
                            ${argument.required ? '<span class="text-danger">*</span>' : ''}
                        </label>
                        <input type="text"
                            class="form-control"
                            name="argument:${escapeHtml(argument.name)}"
                            placeholder="${escapeHtml(argument.description || '')}">
                        ${argument.description ? `<div class="form-text">${escapeHtml(argument.description)}</div>` : ''}
                    </div>
                `;
            });
        }

        if (commandDefinition.options.length > 0) {
            html += `<div class="fw-bold mb-3">${escapeHtml(@json(__('ui.artisan_commands.options_title')))}</div>`;

            commandDefinition.options.forEach(function (option) {
                const optionUi = commandOptionUi(commandDefinition.name, option);

                if (!option.accepts_value) {
                    html += `
                        <div class="border rounded p-4 mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="option:${escapeHtml(option.name)}" id="option_${escapeHtml(option.name)}">
                                <label class="form-check-label fw-semibold" for="option_${escapeHtml(option.name)}">
                                    ${escapeHtml(optionUi.label)}
                                    <code class="ms-2" dir="ltr">--${escapeHtml(option.name)}</code>
                                </label>
                            </div>
                            <div class="form-text">${escapeHtml(optionUi.help)}</div>
                        </div>
                    `;

                    return;
                }

                if (optionUi.type === 'select') {
                    html += `
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                ${escapeHtml(optionUi.label)}
                                <code class="ms-2" dir="ltr">--${escapeHtml(option.name)}</code>
                            </label>
                            <select class="form-select" name="option:${escapeHtml(option.name)}">
                                <option value="">${escapeHtml(@json(__('ui.artisan_commands.leave_empty')))}</option>
                                ${optionUi.choices.map(function (choice) {
                                    return `<option value="${escapeHtml(choice.value)}">${escapeHtml(choice.label)}</option>`;
                                }).join('')}
                            </select>
                            <div class="form-text">${escapeHtml(optionUi.help)}</div>
                        </div>
                    `;

                    return;
                }

                html += `
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            ${escapeHtml(optionUi.label)}
                            <code class="ms-2" dir="ltr">--${escapeHtml(option.name)}</code>
                        </label>
                        <input type="${escapeHtml(optionUi.type)}"
                            class="form-control"
                            name="option:${escapeHtml(option.name)}"
                            placeholder="${escapeHtml(optionUi.placeholder)}">
                        <div class="form-text">${escapeHtml(optionUi.help)}</div>
                    </div>
                `;
            });
        }

        if (commandDefinition.arguments.length === 0 && commandDefinition.options.length === 0) {
            html += `<div class="text-muted">${escapeHtml(@json(__('ui.artisan_commands.no_inputs')))}</div>`;
        }

        html += `
                </form>
                <div class="mt-5">
                    <div class="fw-bold mb-2">${escapeHtml(@json(__('ui.artisan_commands.preview_title')))}</div>
                    <div class="bg-light rounded px-4 py-3">
                        <code id="artisan-command-preview" dir="ltr" class="d-block text-break"></code>
                    </div>
                    <div class="form-text">${escapeHtml(@json(__('ui.artisan_commands.preview_hint')))}</div>
                </div>
            </div>
        `;

        return html;
    }

    function buildPreviewCommand(commandDefinition, form) {
        const segments = [commandDefinition.full_command];

        commandDefinition.arguments.forEach(function (argument) {
            const input = form.querySelector(`[name="argument:${argument.name}"]`);

            if (input && input.value.trim() !== '') {
                segments.push(shellPreviewValue(input.value.trim()));
            }
        });

        commandDefinition.options.forEach(function (option) {
            const input = form.querySelector(`[name="option:${option.name}"]`);

            if (!input) {
                return;
            }

            if (!option.accepts_value) {
                if (input.checked) {
                    segments.push(`--${option.name}`);
                }

                return;
            }

            if (input.value.trim() !== '') {
                segments.push(`--${option.name}=${shellPreviewValue(input.value.trim())}`);
            }
        });

        return segments.join(' ');
    }

    function commandOptionUi(commandName, option) {
        const commandGuides = commandOptionGuides[commandName] || {};
        const guide = commandGuides[option.name] || {};

        return {
            label: guide.label || option.name,
            help: guide.help || option.description || @json(__('ui.artisan_commands.value_option_hint')),
            placeholder: guide.placeholder || @json(__('ui.artisan_commands.optional_value')),
            type: guide.type || 'text',
            choices: guide.choices || [],
        };
    }

    function shellPreviewValue(value) {
        if (/^[A-Za-z0-9_./:@=-]+$/.test(value)) {
            return value;
        }

        return `"${value.replace(/"/g, '\\"')}"`;
    }

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }
});
</script>
@endsection
