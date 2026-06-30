@extends('layouts.app')

@section('title', 'HEKS Scores')
@section('pageName', 'Scores')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">Scores</h3>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Source</th>
                    <th>Grant</th>
                    <th>Payments</th>
                    <th>Scores</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($scores as $score)
                    <tr>
                        <form method="POST" action="{{ route('heks.scores.update', $score) }}">
                            @csrf
                            @method('PUT')
                            <td class="fw-bold">{{ $score->beneficiary?->code }}</td>
                            <td>{{ $score->beneficiary?->name ?? '-' }}</td>
                            <td>{{ $score->source }}</td>
                            <td><input name="grant_amount" class="form-control" value="{{ $score->grant_amount }}"></td>
                            <td>
                                <input name="payment_1" class="form-control mb-2" value="{{ $score->payment_1 }}" placeholder="Payment 1">
                                <input name="payment_2" class="form-control mb-2" value="{{ $score->payment_2 }}" placeholder="Payment 2">
                                <input name="payment_3" class="form-control" value="{{ $score->payment_3 }}" placeholder="Payment 3">
                            </td>
                            <td>
                                <input name="social_score" class="form-control mb-2" value="{{ $score->social_score }}" placeholder="Social">
                                <input name="technical_score" class="form-control mb-2" value="{{ $score->technical_score }}" placeholder="Technical">
                                <input name="total_score" class="form-control" value="{{ $score->total_score }}" placeholder="Total">
                            </td>
                            <td><button class="btn btn-sm btn-light-primary">Save</button></td>
                        </form>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No scores yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $scores->links() }}
        </div>
    </div>
@endsection
