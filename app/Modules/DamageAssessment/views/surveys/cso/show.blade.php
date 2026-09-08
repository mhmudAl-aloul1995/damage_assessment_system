@extends('layouts.app')

@section('title', __('cso_details.title'))
@section('pageName', __('cso_details.title'))

@section('content')
    @include('damage-assessment::surveys.cso._detail_styles')

    @php
        $surveyName = $survey->building_name ?: ($survey->organization_name ?: __('cso_details.title'));
        $activeOrganizationKey = $organizationGroups->first()['key'] ?? null;
        $damageColors = [
            'fully_damaged' => 'danger',
            'partially_damaged' => 'warning',
            'no_damage' => 'success',
            'committee_review' => 'primary',
            'unclassified' => 'secondary',
        ];
    @endphp

    <div class="cso-detail" id="cso-detail">
        <header class="card card-flush mb-6 cso-heading">
            <div class="card-body pb-0">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-5 mb-7">
                    <div class="d-flex align-items-center gap-5 flex-grow-1 min-w-0">
                        <div class="symbol symbol-70px d-none d-sm-inline-flex">
                            <span class="symbol-label bg-light-primary">
                                <i class="ki-duotone ki-home-2 fs-3x text-primary" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>
                            </span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-gray-500 fw-semibold fs-7 mb-2">{{ __('cso_details.title') }}</div>
                            <h1 class="text-gray-900 fs-2 fw-bold mb-3">{{ $surveyName }}</h1>
                            <div class="d-flex flex-wrap gap-4 text-gray-600 fs-7 cso-heading-meta">
                                <span class="d-flex align-items-center gap-2">
                                    <i class="ki-duotone ki-geolocation fs-4" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>
                                    {{ __('cso_details.municipality') }}: {{ $survey->municipalitie ?: '-' }}
                                </span>
                                <span>{{ __('cso_details.neighborhood') }}: {{ $survey->neighborhood ?: '-' }}</span>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('cso-surveys.index') }}" class="btn btn-sm btn-light-primary cso-back">
                        <i class="ki-duotone ki-arrow-left fs-3" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>
                        {{ __('cso_details.back') }}
                    </a>
                </div>

                <dl class="cso-summary mb-6" aria-label="{{ __('cso_details.survey') }}">
                    <div class="border border-dashed border-gray-300 rounded p-4">
                        <dt class="text-gray-600 fw-semibold fs-7 mb-3">{{ __('cso_details.building_damage') }}</dt>
                        <dd class="mb-0">
                            <span class="badge {{ $buildingDamage === 'unclassified' ? 'badge-light' : 'badge-light-'.$damageColors[$buildingDamage] }} fs-7" data-damage="{{ $buildingDamage }}">
                                {{ __('cso_details.damage.'.$buildingDamage) }}
                            </span>
                        </dd>
                    </div>
                    <div class="border border-dashed border-gray-300 rounded p-4">
                        <dt class="text-gray-600 fw-semibold fs-7 mb-3">{{ __('cso_details.researcher') }}</dt>
                        <dd class="text-gray-900 fw-bold mb-0">{{ $survey->assignedto ?: '-' }}</dd>
                    </div>
                    <div class="border border-dashed border-gray-300 rounded p-4">
                        <dt class="text-gray-600 fw-semibold fs-7 mb-3">{{ __('cso_details.date') }}</dt>
                        <dd class="text-gray-900 fw-bold fs-7 mb-0"><bdi>{{ $survey->creationdate ?: '-' }}</bdi></dd>
                    </div>
                    <div class="border border-dashed border-gray-300 rounded p-4">
                        <dt class="text-gray-600 fw-semibold fs-7 mb-2">{{ __('cso_details.organizations') }}</dt>
                        <dd class="text-primary fw-bold fs-2 mb-0">{{ $survey->organizations->count() }}</dd>
                    </div>
                    <div class="border border-dashed border-gray-300 rounded p-4">
                        <dt class="text-gray-600 fw-semibold fs-7 mb-2">{{ __('cso_details.units') }}</dt>
                        <dd class="text-primary fw-bold fs-2 mb-0">{{ $unitCount }}</dd>
                    </div>
                </dl>

                <div class="d-flex flex-wrap align-items-center gap-2 text-gray-500 fs-8 mb-4 cso-heading-meta">
                    <span>{{ __('cso_details.record_identifiers') }}:</span>
                    <bdi class="badge badge-light fw-semibold">{{ $survey->objectid ?: '-' }}</bdi>
                    <bdi>{{ $survey->globalid ?: '-' }}</bdi>
                </div>

                <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bold gap-5 cso-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-active-primary ms-0 py-5" id="cso-survey-tab" data-bs-toggle="tab" data-bs-target="#cso-survey-pane" type="button" role="tab" aria-controls="cso-survey-pane" aria-selected="false">
                            {{ __('cso_details.survey') }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-active-primary ms-0 py-5 active" id="cso-workspace-tab" data-bs-toggle="tab" data-bs-target="#cso-workspace-pane" type="button" role="tab" aria-controls="cso-workspace-pane" aria-selected="true">
                            {{ __('cso_details.workspace') }}
                            <span class="badge badge-light-primary ms-2">{{ $organizationGroups->count() }}</span>
                        </button>
                    </li>
                </ul>
            </div>
        </header>

        <div class="tab-content">
            <section class="tab-pane fade" id="cso-survey-pane" role="tabpanel" aria-labelledby="cso-survey-tab" tabindex="0">
                <div class="card card-flush">
                    <div class="card-header">
                        <h2 class="card-title fw-bold">{{ __('cso_details.survey') }}</h2>
                    </div>
                    <div class="card-body pt-0 cso-survey-sections">
                        @foreach ($sections as $section)
                            @include('damage-assessment::surveys.cso._section_table', ['section' => $section, 'open' => $loop->first])
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="tab-pane fade show active" id="cso-workspace-pane" role="tabpanel" aria-labelledby="cso-workspace-tab" tabindex="0">
                @if ($organizationGroups->isEmpty())
                    <div class="card card-flush">
                        <div class="card-body cso-empty">
                            <i class="ki-duotone ki-office-bag fs-3x text-gray-400 mb-4 d-block" aria-hidden="true"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                            <p class="mb-0">{{ __('cso_details.no_organizations') }}</p>
                        </div>
                    </div>
                @else
                    <div class="card card-flush d-lg-none mb-5 cso-mobile-selector">
                        <div class="card-body p-5">
                            <label for="cso-organization-select" class="form-label fw-bold">{{ __('cso_details.select_organization') }}</label>
                            <select id="cso-organization-select" class="form-select form-select-solid">
                                @foreach ($organizationGroups as $group)
                                    <option value="{{ $group['key'] }}" @selected($activeOrganizationKey === $group['key'])>
                                        {{ $group['name'] }} ({{ $group['units']->count() }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="cso-workspace">
                        <aside class="card card-flush d-none d-lg-flex cso-organizations" aria-label="{{ __('cso_details.organizations') }}">
                            <div class="card-header px-5">
                                <h2 class="card-title fs-5 fw-bold">{{ __('cso_details.organizations') }}</h2>
                                <div class="card-toolbar"><span class="badge badge-light-primary">{{ $organizationGroups->count() }}</span></div>
                            </div>
                            <div class="card-body pt-0 px-5 pb-5">
                                <label for="cso-organization-search" class="visually-hidden">{{ __('cso_details.search_organizations') }}</label>
                                <div class="position-relative mb-5">
                                    <i class="ki-duotone ki-magnifier fs-3 cso-muted cso-search-icon" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>
                                    <input id="cso-organization-search" type="search" class="form-control form-control-solid ps-10" placeholder="{{ __('cso_details.search_organizations') }}">
                                </div>
                                <div class="d-flex flex-column gap-2 cso-organization-list">
                                    @foreach ($organizationGroups as $group)
                                        <button type="button" class="btn btn-active-light-primary d-flex align-items-center gap-3 p-3 cso-organization-button" data-organization="{{ $group['key'] }}" aria-controls="cso-group-{{ $group['key'] }}" aria-pressed="{{ $activeOrganizationKey === $group['key'] ? 'true' : 'false' }}">
                                            <span class="symbol symbol-40px flex-shrink-0" aria-hidden="true">
                                                <span class="symbol-label bg-light text-gray-600 fw-bold">{{ $loop->iteration }}</span>
                                            </span>
                                            <span class="min-w-0">
                                                <strong class="d-block fs-7">{{ $group['name'] }}</strong>
                                                <span class="d-block text-gray-600 fs-8 fw-normal mt-1">{{ __('cso_details.results', ['count' => $group['units']->count()]) }}</span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                                <p id="cso-no-organizations" class="cso-empty mb-0" role="status" hidden>{{ __('cso_details.no_results') }}</p>
                            </div>
                        </aside>

                        <div class="cso-organization-content">
                            @foreach ($organizationGroups as $group)
                                <article id="cso-group-{{ $group['key'] }}" class="cso-organization-panel" data-organization-panel="{{ $group['key'] }}" @if ($activeOrganizationKey !== $group['key']) hidden @endif>
                                    <div class="card card-flush mb-6">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center gap-4 mb-5">
                                                <div class="symbol symbol-50px flex-shrink-0">
                                                    <span class="symbol-label bg-light-primary">
                                                        <i class="ki-duotone ki-office-bag fs-2x text-primary" aria-hidden="true"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                                    </span>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-gray-500 fs-7 mb-1">{{ __('cso_details.organization') }}</div>
                                                    <h3 class="text-gray-900 fs-3 fw-bold mb-0">{{ $group['name'] }}</h3>
                                                </div>
                                            </div>

                                            @if ($group['organization'])
                                                <dl class="d-flex flex-wrap gap-7 mb-5">
                                                    <div>
                                                        <dt class="text-gray-500 fs-7 fw-semibold mb-2">{{ __('cso_details.registration') }}</dt>
                                                        <dd class="fw-bold text-gray-800 mb-0">{{ $group['registration'] }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-gray-500 fs-7 fw-semibold mb-2">{{ __('cso_details.active') }}</dt>
                                                        <dd class="badge badge-light fw-semibold mb-0">{{ $group['active'] }}</dd>
                                                    </div>
                                                </dl>
                                                <details class="cso-organization-details border-top border-gray-200 pt-4">
                                                    <summary class="text-primary fw-bold">
                                                        {{ __('cso_details.organization_details') }}
                                                        <i class="ki-duotone ki-down fs-3 text-primary cso-chevron" aria-hidden="true"></i>
                                                    </summary>
                                                    <div class="pt-5">
                                                        @foreach ($group['sections'] as $section)
                                                            @include('damage-assessment::surveys.cso._section_table', ['section' => $section, 'open' => $loop->first])
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @else
                                                <p class="alert alert-warning mb-0">{{ __('cso_details.unassigned_notice') }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="card card-flush mb-6">
                                        <div class="card-header">
                                            <h4 class="card-title fs-5 fw-bold">{{ __('cso_details.unit_damage_summary') }}</h4>
                                        </div>
                                        <div class="card-body pt-0">
                                            <dl class="cso-damage-summary mb-0">
                                                @foreach ($damageBuckets as $bucket)
                                                    <div class="cso-damage-card {{ $bucket === 'unclassified' ? 'bg-light text-gray-700' : 'bg-light-'.$damageColors[$bucket].' text-'.$damageColors[$bucket] }} rounded p-4" data-damage="{{ $bucket }}">
                                                        <dt class="fs-7 fw-semibold mb-3">{{ __('cso_details.damage.'.$bucket) }}</dt>
                                                        <dd class="fs-2 fw-bold mb-0">{{ $group['damageCounts']->get($bucket, 0) }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    </div>

                                    <div class="card card-flush">
                                        <div class="card-header">
                                            <h4 class="card-title fs-5 fw-bold">{{ __('cso_details.units') }}</h4>
                                            <div class="card-toolbar">
                                                <span class="badge badge-light-primary" data-unit-results role="status" data-template="{{ __('cso_details.results', ['count' => ':count']) }}">{{ __('cso_details.results', ['count' => $group['units']->count()]) }}</span>
                                            </div>
                                        </div>
                                        <div class="card-body pt-0">
                                            @if ($group['units']->isEmpty())
                                                <p class="cso-empty mb-0">{{ __('cso_details.no_units') }}</p>
                                            @else
                                                <div class="cso-unit-tools mb-6">
                                                    <div class="position-relative">
                                                        <label for="cso-unit-search-{{ $group['key'] }}" class="visually-hidden">{{ __('cso_details.search_units') }}</label>
                                                        <i class="ki-duotone ki-magnifier fs-3 cso-muted cso-search-icon" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>
                                                        <input id="cso-unit-search-{{ $group['key'] }}" type="search" class="form-control form-control-solid ps-10" data-unit-search placeholder="{{ __('cso_details.search_units') }}">
                                                    </div>
                                                    <select class="form-select form-select-solid" data-damage-filter aria-label="{{ __('cso_details.damage_status') }}">
                                                        <option value="">{{ __('cso_details.all_damage') }}</option>
                                                        @foreach ($damageBuckets as $bucket)
                                                            <option value="{{ $bucket }}">{{ __('cso_details.damage.'.$bucket) }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="button" class="btn btn-icon btn-light-primary" data-clear-filters aria-label="{{ __('cso_details.clear_filters') }}" title="{{ __('cso_details.clear_filters') }}">
                                                        <i class="ki-duotone ki-cross fs-2" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>
                                                    </button>
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-row-dashed table-row-gray-200 align-middle gy-5 gs-3 mb-0 cso-units-table">
                                                        <caption class="visually-hidden">{{ $group['name'] }} - {{ __('cso_details.units') }}</caption>
                                                        <thead>
                                                            <tr class="text-gray-500 fw-bold fs-7 bg-light">
                                                                <th scope="col" class="min-w-150px">{{ __('cso_details.unit') }}</th>
                                                                <th scope="col" class="min-w-60px">{{ __('cso_details.floor') }}</th>
                                                                <th scope="col" class="min-w-100px">{{ __('cso_details.function') }}</th>
                                                                <th scope="col" class="min-w-100px">{{ __('cso_details.damage_status') }}</th>
                                                                <th scope="col" class="w-40px"><span class="visually-hidden">{{ __('cso_details.view_unit') }}</span></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="text-gray-700 fw-semibold fs-7">
                                                            @foreach ($group['units'] as $unit)
                                                                <tr data-unit-row data-damage="{{ $unit['damage'] }}">
                                                                    <td>
                                                                        <button type="button" class="btn btn-link text-gray-900 text-hover-primary fw-bold p-0 cso-unit-name" data-unit-open="{{ $unit['id'] }}">{{ $unit['name'] }}</button>
                                                                        <div class="text-gray-500 fs-8 mt-1">{{ __('cso_details.number') }}: {{ $unit['number'] }}</div>
                                                                    </td>
                                                                    <td>{{ $unit['floor'] }}</td>
                                                                    <td>{{ $unit['function'] }}</td>
                                                                    <td><span class="badge {{ $unit['damage'] === 'unclassified' ? 'badge-light' : 'badge-light-'.$damageColors[$unit['damage']] }}" data-damage="{{ $unit['damage'] }}">{{ __('cso_details.damage.'.$unit['damage']) }}</span></td>
                                                                    <td>
                                                                        <button type="button" class="btn btn-icon btn-sm btn-light btn-active-light-primary" data-unit-open="{{ $unit['id'] }}" aria-label="{{ __('cso_details.view_unit') }}" title="{{ __('cso_details.view_unit') }}">
                                                                            <i class="ki-duotone ki-eye fs-2" aria-hidden="true"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <p class="cso-empty mb-0" data-no-unit-results hidden>{{ __('cso_details.no_results') }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        </div>

        @foreach ($organizationGroups as $group)
            @foreach ($group['units'] as $unit)
                <template id="cso-unit-template-{{ $unit['id'] }}" data-title="{{ $unit['name'] }}" data-organization-name="{{ $group['name'] }}">
                    <dl class="d-flex flex-wrap gap-7 bg-light rounded p-5 mb-6">
                        <div>
                            <dt class="text-gray-500 fw-semibold fs-7 mb-2">{{ __('cso_details.floor') }}</dt>
                            <dd class="fw-bold mb-0">{{ $unit['floor'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 fw-semibold fs-7 mb-2">{{ __('cso_details.damage_status') }}</dt>
                            <dd class="mb-0"><span class="badge {{ $unit['damage'] === 'unclassified' ? 'badge-light' : 'badge-light-'.$damageColors[$unit['damage']] }}" data-damage="{{ $unit['damage'] }}">{{ __('cso_details.damage.'.$unit['damage']) }}</span></dd>
                        </div>
                    </dl>
                    @foreach ($unit['sections'] as $section)
                        @include('damage-assessment::surveys.cso._section_table', ['section' => $section, 'open' => $loop->first])
                    @endforeach
                </template>
            @endforeach
        @endforeach
    </div>

    <div class="offcanvas offcanvas-end cso-unit-drawer" tabindex="-1" id="cso-unit-drawer" aria-labelledby="cso-unit-title" aria-describedby="cso-unit-organization">
        <div class="offcanvas-header align-items-start border-bottom border-gray-200 p-6">
            <div class="min-w-0">
                <div class="text-gray-500 fs-7 mb-2" id="cso-unit-organization"></div>
                <h3 class="fw-bold fs-3 mb-0" id="cso-unit-title"></h3>
            </div>
            <button type="button" class="btn btn-icon btn-sm btn-light-primary" data-bs-dismiss="offcanvas" aria-label="{{ __('cso_details.close') }}">
                <i class="ki-duotone ki-cross fs-2" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>
            </button>
        </div>
        <div class="offcanvas-body p-6" id="cso-unit-body"></div>
    </div>
@endsection

@section('script')
    @include('damage-assessment::surveys.cso._detail_script')
@endsection
