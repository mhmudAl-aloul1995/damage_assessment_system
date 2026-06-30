@extends('layouts.app')

@section('title', 'HEKS Beneficiaries')
@section('pageName', 'Beneficiaries')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush">
        <div class="card-header align-items-end">
            <h3 class="card-title">Beneficiaries</h3>
            <form class="card-toolbar row g-2" method="GET">
                <div class="col-auto">
                    <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search code, name, ID">
                </div>
                <div class="col-auto">
                    <select name="selected" class="form-select">
                        <option value="">All</option>
                        <option value="1" @selected(request('selected') === '1')>Selected</option>
                        <option value="0" @selected(request('selected') === '0')>Not selected</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="engineer" class="form-select">
                        <option value="">All engineers</option>
                        @foreach ($engineers as $engineer)
                            <option value="{{ $engineer }}" @selected(request('engineer') === $engineer)>{{ $engineer }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-light-primary">Filter</button>
                </div>
            </form>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>ID</th>
                    <th>Engineer</th>
                    <th>Grant</th>
                    <th>Status</th>
                    <th>Related</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($beneficiaries as $beneficiary)
                    <tr>
                        <td class="fw-bold">{{ $beneficiary->code }}</td>
                        <td>{{ $beneficiary->name ?? '-' }}</td>
                        <td>{{ $beneficiary->identity_number ?? '-' }}</td>
                        <td>{{ $beneficiary->field_engineer ?? '-' }}</td>
                        <td>{{ $beneficiary->grant_amount ? number_format((float) $beneficiary->grant_amount, 2) : '-' }}</td>
                        <td>
                            <span class="badge {{ $beneficiary->is_selected ? 'badge-light-success' : 'badge-light' }}">
                                {{ $beneficiary->is_selected ? 'selected' : 'assessed' }}
                            </span>
                            @if ($beneficiary->payment_status)
                                <span class="badge badge-light-warning">{{ str_replace('_', ' ', $beneficiary->payment_status) }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-light">{{ $beneficiary->labels_count }} labels</span>
                            <span class="badge badge-light">{{ $beneficiary->scores_count }} scores</span>
                            <span class="badge badge-light">{{ $beneficiary->payments_count }} pay</span>
                            <span class="badge badge-light">{{ $beneficiary->work_assignments_count }} assign</span>
                            <span class="badge badge-light">{{ $beneficiary->attachments_count }} files</span>
                        </td>
                        <td><a href="{{ route('heks.beneficiaries.edit', $beneficiary) }}" class="btn btn-sm btn-light-primary">Open</a></td>
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
