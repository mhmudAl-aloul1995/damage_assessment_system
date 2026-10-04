@extends('layouts.app')
@section('title', $sectorTitle)
@section('pageName', $sectorTitle)
@section('content')
    @include('damage-assessment::components.sector-navigation', ['sector' => $sector])
    <style>
        .sector-overview-map { height: 540px; min-height: 320px; background: var(--bs-gray-100); }
        .sector-overview-summary-card { appearance: none; text-align: start; cursor: pointer; transition: box-shadow .2s, transform .2s; }
        .sector-overview-summary-card:hover:not(:disabled) { box-shadow: 0 8px 24px #0001; transform: translateY(-2px); }
        .sector-overview-summary-card:focus-visible, .sector-chart-link:focus-visible { outline: 3px solid var(--bs-primary); outline-offset: 3px; }
        .sector-overview-summary-value { font-size: 2.2rem; line-height: 1.3; }
        .sector-overview-summary-card .card-body { width: 100%; padding: 1.5rem; }
        .sector-overview-criterion { font-size: .85rem; line-height: 1.8; min-height: 3.6em; }
        .sector-overview-legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .sector-overview-bar { height: 7px; background: var(--bs-gray-100); border-radius: 5px; overflow: hidden; }
        .sector-overview-bar-fill { height: 100%; border-radius: 5px; transition: width .25s; }
        .sector-chart-link { display: block; width: 100%; border: 0; background: transparent; color: inherit; text-align: start; padding: .6rem 0; border-radius: .4rem; }
        .sector-chart-link:hover:not(:disabled) { background: var(--bs-gray-100); }
        .sector-chart-link:disabled { cursor: default; }
        .sector-overview-map-status { background: var(--bs-body-bg, #fff); position: absolute; inset-inline: 12px; bottom: 12px; padding: 10px 12px; border-radius: 8px; z-index: 2; box-shadow: 0 2px 12px #0001; max-height: 110px; overflow: auto; }
        .sector-overview-audit { max-height: 380px; overflow-y: auto; }
        .sector-overview-chart-rows { max-height: 380px; overflow-y: auto; }
        @media (max-width: 575px) { .sector-overview-map { height: 350px; } .sector-overview-summary-value { font-size: 1.7rem; } .sector-overview-summary-card .card-body { padding: 1rem; } }
        @media (prefers-reduced-motion: reduce) { .sector-overview-summary-card, .sector-overview-bar-fill { transition: none; } }
    </style>
    @php
        $summaryCards = [
            ['metric' => 'total', 'label' => __('sector-overview.totals.'.$sector), 'color' => 'primary'],
            ['metric' => 'completed', 'label' => __('sector-overview.completed'), 'color' => 'success'],
            ['metric' => 'action_required', 'label' => __('sector-overview.action_required'), 'color' => 'warning'],
            ['metric' => 'approved', 'label' => __('sector-overview.approved_label'), 'color' => 'info'],
        ];
        $approvalCriterion = in_array($sector, ['buildings', 'housing-units'], true) ? 'building_approved' : 'approved';
    @endphp
    <div id="sector-overview" data-sector="{{ $sector }}" data-stats-url="{{ route('sector-overview.stats', $sector) }}" data-map-url="{{ route('sector-overview.map', $sector) }}" data-records-url="{{ route('sector-overview.records', $sector) }}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
            <div><h2 class="fs-2 fw-bold mb-2">{{ $sectorTitle }}</h2><p class="text-muted mb-0">{{ __('sector-overview.subtitle') }}</p></div>
            <span class="text-muted fs-7" id="sector-calculated-at"></span>
        </div>
        <form id="sector-overview-filters" method="get" class="card border border-gray-200 mb-5">
            <div class="card-body py-5">
                <div class="row g-4 align-items-end">
                    @foreach(['municipality' => $municipalities, 'neighborhood' => $statistics['neighborhoods'], 'damage_status' => $damageBuckets, 'audit_status' => $auditBuckets, 'field_completion' => ['completed', 'not_completed']] as $filter => $options)
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-semibold" for="sector-{{ $filter }}">{{ __('sector-overview.'.($filter === 'field_completion' ? 'fieldwork_chart' : ($filter === 'audit_status' ? 'audit_chart' : $filter))) }}</label>
                            <select class="form-select form-select-solid" name="{{ $filter }}" id="sector-{{ $filter }}">
                                <option value="">{{ __('sector-overview.all') }}</option>
                                @foreach($options as $option)
                                    <option value="{{ $option }}" @selected(($filters[$filter] ?? '') === $option)>{{ match ($filter) { 'damage_status' => __('sector-overview.damage.'.$option), 'audit_status' => __('sector-overview.audit.'.$option), 'field_completion' => __('sector-overview.fieldwork.'.$option), default => $option } }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="col-6 col-lg-2 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('sector-overview.apply') }}</button>
                        <button type="button" id="sector-reset" class="btn btn-light btn-sm">{{ __('sector-overview.reset') }}</button>
                    </div>
                </div>
                <p class="text-muted fs-7 mb-0 mt-4">{{ __('sector-overview.scope_note') }}</p>
                @foreach(['west', 'south', 'east', 'north'] as $bound)<input type="hidden" name="{{ $bound }}" value="{{ $filters[$bound] ?? '' }}">@endforeach
            </div>
        </form>
        <div id="sector-data-status" role="status" aria-live="polite" class="text-muted mb-3"></div>
        <div id="sector-data-error" role="alert" class="alert alert-danger d-none"></div>
        <div id="sector-data-content">
            <div class="sector-overview-summary-row row g-4 mb-5">
                @foreach($summaryCards as $card)
                    <div class="col-6 col-xl-3">
                        <button type="button" class="sector-overview-summary-card card border border-gray-200 w-100 h-100" data-drill-metric="{{ $card['metric'] }}" aria-label="{{ $card['label'] }} — {{ __('sector-overview.view_records') }}">
                            <span class="card-body">
                                <span class="d-block text-muted fw-semibold mb-2">{{ $card['label'] }}</span>
                                <strong class="sector-overview-summary-value d-block text-{{ $card['color'] }} mb-2" data-metric="{{ $card['metric'] }}">{{ number_format($statistics['summary'][$card['metric']]) }}</strong>
                                <span class="sector-overview-criterion d-block text-muted">{{ __('sector-overview.criteria.'.($card['metric'] === 'approved' ? $approvalCriterion : ($card['metric'] === 'completed' && $sector === 'housing-units' ? 'units_completed' : $card['metric']))) }}</span>
                                <span class="d-block text-primary fs-7 mt-3">{{ __('sector-overview.view_records') }} ←</span>
                            </span>
                        </button>
                    </div>
                @endforeach
            </div>
            <div class="row g-5 mb-5">
                <div class="col-xl-8">
                    <div class="card border border-gray-200 h-100 overflow-hidden">
                        <div class="card-header align-items-center gap-3 py-4">
                            <h3 class="card-title mb-0">{{ __('sector-overview.map') }}</h3>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <label for="sector-map-mode" class="visually-hidden">{{ __('sector-overview.map_mode') }}</label>
                                <select id="sector-map-mode" class="form-select form-select-sm w-auto"><option value="damage">{{ __('sector-overview.by_damage') }}</option><option value="audit">{{ __('sector-overview.by_audit') }}</option></select>
                                <button type="button" id="sector-map-extent" class="btn btn-sm btn-light-primary" disabled>{{ __('sector-overview.zoom_all') }}</button>
                            </div>
                        </div>
                        <div class="px-6 py-3 border-bottom">
                            <label class="form-check form-check-custom form-check-sm"><input type="checkbox" class="form-check-input" id="sector-extent-filter" disabled @checked(isset($filters['west']))><span class="form-check-label">{{ __('sector-overview.extent_filter') }}</span></label>
                            <p class="text-muted fs-7 mb-0 mt-2" id="sector-extent-note">{{ __('sector-overview.'.(isset($filters['west']) ? 'extent_note' : 'map_explanation')) }}</p>
                        </div>
                        <div class="position-relative flex-grow-1">
                            <div id="sector-map" class="sector-overview-map" aria-label="{{ __('sector-overview.map') }}"></div>
                            <div id="sector-map-status" class="sector-overview-map-status fs-7" role="status" aria-live="polite">{{ __('sector-overview.loading_map') }}</div>
                        </div>
                        <div class="card-footer py-3">
                            <div class="d-flex flex-wrap gap-3 fs-7" id="sector-map-legend"></div>
                            @if($sector === 'housing-units')<p class="text-muted fs-7 mb-0 mt-3">{{ __('sector-overview.units_map_note') }}</p>@endif
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card border border-gray-200 h-100">
                        <div class="card-body">
                            <h3 class="fs-4 mb-3">{{ __('sector-overview.fieldwork_chart') }}</h3>
                            <p class="text-muted fs-7">{{ __('sector-overview.fieldwork_note') }}</p>
                            <div id="sector-fieldwork-chart"></div>
                            @if($sector === 'housing-units')<p class="text-muted fs-7 mt-3">{{ __('sector-overview.units_completion_note') }}</p>@endif
                            <hr class="my-6">
                            <h3 class="fs-4 mb-3">{{ __('sector-overview.audit_chart') }}</h3>
                            <p class="text-muted fs-7">{{ __('sector-overview.audit_note') }}</p>
                            <div id="sector-progress-chart" class="sector-overview-audit"></div>
                            <p class="text-muted fs-8 mt-3 mb-0">{{ __('sector-overview.unknown_audit_note') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-5">
                <div class="col-lg-6"><div class="card border border-gray-200 h-100"><div class="card-header"><h3 class="card-title">{{ __('sector-overview.damage_chart') }}</h3></div><div class="card-body sector-overview-chart-rows" id="sector-damage-chart"></div></div></div>
                <div class="col-lg-6"><div class="card border border-gray-200 h-100"><div class="card-header"><h3 class="card-title" id="sector-specific-title">{{ $statistics['chart']['title'] }}</h3></div><div class="card-body"><p class="text-muted fs-7" id="sector-specific-note">{{ $statistics['chart']['note'] }}</p><div class="sector-overview-chart-rows" id="sector-specific-chart"></div></div></div></div>
            </div>
        </div>
        <div class="modal fade" id="sector-records-modal" tabindex="-1" aria-labelledby="sector-records-title" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
                <div class="modal-header"><h3 class="modal-title" id="sector-records-title">{{ __('sector-overview.records_title') }}</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('sector-overview.close') }}"></button></div>
                <div class="modal-body">
                    <p class="text-muted">{{ __('sector-overview.records_note') }}</p>
                    <p id="sector-records-scope" class="fw-semibold"></p>
                    <div id="sector-records-status" role="status" aria-live="polite"></div>
                    <div class="table-responsive"><table class="table table-row-bordered align-middle"><thead><tr>@foreach(['objectid', 'municipality', 'neighborhood', 'damage_status', 'fieldwork_chart', 'audit_chart'] as $column)<th scope="col">{{ __('sector-overview.'.$column) }}</th>@endforeach</tr></thead><tbody id="sector-records-body"></tbody></table></div>
                </div>
                <div class="modal-footer justify-content-between"><span id="sector-records-count"></span><div class="d-flex gap-2"><button type="button" id="sector-records-previous" class="btn btn-sm btn-light" disabled>{{ __('sector-overview.previous') }}</button><button type="button" id="sector-records-next" class="btn btn-sm btn-light-primary" disabled>{{ __('sector-overview.next') }}</button></div></div>
            </div></div>
        </div>
    </div>
@endsection
@section('script')
    <link rel="stylesheet" href="https://js.arcgis.com/4.22/esri/themes/light/main.css">
    <script id="sector-overview-data" type="application/json">{!! json_encode(['statistics' => $statistics, 'labels' => __('sector-overview'), 'locale' => app()->getLocale()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @include('damage-assessment::dashboard.partials.sector-overview-script')
@endsection
