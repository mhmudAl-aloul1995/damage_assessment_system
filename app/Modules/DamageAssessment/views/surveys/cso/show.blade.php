@extends('layouts.app')

@section('title', __('cso_details.title'))
@section('pageName', __('cso_details.title'))

@section('content')
    @include('damage-assessment::surveys.cso._detail_styles')

    @php
        $surveyName = $survey->building_name ?: ($survey->organization_name ?: __('cso_details.title'));
        $activeOrganizationKey = $organizationGroups->first()['key'] ?? null;
    @endphp

    <div class="cso-detail" id="cso-detail">
        <header class="cso-heading">
            <div class="cso-heading-main">
                <a href="{{ route('cso-surveys.index') }}" class="cso-back" aria-label="{{ __('cso_details.back') }}">
                    <i class="ki-duotone ki-arrow-left fs-2"></i>
                </a>

                <div class="min-w-0">
                    <h1>{{ $surveyName }}</h1>
                    <div class="cso-heading-meta">
                        <span>{{ __('cso_details.municipality') }}: {{ $survey->municipalitie ?: '-' }}</span>
                        <span>{{ __('cso_details.neighborhood') }}: {{ $survey->neighborhood ?: '-' }}</span>
                        <span>{{ __('cso_details.record_identifiers') }}: {{ $survey->objectid ?: '-' }} / {{ $survey->globalid ?: '-' }}</span>
                    </div>
                </div>
            </div>

            <dl class="cso-summary" aria-label="{{ __('cso_details.survey') }}">
                <div class="cso-summary-item">
                    <dt>{{ __('cso_details.building_damage') }}</dt>
                    <dd>
                        <span class="cso-chip" data-damage="{{ $buildingDamage }}">
                            {{ __('cso_details.damage.'.$buildingDamage) }}
                        </span>
                    </dd>
                </div>
                <div class="cso-summary-item">
                    <dt>{{ __('cso_details.researcher') }}</dt>
                    <dd>{{ $survey->assignedto ?: '-' }}</dd>
                </div>
                <div class="cso-summary-item">
                    <dt>{{ __('cso_details.date') }}</dt>
                    <dd>{{ $survey->creationdate ?: '-' }}</dd>
                </div>
                <div class="cso-summary-item">
                    <dt>{{ __('cso_details.organizations') }}</dt>
                    <dd>{{ $survey->organizations->count() }}</dd>
                </div>
                <div class="cso-summary-item">
                    <dt>{{ __('cso_details.units') }}</dt>
                    <dd>{{ $unitCount }}</dd>
                </div>
            </dl>
        </header>

        <ul class="nav nav-tabs cso-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cso-survey-tab" data-bs-toggle="tab" data-bs-target="#cso-survey-pane" type="button" role="tab" aria-controls="cso-survey-pane" aria-selected="false">
                    {{ __('cso_details.survey') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="cso-workspace-tab" data-bs-toggle="tab" data-bs-target="#cso-workspace-pane" type="button" role="tab" aria-controls="cso-workspace-pane" aria-selected="true">
                    {{ __('cso_details.workspace') }}
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <section class="tab-pane fade cso-tab-pane" id="cso-survey-pane" role="tabpanel" aria-labelledby="cso-survey-tab" tabindex="0">
                <div class="cso-survey-sections">
                    @foreach ($sections as $section)
                        @include('damage-assessment::surveys.cso._section_table', ['section' => $section, 'open' => $loop->first])
                    @endforeach
                </div>
            </section>

            <section class="tab-pane fade show active cso-tab-pane" id="cso-workspace-pane" role="tabpanel" aria-labelledby="cso-workspace-tab" tabindex="0">
                @if ($organizationGroups->isEmpty())
                    <div class="cso-organization-content">
                        <p class="cso-empty">{{ __('cso_details.no_organizations') }}</p>
                    </div>
                @else
                    <div class="cso-mobile-selector">
                        <label for="cso-organization-select" class="form-label fw-semibold">{{ __('cso_details.select_organization') }}</label>
                        <select id="cso-organization-select" class="form-select">
                            @foreach ($organizationGroups as $group)
                                <option value="{{ $group['key'] }}" @selected($activeOrganizationKey === $group['key'])>
                                    {{ $group['name'] }} ({{ $group['units']->count() }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cso-workspace">
                        <aside class="cso-organizations" aria-label="{{ __('cso_details.organizations') }}">
                            <h2>{{ __('cso_details.organizations') }}</h2>
                            <input id="cso-organization-search" type="search" class="form-control form-control-sm mt-3" placeholder="{{ __('cso_details.search_organizations') }}">

                            <div class="cso-organization-list">
                                @foreach ($organizationGroups as $group)
                                    <button type="button" class="cso-organization-button" data-organization="{{ $group['key'] }}" aria-controls="cso-group-{{ $group['key'] }}" aria-pressed="{{ $activeOrganizationKey === $group['key'] ? 'true' : 'false' }}">
                                        <strong>{{ $group['name'] }}</strong>
                                        <span>{{ trans_choice('cso_details.results', $group['units']->count(), ['count' => $group['units']->count()]) }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <p id="cso-no-organizations" class="cso-empty" hidden>{{ __('cso_details.no_results') }}</p>
                        </aside>

                        <div class="cso-organization-content">
                            @foreach ($organizationGroups as $group)
                                <article id="cso-group-{{ $group['key'] }}" class="cso-organization-panel" data-organization-panel="{{ $group['key'] }}" @if ($activeOrganizationKey !== $group['key']) hidden @endif>
                                    <div class="cso-eyebrow">{{ __('cso_details.organization') }}</div>
                                    <h3>{{ $group['name'] }}</h3>

                                    @if ($group['organization'])
                                        <dl class="cso-meta-row">
                                            <div>
                                                <dt>{{ __('cso_details.registration') }}</dt>
                                                <dd>{{ $group['registration'] }}</dd>
                                            </div>
                                            <div>
                                                <dt>{{ __('cso_details.active') }}</dt>
                                                <dd>{{ $group['active'] }}</dd>
                                            </div>
                                        </dl>

                                        <details class="cso-organization-details">
                                            <summary>{{ __('cso_details.organization_details') }}</summary>
                                            @foreach ($group['sections'] as $section)
                                                @include('damage-assessment::surveys.cso._section_table', ['section' => $section, 'open' => $loop->first])
                                            @endforeach
                                        </details>
                                    @else
                                        <p class="alert alert-warning mt-4">{{ __('cso_details.unassigned_notice') }}</p>
                                    @endif

                                    <h4 class="fs-6 fw-bold mt-6 mb-0">{{ __('cso_details.unit_damage_summary') }}</h4>
                                    <dl class="cso-damage-summary">
                                        @foreach ($damageBuckets as $bucket)
                                            <div class="cso-damage-card" data-damage="{{ $bucket }}">
                                                <dt>{{ __('cso_details.damage.'.$bucket) }}</dt>
                                                <dd>{{ $group['damageCounts']->get($bucket, 0) }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>

                                    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
                                        <div>
                                            <h4 class="fs-6 fw-bold mb-1">{{ __('cso_details.units') }}</h4>
                                            <span class="cso-muted" data-unit-results data-template="{{ __('cso_details.results', ['count' => ':count']) }}"></span>
                                        </div>
                                    </div>

                                    @if ($group['units']->isEmpty())
                                        <p class="cso-empty">{{ __('cso_details.no_units') }}</p>
                                    @else
                                        <div class="cso-unit-tools">
                                            <input type="search" class="form-control form-control-sm" data-unit-search placeholder="{{ __('cso_details.search_units') }}">
                                            <select class="form-select form-select-sm" data-damage-filter>
                                                <option value="">{{ __('cso_details.all_damage') }}</option>
                                                @foreach ($damageBuckets as $bucket)
                                                    <option value="{{ $bucket }}">{{ __('cso_details.damage.'.$bucket) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="cso-icon-button" data-clear-filters aria-label="{{ __('cso_details.clear_filters') }}" title="{{ __('cso_details.clear_filters') }}">
                                                <i class="ki-duotone ki-cross fs-2"></i>
                                            </button>
                                        </div>

                                        <div class="cso-table-scroll">
                                            <table class="cso-units-table">
                                                <caption class="visually-hidden">{{ $group['name'] }} - {{ __('cso_details.units') }}</caption>
                                                <thead>
                                                    <tr>
                                                        <th style="width: 31%">{{ __('cso_details.unit') }}</th>
                                                        <th style="width: 12%">{{ __('cso_details.floor') }}</th>
                                                        <th>{{ __('cso_details.function') }}</th>
                                                        <th style="width: 23%">{{ __('cso_details.damage_status') }}</th>
                                                        <th style="width: 50px"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($group['units'] as $unit)
                                                        <tr data-unit-row data-damage="{{ $unit['damage'] }}">
                                                            <td>
                                                                <button type="button" class="cso-unit-name" data-unit-open="{{ $unit['id'] }}">
                                                                    {{ $unit['name'] }}
                                                                </button>
                                                                <div class="cso-muted">{{ __('cso_details.number') }}: {{ $unit['number'] }}</div>
                                                            </td>
                                                            <td>{{ $unit['floor'] }}</td>
                                                            <td>{{ $unit['function'] }}</td>
                                                            <td>
                                                                <span class="cso-chip" data-damage="{{ $unit['damage'] }}">
                                                                    {{ __('cso_details.damage.'.$unit['damage']) }}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="cso-icon-button" data-unit-open="{{ $unit['id'] }}" aria-label="{{ __('cso_details.view_unit') }}" title="{{ __('cso_details.view_unit') }}">
                                                                    <i class="ki-duotone ki-eye fs-2"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <p class="cso-empty" data-no-unit-results hidden>{{ __('cso_details.no_results') }}</p>
                                    @endif
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
                    <dl class="cso-meta-row">
                        <div>
                            <dt>{{ __('cso_details.floor') }}</dt>
                            <dd>{{ $unit['floor'] }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('cso_details.damage_status') }}</dt>
                            <dd>
                                <span class="cso-chip" data-damage="{{ $unit['damage'] }}">
                                    {{ __('cso_details.damage.'.$unit['damage']) }}
                                </span>
                            </dd>
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
        <div class="offcanvas-header align-items-start">
            <div class="min-w-0">
                <div class="cso-eyebrow" id="cso-unit-organization"></div>
                <h3 id="cso-unit-title"></h3>
            </div>
            <button type="button" class="btn btn-icon btn-sm btn-light" data-bs-dismiss="offcanvas" aria-label="{{ __('cso_details.close') }}">
                <i class="ki-duotone ki-cross fs-2"></i>
            </button>
        </div>
        <div class="offcanvas-body" id="cso-unit-body"></div>
    </div>
@endsection

@section('script')
    @include('damage-assessment::surveys.cso._detail_script')
@endsection
