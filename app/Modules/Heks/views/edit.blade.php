@extends('layouts.app')

@section('title', 'Edit HEKS Beneficiary')
@section('pageName', $beneficiary->code)

@section('content')
    @include('heks::partials.nav')

    <form method="POST" action="{{ route('heks.beneficiaries.update', $beneficiary) }}" class="card card-flush mb-6">
        @csrf
        @method('PUT')
        <div class="card-header"><h3 class="card-title">{{ $beneficiary->code }} - {{ $beneficiary->name }}</h3></div>
        <div class="card-body row g-4">
            @foreach ([
                'name' => 'Name',
                'identity_number' => 'ID',
                'phone' => 'Phone',
                'alternate_phone' => 'Alt Phone',
                'field_engineer' => 'Engineer',
                'visit_date' => 'Visit Date',
                'governorate' => 'Governorate',
                'area' => 'Area',
                'displacement_status' => 'Displacement',
                'occupancy_status' => 'Occupancy',
                'damage_status' => 'Damage',
                'grant_amount' => 'Grant',
                'payment_1' => 'Payment 1',
                'payment_2' => 'Payment 2',
                'payment_3' => 'Payment 3',
            ] as $field => $label)
                <div class="col-md-4">
                    <label class="form-label">{{ $label }}</label>
                    <input name="{{ $field }}" class="form-control" value="{{ old($field, $field === 'visit_date' ? $beneficiary->{$field}?->format('Y-m-d') : $beneficiary->{$field}) }}">
                </div>
            @endforeach
            @foreach (['address' => 'Address', 'social_notes' => 'Social Notes', 'engineer_notes' => 'Engineer Notes', 'recommendations' => 'Recommendations'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label">{{ $label }}</label>
                    <textarea name="{{ $field }}" class="form-control" rows="3">{{ old($field, $beneficiary->{$field}) }}</textarea>
                </div>
            @endforeach
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary">Save Beneficiary</button>
        </div>
    </form>

    <div class="row g-5">
        <div class="col-lg-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">Labels</h3></div>
                <div class="card-body">
                    @forelse ($beneficiary->labels as $label)
                        <form method="POST" action="{{ route('heks.labels.update', $label) }}" class="border-bottom pb-3 mb-3">
                            @csrf
                            @method('PUT')
                            <input name="label_key" class="form-control mb-2" value="{{ $label->label_key }}">
                            <textarea name="label_value" class="form-control mb-2" rows="2">{{ $label->label_value }}</textarea>
                            <input name="version" class="form-control mb-2" value="{{ $label->version }}">
                            <button class="btn btn-sm btn-light-primary">Save</button>
                        </form>
                    @empty
                        <div class="text-muted">No labels.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">Follow-ups</h3></div>
                <div class="card-body">
                    @forelse ($beneficiary->followUps as $followUp)
                        <form method="POST" action="{{ route('heks.follow-ups.update', $followUp) }}" class="border-bottom pb-3 mb-3">
                            @csrf
                            @method('PUT')
                            <input name="visit_date" class="form-control mb-2" value="{{ $followUp->visit_date?->format('Y-m-d') }}">
                            <input name="engineer_name" class="form-control mb-2" value="{{ $followUp->engineer_name }}">
                            <input name="working_condition" class="form-control mb-2" value="{{ $followUp->working_condition }}">
                            <input name="completion_percentage" class="form-control mb-2" value="{{ $followUp->completion_percentage }}">
                            <textarea name="engineer_recommendations" class="form-control mb-2" rows="2">{{ $followUp->engineer_recommendations }}</textarea>
                            <button class="btn btn-sm btn-light-primary">Save</button>
                        </form>
                    @empty
                        <div class="text-muted">No follow-ups.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">Scores</h3></div>
                <div class="card-body">
                    @forelse ($beneficiary->scores as $score)
                        <form method="POST" action="{{ route('heks.scores.update', $score) }}" class="border-bottom pb-3 mb-3">
                            @csrf
                            @method('PUT')
                            <input name="grant_amount" class="form-control mb-2" value="{{ $score->grant_amount }}" placeholder="Grant">
                            <input name="payment_1" class="form-control mb-2" value="{{ $score->payment_1 }}" placeholder="Payment 1">
                            <input name="payment_2" class="form-control mb-2" value="{{ $score->payment_2 }}" placeholder="Payment 2">
                            <input name="payment_3" class="form-control mb-2" value="{{ $score->payment_3 }}" placeholder="Payment 3">
                            <input name="social_score" class="form-control mb-2" value="{{ $score->social_score }}" placeholder="Social">
                            <input name="technical_score" class="form-control mb-2" value="{{ $score->technical_score }}" placeholder="Technical">
                            <button class="btn btn-sm btn-light-primary">Save</button>
                        </form>
                    @empty
                        <div class="text-muted">No scores.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mt-1">
        <div class="col-lg-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">Payments</h3></div>
                <div class="card-body">
                    @forelse ($beneficiary->payments as $payment)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="fw-bold">{{ $payment->source }}</div>
                            <div class="text-muted small">Grant: {{ $payment->grant_amount ? number_format((float) $payment->grant_amount, 2) : '-' }}</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <span class="badge badge-light">30% {{ $payment->payment_1_amount ?? '-' }}</span>
                                <span class="badge badge-light">50% {{ $payment->payment_2_amount ?? '-' }}</span>
                                <span class="badge badge-light">20% {{ $payment->payment_3_amount ?? '-' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">No payments.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">Assignments</h3></div>
                <div class="card-body">
                    @forelse ($beneficiary->workAssignments as $assignment)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="fw-bold">{{ $assignment->engineer_name ?? '-' }}</div>
                            <div class="text-muted small">{{ $assignment->source }}</div>
                            <div class="mt-2">Contract: {{ $assignment->contract_amount_ils ? number_format((float) $assignment->contract_amount_ils, 2) : '-' }} ILS</div>
                        </div>
                    @empty
                        <div class="text-muted">No assignments.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">Attachments</h3></div>
                <div class="card-body">
                    @forelse ($beneficiary->attachments as $attachment)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="fw-bold text-truncate">{{ $attachment->filename ?? '-' }}</div>
                            <div class="text-muted small">{{ $attachment->attachment_type ?? $attachment->source }}</div>
                            @if ($attachment->url)
                                <a class="btn btn-sm btn-light mt-2" href="{{ $attachment->url }}" target="_blank" rel="noopener">Open</a>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">No attachments.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
