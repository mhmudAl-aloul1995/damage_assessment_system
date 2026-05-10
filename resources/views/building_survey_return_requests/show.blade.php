@extends('layouts.app')

@section('title', 'تفاصيل طلب الإرجاع')
@section('pageName', 'تفاصيل الطلب')

@section('content')
    @php
        $buildingGovernorate = strtolower(trim((string) $returnRequest->building?->governorate));
        $buildingRegion = match ($buildingGovernorate) {
            'north gaza', 'gaza' => 'north',
            'deir al-balah', 'deir al balah', 'middle area', 'khan younis', 'rafah' => 'south',
            default => null,
        };
        $autoAreaManager = $buildingRegion ? $areaManagers->firstWhere('region', $buildingRegion) : null;
        $canTeamLeaderApprove =
            auth()->user()->hasAnyRole(['Team Leader', 'Team leader']) &&
            (int) $returnRequest->team_leader_id === (int) auth()->id() &&
            $returnRequest->current_step === 'team_leader';
        $canAreaManagerApprove =
            auth()->user()->hasRole('Area Manager') &&
            (int) $returnRequest->area_manager_id === (int) auth()->id() &&
            $returnRequest->current_step === 'area_manager';
    @endphp

    <div class="card card-flush mb-7">
        <div class="card-header pt-7">
            <div class="card-title">
                <h2>تفاصيل طلب الإرجاع #{{ $returnRequest->id }}</h2>
            </div>
            <div class="card-toolbar">
                @if ($canTeamLeaderApprove)
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#teamLeaderApproveModal">
                        موافقة Team Leader
                    </button>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">رفض</button>
                @endif
                @if ($canAreaManagerApprove)
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#areaManagerApproveModal">
                        موافقة Area Manager
                    </button>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">رفض</button>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div id="workflowPageMessage" class="alert d-none"></div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="row g-5">
                <div class="col-md-4">
                    <span class="text-muted d-block">اسم المبنى</span>
                    <span class="fw-bold">{{ $returnRequest->building?->building_name ?? '-' }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">objectid</span>
                    <span class="fw-bold">{{ $returnRequest->building_objectid }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">globalid</span>
                    <span class="fw-bold">{{ $returnRequest->building_globalid ?? '-' }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">مقدم الطلب</span>
                    <span>{{ $returnRequest->requester?->name ?? '-' }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Team Leader</span>
                    <span>{{ $returnRequest->teamLeader?->name ?? '-' }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Area Manager</span>
                    <span>{{ $returnRequest->areaManager?->name ?? '-' }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Status</span>
                    <span class="badge badge-light-primary">{{ $returnRequest->status }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Current Step</span>
                    <span class="badge badge-light-info">{{ $returnRequest->current_step }}</span>
                </div>
                <div class="col-md-12">
                    <span class="text-muted d-block">سبب الطلب / الرفض</span>
                    <span>{{ $returnRequest->reason ?? '-' }}</span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">ملاحظات Team Leader</span>
                    <span>{{ $returnRequest->team_leader_notes ?? '-' }}</span>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">ملاحظات Area Manager</span>
                    <span>{{ $returnRequest->area_manager_notes ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header pt-7">
            <div class="card-title">
                <h3>Timeline</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="timeline">
                @foreach ($returnRequest->logs as $log)
                    <div class="timeline-item">
                        <div class="timeline-line w-40px"></div>
                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                            <div class="symbol-label bg-light">
                                <i class="ki-duotone ki-check fs-2 text-primary"></i>
                            </div>
                        </div>
                        <div class="timeline-content mb-10 mt-n1">
                            <div class="fw-bold">{{ $log->action }} - {{ $log->step }}</div>
                            <div class="text-muted">
                                {{ $log->user?->name ?? '-' }} | {{ $log->created_at?->format('Y-m-d h:i A') }}
                            </div>
                            <div>{{ $log->notes ?? '-' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="modal fade" id="teamLeaderApproveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content workflow-ajax-form"
                action="{{ route('building-survey-return-requests.team-leader.approve', $returnRequest) }}">
                @csrf
                <div class="modal-header">
                    <h3>موافقة Team Leader</h3>
                </div>
                <div class="modal-body">
                    <div class="workflow-form-errors alert alert-danger d-none"></div>
                    <div class="alert alert-info">
                        <div class="fw-bold mb-1">Area Manager سيتم تحديده تلقائياً حسب محافظة المبنى.</div>
                        <div>المحافظة: {{ $returnRequest->building?->governorate ?? '-' }}</div>
                        <div>المنطقة: {{ $buildingRegion ?? '-' }}</div>
                        <div>Area Manager: {{ $autoAreaManager?->name ?? 'غير متوفر' }}</div>
                    </div>
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control form-control-solid" rows="4"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button class="btn btn-success" type="submit">
                        <span class="indicator-label">موافقة</span>
                        <span class="indicator-progress">يرجى الانتظار...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="areaManagerApproveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content workflow-ajax-form"
                action="{{ route('building-survey-return-requests.area-manager.approve', $returnRequest) }}">
                @csrf
                <div class="modal-header">
                    <h3>موافقة Area Manager</h3>
                </div>
                <div class="modal-body">
                    <div class="workflow-form-errors alert alert-danger d-none"></div>
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control form-control-solid" rows="4"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button class="btn btn-success" type="submit">
                        <span class="indicator-label">موافقة نهائية</span>
                        <span class="indicator-progress">يرجى الانتظار...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content workflow-ajax-form"
                action="{{ route('building-survey-return-requests.reject', $returnRequest) }}">
                @csrf
                <div class="modal-header">
                    <h3>رفض الطلب</h3>
                </div>
                <div class="modal-body">
                    <div class="workflow-form-errors alert alert-danger d-none"></div>
                    <label class="required form-label">سبب الرفض</label>
                    <textarea name="reason" class="form-control form-control-solid" rows="4" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button class="btn btn-danger" type="submit">
                        <span class="indicator-label">رفض</span>
                        <span class="indicator-progress">يرجى الانتظار...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $('[data-control="select2"]').select2({
            dir: 'rtl',
            width: '100%'
        });

        $('.workflow-ajax-form').on('submit', function(event) {
            event.preventDefault();

            const form = $(this);
            const submitButton = form.find('[type="submit"]');
            const errorsBox = form.find('.workflow-form-errors');

            submitButton.attr('data-kt-indicator', 'on').prop('disabled', true);
            errorsBox.addClass('d-none').html('');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                headers: {
                    Accept: 'application/json',
                },
                success: function(response) {
                    bootstrap.Modal.getOrCreateInstance(form.closest('.modal')[0]).hide();

                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'تم تنفيذ الإجراء بنجاح.');
                    }

                    $('#workflowPageMessage')
                        .removeClass('d-none alert-danger')
                        .addClass('alert-success')
                        .text(response.message || 'تم تنفيذ الإجراء بنجاح.');

                    $('.card-toolbar button').prop('disabled', true);

                    if (response.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.redirect_url;
                        }, 700);
                    }
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors ?
                        Object.values(xhr.responseJSON.errors).flat() : [xhr.responseJSON?.message ||
                            'تعذر تنفيذ الإجراء.'
                        ];

                    errorsBox.removeClass('d-none').html(errors.join('<br>'));

                    if (typeof toastr !== 'undefined') {
                        toastr.error(errors[0] || 'تعذر تنفيذ الإجراء.');
                    }
                },
                complete: function() {
                    submitButton.removeAttr('data-kt-indicator').prop('disabled', false);
                }
            });
        });
    </script>
@endsection
