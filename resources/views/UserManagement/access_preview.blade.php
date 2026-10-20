@extends('layouts.app')
@section('title', __('access_preview.title'))
@section('pageName', __('access_preview.title'))

@section('content')
<div class="card">
    <div class="card-header align-items-center gap-4 py-5">
        <div><h2>{{ __('access_preview.title') }}</h2><p class="text-muted mb-0">{{ __('access_preview.subtitle') }}</p></div>
        <a href="{{ route('users.index') }}" class="btn btn-light">{{ __('access.users') }}</a>
    </div>
    <div class="card-body">
        @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('access-preview.store') }}">
            @csrf
            <div class="row g-6">
                <div class="col-12">
                    <label for="preview-mode" class="form-label required">{{ __('access_preview.mode') }}</label>
                    <select id="preview-mode" name="mode" class="form-select" required>
                        <option value="user" @selected(old('mode', request('mode')) !== 'role')>{{ __('access_preview.user_mode') }}</option>
                        <option value="role" @selected(old('mode', request('mode')) === 'role')>{{ __('access_preview.role_mode') }}</option>
                    </select>
                </div>
                <div class="col-lg-6">
                    <label for="preview-user" class="form-label required">{{ __('access_preview.user') }}</label>
                    <select id="preview-user" name="user_id" class="form-select" data-control="select2" aria-describedby="preview-user-help" required>
                        @foreach($users as $previewUser)
                            <option value="{{ $previewUser->id }}" @selected((int) old('user_id', $selectedUserId) === $previewUser->id)>{{ $previewUser->name }} — {{ $previewUser->email }}</option>
                        @endforeach
                    </select>
                    <p id="preview-user-help" class="form-text">{{ __('access_preview.user_help') }}</p>
                </div>
                <div class="col-lg-6">
                    <label for="preview-role" class="form-label">{{ __('access_preview.role') }}</label>
                    <select id="preview-role" name="role_id" class="form-select" aria-describedby="preview-role-help"
                        @disabled(old('mode', request('mode')) !== 'role') @required(old('mode', request('mode')) === 'role')>
                        <option value="">{{ __('access_preview.choose_role') }}</option>
                        @foreach($roles as $previewRole)
                            <option value="{{ $previewRole->id }}" @selected((int) old('role_id') === $previewRole->id)>{{ $previewRole->name }}</option>
                        @endforeach
                    </select>
                    <p id="preview-role-help" class="form-text">{{ __('access_preview.role_help') }}</p>
                </div>
                <div class="col-12"><div class="alert alert-warning mb-0">{{ __('access_preview.live_notice') }}</div></div>
                <div class="col-12"><button type="submit" class="btn btn-primary">{{ __('access_preview.start') }}</button></div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mode = document.getElementById('preview-mode');
    const role = document.getElementById('preview-role');
    const updateRole = () => {
        role.disabled = mode.value !== 'role';
        role.required = mode.value === 'role';
    };
    if (window.jQuery) {
        window.jQuery(mode).on('change.accessPreview', updateRole);
    } else {
        mode.addEventListener('change', updateRole);
    }
    updateRole();
});
</script>
@endsection
