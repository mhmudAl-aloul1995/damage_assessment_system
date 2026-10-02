@extends('layouts.app')

@section('title', $sectorTitle)
@section('pageName', $sectorTitle)

@section('content')
    @include('damage-assessment::components.sector-navigation', ['sector' => $sector])
    <style>
        .sector-overview-map { height: 440px; min-height: 320px; background: var(--bs-gray-100); }
        .sector-overview-donut { width: 190px; height: 190px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; }
        .sector-overview-donut-center { width: 136px; height: 136px; border-radius: 50%; background: var(--bs-body-bg, #fff); display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .sector-overview-legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .sector-overview-bar { height: 10px; background: var(--bs-gray-100); border-radius: 5px; overflow: hidden; }
        .sector-overview-bar-fill { height: 100%; border-radius: 5px; transition: width .25s; }
        .sector-overview-map-status { background: var(--bs-body-bg, #fff); position: absolute; inset-inline: 16px; bottom: 16px; padding: 10px 16px; border-radius: 8px; z-index: 2; box-shadow: 0 2px 12px #0001; }
        .sector-overview-summary-card { flex: 1 1 0; min-width: 0; }
        .sector-overview-summary-label { min-height: 2.4em; line-height: 1.2; overflow-wrap: anywhere; }
        .sector-overview-summary-value { font-size: 1.7rem; line-height: 1.1; overflow-wrap: anywhere; }
        @media (max-width: 991.98px) {
            .sector-overview-summary-row { gap: .5rem !important; }
            .sector-overview-summary-card .card-body { padding: .75rem .35rem !important; }
            .sector-overview-summary-label { font-size: .7rem; }
            .sector-overview-summary-value { font-size: 1rem; }
        }
        @media (max-width: 575px) { .sector-overview-map { height: 340px; } }
    </style>
    @php
        $summaryCards = match ($sector) {
            'buildings' => [
                ['metric' => 'total', 'label' => __('sector-overview.total'), 'color' => 'primary'],
                ['metric' => 'completed', 'label' => __('sector-overview.completed'), 'color' => 'success'],
                ['metric' => 'fully_damaged', 'label' => __('sector-overview.damage.fully_damaged'), 'color' => 'danger'],
                ['metric' => 'partially_damaged', 'label' => __('sector-overview.damage.partially_damaged'), 'color' => 'warning'],
                ['metric' => 'committee_review', 'label' => __('sector-overview.technical_committee'), 'color' => 'info'],
                ['metric' => 'no_damage', 'label' => __('sector-overview.damage.no_damage'), 'color' => 'success'],
                ['metric' => 'assessment_blocked', 'label' => __('sector-overview.assessment_blocked'), 'color' => 'dark'],
                ['metric' => 'pending', 'label' => __('sector-overview.pending'), 'color' => 'warning'],
                ['metric' => 'approved', 'label' => __('sector-overview.approved'), 'color' => 'info'],
            ],
            'housing-units' => [
                ['metric' => 'total', 'label' => __('sector-overview.total'), 'color' => 'primary'],
                ['metric' => 'fully_damaged', 'label' => __('sector-overview.damage.fully_damaged'), 'color' => 'danger'],
                ['metric' => 'partially_damaged', 'label' => __('sector-overview.damage.partially_damaged'), 'color' => 'warning'],
                ['metric' => 'committee_review', 'label' => __('sector-overview.technical_committee'), 'color' => 'info'],
                ['metric' => 'assessment_blocked', 'label' => __('sector-overview.assessment_blocked'), 'color' => 'dark'],
                ['metric' => 'pending', 'label' => __('sector-overview.pending'), 'color' => 'warning'],
                ['metric' => 'approved', 'label' => __('sector-overview.approved'), 'color' => 'info'],
            ],
            default => [
                ['metric' => 'total', 'label' => __('sector-overview.total'), 'color' => 'primary'],
                ['metric' => 'completed', 'label' => __('sector-overview.completed'), 'color' => 'success'],
                ['metric' => 'pending', 'label' => __('sector-overview.pending'), 'color' => 'warning'],
                ['metric' => 'approved', 'label' => __('sector-overview.approved'), 'color' => 'info'],
            ],
        };
    @endphp
    <div id="sector-overview" data-stats-url="{{ route('sector-overview.stats', $sector) }}" data-map-url="{{ route('sector-overview.map', $sector) }}">
        <div class="mb-5">
            <h2 class="fs-2 fw-bold text-gray-900 mb-2">{{ $sectorTitle }}</h2>
            <p class="text-muted mb-0">{{ __('sector-overview.subtitle') }}</p>
        </div>
        <form id="sector-overview-filters" method="get" class="card border border-gray-200 mb-5">
            <div class="card-body py-5">
                <div class="row g-4 align-items-end">
                    @foreach(['municipality' => $municipalities, 'neighborhood' => $statistics['neighborhoods'], 'damage_status' => $damageBuckets] as $filter => $options)
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="sector-{{ $filter }}">{{ __('sector-overview.'.$filter) }}</label>
                            <select class="form-select form-select-solid" name="{{ $filter }}" id="sector-{{ $filter }}">
                                <option value="">{{ __('sector-overview.all') }}</option>
                                @foreach($options as $option)
                                    <option value="{{ $option }}" @selected(($filters[$filter] ?? '') === $option)>{{ $filter === 'damage_status' ? __('sector-overview.damage.'.$option) : $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="col-md-3 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('sector-overview.apply') }}</button>
                        <button type="button" id="sector-reset" class="btn btn-light btn-sm">{{ __('sector-overview.reset') }}</button>
                    </div>
                </div>
            </div>
        </form>
        <div id="sector-data-status" role="status" aria-live="polite" class="text-muted mb-3"></div>
        <div id="sector-data-error" role="alert" class="alert alert-danger d-none"></div>
        <div id="sector-data-content">
            <div class="sector-overview-summary-row d-flex flex-nowrap gap-4 mb-5">
                @foreach($summaryCards as $card)
                    <div class="sector-overview-summary-card">
                        <div class="card border border-gray-200 h-100">
                            <div class="card-body py-6 px-3 text-center">
                                <div class="sector-overview-summary-label text-muted fw-semibold mb-3">{{ $card['label'] }}</div>
                                <div class="sector-overview-summary-value fw-bold text-{{ $card['color'] }}" data-metric="{{ $card['metric'] }}">{{ number_format($statistics['summary'][$card['metric']]) }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($sector === 'housing-units')
                <p class="text-muted fs-7">{{ __('sector-overview.units_completion_note') }}</p>
            @endif
            <div class="card border border-gray-200 mb-5 overflow-hidden">
                <div class="card-header align-items-center py-4">
                    <div><h3 class="card-title mb-1">{{ __('sector-overview.map') }}</h3><span class="text-muted fs-7">{{ __('sector-overview.map_description') }}</span></div>
                    <button type="button" id="sector-map-extent" class="btn btn-sm btn-light-primary" disabled>{{ __('sector-overview.zoom_all') }}</button>
                </div>
                <div class="position-relative">
                    <div id="sector-map" class="sector-overview-map" aria-label="{{ __('sector-overview.map') }}"></div>
                    <div id="sector-map-status" class="sector-overview-map-status fs-7" role="status" aria-live="polite">{{ __('sector-overview.loading_map') }}</div>
                </div>
                <div class="card-footer py-3">
                    <div class="d-flex flex-wrap gap-4 fs-7" id="sector-map-legend"></div>
                    @if($sector === 'housing-units')<p class="text-muted fs-7 mb-0 mt-2">{{ __('sector-overview.units_map_note') }}</p>@endif
                </div>
            </div>
            <div class="row g-5">
                <div class="col-lg-6">
                    <div class="card border border-gray-200 h-100">
                        <div class="card-header"><h3 class="card-title">{{ __('sector-overview.damage_chart') }}</h3></div>
                        <div class="card-body d-flex gap-6 flex-wrap align-items-center justify-content-center" id="sector-damage-chart"></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border border-gray-200 h-100">
                        <div class="card-header"><h3 class="card-title">{{ __('sector-overview.progress_chart') }}</h3></div>
                        <div class="card-body" id="sector-progress-chart"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <link rel="stylesheet" href="https://js.arcgis.com/4.22/esri/themes/light/main.css">
    <script id="sector-overview-data" type="application/json">{!! json_encode(['statistics' => $statistics, 'labels' => __('sector-overview'), 'locale' => app()->getLocale()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @include('damage-assessment::dashboard.partials.sector-overview-script')
@endsection
