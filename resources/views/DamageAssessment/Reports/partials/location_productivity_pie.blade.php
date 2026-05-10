<div class="neighborhood-pie-card">
    <div class="neighborhood-pie-title">{{ $pie['title'] }}</div>
    <div class="neighborhood-pie-subtitle">
        {{ $pie['subtitle'] }} | {{ number_format($pie['buildings_count']) }} buildings
    </div>
    <div class="neighborhood-pie-chart-wrap">
        <div id="{{ $pie['id'] }}" class="neighborhood-pie-chart"></div>
        <span class="neighborhood-pie-inner-percent completed">
            {{ $pie['completed_percent'] }}%
        </span>
        <span class="neighborhood-pie-inner-percent not-completed">
            {{ $pie['not_completed_percent'] }}%
        </span>
    </div>
    <div class="neighborhood-pie-percent-row">
        <span class="neighborhood-pie-percent completed">
            {{ $pie['completed_percent'] }}%
        </span>
        <span class="neighborhood-pie-percent not-completed">
            {{ $pie['not_completed_percent'] }}%
        </span>
    </div>
    <div class="neighborhood-pie-legend">
        <span class="neighborhood-pie-legend-item">
            <span class="neighborhood-pie-dot completed"></span>
            Completed
        </span>
        <span class="neighborhood-pie-legend-item">
            <span class="neighborhood-pie-dot not-completed"></span>
            Not Completed
        </span>
    </div>
</div>
