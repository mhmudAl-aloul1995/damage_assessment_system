@extends('layouts.app')

@section('title', 'المساعدة النقدية لإصلاح المأوى الطارئ (HEKS)')
@section('pageName', 'HEKS Dashboard')

@section('content')
    @include('heks::partials.nav')

    <div class="row g-5 mb-6">
        @foreach ([
            'beneficiaries' => 'Beneficiaries',
            'labels' => 'Labels',
            'follow_ups' => 'Follow-ups',
            'scores' => 'Scores',
            'imports' => 'Imports',
            'grant_total' => 'Grant ILS',
        ] as $key => $label)
            <div class="col-md-2 col-6">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-gray-500 fw-semibold">{{ $label }}</div>
                        <div class="fs-2hx fw-bold">{{ is_float($stats[$key]) ? number_format($stats[$key], 2) : $stats[$key] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-5">
        <div class="col-lg-6">
            <div class="card card-flush">
                <div class="card-header"><h3 class="card-title">Latest Imports</h3></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>File</th><th>Type</th><th>Rows</th><th>Date</th></tr></thead>
                            <tbody>
                            @forelse ($latestImports as $import)
                                <tr>
                                    <td>{{ $import->filename }}</td>
                                    <td><span class="badge badge-light-primary">{{ $import->type }}</span></td>
                                    <td>{{ $import->total_rows }}</td>
                                    <td>{{ $import->created_at?->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">No imports yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-flush">
                <div class="card-header"><h3 class="card-title">Label Distribution</h3></div>
                <div class="card-body">
                    @forelse ($labelDistribution as $label => $count)
                        <div class="d-flex justify-content-between border-bottom py-3">
                            <span class="fw-semibold">{{ $label }}</span>
                            <span class="badge badge-light">{{ $count }}</span>
                        </div>
                    @empty
                        <div class="text-muted">No labels imported yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
