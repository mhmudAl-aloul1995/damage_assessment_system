@extends('layouts.app')

@section('title', 'Telegram Destination')
@section('pageName', 'Telegram Destination')

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning mb-5">{{ session('warning') }}</div>
    @endif

    <div class="row g-5">
        <div class="col-lg-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold">Destination Summary</h3></div>
                </div>
                <div class="card-body">
                    <div class="mb-4"><div class="text-muted fs-7">Name</div><div class="fw-bold">{{ $destination->name }}</div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Type</div><div class="fw-bold">{{ $destination->type }}</div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Scope</div><div class="fw-bold">{{ $destination->scope_type }}</div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Status</div><div class="fw-bold">{{ $destination->status }}</div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Chat ID</div><div class="fw-bold">{{ $destination->chat_id ?: '-' }}</div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Telegram Username</div><div class="fw-bold">{{ $destination->telegram_username ?: '-' }}</div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Linked At</div><div class="fw-bold">{{ optional($destination->linked_at)->format('Y-m-d H:i') ?: '-' }}</div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Related Model</div><div class="fw-bold">{{ $destination->relatedModel?->name ?? $destination->related_model_type ?? '-' }}</div></div>
                    <div class="mb-4"><div class="text-muted fs-7">Shareable Link</div><div class="fw-bold" style="word-break: break-all;">{{ $shareableLink ?: '-' }}</div></div>

                    <div class="d-flex flex-wrap gap-2 mt-6">
                        <form method="POST" action="{{ route('telegram.destinations.regenerate-link', $destination) }}">
                            @csrf
                            <button type="submit" class="btn btn-light-primary btn-sm">Regenerate Link</button>
                        </form>
                        <form method="POST" action="{{ route('telegram.destinations.refresh', $destination) }}">
                            @csrf
                            <button type="submit" class="btn btn-light-info btn-sm">Refresh Status</button>
                        </form>
                        <form method="POST" action="{{ route('telegram.destinations.unlink', $destination) }}">
                            @csrf
                            <button type="submit" class="btn btn-light-warning btn-sm">Unlink</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-flush border border-gray-200">
                <div class="card-header">
                    <div class="card-title"><h3 class="fw-bold">Destination Preferences</h3></div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('telegram.destinations.preferences.update', $destination) }}">
                        @csrf
                        @method('PUT')

                        @php
                            $preferences = $destination->preferences;
                        @endphp

                        <div class="row g-5">
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_new_records" value="1" @checked($preferences?->notify_new_records) />
                                    <span class="form-check-label">Notify new records</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_errors" value="1" @checked($preferences?->notify_errors) />
                                    <span class="form-check-label">Notify errors</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_status_changes" value="1" @checked($preferences?->notify_status_changes) />
                                    <span class="form-check-label">Notify status changes</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_reports" value="1" @checked($preferences?->notify_reports) />
                                    <span class="form-check-label">Notify reports</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notify_broadcasts" value="1" @checked($preferences?->notify_broadcasts) />
                                    <span class="form-check-label">Notify broadcasts</span>
                                </label>
                            </div>
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">Save Preferences</button>
                            </div>
                        </div>
                    </form>

                    <div class="separator my-8"></div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-1">Danger Zone</h4>
                            <div class="text-muted fs-7">Disable or permanently delete this destination.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('telegram.destinations.disable', $destination) }}">
                                @csrf
                                <button type="submit" class="btn btn-light-warning">Disable</button>
                            </form>
                            <form method="POST" action="{{ route('telegram.destinations.destroy', $destination) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light-danger" onclick="return confirm('Delete this destination?')">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
