@extends('layouts.app')

@section('title', 'Building Deletion Management')
@section('pageName', 'Building Deletion Management')

@section('content')
    <div class="card card-flush">
        <div class="card-header pt-7">
            <div class="card-title">
                <h2>Building Deletion Management</h2>
            </div>
            <div class="card-toolbar">
                @can('create', \App\Models\BuildingDeletionRequest::class)
                    <a href="{{ route('building-deletions.create') }}" class="btn btn-primary">New Request</a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Request</th>
                            <th>ObjectID</th>
                            <th>GlobalID</th>
                            <th>Requested By</th>
                            <th>Status</th>
                            <th>Snapshot Hash</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $request)
                            <tr>
                                <td>#DEL-{{ str_pad((string) $request->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $request->building_objectid ?? '-' }}</td>
                                <td class="text-break">{{ $request->building_globalid }}</td>
                                <td>{{ $request->requester?->name ?? '-' }}</td>
                                <td><span class="badge badge-light-primary">{{ $request->status->value }}</span></td>
                                <td class="text-break">{{ $request->latestSnapshot?->snapshot_hash ?? '-' }}</td>
                                <td>{{ $request->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('building-deletions.show', $request) }}" class="btn btn-sm btn-light-primary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $requests->links() }}
        </div>
    </div>
@endsection
