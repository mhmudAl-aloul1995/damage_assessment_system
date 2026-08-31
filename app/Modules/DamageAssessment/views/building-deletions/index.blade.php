@extends('layouts.app')

@section('title', __('ui.building_deletions.title'))
@section('pageName', __('ui.building_deletions.title'))

@section('content')
    <div class="card card-flush">
        <div class="card-header pt-7">
            <div class="card-title">
                <h2>{{ __('ui.building_deletions.title') }}</h2>
            </div>
            <div class="card-toolbar">
                @can('create', \App\Models\BuildingDeletionRequest::class)
                    <a href="{{ route('building-deletions.create') }}" class="btn btn-primary">{{ __('ui.building_deletions.new_request') }}</a>
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
                            <th>{{ __('ui.building_deletions.request') }}</th>
                            <th>{{ __('ui.building_deletions.object_id') }}</th>
                            <th>{{ __('ui.building_deletions.global_id') }}</th>
                            <th>{{ __('ui.building_deletions.requested_by') }}</th>
                            <th>{{ __('ui.building_deletions.status') }}</th>
                            <th>{{ __('ui.building_deletions.snapshot_hash') }}</th>
                            <th>{{ __('ui.building_deletions.created') }}</th>
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
                                <td><span class="badge badge-light-primary">{{ __('ui.building_deletions.status_labels.'.$request->status->value) }}</span></td>
                                <td class="text-break">{{ $request->latestSnapshot?->snapshot_hash ?? '-' }}</td>
                                <td>{{ $request->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('building-deletions.show', $request) }}" class="btn btn-sm btn-light-primary">{{ __('ui.building_deletions.view') }}</a>
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
