@extends('layouts.app')

@php
    $recordName = $recordType === 'housing-unit'
        ? ($decisionable->housing_unit_number ?: $decisionable->full_name ?: $decisionable->objectid)
        : ($decisionable->building_name ?: $decisionable->objectid);
@endphp

@section('title', __('multilingual.committee_decision_show.title'))
@section('pageName', __('multilingual.committee_decision_show.page_name'))

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif

    <div class="row g-5 mb-5">
        <div class="col-lg-4">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold m-0">{{ __('multilingual.committee_decision_show.summary_title') }}</h3></div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.record_type') }}</div>
                        <div class="fw-bold">{{ $recordType === 'housing-unit' ? __('multilingual.committee_decision_show.housing_unit') : __('multilingual.committee_decision_show.building') }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.record_number') }}</div>
                        <div class="fw-bold">{{ $decisionable->objectid ?? '-' }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.name') }}</div>
                        <div class="fw-bold">{{ $recordName }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.building') }}</div>
                        <div class="fw-bold">{{ $building?->building_name ?? '-' }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.field_engineer') }}</div>
                        <div class="fw-bold">{{ $building?->assignedto ?? '-' }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.decision_status') }}</div>
                        <div class="fw-bold">{{ $statusLabels[$decision->status] ?? $decision->status }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.committee_decision') }}</div>
                        <div class="fw-bold">{{ $decisionTypes[$decision->decision_type] ?? '-' }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.action') }}</div>
                        <div class="fw-bold">{{ $decision->action_text ?: '-' }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.required_signatures') }}</div>
                        <div class="fw-bold">
                            {{ $decision->signatures->filter(fn ($signature) => $signature->committeeMember?->is_required && $signature->committeeMember?->is_active)->where('status', 'approved')->count() }}
                            /
                            {{ $decision->signatures->filter(fn ($signature) => $signature->committeeMember?->is_required && $signature->committeeMember?->is_active)->count() }}
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.arcgis_status') }}</div>
                        <div class="fw-bold">{{ $decision->arcgis_sync_status ?: 'pending' }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-7">{{ __('multilingual.committee_decision_show.telegram_status') }}</div>
                        <div class="fw-bold">{{ $decision->telegram_status ?: 'pending' }}</div>
                    </div>
                    @if ($canRetryTelegram && $decision->isCompleted() && $decision->telegram_status !== 'sent')
                        <div class="mt-6">
                            <form method="POST" action="{{ route('committee-decisions.retry-telegram', $decision) }}">
                                @csrf
                                <button type="submit" class="btn btn-light-success btn-sm">{{ __('multilingual.committee_decision_show.retry_telegram') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-flush border border-gray-200 mb-5">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold m-0">{{ __('multilingual.committee_decision_show.form_title') }}</h3></div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('committee-decisions.update', $decision) }}">
                        @csrf
                        @method('PUT')

                        <div class="row g-5">
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('multilingual.committee_decision_show.decision_type') }}</label>
                                <select name="decision_type" class="form-select form-select-solid" {{ $canManageContent ? '' : 'disabled' }}>
                                    @foreach ($decisionTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('decision_type', $decision->decision_type) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('multilingual.committee_decision_show.decision_date') }}</label>
                                <input type="date" name="decision_date" class="form-control form-control-solid"
                                    value="{{ old('decision_date', optional($decision->decision_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}"
                                    {{ $canManageContent ? '' : 'disabled' }}>
                            </div>
                            <div class="col-12">
                                <label class="form-label required">{{ __('multilingual.committee_decision_show.decision_text') }}</label>
                                <textarea name="decision_text" rows="5" class="form-control form-control-solid" {{ $canManageContent ? '' : 'disabled' }}>{{ old('decision_text', $decision->decision_text) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('multilingual.committee_decision_show.action_text') }}</label>
                                <textarea name="action_text" rows="3" class="form-control form-control-solid" {{ $canManageContent ? '' : 'disabled' }}>{{ old('action_text', $decision->action_text) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('multilingual.committee_decision_show.notes') }}</label>
                                <textarea name="notes" rows="3" class="form-control form-control-solid" {{ $canManageContent ? '' : 'disabled' }}>{{ old('notes', $decision->notes) }}</textarea>
                            </div>
                            @if ($canManageContent)
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary">{{ __('multilingual.committee_decision_show.save_decision') }}</button>
                                </div>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-flush border border-gray-200">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold m-0">{{ __('multilingual.committee_decision_show.signatures_title') }}</h3></div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th>{{ __('multilingual.committee_decision_show.columns.member') }}</th>
                                    <th>{{ __('multilingual.committee_decision_show.columns.title') }}</th>
                                    <th>{{ __('multilingual.committee_decision_show.columns.required') }}</th>
                                    <th>{{ __('multilingual.committee_decision_show.columns.status') }}</th>
                                    <th>{{ __('multilingual.committee_decision_show.columns.notes') }}</th>
                                    <th>{{ __('multilingual.committee_decision_show.columns.signed_at') }}</th>
                                    <th>{{ __('multilingual.committee_decision_show.columns.user') }}</th>
                                    <th>{{ __('multilingual.committee_decision_show.columns.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($decision->signatures->sortBy(fn ($signature) => $signature->committeeMember->sort_order ?? 0) as $signature)
                                    @php
                                        $isLinkedToCurrentUser = ! $signature->committeeMember?->user_id || $signature->committeeMember?->user_id === auth()->id();
                                        $signatureLocked = $decision->isCompleted();
                                        $signatureReason = null;

                                        if (! $canSign) {
                                            $signatureReason = __('multilingual.committee_decision_show.reasons.no_permission');
                                        } elseif (! $signature->committeeMember?->is_active) {
                                            $signatureReason = __('multilingual.committee_decision_show.reasons.member_inactive');
                                        } elseif (! $isLinkedToCurrentUser) {
                                            $signatureReason = __('multilingual.committee_decision_show.reasons.linked_to_other_user');
                                        } elseif ($signatureLocked) {
                                            $signatureReason = __('multilingual.committee_decision_show.reasons.decision_completed');
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $signature->committeeMember?->name }}</td>
                                        <td>{{ $signature->committeeMember?->title ?: '-' }}</td>
                                        <td>
                                            <span class="badge badge-light-{{ $signature->committeeMember?->is_required ? 'primary' : 'secondary' }}">
                                                {{ $signature->committeeMember?->is_required ? __('multilingual.committee_decision_show.required_badge') : __('multilingual.committee_decision_show.optional_badge') }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-{{ $signature->status === 'approved' ? 'success' : ($signature->status === 'rejected' ? 'danger' : 'warning') }}">
                                                {{ __('multilingual.committee_decision_show.signature_statuses.'.$signature->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $signature->notes ?: '-' }}</td>
                                        <td>{{ optional($signature->signed_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                        <td>{{ $signature->signedByUser?->name ?: '-' }}</td>
                                        <td>
                                            @if (! $signatureReason)
                                                <form method="POST" action="{{ route('committee-decisions.sign', $decision) }}" class="d-flex gap-2 flex-wrap">
                                                    @csrf
                                                    <input type="hidden" name="committee_member_id" value="{{ $signature->committee_member_id }}">
                                                    <select name="status" class="form-select form-select-sm form-select-solid w-125px">
                                                        <option value="approved">{{ __('multilingual.committee_decision_show.signature_statuses.approved') }}</option>
                                                        <option value="rejected">{{ __('multilingual.committee_decision_show.signature_statuses.rejected') }}</option>
                                                        <option value="pending">{{ __('multilingual.committee_decision_show.signature_statuses.pending') }}</option>
                                                    </select>
                                                    <input type="text" name="notes" class="form-control form-control-sm form-control-solid w-175px" placeholder="{{ __('multilingual.committee_decision_show.columns.notes') }}">
                                                    <button type="submit" class="btn btn-light-primary btn-sm">{{ __('multilingual.committee_decision_show.submit_signature') }}</button>
                                                </form>
                                            @else
                                                <span class="text-muted">{{ $signatureReason }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
