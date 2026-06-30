@extends('layouts.app')

@section('title', 'HEKS Data Quality')
@section('pageName', 'Data Quality')

@section('content')
    @include('heks::partials.nav')

    <div class="row g-5 mb-6">
        @foreach ([
            'Missing identity numbers' => $missingIdentity,
            'Without scores' => $missingScores,
            'Without follow-ups' => $missingFollowUps,
            'Duplicate identity numbers' => $duplicateIdentities->count(),
        ] as $label => $value)
            <div class="col-md-3 col-6">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-gray-500 fw-semibold">{{ $label }}</div>
                        <div class="fs-2hx fw-bold">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">Duplicate IDs</h3>
        </div>
        <div class="card-body">
            @forelse ($duplicateIdentities as $identity => $count)
                <div class="d-flex justify-content-between border-bottom py-3">
                    <span class="fw-semibold">{{ $identity }}</span>
                    <span class="badge badge-light-danger">{{ $count }}</span>
                </div>
            @empty
                <div class="text-muted">No duplicate identity numbers found.</div>
            @endforelse
        </div>
    </div>
@endsection
