@extends('layouts.app')
@section('title', __('access.title'))
@section('pageName', __('access.title'))

@section('content')
<style>
    .access-center .access-stat { border-inline-start: 3px solid var(--bs-primary); }
    .access-center .access-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:1.5rem; }
    .access-center .access-workspace { display:grid; grid-template-columns:250px minmax(0,1fr); gap:1.5rem; }
    .access-center .access-group { text-align:start; width:100%; border:0; border-radius:.65rem; padding:1rem; background:transparent; color:var(--bs-gray-700); }
    .access-center .access-group[aria-pressed="true"] { background:var(--bs-primary-light); color:var(--bs-primary); font-weight:700; }
    .access-center .permission-cell { display:flex; gap:.65rem; align-items:flex-start; padding:.65rem; border-radius:.5rem; cursor:pointer; }
    .access-center .permission-cell:has(input:checked) { background:var(--bs-primary-light); }
    .access-center .permission-code { display:none; font-size:.75rem; overflow-wrap:anywhere; }
    .access-center.show-codes .permission-code { display:block; }
    .access-center .access-savebar { position:sticky; bottom:1rem; z-index:10; background:var(--bs-body-bg); border:1px solid var(--bs-border-color); box-shadow:0 4px 24px #00000012; }
    .access-center .permission-matrix { min-width:900px; }
    .access-center .permission-matrix th { white-space:nowrap; }
    .access-center .permission-matrix td:last-child { min-width:250px; }
    .access-center [hidden] { display:none !important; }
    @media(max-width:991px) { .access-center .access-workspace { grid-template-columns:1fr; } .access-center .access-navigation { max-height:220px; overflow:auto; } }
    @media(max-width:767px) {
        .access-center .permission-matrix { min-width:0; }
        .access-center .permission-matrix thead { display:none; }
        .access-center .permission-matrix tbody { display:block; }
        .access-center .permission-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; padding-block:1rem; }
        .access-center .permission-row th { grid-column:1 / -1; }
        .access-center .permission-row td { padding:0; border:0; }
        .access-center .permission-row td:not(:has(.permission-cell)) { display:none; }
        .access-center .permission-row td:last-child { min-width:0; grid-column:1 / -1; }
    }
</style>
<div class="app-container container-fluid access-center" id="access-center">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-4 mb-7">
        <div><h2 class="fs-2x fw-bold mb-2">{{ __('access.title') }}</h2><p class="text-muted mb-0">{{ __('access.subtitle') }}</p></div>
        @if($canViewUsers)<a href="{{ route('users.index') }}" class="btn btn-light">{{ __('access.users') }}</a>@endif
    </div>
    <div id="access-feedback" role="alert" tabindex="-1" class="alert" hidden></div>
    <div class="row g-4 mb-7">
        <div class="col-sm-4"><div class="card access-stat"><div class="card-body py-5"><div class="text-muted">{{ __('access.roles') }}</div><strong class="fs-2x" id="role-count">{{ $roles->count() }}</strong></div></div></div>
        <div class="col-sm-4"><div class="card access-stat"><div class="card-body py-5"><div class="text-muted">{{ __('access.permissions') }}</div><strong class="fs-2x">{{ $permissionGroups->sum(fn ($group) => $group['permissions']->count()) }}</strong></div></div></div>
        <div class="col-sm-4"><div class="card access-stat"><div class="card-body py-5"><div class="text-muted">{{ __('access.modules') }}</div><strong class="fs-2x">{{ $permissionGroups->count() }}</strong></div></div></div>
    </div>
    <div class="alert alert-light border text-gray-700 mb-6">{{ __('access.legacy_notice') }}</div>

    <section id="role-list" aria-label="{{ __('access.roles') }}">
        <div class="d-flex flex-wrap gap-3 mb-6">
            <input id="role-search" type="search" class="form-control form-control-solid mw-350px" aria-label="{{ __('access.search_roles') }}" placeholder="{{ __('access.search_roles') }}">
            <select id="role-filter" class="form-select form-select-solid w-auto" aria-label="{{ __('access.roles') }}">
                <option value="all">{{ __('access.all_roles') }}</option><option value="system">{{ __('access.system_roles') }}</option><option value="custom">{{ __('access.custom_roles') }}</option>
            </select>
            @if($can['create'])<button type="button" id="create-role" class="btn btn-primary ms-auto">{{ __('access.new_role') }}</button>@endif
        </div>
        <div class="access-grid" id="role-cards"></div>
        <div id="roles-empty" class="card card-body text-center py-15" hidden><h3>{{ __('access.no_roles') }}</h3><p class="text-muted">{{ __('access.empty_roles') }}</p></div>
    </section>

    <section id="role-editor" hidden aria-labelledby="editor-heading">
        <button type="button" id="back-to-roles" class="btn btn-light mb-5">{{ __('access.back') }}</button>
        <div class="card mb-6"><div class="card-body">
            <h3 id="editor-heading" class="mb-5"></h3>
            <div id="role-notice" class="alert alert-warning" hidden></div>
            <label for="role-name" class="form-label fw-bold">{{ __('access.name') }}</label>
            <input id="role-name" maxlength="255" required class="form-control mw-500px" autocomplete="off">
        </div></div>
        <div class="access-workspace">
            <nav class="card align-self-start access-navigation" aria-label="{{ __('access.modules') }}"><div class="card-body p-3">
                @foreach($permissionGroups as $group)
                    <button type="button" class="access-group d-flex justify-content-between gap-3" data-group="{{ $group['key'] }}" aria-pressed="false" aria-controls="group-{{ $group['key'] }}">
                        <span>{{ $group['label'] }}</span><span class="badge badge-light text-nowrap group-count" dir="ltr">0 / {{ $group['permissions']->count() }}</span>
                    </button>
                @endforeach
            </div></nav>
            <div class="card min-w-0">
                <div class="card-header flex-column align-items-stretch gap-4 py-5">
                    <input type="search" id="permission-search" class="form-control form-control-solid" aria-label="{{ __('access.search_permissions') }}" placeholder="{{ __('access.search_permissions') }}">
                    <div class="d-flex flex-wrap gap-5">
                        <label class="form-check form-check-custom form-check-solid gap-2"><input type="checkbox" id="selected-only" class="form-check-input"><span>{{ __('access.selected_only') }}</span></label>
                        <label class="form-check form-check-custom form-check-solid gap-2"><input type="checkbox" id="show-codes" class="form-check-input"><span>{{ __('access.show_codes') }}</span></label>
                    </div>
                    <span class="text-muted fs-7">{{ __('access.filter_hint') }}</span>
                </div>
                <div class="card-body p-5">
                    @foreach($permissionGroups as $group)
                        <section id="group-{{ $group['key'] }}" class="permission-group" data-group="{{ $group['key'] }}" hidden>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                                <h3 class="mb-0">{{ $group['label'] }}</h3>
                                @if($group['permissions']->isNotEmpty())
                                    <label class="form-check form-check-custom form-check-solid gap-2"><input type="checkbox" class="form-check-input select-visible"><span>{{ __('access.select_visible') }}</span></label>
                                @endif
                            </div>
                            @if($group['permissions']->isEmpty())
                                <div class="text-center py-12 text-muted"><p>{{ __('access.no_permissions') }}</p>
                                    @if(in_array($group['key'], ['heks', 'borrowers']))<p>{{ __('access.legacy_empty') }}</p>@endif
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle permission-matrix">
                                        <thead><tr class="text-gray-600 fw-bold"><th>{{ __('access.resource') }}</th>
                                            @foreach(['view', 'create', 'update', 'delete', 'export', 'advanced'] as $column)<th>{{ $column === 'advanced' ? __('access.advanced') : __('access.actions.'.$column) }}</th>@endforeach
                                        </tr></thead>
                                        <tbody>
                                            @foreach($group['rows'] as $resource => $row)
                                                <tr class="permission-row" data-resource="{{ $row['label'] }}">
                                                    <th scope="row" class="fw-semibold">{{ $row['label'] }}</th>
                                                    @foreach(['view', 'create', 'update', 'delete', 'export', 'advanced'] as $column)
                                                        <td>
                                                            @forelse($row['cells']->get($column, collect()) as $permission)
                                                                <label class="permission-cell" data-search="{{ mb_strtolower($group['label'].' '.$row['label'].' '.$permission['label'].' '.$permission['name']) }}">
                                                                    <input type="checkbox" class="form-check-input permission-toggle flex-shrink-0" value="{{ $permission['name'] }}" data-group="{{ $group['key'] }}" data-label="{{ $row['label'].' — '.$permission['label'] }}" aria-label="{{ $row['label'].' — '.$permission['label'] }}">
                                                                    <span><span>{{ $permission['label'] }}</span>
                                                                        @if($permission['sensitive'])<span class="text-warning" title="{{ __('access.sensitive') }}" aria-label="{{ __('access.sensitive') }}">●</span>@endif
                                                                        <code class="permission-code text-muted" dir="ltr">{{ $permission['name'] }}</code>
                                                                    </span>
                                                                </label>
                                                            @empty
                                                                <span class="text-muted" title="{{ __('access.unavailable') }}" aria-label="{{ __('access.unavailable') }}">—</span>
                                                            @endforelse
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="permission-empty text-muted text-center py-8" hidden>{{ __('access.no_permissions') }}</p>
                            @endif
                        </section>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="access-savebar rounded p-5 mt-6 d-flex flex-wrap align-items-center justify-content-between gap-4">
            <div><strong id="selection-summary"></strong><div id="changes-summary" class="text-muted fs-7" aria-live="polite"></div></div>
            <div class="d-flex gap-3"><button type="button" id="discard-role" class="btn btn-light">{{ __('access.cancel') }}</button><button type="button" id="review-role" class="btn btn-primary">{{ __('access.save') }}</button></div>
        </div>
    </section>
    <div class="modal fade" id="role-review" tabindex="-1" aria-labelledby="review-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h3 id="review-title">{{ __('access.review') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('access.close') }}"></button></div>
            <div class="modal-body"><div id="review-error" class="alert alert-danger" role="alert" hidden></div><p id="review-impact" class="alert alert-light-primary"></p><p id="review-name"></p><div class="row g-5"><div class="col-md-6"><h4 class="text-success">{{ __('access.added') }}</h4><ul id="review-added" class="ps-5"></ul></div><div class="col-md-6"><h4 class="text-danger">{{ __('access.removed') }}</h4><ul id="review-removed" class="ps-5"></ul></div></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('access.close') }}</button><button type="button" id="confirm-role" class="btn btn-primary">{{ __('access.confirm') }}</button></div>
        </div></div>
    </div>
    <div class="modal fade" id="role-members" tabindex="-1" aria-labelledby="members-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h3 id="members-title">{{ __('access.members') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('access.close') }}"></button></div>
            <div class="modal-body" id="members-body"></div>
            <div class="modal-footer"><button type="button" id="members-previous" class="btn btn-light">{{ __('access.previous') }}</button><button type="button" id="members-next" class="btn btn-light">{{ __('access.next') }}</button></div>
        </div></div>
    </div>
</div>
@endsection

@section('script')
@include('UserManagement.roles-script')
@endsection
