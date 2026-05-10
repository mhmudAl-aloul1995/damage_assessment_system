@php
    $neighborhoodsCount = $neighborhoodsCount ?? null;
@endphp

<div class="location-pie-card {{ $variant ?? 'neighborhood' }}">
    <div class="location-pie-title">{{ $pie['title'] }}</div>
    <div class="location-pie-meta">
        {{ $pie['subtitle'] }} | {{ number_format($pie['buildings_count']) }} buildings
    </div>

    <div class="location-pie-chart-wrap">
        <div id="{{ $pie['id'] }}" class="location-pie-chart"></div>
        <span class="location-pie-inner-percent completed">
            {{ $pie['completed_percent'] }}%
        </span>
        <span class="location-pie-inner-percent not-completed">
            {{ $pie['not_completed_percent'] }}%
        </span>
    </div>

    <div class="location-pie-summary-grid {{ $neighborhoodsCount === null ? 'two-items' : '' }}">
        <div class="location-pie-summary-item completed">
            <span class="location-pie-summary-label">Completed</span>
            <span class="location-pie-summary-value">
                {{ number_format($pie['series'][0]) }} ({{ $pie['completed_percent'] }}%)
            </span>
        </div>
        <div class="location-pie-summary-item not-completed">
            <span class="location-pie-summary-label">Not Completed</span>
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
