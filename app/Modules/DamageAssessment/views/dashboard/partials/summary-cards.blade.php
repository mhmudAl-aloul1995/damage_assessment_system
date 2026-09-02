@php
	$dashboardStatsBuckets = compact('buildingStats', 'unitStats', 'publicBuildingStats', 'roadFacilityStats', 'csoSurveyStats');

	$formatDashboardValue = function ($value, int $decimalPlaces = 0, ?string $suffix = null): string {
		if (is_numeric($value)) {
			$value = number_format((float) $value, $decimalPlaces);
		}

		return trim((string) $value . ($suffix ? ' ' . $suffix : ''));
	};
@endphp

<div class="row g-5 g-xl-8 damage-dashboard-stats">
	@forelse ($dashboardCards as $dashboardCard)
		@php
			$statsBucket = $dashboardStatsBuckets[$dashboardCard->source_bucket] ?? [];
			$totalValue = $statsBucket[$dashboardCard->total_stat_key] ?? 0;
			$target = data_get($dashboardCard->options, 'target');
			$targetReached = is_numeric($target) && (float) $totalValue >= (float) $target;
			$columnClass = match ($dashboardCard->key) {
				'cso_surveys' => 'dashboard-summary-col-cso',
				'public_buildings' => 'dashboard-summary-col-public',
				'road_facilities' => 'dashboard-summary-col-road',
				default => '',
			};
		@endphp

		<div class="col-sm-6 col-lg-6 col-xxl-3 dashboard-summary-col {{ $columnClass }}">
			<div class="card card-xl-stretch mb-xl-8 dashboard-summary-card @if ($targetReached) housing-target-achieved @endif">
				<div class="card-body p-0">
					<div style="background-color: {{ $dashboardCard->color }};"
						class="px-9 pt-7 card-rounded h-275px w-100 dashboard-summary-header">
						@if ($targetReached)
							<div class="target-confetti" aria-hidden="true">
								@for ($i = 0; $i < 8; $i++)
									<span></span>
								@endfor
							</div>
						@endif

						<div class="d-flex flex-stack @if ($targetReached) target-title-row @endif">
							<h3 class="m-0 text-white fw-bold fs-3">{{ __($dashboardCard->title) }}</h3>
							<div class="ms-1">
								<button type="button"
									class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
									data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
									<i class="ki-duotone {{ $dashboardCard->icon }} fs-7 fs-lg-6">
										<span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
									</i>
								</button>
							</div>
						</div>

						<div class="d-flex text-center flex-column text-white pt-8 @if ($targetReached) target-total-wrap @endif">
							<span class="fw-semibold fs-7 text-wrap">{{ $dashboardCard->subtitle ? __($dashboardCard->subtitle) : '' }}</span>
							<span class="fw-bold fs-1 fs-lg-2x pt-1 @if ($targetReached) target-total-number @endif">{{ $formatDashboardValue($totalValue) }}</span>
							@if ($targetReached)
								<span class="target-achieved-badge">تم تحقيق التارجت {{ number_format((float) $target) }}+</span>
							@endif
						</div>
					</div>

					<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1 dashboard-summary-body"
						style="margin-top: -100px">
						@foreach ($dashboardCard->items as $dashboardCardItem)
							@php
								$itemStatsBucket = $dashboardStatsBuckets[$dashboardCardItem->source_bucket] ?? [];
								$itemValue = $itemStatsBucket[$dashboardCardItem->stat_key] ?? 0;
								$itemLink = $dashboardCardItem->link_group && $dashboardCardItem->link_key
									? data_get($dashboardStatLinks, $dashboardCardItem->link_group . '.' . $dashboardCardItem->link_key)
									: null;
							@endphp

							<div class="d-flex align-items-center mb-6">
								<div class="symbol symbol-25px w-25px me-5">
									<span class="symbol-label bg-lighten">
										<i class="ki-duotone {{ $dashboardCardItem->icon }}">
											<span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span>
										</i>
									</span>
								</div>
								<div class="d-flex align-items-center flex-wrap w-100">
									<div class="mb-1 pe-3 flex-grow-1">
										@if ($itemLink)
											<a href="{{ $itemLink }}"
												class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __($dashboardCardItem->title) }}</a>
										@else
											<span class="fs-10 fs-lg-7 text-gray-800 fw-bold text-wrap">{{ __($dashboardCardItem->title) }}</span>
										@endif
									</div>
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
										{{ $formatDashboardValue($itemValue, $dashboardCardItem->decimal_places, $dashboardCardItem->value_suffix) }}
									</div>
								</div>
							</div>
						@endforeach
					</div>
				</div>
			</div>
		</div>
	@empty
		<div class="col-12">
			<div class="alert alert-warning mb-0">لم يتم إعداد بطاقات لوحة التحكم بعد.</div>
		</div>
	@endforelse
</div>
