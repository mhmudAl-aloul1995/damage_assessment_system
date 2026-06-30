@extends('layouts.app')

@section('title', 'HEKS Follow-ups')
@section('pageName', 'Follow-ups')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">Follow-ups</h3>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Visit</th>
                    <th>Engineer</th>
                    <th>Condition</th>
                    <th>Completed ILS</th>
                    <th>%</th>
                    <th>Recommendations</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($followUps as $followUp)
                    <tr>
                        <form method="POST" action="{{ route('heks.follow-ups.update', $followUp) }}">
                            @csrf
                            @method('PUT')
                            <td class="fw-bold">{{ $followUp->code }}</td>
                            <td>{{ $followUp->beneficiary?->name ?? '-' }}</td>
                            <td>
                                <input name="visit_number" class="form-control mb-2" value="{{ $followUp->visit_number }}">
                                <input name="visit_date" class="form-control" value="{{ $followUp->visit_date?->format('Y-m-d') }}">
                            </td>
                            <td><input name="engineer_name" class="form-control" value="{{ $followUp->engineer_name }}"></td>
                            <td>
                                <input name="working_condition" class="form-control mb-2" value="{{ $followUp->working_condition }}">
                                <textarea name="other_condition" class="form-control" rows="2">{{ $followUp->other_condition }}</textarea>
                            </td>
                            <td><input name="completed_amount_ils" class="form-control" value="{{ $followUp->completed_amount_ils }}"></td>
                            <td><input name="completion_percentage" class="form-control" value="{{ $followUp->completion_percentage }}"></td>
                            <td><textarea name="engineer_recommendations" class="form-control" rows="2">{{ $followUp->engineer_recommendations }}</textarea></td>
                            <td><button class="btn btn-sm btn-light-primary">Save</button></td>
                        </form>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">No follow-ups yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $followUps->links() }}
        </div>
    </div>
@endsection
