@extends('layouts.app')

@section('title', 'HEKS Labels')
@section('pageName', 'Labels')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">Labels</h3>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Version</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($labels as $label)
                    <tr>
                        <form method="POST" action="{{ route('heks.labels.update', $label) }}">
                            @csrf
                            @method('PUT')
                            <td class="fw-bold">{{ $label->beneficiary?->code }}</td>
                            <td>{{ $label->beneficiary?->name ?? '-' }}</td>
                            <td><input name="label_key" class="form-control" value="{{ $label->label_key }}"></td>
                            <td><textarea name="label_value" class="form-control" rows="2">{{ $label->label_value }}</textarea></td>
                            <td><input name="version" class="form-control" value="{{ $label->version }}"></td>
                            <td><button class="btn btn-sm btn-light-primary">Save</button></td>
                        </form>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No labels yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $labels->links() }}
        </div>
    </div>
@endsection
