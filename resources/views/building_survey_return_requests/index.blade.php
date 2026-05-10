@extends('layouts.app')

@section('title', 'طلبات إرجاع استبيان المبنى')
@section('pageName', 'طلبات الإرجاع')

@section('content')
    <div class="card card-flush">
        <div class="card-header pt-7">
            <div class="card-title">
                <h2>طلبات إرجاع استبيان المبنى</h2>
            </div>
            <div class="card-toolbar">
                @if (auth()->user()->hasRole('Field Engineer'))
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#createReturnRequestModal">
                        طلب جديد
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle" id="returnRequestsTable">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>اسم المبنى</th>
                            <th>objectid</th>
                            <th>مقدم الطلب</th>
                            <th>Team Leader</th>
                            <th>Area Manager</th>
                            <th>Status</th>
                            <th>Current Step</th>
                            <th>Requested At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $returnRequest)
                            @php
                                $statusClass =
                                    [
                                        'pending' => 'badge-light-warning',
                                        'approved_by_team_leader' => 'badge-light-info',
                                        'approved_by_area_manager' => 'badge-light-info',
                                        'completed' => 'badge-light-success',
                                        'rejected' => 'badge-light-danger',
                                    ][$returnRequest->status] ?? 'badge-light';
                            @endphp
                            <tr>
                                <td>{{ $returnRequest->building?->building_name ?? '-' }}</td>
                                <td>{{ $returnRequest->building_objectid }}</td>
                                <td>{{ $returnRequest->requester?->name ?? '-' }}</td>
                                <td>{{ $returnRequest->teamLeader?->name ?? '-' }}</td>
                                <td>{{ $returnRequest->areaManager?->name ?? '-' }}</td>
                                <td><span class="badge {{ $statusClass }}">{{ $returnRequest->status }}</span></td>
                                <td><span class="badge badge-light-primary">{{ $returnRequest->current_step }}</span></td>
                                <td>{{ $returnRequest->requested_at?->format('Y-m-d h:i A') ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('building-survey-return-requests.show', $returnRequest) }}"
                                        class="btn btn-sm btn-light-primary">عرض</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        @if (auth()->user()->hasRole('Field Engineer'))
        <div class="modal fade" id="createReturnRequestModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered mw-650px">
                <div class="modal-content">
                    <form id="createReturnRequestForm">
                        @csrf
                        <div class="modal-header">
                            <h2 class="fw-bold">طلب إرجاع استبيان مبنى</h2>
                            <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                                <i class="ki-duotone ki-cross fs-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                        <div class="modal-body scroll-y mx-5 mx-xl-10 my-7">
                            <div id="createReturnRequestErrors" class="alert alert-danger d-none"></div>

                            <div class="mb-7">
                                <label class="required form-label fw-semibold">المبنى</label>
                                <select name="building_objectid" id="returnRequestBuildingSelect"
                                    class="form-select form-select-solid" data-control="select2"
                                    data-dropdown-parent="#createReturnRequestModal"
                                    data-placeholder="ابحث عن objectid أو اسم المبنى">
                                    <option value=""></option>
                                    @foreach ($buildings as $building)
                                        <option value="{{ $building->objectid }}">
                                            {{ $building->objectid }} - {{ $building->building_name ?? $building->globalid }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-7">
                                <label class="form-label fw-semibold">سبب الطلب</label>
                                <textarea name="reason" class="form-control form-control-solid" rows="4" placeholder="اكتب سبب إرجاع الاستبيان"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary" id="createReturnRequestSubmit">
                                <span class="indicator-label">إرسال الطلب</span>
                                <span class="indicator-progress">يرجى الانتظار...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif
@endsection

@section('script')
    <script>
        const returnRequestsTable = $('#returnRequestsTable').DataTable({
            pageLength: 25,
            order: [],
        });

        @if (auth()->user()->hasRole('Field Engineer'))
            $('#returnRequestBuildingSelect').select2({
                dir: 'rtl',
                width: '100%',
                dropdownParent: $('#createReturnRequestModal'),
            });

            $('#createReturnRequestModal').on('hidden.bs.modal', function() {
                $('#createReturnRequestForm')[0].reset();
                $('#returnRequestBuildingSelect').val('').trigger('change');
                $('#createReturnRequestErrors').addClass('d-none').html('');
            });

            $('#createReturnRequestForm').on('submit', function(event) {
                event.preventDefault();

                const form = $(this);
                const submitButton = $('#createReturnRequestSubmit');
                const errorsBox = $('#createReturnRequestErrors');

                submitButton.attr('data-kt-indicator', 'on').prop('disabled', true);
                errorsBox.addClass('d-none').html('');

                $.ajax({
                    url: "{{ route('building-survey-return-requests.store') }}",
                    type: "POST",
                    data: form.serialize(),
                    headers: {
                        Accept: 'application/json',
                    },
                    success: function(response) {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('createReturnRequestModal'))
                            .hide();

                        if (typeof toastr !== 'undefined') {
                            toastr.success(response.message || 'تم إرسال الطلب بنجاح.');
                        }

                        if (response.table_row) {
                            returnRequestsTable.row.add(response.table_row).draw(false);
                        }
                    },
                    error: function(xhr) {
                        const errors = xhr.responseJSON?.errors ?
                            Object.values(xhr.responseJSON.errors).flat() : [xhr.responseJSON?.message ||
                                'تعذر إرسال الطلب.'
                            ];

                        errorsBox.removeClass('d-none').html(errors.join('<br>'));

                        if (typeof toastr !== 'undefined') {
                            toastr.error(errors[0] || 'تعذر إرسال الطلب.');
                        }
                    },
                    complete: function() {
                        submitButton.removeAttr('data-kt-indicator').prop('disabled', false);
                    }
                });
            });
        @endif
    </script>
@endsection
