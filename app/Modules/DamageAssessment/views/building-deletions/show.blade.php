@extends('layouts.app')

@section('title', __('ui.building_deletions.show_title'))
@section('pageName', __('ui.building_deletions.title'))

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card card-flush mb-6">
        <div class="card-header pt-7">
            <div class="card-title d-block">
                <h2>#DEL-{{ str_pad((string) $request->id, 5, '0', STR_PAD_LEFT) }}</h2>
                <div class="text-muted">{{ $building?->building_name ?? __('ui.building_deletions.archived_building') }} | {{ $request->building_objectid }} | {{ $request->building_globalid }}</div>
            </div>
            <div class="card-toolbar">
                <span class="badge badge-light-primary fs-6">{{ __('ui.building_deletions.status_labels.'.$request->status->value) }}</span>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-5">
                @foreach (['request_submitted', 'gis_approved', 'snapshot_verified', 'gis_units_deleted', 'gis_building_deleted', 'local_archived', 'completed'] as $step)
                    <div class="col-md">
                        <div class="border rounded p-4 h-100 {{ $request->last_successful_step === $step ? 'bg-light-primary' : '' }}">
                            <div class="fw-bold">{{ __('ui.building_deletions.steps.'.$step) }}</div>
                            <div class="text-muted small">{{ $request->auditLogs->firstWhere('step', $step)?->created_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-lg-7">
            <div class="card card-flush mb-6">
                <div class="card-header"><h3 class="card-title">{{ __('ui.building_deletions.overview') }}</h3></div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">{{ __('ui.building_deletions.requested_by') }}</dt><dd class="col-sm-8">{{ $request->requester?->name }}</dd>
                        <dt class="col-sm-4">{{ __('ui.building_deletions.reason') }}</dt><dd class="col-sm-8">{{ $request->reason }}</dd>
                        <dt class="col-sm-4">{{ __('ui.building_deletions.notes') }}</dt><dd class="col-sm-8">{{ $request->notes ?? '-' }}</dd>
                        <dt class="col-sm-4">{{ __('ui.building_deletions.gis_reviewer') }}</dt><dd class="col-sm-8">{{ $request->gisReviewer?->name ?? '-' }}</dd>
                        <dt class="col-sm-4">{{ __('ui.building_deletions.failed_step') }}</dt><dd class="col-sm-8">{{ $request->failed_step ? __('ui.building_deletions.steps.'.$request->failed_step) : '-' }}</dd>
                        <dt class="col-sm-4">{{ __('ui.building_deletions.failure_reason') }}</dt><dd class="col-sm-8">{{ $request->failure_reason ?? '-' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card card-flush">
                <div class="card-header"><h3 class="card-title">{{ __('ui.building_deletions.execution_timeline') }}</h3></div>
                <div class="card-body">
                    @foreach ($request->auditLogs->sortByDesc('created_at') as $log)
                        <div class="border-bottom py-3">
                            <div class="fw-bold">{{ __('ui.building_deletions.steps.'.$log->step) }} <span class="badge badge-light">{{ __('ui.building_deletions.status_labels.'.$log->status) }}</span></div>
                            <div class="text-muted">{{ $log->created_at?->format('Y-m-d H:i:s') }} | {{ $log->user?->name ?? __('ui.building_deletions.system') }}</div>
                            <div>{{ $log->message }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-flush mb-6">
                <div class="card-header"><h3 class="card-title">{{ __('ui.building_deletions.snapshot') }}</h3></div>
                <div class="card-body">
                    @if ($request->latestSnapshot)
                        <div class="mb-3"><span class="fw-bold">{{ __('ui.building_deletions.hash') }}:</span> <span class="text-break">{{ $request->latestSnapshot->snapshot_hash }}</span></div>
                        <div class="mb-3"><span class="fw-bold">{{ __('ui.building_deletions.verified_at') }}:</span> {{ $request->latestSnapshot->verified_at?->format('Y-m-d H:i') }}</div>
                        <div class="row g-3">
                            <div class="col-6"><div class="border rounded p-3">{{ __('ui.building_deletions.base_units') }}<br><strong>{{ count($request->latestSnapshot->base_data['housing_units'] ?? []) }}</strong></div></div>
                            <div class="col-6"><div class="border rounded p-3">{{ __('ui.building_deletions.audited_units') }}<br><strong>{{ count($request->latestSnapshot->audited_data['housing_units'] ?? []) }}</strong></div></div>
                        </div>
                        @if ($canViewRawSnapshot)
                            <a href="{{ route('building-deletions.raw-snapshot', $request) }}" class="btn btn-light-primary mt-4">{{ __('ui.building_deletions.view_raw_json') }}</a>
                        @endif
                    @else
                        <div class="text-muted">{{ __('ui.building_deletions.snapshot_not_created') }}</div>
                    @endif
                </div>
            </div>

            @if ($canReview && $request->status === \App\Enums\BuildingDeletionStatus::PendingGisReview)
                <div class="card card-flush">
                    <div class="card-header"><h3 class="card-title">{{ __('ui.building_deletions.gis_decision') }}</h3></div>
                    <div class="card-body">
                        <div class="alert alert-danger">{{ __('ui.building_deletions.gis_decision_warning') }}</div>
                        <form method="POST" action="{{ route('building-deletions.review', $request) }}" id="gisReviewForm">
                            @csrf
                            <div class="mb-5">
                                <select name="decision" class="form-select form-select-solid" required>
                                    <option value="approve">{{ __('ui.building_deletions.approve_sign') }}</option>
                                    <option value="return">{{ __('ui.building_deletions.return_revision') }}</option>
                                    <option value="reject">{{ __('ui.building_deletions.reject') }}</option>
                                </select>
                            </div>
                            <label class="form-check form-check-custom form-check-solid mb-4">
                                <input class="form-check-input" type="checkbox" name="reviewed_all_records" value="1">
                                <span class="form-check-label">{{ __('ui.building_deletions.reviewed_all_records') }}</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid mb-5">
                                <input class="form-check-input" type="checkbox" name="understands_snapshot_gate" value="1">
                                <span class="form-check-label">{{ __('ui.building_deletions.understands_snapshot_gate') }}</span>
                            </label>
                            <textarea name="gis_notes" class="form-control form-control-solid mb-5" rows="3" placeholder="{{ __('ui.building_deletions.gis_notes') }}" required></textarea>
                            <button type="submit" class="btn btn-danger d-block">{{ __('ui.building_deletions.submit_decision') }}</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($canProcess && $request->status === \App\Enums\BuildingDeletionStatus::Failed)
                <form method="POST" action="{{ route('building-deletions.retry', $request) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning w-100 mt-5">{{ __('ui.building_deletions.retry_failed_step') }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection
