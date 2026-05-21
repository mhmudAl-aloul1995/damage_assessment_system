@php
    $neighborhoodsCount = $neighborhoodsCount ?? null;
    $countLabel = $countLabel ?? 'buildings';
    $firstMetricLabel = $firstMetricLabel ?? 'Completed';
    $secondMetricLabel = $secondMetricLabel ?? 'Not Completed';
    $firstMetricClass = $firstMetricClass ?? 'completed';
    $secondMetricClass = $secondMetricClass ?? 'not-completed';
    $totalCount = $pie['units_count'] ?? $pie['buildings_count'];
    $summaryItems = $pie['summary_items'] ?? [
        ['label' => $firstMetricLabel, 'value' => $pie['series'][0] ?? 0, 'percent' => $pie['completed_percent']],
        ['label' => $secondMetricLabel, 'value' => $pie['series'][1] ?? 0, 'percent' => $pie['not_completed_percent']],
    ];
@endphp

<div class="location-pie-card {{ $variant ?? 'neighborhood' }}">
    <div class="location-pie-title">{{ $pie['title'] }}</div>
    <div class="location-pie-meta">
        {{ $pie['subtitle'] }} | {{ number_format($totalCount) }} {{ $countLabel }}
    </div>

    <div class="location-pie-chart-wrap">
        <div id="{{ $pie['id'] }}" class="location-pie-chart"></div>
        @if (count($pie['series']) === 2)
            <span class="location-pie-inner-percent {{ $firstMetricClass }}">
                {{ $pie['completed_percent'] }}%
            </span>
            <span class="location-pie-inner-percent {{ $secondMetricClass }}">
                {{ $pie['not_completed_percent'] }}%
            </span>
        @endif
    </div>

    <div class="location-pie-summary-grid {{ $neighborhoodsCount === null && count($summaryItems) === 2 ? 'two-items' : '' }}">
        @foreach ($summaryItems as $summaryItem)
            <div class="location-pie-summary-item {{ $loop->first ? $firstMetricClass : ($loop->iteration === 2 ? $secondMetricClass : '') }}">
                <span class="location-pie-summary-label" style="color: {{ $summaryItem['color'] ?? '#7e8299' }}">{{ $summaryItem['label'] }}</span>
                <span class="location-pie-summary-value" style="color: {{ $summaryItem['color'] ?? '#181c32' }}">
                    {{ number_format($summaryItem['value']) }} ({{ $summaryItem['percent'] }}%)
                </span>
            </div>
        @endforeach
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
