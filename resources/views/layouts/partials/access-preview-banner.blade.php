@if($accessPreview = request()->attributes->get(\App\Support\Access\AccessPreview::SESSION_KEY))
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6" role="status" id="access-preview-banner">
        <div>
            <strong class="d-block">{{ __('access_preview.active', ['user' => $accessPreview['user_name']]) }}</strong>
            @if($accessPreview['mode'] === 'role')<span class="d-block">{{ __('access_preview.active_role', ['role' => $accessPreview['role_name']]) }}</span>@endif
            <span class="d-block">{{ __('access_preview.actor', ['name' => $accessPreview['actor_name']]) }}</span>
            <span class="d-block mt-2">{{ __('access_preview.live_notice') }}</span>
        </div>
        <form method="POST" action="{{ route('access-preview.destroy') }}">
            @csrf
            <button type="submit" class="btn btn-dark">{{ __('access_preview.stop') }}</button>
        </form>
    </div>
@endif
