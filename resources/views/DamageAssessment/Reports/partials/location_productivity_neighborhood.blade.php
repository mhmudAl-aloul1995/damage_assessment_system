@php
    $completed = (int) $pie['completed_percent'];
    $notCompleted = (int) $pie['not_completed_percent'];
@endphp

<div class="location-neighborhood-card">
    <div class="d-flex justify-content-between gap-3 mb-2">
        <div>
            <div class="location-neighborhood-title">{{ $pie['title'] }}</div>
            <div class="location-neighborhood-meta">{{ number_format($pie['buildings_count']) }} buildings</div>
        </div>
        <div class="location-neighborhood-percent">{{ $completed }}%</div>
    </div>

    <div class="location-neighborhood-progress" aria-label="Completed {{ $completed }}%, not completed {{ $notCompleted }}%">
        <span class="completed" style="width: {{ $completed }}%"></span>
        <span class="not-completed" style="width: {{ $notCompleted }}%"></span>
    </div>

    <div class="d-flex justify-content-between mt-2 location-neighborhood-breakdown">
        <span>Completed: {{ $pie['series'][0] }}</span>
        <span>Not completed: {{ $pie['series'][1] }}</span>
    </div>
</div>
