@php
    $locationPieNodes = $locationPieNodes ?? [];
    $countLabel = $countLabel ?? 'items';
    $description = $description ?? null;
    $emptyLabel = $emptyLabel ?? "No matching damaged {$countLabel}.";
@endphp

<div class="card-body p-0">
    <div class="px-8 pt-6">
        <h3 class="fw-bold mb-1">Location Pie Charts</h3>
        @if ($description)
            <div class="text-muted fs-7">{{ $description }}</div>
        @endif
    </div>

    @if (count($locationPieNodes))
        <div class="location-pie-tree mt-5">
            @foreach ($locationPieNodes as $municipalityNode)
                @php
                    $municipalityPie = $municipalityNode['pie'];
                    $showNeighborhoodPies = count($municipalityNode['neighborhoods']) > 0;
                @endphp
                <div class="location-pie-section">
                    <button class="location-pie-section-toggle" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapse_{{ $municipalityPie['id'] }}"
                        aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                        aria-controls="collapse_{{ $municipalityPie['id'] }}">
                        <span>
                            <span class="location-pie-section-title d-block">{{ $municipalityPie['title'] }}</span>
                            <span class="location-pie-section-meta">
                                Municipality | {{ number_format($municipalityPie['items_count']) }} {{ $countLabel }}
                                @if ($showNeighborhoodPies)
                                    | {{ count($municipalityNode['neighborhoods']) }} neighborhoods
                                @endif
                            </span>
                            <span class="location-collapse-cue d-block mt-1">
                                <span class="when-closed">Click to expand</span>
                                <span class="when-open">Click to collapse</span>
                            </span>
                        </span>
                        <span class="location-collapse-icon" aria-hidden="true"></span>
                    </button>

                    <div id="collapse_{{ $municipalityPie['id'] }}"
                        class="collapse location-pie-collapse {{ $loop->first ? 'show' : '' }}">
                        <div class="location-primary-body">
                            @include('damage-assessment::reports.partials.location_productivity_neighborhood', [
                                'pie' => $municipalityPie,
                                'variant' => 'primary',
                                'neighborhoodsCount' => $showNeighborhoodPies ? count($municipalityNode['neighborhoods']) : null,
                                'countLabel' => $countLabel,
                                'firstMetricLabel' => __('multilingual.area_productivity_reports.metrics.totally_damaged'),
                                'secondMetricLabel' => __('multilingual.area_productivity_reports.metrics.partially_damaged'),
                                'firstMetricClass' => 'totally-damaged',
                                'secondMetricClass' => 'partially-damaged',
                            ])
                        </div>

                        @if ($showNeighborhoodPies)
                            <div class="p-4 pt-0">
                                <div class="location-municipality-title mb-3">
                                    Neighborhoods under {{ $municipalityPie['title'] }}
                                </div>
                                <div class="location-neighborhood-grid">
                                    @foreach ($municipalityNode['neighborhoods'] as $neighborhoodPie)
                                        @include('damage-assessment::reports.partials.location_productivity_neighborhood', [
                                            'pie' => $neighborhoodPie,
                                            'countLabel' => $countLabel,
                                            'firstMetricLabel' => __('multilingual.area_productivity_reports.metrics.totally_damaged'),
                                            'secondMetricLabel' => __('multilingual.area_productivity_reports.metrics.partially_damaged'),
                                            'firstMetricClass' => 'totally-damaged',
                                            'secondMetricClass' => 'partially-damaged',
                                        ])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-10 text-center text-muted">{{ $emptyLabel }}</div>
    @endif
</div>
