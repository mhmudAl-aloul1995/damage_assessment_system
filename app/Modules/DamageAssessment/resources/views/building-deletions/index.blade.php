@extends('layouts.app')

@section('title', __('ui.building_deletions.title'))
@section('pageName', __('ui.building_deletions.title'))

@section('content')
    <style>
        .building-deletions-pagination {
            overflow-x: auto;
            padding-top: .75rem;
        }

        .building-deletions-pagination .pagination {
            align-items: center;
            flex-wrap: wrap;
            gap: .25rem;
            justify-content: flex-end;
            margin-bottom: 0;
        }

        .building-deletions-pagination .page-link {
            align-items: center;
            display: inline-flex;
            justify-content: center;
            min-height: 2.25rem;
            min-width: 2.25rem;
        }

        .building-deletions-pagination svg {
            height: 1rem;
            max-height: 1rem;
            max-width: 1rem;
            width: 1rem;
        }
    </style>

    <div class="card card-flush">
        <div class="card-header pt-7">
            <div class="card-title">
                <h2>{{ __('ui.building_deletions.title') }}</h2>
            </div>
            <div class="card-toolbar">
                @if (auth()->user()?->hasAnyRole(['Gis Officer', 'Database Officer']))
                    <button type="submit" form="buildingDeletionBulkApproveForm" class="btn btn-light-success me-2" data-building-deletion-bulk-approve disabled>
                        <i class="ki-duotone ki-check-square fs-3 me-1"></i>
                        {{ __('ui.building_deletions.bulk_approve_selected') }}
                        <span class="badge badge-success ms-2" data-building-deletion-selected-count>0</span>
                    </button>
                @endif
                @can('create', \App\Models\BuildingDeletionRequest::class)
                    <button type="button" class="btn btn-primary" data-building-deletion-open-modal data-url="{{ route('building-deletions.create') }}">
                        {{ __('ui.building_deletions.new_request') }}
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form id="buildingDeletionBulkApproveForm" method="POST" action="{{ route('building-deletions.bulk-approve') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="w-40px">
                                    @if (auth()->user()?->hasAnyRole(['Gis Officer', 'Database Officer']))
                                        <input type="checkbox" class="form-check-input" data-building-deletion-select-all aria-label="{{ __('ui.building_deletions.select_all_pending') }}">
                                    @endif
                                </th>
                                <th>{{ __('ui.building_deletions.request') }}</th>
                                <th>{{ __('ui.building_deletions.object_id') }}</th>
                                <th>{{ __('ui.building_deletions.global_id') }}</th>
                                <th>{{ __('ui.building_deletions.requested_by') }}</th>
                                <th>{{ __('ui.building_deletions.status') }}</th>
                                <th>{{ __('ui.building_deletions.snapshot_hash') }}</th>
                                <th>{{ __('ui.building_deletions.created') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $request)
                                <tr>
                                    <td>
                                        @if (auth()->user()?->hasAnyRole(['Gis Officer', 'Database Officer']) && $request->status === \App\Enums\BuildingDeletionStatus::PendingGisReview)
                                            <input type="checkbox" name="request_ids[]" value="{{ $request->id }}" class="form-check-input" data-building-deletion-row-checkbox aria-label="{{ __('ui.building_deletions.select_request', ['request' => '#DEL-'.str_pad((string) $request->id, 5, '0', STR_PAD_LEFT)]) }}">
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>#DEL-{{ str_pad((string) $request->id, 5, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $request->building_objectid ?? '-' }}</td>
                                    <td class="text-break">{{ $request->building_globalid }}</td>
                                    <td>{{ $request->requester?->name ?? '-' }}</td>
                                    <td><span class="badge badge-light-primary">{{ __('ui.building_deletions.status_labels.'.$request->status->value) }}</span></td>
                                    <td class="text-break">{{ $request->latestSnapshot?->snapshot_hash ?? '-' }}</td>
                                    <td>{{ $request->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <a href="{{ route('building-deletions.show', $request) }}" class="btn btn-sm btn-light-primary">{{ __('ui.building_deletions.view') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="text-center text-muted py-10">
                                            {{ __('ui.building_deletions.no_requests') }}
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>

            <div class="building-deletions-pagination">
                {{ $requests->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <div class="modal fade" id="buildingDeletionRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">{{ __('ui.building_deletions.new_title') }}</h3>
                    <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                    </button>
                </div>
                <div class="modal-body" id="buildingDeletionRequestModalBody">
                    <div class="text-muted">{{ __('ui.building_deletions.loading_form') }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            const bulkApproveForm = document.getElementById('buildingDeletionBulkApproveForm');
            const bulkApproveButton = document.querySelector('[data-building-deletion-bulk-approve]');
            const selectedCountBadge = document.querySelector('[data-building-deletion-selected-count]');
            const selectAll = document.querySelector('[data-building-deletion-select-all]');
            const rowCheckboxes = Array.from(document.querySelectorAll('[data-building-deletion-row-checkbox]'));
            const modalElement = document.getElementById('buildingDeletionRequestModal');
            const modalBody = document.getElementById('buildingDeletionRequestModalBody');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const messages = {
                loading: @json(__('ui.building_deletions.loading_form')),
                loadFailed: @json(__('ui.building_deletions.load_form_failed')),
                submitFailed: @json(__('ui.building_deletions.submit_failed')),
                bulkApproveConfirm: @json(__('ui.building_deletions.bulk_approve_confirm')),
            };

            function syncBulkApproveState() {
                const selectedCount = rowCheckboxes.filter((checkbox) => checkbox.checked).length;

                if (bulkApproveButton) {
                    bulkApproveButton.disabled = selectedCount === 0;
                }

                if (selectedCountBadge) {
                    selectedCountBadge.textContent = selectedCount;
                }

                if (selectAll) {
                    selectAll.checked = selectedCount > 0 && selectedCount === rowCheckboxes.length;
                    selectAll.indeterminate = selectedCount > 0 && selectedCount < rowCheckboxes.length;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    rowCheckboxes.forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });

                    syncBulkApproveState();
                });
            }

            rowCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', syncBulkApproveState);
            });

            if (bulkApproveForm) {
                bulkApproveForm.addEventListener('submit', function (event) {
                    if (!confirm(messages.bulkApproveConfirm)) {
                        event.preventDefault();
                    }
                });
            }

            syncBulkApproveState();

            function initializeModalForm() {
                const form = modalBody.querySelector('#buildingDeletionForm');

                $(modalBody).find('[data-control="select2"]').select2({
                    dir: @json(app()->getLocale() === 'ar' ? 'rtl' : 'ltr'),
                    width: '100%',
                    dropdownParent: $('#buildingDeletionRequestModal'),
                    minimumInputLength: 1,
                    ajax: {
                        url: function () {
                            return this.dataset.buildingSearchUrl;
                        },
                        delay: 250,
                        data: function (params) {
                            return {
                                q: params.term || '',
                            };
                        },
                        processResults: function (data) {
                            return data;
                        },
                    },
                });

                if (!form) {
                    return;
                }

                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    const submitButton = form.querySelector('[type="submit"]');
                    const errorsBox = form.querySelector('.building-deletion-errors');

                    if (errorsBox) {
                        errorsBox.classList.add('d-none');
                        errorsBox.innerHTML = '';
                    }

                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                        .then(async function (response) {
                            const data = await response.json().catch(function () {
                                return {};
                            });

                            if (!response.ok) {
                                throw data;
                            }

                            if (data.redirect_url) {
                                window.location.href = data.redirect_url;
                            }
                        })
                        .catch(function (error) {
                            const errors = error.errors ? Object.values(error.errors).flat() : [error.message || messages.submitFailed];

                            if (errorsBox) {
                                errorsBox.innerHTML = errors.map(function (message) {
                                    return `<div>${escapeHtml(message)}</div>`;
                                }).join('');
                                errorsBox.classList.remove('d-none');
                            } else if (typeof toastr !== 'undefined') {
                                toastr.error(errors.join('\n'));
                            }
                        })
                        .finally(function () {
                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                        });
                });
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            document.querySelectorAll('[data-building-deletion-open-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    modalBody.innerHTML = `<div class="text-muted">${messages.loading}</div>`;
                    modal.show();

                    fetch(button.dataset.url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error(messages.loadFailed);
                            }

                            return response.text();
                        })
                        .then(function (html) {
                            modalBody.innerHTML = html;
                            initializeModalForm();
                        })
                        .catch(function () {
                            modalBody.innerHTML = `<div class="alert alert-danger">${messages.loadFailed}</div>`;
                        });
                });
            });
        })();
    </script>
@endsection
