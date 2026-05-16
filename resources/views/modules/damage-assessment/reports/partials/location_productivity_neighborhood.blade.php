@php
    $neighborhoodsCount = $neighborhoodsCount ?? null;
    $countLabel = $countLabel ?? 'buildings';
    $firstMetricLabel = $firstMetricLabel ?? 'Completed';
    $secondMetricLabel = $secondMetricLabel ?? 'Not Completed';
    $firstMetricClass = $firstMetricClass ?? 'completed';
    $secondMetricClass = $secondMetricClass ?? 'not-completed';
    $totalCount = $pie['units_count'] ?? $pie['buildings_count'];
@endphp

<div class="location-pie-card {{ $variant ?? 'neighborhood' }}">
    <div class="location-pie-title">{{ $pie['title'] }}</div>
    <div class="location-pie-meta">
        {{ $pie['subtitle'] }} | {{ number_format($totalCount) }} {{ $countLabel }}
    </div>

    <div class="location-pie-chart-wrap">
        <div id="{{ $pie['id'] }}" class="location-pie-chart"></div>
        <span class="location-pie-inner-percent {{ $firstMetricClass }}">
            {{ $pie['completed_percent'] }}%
        </span>
        <span class="location-pie-inner-percent {{ $secondMetricClass }}">
            {{ $pie['not_completed_percent'] }}%
        </span>
    </div>

    <div class="location-pie-summary-grid {{ $neighborhoodsCount === null ? 'two-items' : '' }}">
        <div class="location-pie-summary-item {{ $firstMetricClass }}">
            <span class="location-pie-summary-label">{{ $firstMetricLabel }}</span>
            <span class="location-pie-summary-value">
                {{ number_format($pie['series'][0]) }} ({{ $pie['completed_percent'] }}%)
            </span>
        </div>
        <div class="location-pie-summary-item {{ $secondMetricClass }}">
            <span class="location-pie-summary-label">{{ $secondMetricLabel }}</span>
            <span class="location-pie-summary-value">
                {{ number_format($pie['series'][1]) }} ({{ $pie['not_completed_percent'] }}%)
            </span>
        </div>
        @if ($neighborhoodsCount !== null)
            <div class="location-pie-summary-item neighborhoods">
                <span class="location-pie-summary-label">Neighborhoods</span>
                <span class="location-pie-summary-value">
                    {{ number_format($neighborhoodsCount) }}
                </span>
            </div>
        @endif
    </div>
</div>
