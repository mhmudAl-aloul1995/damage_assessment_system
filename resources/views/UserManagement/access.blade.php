@extends('layouts.app')
@section('title', __('access.access_title'))
@section('pageName', __('access.access_title'))

@section('content')
<div class="app-container container-xxl">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-7">
        <div><h2 class="fs-2x fw-bold">{{ $user->name }}</h2><p class="text-muted mb-0">{{ __('access.access_subtitle') }} · {{ $user->email }}</p></div>
        <a href="{{ route('users.index') }}" class="btn btn-light">{{ __('access.users') }}</a>
    </div>
    @unless($user->is_active)<div class="alert alert-warning">{{ __('access.inactive_notice') }}</div>@endunless
    @if($user->hasRole('Database Officer'))<div class="alert alert-info">{{ __('access.admin_notice') }}</div>@endif
    <div class="alert alert-light border">{{ __('access.legacy_notice') }}</div>
    <div class="row g-5 mb-6">
        <div class="col-lg-5"><div class="card h-100"><div class="card-body">
            <h3 class="mb-5">{{ __('access.roles') }}</h3>
            <div class="d-flex flex-wrap gap-3">
                @forelse($user->roles as $role)<span class="badge badge-light-primary fs-6">{{ $role->name }}</span>@empty<span class="text-muted">—</span>@endforelse
            </div>
            <p class="text-muted mt-5 mb-0">{{ __('access.direct_notice') }}</p>
        </div></div></div>
        <div class="col-lg-7"><div class="card h-100"><div class="card-body">
            <h3 class="mb-5">{{ __('access.scope') }}</h3>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted">{{ __('access.region') }}</dt><dd class="col-sm-8">{{ $user->region ?: __('access.scope_default') }}</dd>
                <dt class="col-sm-4 text-muted">{{ __('access.phases') }}</dt><dd class="col-sm-8">{{ $user->allowed_phase_numbers ? implode('، ', $user->allowed_phase_numbers) : __('access.scope_default') }}</dd>
                <dt class="col-sm-4 text-muted">{{ __('access.default_phase') }}</dt><dd class="col-sm-8">{{ $user->default_phase_number ?: __('access.scope_default') }}</dd>
            </dl>
        </div></div></div>
    </div>
    <div class="card">
        <div class="card-header flex-wrap gap-4 py-5 align-items-center">
            <input type="search" id="access-search" class="form-control form-control-solid mw-400px" aria-label="{{ __('access.search_permissions') }}" placeholder="{{ __('access.search_permissions') }}">
            <select id="access-source" class="form-select w-auto" aria-label="{{ __('access.source') }}"><option value="all">{{ __('access.source') }}</option><option value="direct">{{ __('access.direct') }}</option><option value="inherited">{{ __('access.inherited') }}</option><option value="both">{{ __('access.both') }}</option></select>
        </div>
        <div class="card-body">
            @foreach($accessGroups as $group => $permissions)
                <section class="access-permission-group mb-7">
                    <h3 class="mb-4">{{ $groupLabels[$group] ?? $group }}</h3>
                    <div class="table-responsive"><table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted"><th>{{ __('access.permissions') }}</th><th>{{ __('access.source') }}</th></tr></thead>
                        <tbody>
                            @foreach($permissions as $permission)
                                <tr class="access-permission" data-direct="{{ $permission['direct'] ? '1' : '0' }}" data-inherited="{{ $permission['roles']->isNotEmpty() ? '1' : '0' }}">
                                    <td><span class="fw-semibold">{{ app(App\Support\Access\PermissionCatalog::class)->translate('resources', $permission['resource']) }} — {{ $permission['label'] }}</span><code dir="ltr" class="d-block text-muted fs-8">{{ $permission['name'] }}</code></td>
                                    <td>
                                        @if($permission['direct'])<span class="badge badge-light-info m-1">{{ __('access.direct') }}</span>@endif
                                        @foreach($permission['roles'] as $roleName)<span class="badge badge-light-primary m-1">{{ $roleName }}</span>@endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                </section>
            @endforeach
            <p id="access-empty" class="text-center text-muted py-10" @if($accessGroups->isNotEmpty()) hidden @endif>{{ __('access.no_access') }}</p>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('access-search');
    const source = document.getElementById('access-source');
    const rows = [...document.querySelectorAll('.access-permission')];
    function filterAccess() {
        rows.forEach(row => {
            const direct = row.dataset.direct === '1';
            const inherited = row.dataset.inherited === '1';
            const matches = source.value === 'all' || (source.value === 'direct' && direct) || (source.value === 'inherited' && inherited) || (source.value === 'both' && direct && inherited);
            row.hidden = !matches || !row.textContent.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase());
        });
        document.querySelectorAll('.access-permission-group').forEach(group => {
            group.hidden = [...group.querySelectorAll('.access-permission')].every(row => row.hidden);
        });
        document.getElementById('access-empty').hidden = rows.some(row => !row.hidden);
    }
    search.addEventListener('input', filterAccess);
    source.addEventListener('change', filterAccess);
});
</script>
@endsection