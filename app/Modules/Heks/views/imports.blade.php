@extends('layouts.app')

@section('title', 'HEKS Imports')
@section('pageName', 'Imports')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush mb-6">
        <div class="card-header"><h3 class="card-title">Import Excel File</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('heks.imports.store') }}" enctype="multipart/form-data" class="row g-4 align-items-end">
                @csrf
                <div class="col-lg-4">
                    <label class="form-label">File</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="auto">Auto detect</option>
                        <option value="followups">Follow-ups</option>
                        <option value="scores">Scoring / Payments / Labels</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button class="btn btn-primary w-100">Import</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header"><h3 class="card-title">Import History</h3></div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead><tr><th>File</th><th>Type</th><th>Sheets</th><th>Total</th><th>Created</th><th>Updated</th><th>Skipped</th><th>User</th><th>Date</th></tr></thead>
                <tbody>
                @forelse ($imports as $import)
                    <tr>
                        <td>{{ $import->filename }}</td>
                        <td>{{ $import->type }}</td>
                        <td>{{ $import->sheet_name }}</td>
                        <td>{{ $import->total_rows }}</td>
                        <td>{{ $import->created_rows }}</td>
                        <td>{{ $import->updated_rows }}</td>
                        <td>{{ $import->skipped_rows }}</td>
                        <td>{{ $import->user?->name ?? '-' }}</td>
                        <td>{{ $import->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">No imports yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $imports->links() }}
        </div>
    </div>
@endsection
