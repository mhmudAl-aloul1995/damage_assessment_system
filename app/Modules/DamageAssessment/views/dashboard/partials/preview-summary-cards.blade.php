<section class="row g-3 flex-xl-nowrap mb-8" aria-label="بطاقات لوحة التحكم الفعلية" id="preview-live-cards">
    @forelse ($dashboardCards as $dashboardCard)
        <div class="col-12 col-md-6 col-xl" data-dashboard-card="{{ $dashboardCard->key }}">
            <article class="card card-flush h-100 border-top border-3" style="border-top-color: {{ $dashboardCard->color }} !important;">
                <div class="card-body p-4 p-xxl-5">
                    <div class="d-flex align-items-center gap-2 mb-5">
                        <span class="symbol symbol-30px"><span class="symbol-label bg-light"><i class="ki-duotone {{ $dashboardCard->icon }} fs-2" style="color: {{ $dashboardCard->color }}" aria-hidden="true"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i></span></span>
                        <h3 class="fs-6 fw-bold text-gray-900 mb-0">{{ $translateDashboardText($dashboardCard->title) }}</h3>
                    </div>
                    <div class="fs-2hx fw-bold text-gray-900" data-dashboard-total>{{ $formatDashboardValue($dashboardStatsBuckets[$dashboardCard->source_bucket][$dashboardCard->total_stat_key] ?? 0) }}</div>
                    <p class="text-muted fs-7 mb-5">{{ $translateDashboardText($dashboardCard->subtitle) }}</p>
                    @foreach ($dashboardCard->items->chunk(5) as $items)
                        @if ($loop->index === 1)
                            <details class="mt-3">
                                <summary class="text-primary fw-semibold fs-7 py-3">عرض جميع البنود ({{ $dashboardCard->items->count() }})</summary>
                        @endif
                        @foreach ($items as $dashboardCardItem)
                            @php
                                $itemValue = $dashboardCardItem->calculation_type === 'count_condition'
                                    ? ($dashboardCardItemValues[$dashboardCardItem->id] ?? 0)
                                    : ($dashboardStatsBuckets[$dashboardCardItem->source_bucket][$dashboardCardItem->stat_key] ?? 0);
                                $itemLink = $dashboardConditionLink($dashboardCardItem)
                                    ?: ($dashboardCardItem->link_group && $dashboardCardItem->link_key
                                        ? data_get($dashboardStatLinks, $dashboardCardItem->link_group.'.'.$dashboardCardItem->link_key)
                                        : null);
                            @endphp
                            <div class="d-flex align-items-center gap-3 py-3 border-top border-gray-200" data-dashboard-item="{{ $dashboardCardItem->key }}">
                                <span class="text-gray-500"><i class="ki-duotone {{ $dashboardCardItem->icon }} fs-4" aria-hidden="true"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span></i></span>
                                @if ($itemLink)
                                    <a href="{{ $itemLink }}" class="text-gray-700 text-hover-primary fs-7 flex-grow-1">{{ $translateDashboardText($dashboardCardItem->title) }}</a>
                                @else
                                    <span class="text-gray-700 fs-7 flex-grow-1">{{ $translateDashboardText($dashboardCardItem->title) }}</span>
                                @endif
                                <span class="badge badge-light fs-7" data-dashboard-item-value>{{ $formatDashboardValue($itemValue, $dashboardCardItem->decimal_places, $dashboardCardItem->value_suffix) }}</span>
                            </div>
                        @endforeach
                        @if ($loop->last && $loop->index >= 1)
                            </details>
                        @endif
                    @endforeach
                    @if ($dashboardCard->items->isEmpty())
                        <p class="text-muted fs-7 mb-0">لا توجد بنود مفعّلة لهذه البطاقة.</p>
                    @endif
                </div>
            </article>
        </div>
    @empty
        <div class="col-12"><div class="notice bg-light rounded p-6 text-gray-600">لا توجد بطاقات مفعّلة لعرضها.</div></div>
    @endforelse
</section>
