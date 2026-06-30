@extends('layouts.app')

@section('title', 'HEKS Beneficiaries')
@section('pageName', 'Beneficiaries')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">Beneficiaries</h3>
            <form class="card-toolbar" method="GET">
                <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search code, name, ID">
            </form>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Code</th><th>Name</th><th>ID</th><th>Phone</th><th>Engineer</th><th>Grant</th><th>Related</th><th></th></tr></thead>
                <tbody>
                @forelse ($beneficiaries as $beneficiary)
                    <tr>
                        <td class="fw-bold">{{ $beneficiary->code }}</td>
                        <td>{{ $beneficiary->name ?? '-' }}</td>
                        <td>{{ $beneficiary->identity_number ?? '-' }}</td>
                        <td>{{ $beneficiary->phone ?? '-' }}</td>
                        <td>{{ $beneficiary->field_engineer ?? '-' }}</td>
                        <td>{{ $beneficiary->grant_amount ? number_format((float) $beneficiary->grant_amount, 2) : '-' }}</td>
                        <td>
                            <span class="badge badge-light">{{ $beneficiary->labels_count }} labels</span>
                            <span class="badge badge-light">{{ $beneficiary->follow_ups_count }} FU</span>
                            <span class="badge badge-light">{{ $beneficiary->scores_count }} scores</span>
                        </td>
                        <td><a href="{{ route('heks.beneficiaries.edit', $beneficiary) }}" class="btn btn-sm btn-light-primary">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">No beneficiaries yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $beneficiaries->links() }}
        </div>
    </div>
@endsection
