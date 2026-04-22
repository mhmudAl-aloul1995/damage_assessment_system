@extends('layouts.app')

@section('title', 'Telegram Destinations')
@section('pageName', 'Telegram Destinations')

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold">Create Telegram Destination</h3>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('telegram.destinations.store') }}">
                @csrf
                <div class="row g-5">
                    <div class="col-md-3">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" class="form-control form-control-solid" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Type</label>
                        <select name="type" class="form-select form-select-solid" required>
                            <option value="user">User</option>
                            <option value="group">Group</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Scope</label>
                        <select name="scope_type" class="form-select form-select-solid" required>
                            @foreach ($scopes as $scope)
                                <option value="{{ $scope }}">{{ str($scope)->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Related User</label>
                        <select name="user_id" class="form-select form-select-solid">
                            <option value="">No related user</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}{{ $user->username_arcgis ? ' - '.$user->username_arcgis : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Context ID</label>
                        <input type="number" name="context_id" class="form-control form-control-solid">
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">Create Destination</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold">Telegram Destinations</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="telegram_destinations_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>Name</th>
                            <th>Type</th>
                            <th>Scope</th>
                            <th>Status</th>
                            <th>Chat ID</th>
                            <th>Telegram</th>
                            <th>Shareable Link</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#telegram_destinations_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('telegram.destinations.data') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'type', name: 'type' },
                    { data: 'scope_type', name: 'scope_type' },
                    { data: 'link_status', name: 'status' },
                    { data: 'chat_id', name: 'chat_id', defaultContent: '-' },
                    { data: 'telegram_username', name: 'telegram_username', defaultContent: '-' },
                    { data: 'shareable_link', name: 'shareable_link', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });
        });
    </script>
@endsection
