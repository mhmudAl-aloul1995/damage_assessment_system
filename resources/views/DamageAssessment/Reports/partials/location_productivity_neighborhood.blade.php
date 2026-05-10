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

    <div class="location-pie-percent-row">
        <span class="location-pie-percent completed">
            {{ $pie['completed_percent'] }}%
        </span>
        <span class="location-pie-percent not-completed">
            {{ $pie['not_completed_percent'] }}%
        </span>
    </div>
</div>
