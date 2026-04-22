@extends('layouts.app')

@section('title', 'Telegram Broadcasts')
@section('pageName', 'Telegram Broadcasts')

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold">Create Broadcast</h3>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('telegram.broadcasts.store') }}">
                @csrf
                <div class="row g-5">
                    <div class="col-md-4">
                        <label class="form-label required">Title</label>
                        <input type="text" name="title" class="form-control form-control-solid" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Target Type</label>
                        <select name="target_type" class="form-select form-select-solid">
                            <option value="all">All destinations</option>
                            <option value="scope">By scope</option>
                            <option value="selected">Selected destinations</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Scope</label>
                        <select name="scope_type" class="form-select form-select-solid">
                            <option value="">No scope</option>
                            @foreach ($scopes as $scope)
                                <option value="{{ $scope }}">{{ str($scope)->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Context IDs</label>
                        <input type="text" name="context_ids[]" class="form-control form-control-solid" placeholder="e.g. 10">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Selected Destinations</label>
                        <select name="destination_ids[]" class="form-select form-select-solid" multiple data-control="select2" data-close-on-select="false">
                            @foreach ($destinations as $destination)
                                <option value="{{ $destination->id }}">{{ $destination->name }} ({{ $destination->scope_type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required">Message</label>
                        <textarea name="message" rows="5" class="form-control form-control-solid" required></textarea>
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">Queue Broadcast</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold">Broadcast Log</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="telegram_broadcasts_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>Title</th>
                            <th>Target Type</th>
                            <th>Status</th>
                            <th>Sent Count</th>
                            <th>Failed Count</th>
                            <th>Sent At</th>
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
            $('#telegram_broadcasts_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('telegram.broadcasts.data') }}',
                order: [[5, 'desc']],
                columns: [
                    { data: 'title', name: 'title' },
                    { data: 'target_type', name: 'target_type' },
                    { data: 'status', name: 'status' },
                    { data: 'sent_count', name: 'sent_count' },
                    { data: 'failed_count', name: 'failed_count' },
                    { data: 'sent_at', name: 'sent_at' },
                ]
            });
        });
    </script>
@endsection
