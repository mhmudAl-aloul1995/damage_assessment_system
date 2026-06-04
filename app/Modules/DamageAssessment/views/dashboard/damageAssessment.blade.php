@extends('layouts.app')
@section('title', __('ui.damage_dashboard.title'))
@section('pageName', __('ui.damage_dashboard.title'))


@section('content')
	@php
		$dashboardStatLinks = [
			'buildings' => [
				'fully_damaged' => url('damage-assessment/building') . '?' . http_build_query(['building_damage_status' => 'fully_damaged']),
				'partially_damaged' => url('damage-assessment/building') . '?' . http_build_query(['building_damage_status' => 'partially_damaged']),
				'committee_review' => url('damage-assessment/building') . '?' . http_build_query(['building_damage_status' => 'committee_review']),
				'assessment_blocked' => url('damage-assessment/building') . '?' . http_build_query(['security_situation' => 'Unsafe']),
				'bodies_present' => url('damage-assessment/building') . '?' . http_build_query(['bodies_present' => 'yes3']),
				'uxo_present' => url('damage-assessment/building') . '?' . http_build_query(['uxo_present' => 'yes3']),
				'debris_blocking' => url('damage-assessment/building') . '?' . http_build_query(['building_debris_exist' => 'yes']),
				'completed' => url('damage-assessment/building') . '?' . http_build_query(['field_status' => 'COMPLETED']),
			],
			'housing' => [
				'fully_damaged' => url('damage-assessment/housing') . '?' . http_build_query(['unit_damage_status' => 'fully_damaged2']),
				'partially_damaged' => url('damage-assessment/housing') . '?' . http_build_query(['unit_damage_status' => 'partially_damaged2']),
				'committee_review' => url('damage-assessment/housing') . '?' . http_build_query(['unit_damage_status' => 'committee_review2']),
				'assessment_blocked' => url('damage-assessment/housing') . '?' . http_build_query(['security_situation_unit' => 'Unsafe']),
				'structural_support' => url('damage-assessment/housing') . '?' . http_build_query(['unit_support_needed' => 'yes']),
				'at_risk_of_collapse' => url('damage-assessment/housing') . '?' . http_build_query(['unit_stripping' => 'yes']),
				'habitable' => url('damage-assessment/housing') . '?' . http_build_query(['is_the_housing_unit_or_living_habitable' => 'yes']),
				'fire_affected' => url('damage-assessment/housing') . '?' . http_build_query(['has_fire' => 'yes']),
			],
			'public_buildings' => [
				'damaged' => route('public-buildings.index') . '?' . http_build_query(['damaged_only' => 1]),
				'units' => route('public-buildings.index') . '?' . http_build_query(['with_units' => 1]),
				'municipalities' => route('public-buildings.index') . '?' . http_build_query(['has_municipality' => 1]),
				'neighborhoods' => route('public-buildings.index') . '?' . http_build_query(['has_neighborhood' => 1]),
				'assigned_staff' => route('public-buildings.index') . '?' . http_build_query(['has_assignedto' => 1]),
				'occupied' => route('public-buildings.index') . '?' . http_build_query(['occupied_only' => 1]),
				'bodies' => route('public-buildings.index') . '?' . http_build_query(['bodies_only' => 1]),
				'uxo' => route('public-buildings.index') . '?' . http_build_query(['uxo_only' => 1]),
			],
			'road_facilities' => [
				'damaged' => route('road-facilities.index') . '?' . http_build_query(['damaged_only' => 1]),
				'items' => route('road-facilities.index') . '?' . http_build_query(['with_items' => 1]),
				'municipalities' => route('road-facilities.index') . '?' . http_build_query(['has_municipality' => 1]),
				'neighborhoods' => route('road-facilities.index') . '?' . http_build_query(['has_neighborhood' => 1]),
				'potholes' => route('road-facilities.index') . '?' . http_build_query(['potholes_only' => 1]),
				'obstacles' => route('road-facilities.index') . '?' . http_build_query(['obstacles_only' => 1]),
				'buried_bodies' => route('road-facilities.index') . '?' . http_build_query(['buried_bodies_only' => 1]),
				'uxo' => route('road-facilities.index') . '?' . http_build_query(['uxo_only' => 1]),
			],
		];
	@endphp
	<style>
		#externalLegendDiv {
			padding: 10px;
			background-color: white;
			border: 1px solid #ccc;
			max-height: 400px;
			overflow-y: auto;
		}

		.esri-popup {
			z-index: 999 !important;
		}

		/* Specifically for the main container if it still hides */
		.esri-popup__main-container {
			z-index: 1000 !important;
		}

		.esri-legend__layer-caption {
			display: none;
		}

		td {

			font-size: 11px !important;

		}

		/* Force pagination buttons to stay on one line */
		#kt_table_building_wrapper .dataTables_paginate ul.pagination {
			display: flex !important;
			flex-wrap: nowrap !important;
			margin-bottom: 0;
		}

		/* Ensure the footer row uses full width */
		#kt_table_building_wrapper .row {
			width: 100%;
			margin-right: 0;
			margin-left: 0;
		}



		.esri-scale-bar__label {
			color: white !important;
			background-color: black !important;


		}

		.damage-dashboard-toolbar {
			background: var(--bs-body-bg);
			border-bottom: 1px solid var(--bs-gray-200);
			box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
			margin: -0.5rem -0.5rem 2rem;
			min-height: 68px;
			padding: 0.75rem 1.65rem;
		}

		.damage-dashboard-toolbar-main {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: 1rem;
			justify-content: flex-end;
			width: 100%;
		}

		.damage-dashboard-toolbar-actions {
			align-items: center;
			direction: ltr;
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
			justify-content: flex-end;
			min-width: 0;
			width: 100%;
		}

		.damage-dashboard-toolbar .toolbar-control-group {
			align-items: center;
			display: flex;
			flex: 0 1 auto;
			gap: 0.5rem;
			min-width: 0;
		}

		.damage-dashboard-toolbar .toolbar-period-group {
			flex-wrap: wrap;
			justify-content: flex-end;
		}

		.damage-dashboard-toolbar .toolbar-label {
			color: var(--bs-gray-700);
			direction: rtl;
			font-weight: 700;
			white-space: nowrap;
		}

		.damage-dashboard-toolbar .toolbar-icon-button {
			background: var(--bs-gray-100);
			border-radius: 8px;
			height: 44px;
			width: 44px;
		}

		.damage-dashboard-toolbar .toolbar-icon-button.active {
			background: var(--bs-primary-light);
		}

		.damage-dashboard-toolbar .toolbar-period-button {
			background: var(--bs-gray-100);
			border-radius: 8px;
			color: var(--bs-gray-600);
			font-weight: 700;
			min-width: 72px;
			white-space: nowrap;
		}

		.damage-dashboard-toolbar .toolbar-period-button.active {
			background: var(--bs-primary-light);
			color: var(--bs-primary);
		}

		.damage-dashboard-toolbar .toolbar-neighborhood-wrap {
			flex: 1 1 168px;
			min-width: 150px;
			width: 168px;
		}

		.damage-dashboard-toolbar .toolbar-date-range-wrap {
			flex: 1 1 210px;
			min-width: 180px;
			width: 210px;
		}

		.damage-dashboard-toolbar .toolbar-neighborhood-select,
		.damage-dashboard-toolbar .toolbar-date-range-input,
		.damage-dashboard-toolbar .toolbar-neighborhood-wrap .select2-selection {
			background-color: var(--bs-gray-100) !important;
			border-color: transparent !important;
			border-radius: 8px !important;
			min-height: 44px;
		}

		@media (max-width: 767.98px) {
			.damage-dashboard-toolbar {
				margin-inline: 0;
				padding: 0.85rem;
			}

			.damage-dashboard-toolbar-main {
				align-items: stretch;
				gap: 0.75rem;
				width: 100%;
			}

			.damage-dashboard-toolbar-actions {
				align-items: stretch;
				gap: 0.75rem;
				justify-content: stretch;
				width: 100%;
			}

			.damage-dashboard-toolbar .toolbar-control-group {
				align-items: stretch;
				flex: 1 1 100%;
				flex-direction: column-reverse;
				gap: 0.4rem;
			}

			.damage-dashboard-toolbar .toolbar-period-group {
				display: grid;
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}

			.damage-dashboard-toolbar .toolbar-period-group .toolbar-label {
				grid-column: 1 / -1;
			}

			.damage-dashboard-toolbar .toolbar-period-button {
				min-width: 0;
				width: 100%;
			}

			.damage-dashboard-toolbar .toolbar-label {
				white-space: normal;
			}

			.damage-dashboard-toolbar .toolbar-neighborhood-wrap,
			.damage-dashboard-toolbar .toolbar-date-range-wrap {
				min-width: 0;
				width: 100% !important;
			}
		}

		@media (min-width: 768px) and (max-width: 1199.98px) {
			.damage-dashboard-toolbar-actions {
				justify-content: flex-start;
			}

			.damage-dashboard-toolbar .toolbar-control-group {
				flex: 1 1 calc(50% - 0.75rem);
			}

			.damage-dashboard-toolbar .toolbar-period-group {
				flex: 1 1 100%;
			}
		}

		@media (max-width: 420px) {
			.damage-dashboard-toolbar .toolbar-period-group {
				grid-template-columns: minmax(0, 1fr);
			}
		}

		.damage-dashboard-stats {
			--summary-card-height: 700px;
			--summary-header-height: 230px;
			--summary-body-height: 560px;
			--summary-body-offset: 68px;
			--summary-label-size: clamp(0.72rem, 0.66rem + 0.22vw, 0.92rem);
			--summary-number-size: clamp(0.82rem, 0.78rem + 0.18vw, 1rem);
			--summary-total-size: clamp(2rem, 1.8rem + 0.8vw, 2.8rem);
			--summary-gap: 0.55rem;
			--summary-body-inline-space: 2.25rem;
			--summary-body-padding-x: 1.5rem;
			--summary-body-padding-y: 1.25rem;
			--summary-item-min-height: 2.9rem;
			--summary-row-gap: 0.35rem;
		}

		.damage-dashboard-stats>[class*="col-"] {
			display: flex;
		}

		.damage-dashboard-stats .dashboard-summary-card {
			width: 100%;
			height: 100%;
			min-height: 0;
			max-height: none;
			margin-bottom: 0 !important;
		}

		.damage-dashboard-stats .dashboard-summary-card>.card-body {
			display: flex;
			flex-direction: column;
			height: 100%;
			padding: 0 !important;
			overflow: hidden;
		}

		.damage-dashboard-stats .dashboard-summary-header {
			height: var(--summary-header-height);
			min-height: var(--summary-header-height) !important;
			max-height: var(--summary-header-height);
			flex: 0 0 auto;
		}

		.damage-dashboard-stats .dashboard-summary-body {
			height: auto;
			min-height: 0;
			max-height: none;
			margin-inline: var(--summary-body-inline-space) !important;
			margin-top: calc(var(--summary-body-offset) * -1) !important;
			display: flex;
			flex: 1 1 auto;
			flex-direction: column;
			justify-content: flex-start;
			gap: var(--summary-row-gap);
			overflow: visible;
			padding: var(--summary-body-padding-y) var(--summary-body-padding-x) !important;
		}

		.damage-dashboard-stats .dashboard-summary-header .d-flex.text-center.flex-column.text-white.pt-8 {
			padding-top: clamp(1.5rem, 1.35rem + 0.3vw, 1.9rem) !important;
		}

		.damage-dashboard-stats .dashboard-summary-header .fw-semibold.fs-7 {
			font-size: clamp(0.85rem, 0.8rem + 0.14vw, 0.98rem) !important;
		}

		.damage-dashboard-stats .dashboard-summary-header .fw-bold.fs-1.fs-lg-2x.pt-1 {
			font-size: var(--summary-total-size) !important;
			line-height: 1.08;
			padding-top: 0.35rem !important;
		}

		.damage-dashboard-stats .d-flex.align-items-center.flex-wrap.w-100 {
			display: flex !important;
			flex-wrap: nowrap !important;
			align-items: center !important;
			justify-content: space-between;
			gap: var(--summary-gap);
			min-width: 0;
		}

		.damage-dashboard-stats .d-flex.align-items-center.flex-wrap.w-100>.mb-1.pe-3.flex-grow-1 {
			flex: 1 1 auto;
			min-width: 0;
			margin-bottom: 0 !important;
		}

		.damage-dashboard-stats .d-flex.align-items-center.flex-wrap.w-100>.mb-1.pe-3.flex-grow-1 a {
			display: block;
			line-height: 1.25;
			min-height: 1.25em;
			font-size: var(--summary-label-size) !important;
			overflow: visible;
			text-wrap: nowrap;
			white-space: nowrap;
			word-break: normal;
			overflow-wrap: normal;
		}

		.damage-dashboard-stats .d-flex.align-items-center.flex-wrap.w-100>.fw-bold,
		.damage-dashboard-stats .d-flex.align-items-center.flex-wrap.w-100>.d-flex.align-items-center {
			flex: 0 0 auto;
			white-space: nowrap;
			font-size: var(--summary-number-size) !important;
		}

		.damage-dashboard-stats .symbol.symbol-25px.w-25px.me-5 {
			margin-inline-end: clamp(0.45rem, 0.35rem + 0.25vw, 0.75rem) !important;
		}

		.damage-dashboard-stats .dashboard-summary-body .d-flex.align-items-center.mb-6 {
			min-height: 0;
			margin-bottom: 0 !important;
		}

		.damage-dashboard-stats .dashboard-summary-body>.d-flex.align-items-center {
			min-height: var(--summary-item-min-height);
		}

		@media (max-width: 991.98px) {
			.damage-dashboard-stats {
				--summary-card-height: 660px;
				--summary-header-height: 215px;
				--summary-body-height: 530px;
				--summary-body-offset: 56px;
				--summary-label-size: clamp(0.68rem, 0.63rem + 0.18vw, 0.82rem);
				--summary-number-size: clamp(0.78rem, 0.74rem + 0.14vw, 0.9rem);
				--summary-total-size: clamp(1.75rem, 1.6rem + 0.45vw, 2.25rem);
				--summary-gap: 0.5rem;
				--summary-body-inline-space: 1.6rem;
				--summary-body-padding-x: 1.25rem;
				--summary-body-padding-y: 1rem;
				--summary-item-min-height: 2.65rem;
				--summary-row-gap: 0.35rem;
			}
		}

		@media (max-width: 767.98px) {
			.damage-dashboard-stats {
				--summary-card-height: 620px;
				--summary-header-height: 200px;
				--summary-body-height: 500px;
				--summary-body-offset: 44px;
				--summary-label-size: clamp(0.64rem, 0.6rem + 0.16vw, 0.74rem);
				--summary-number-size: clamp(0.72rem, 0.68rem + 0.12vw, 0.82rem);
				--summary-total-size: clamp(1.5rem, 1.38rem + 0.32vw, 1.95rem);
				--summary-gap: 0.45rem;
				--summary-body-inline-space: 1rem;
				--summary-body-padding-x: 1rem;
				--summary-body-padding-y: 0.9rem;
				--summary-item-min-height: 2.45rem;
				--summary-row-gap: 0.3rem;
			}
		}

		@media (max-width: 575.98px) {
			.damage-dashboard-stats {
				--summary-card-height: 600px;
				--summary-header-height: 190px;
				--summary-body-height: 485px;
				--summary-body-offset: 38px;
				--summary-label-size: clamp(0.6rem, 0.58rem + 0.1vw, 0.68rem);
				--summary-number-size: clamp(0.68rem, 0.66rem + 0.08vw, 0.76rem);
				--summary-total-size: clamp(1.3rem, 1.2rem + 0.22vw, 1.65rem);
				--summary-gap: 0.4rem;
				--summary-body-inline-space: 0.75rem;
				--summary-body-padding-x: 0.75rem;
				--summary-body-padding-y: 0.75rem;
				--summary-item-min-height: 2.3rem;
				--summary-row-gap: 0.25rem;
			}
		}

		.damage-map-shell {
			position: relative;
		}

		.damage-map-fullscreen {
			background: var(--bs-body-bg);
			height: 100vh !important;
			inset: 0;
			position: fixed !important;
			width: 100vw !important;
			z-index: 1050;
		}

		.damage-map-fullscreen #viewDiv {
			border-radius: 0 !important;
			height: 100vh !important;
			margin: 0 !important;
		}

		body.damage-map-fullscreen-active {
			overflow: hidden;
		}

		body.damage-map-fullscreen-active .app-header,
		body.damage-map-fullscreen-active .app-sidebar,
		body.damage-map-fullscreen-active .app-footer,
		body.damage-map-fullscreen-active .toolbar,
		body.damage-map-fullscreen-active #kt_app_header,
		body.damage-map-fullscreen-active #kt_app_sidebar,
		body.damage-map-fullscreen-active #kt_app_footer,
		body.damage-map-fullscreen-active #kt_app_toolbar {
			display: none !important;
		}

		.arcgis-map-fullscreen-button {
			position: absolute;
			inset-block-start: 1rem;
			left: auto;
			right: 1rem;
			z-index: 1048;
		}

		.arcgis-map-filter-panel {
			position: absolute;
			inset-block-start: 1rem;
			left: 1rem;
			right: auto;
			width: 300px;
			max-width: calc(100% - 2rem);
			max-height: calc(100% - 2rem);
			overflow: hidden;
			z-index: 1047;
			box-shadow: 0 10px 30px rgba(15, 23, 42, 0.16);
		}

		[dir="ltr"] .arcgis-map-filter-panel {
			left: auto;
			right: 1rem;
		}

		.arcgis-map-filter-panel .card-header {
			border-bottom: 0;
			cursor: pointer;
		}

		.arcgis-map-filter-panel .form-label {
			font-size: 0.78rem;
			margin-bottom: 0.35rem;
		}

		.arcgis-map-filter-panel .form-control,
		.arcgis-map-filter-panel .form-select,
		.arcgis-map-filter-panel .select2-selection {
			min-height: 36px !important;
		}

		.arcgis-map-filter-body {
			max-height: 520px;
			overflow-y: auto;
		}

		.arcgis-map-filter-toggle {
			align-items: center;
			display: inline-flex;
			height: 32px;
			justify-content: center;
			width: 32px;
		}

		.arcgis-map-filter-panel.is-collapsed .arcgis-map-filter-body {
			display: none;
		}

		@media (max-width: 767.98px) {
			.arcgis-map-filter-panel {
				left: 2.5%;
				right: 2.5%;
				width: 95%;
			}

			[dir="ltr"] .arcgis-map-filter-panel {
				left: 2.5%;
				right: 2.5%;
			}

			.arcgis-map-fullscreen-button {
				inset-block-start: auto;
				inset-block-end: 1rem;
			}
		}
	</style>

	<div class="damage-dashboard-toolbar">
		<div class="damage-dashboard-toolbar-main">
			<div class="damage-dashboard-toolbar-actions">


				<div class="toolbar-control-group toolbar-neighborhood-group">
					<div class="toolbar-neighborhood-wrap">
						<select id="dashboard_toolbar_neighborhood"
							class="form-select form-select-sm toolbar-neighborhood-select" data-control="select2"
							data-placeholder="{{ __('ui.damage_dashboard.select_neighborhood') }}" data-allow-clear="true">
							<option value="">{{ __('ui.damage_dashboard.all_neighborhoods') }}</option>
							@foreach ($neighborhoods as $neighborhood)
								<option value="{{ $neighborhood }}"
									@selected($dashboardFilters['selectedNeighborhood'] === $neighborhood)>
									{{ $neighborhood }}
								</option>
							@endforeach
						</select>
					</div>
					<span class="toolbar-label">{{ __('ui.damage_dashboard.period_by_neighborhood') }}:</span>
				</div>

				<div class="separator separator-vertical h-30px d-none d-md-block"></div>

				<div class="toolbar-control-group toolbar-date-group">
					<div class="toolbar-date-range-wrap">
						<input type="text" id="dashboard_toolbar_date_range"
							class="form-control form-control-sm toolbar-date-range-input"
							placeholder="{{ __('ui.damage_dashboard.date_range') }}"
							value="{{ $dashboardFilters['startDate'] && $dashboardFilters['endDate'] ? $dashboardFilters['startDate'] . ' - ' . $dashboardFilters['endDate'] : '' }}"
							readonly>
					</div>
					<span class="toolbar-label">{{ __('ui.damage_dashboard.date_range') }}:</span>
				</div>

				<div class="separator separator-vertical h-30px d-none d-md-block"></div>

				<div class="toolbar-control-group toolbar-period-group" data-kt-buttons="true">
					<button type="button"
						class="btn btn-sm toolbar-period-button dashboard-toolbar-period @if ($dashboardFilters['period'] === 'day') active @endif"
						data-period="day">{{ __('ui.damage_dashboard.yesterday') }}</button>
					<button type="button"
						class="btn btn-sm toolbar-period-button dashboard-toolbar-period @if ($dashboardFilters['period'] === 'week') active @endif"
						data-period="week">{{ __('ui.damage_dashboard.week') }}</button>
					<button type="button"
						class="btn btn-sm toolbar-period-button dashboard-toolbar-period @if ($dashboardFilters['period'] === 'today') active @endif"
						data-period="today">{{ __('ui.damage_dashboard.today') }}</button>
					<button type="button"
						class="btn btn-sm toolbar-period-button dashboard-toolbar-period @if ($dashboardFilters['period'] === 'all') active @endif"
						data-period="all">{{ __('ui.damage_dashboard.all') }}</button>
					<span class="toolbar-label">{{ __('ui.damage_dashboard.filter_by') }}:</span>
				</div>
			</div>


		</div>
	</div>

	<div class="row g-5 g-xl-8 damage-dashboard-stats">
		<!--begin::Col-->
		<!-- 1. Changed to responsive column: col-sm-6 col-xl-3 -->
		<div class="col-sm-6 col-xl-3 mb-5">
			<div class="card card-xl-stretch mb-xl-8 dashboard-summary-card">
				<div class="card-body p-0">
					<!-- 2. Changed h-275px to min-h-275px to allow expansion if text wraps -->
					<div style="background-color: #ad3d3d;"
						class="px-9 pt-7 card-rounded min-h-275px w-100 dashboard-summary-header">
						<div class="d-flex flex-stack">
							<h3 class="m-0 text-white fw-bold fs-3">{{ __('ui.damage_dashboard.buildings') }}</h3>
							<div class="ms-1">
								<button type="button"
									class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
									data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
									<i class="ki-duotone ki-category fs-7 fs-lg-6"><span class="path1"></span><span
											class="path2"></span><span class="path3"></span><span class="path4"></span></i>
								</button>
							</div>
						</div>
						<div class="d-flex text-center flex-column text-white pt-8">
							<!-- Added text-wrap here -->
							<span
								class="fw-semibold fs-7 text-wrap">{{ __('ui.damage_dashboard.assessed_buildings') }}</span>
							<span class="fw-bold fs-1 fs-lg-2x pt-1">{{ $buildingStats['completed'] }}</span>
						</div>
					</div>

					<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1 dashboard-summary-body"
						style="margin-top: -100px">
						<!-- Item 1 -->
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-compass fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<!-- Added text-wrap -->
									<a href="{{ $dashboardStatLinks['buildings']['fully_damaged'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.fully_damaged') }}</a>
								</div>
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
										{{ $buildingStats['fully_damaged'] }}
									</div>
								</div>
							</div>
						</div>

						<!-- Item 2 -->
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-element-11 fs-1"><span
											class="path1"></span><span class="path2"></span><span class="path3"></span><span
											class="path4"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['buildings']['partially_damaged'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.partially_damaged') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $buildingStats['partially_damaged'] }}
								</div>
							</div>
						</div>

						<!-- Item 3 -->
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-graph-up fs-1"><span
											class="path1"></span><span class="path2"></span><span class="path3"></span><span
											class="path4"></span><span class="path5"></span><span
											class="path6"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['buildings']['committee_review'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.committee_review') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $buildingStats['committee_review'] }}
								</div>
							</div>
						</div>

						<!-- Item 4 -->
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-shield-search fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['buildings']['assessment_blocked'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.assessment_blocked') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $buildingStats['security_unsafe'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-people fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['buildings']['bodies_present'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.bodies_present') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $buildingStats['bodies'] }}</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-security-user fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['buildings']['uxo_present'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.uxo_present') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $buildingStats['uxo'] }}</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-geolocation fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['buildings']['debris_blocking'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.debris_blocking') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $buildingStats['debris'] }}</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-check fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['buildings']['completed'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.completed') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $buildingStats['completed'] }}</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!--end::Col-->
		<!--begin::Col-->
		<div class="d-none">
			<!--begin::Mixed Widget 1-->
			<div class="card card-xl-stretch mb-xl-8 dashboard-summary-card">
				<!--begin::Body-->
				<div class="card-body p-0">
					<!--begin::Header-->
					<div style="  background-color: #ccb050; "
						class="px-9 pt-7 text-white card-rounded h-275px w-100 dashboard-summary-header">
						<!--begin::Heading-->
						<div class="d-flex flex-stack">
							<h3 class="m-0  text-white fw-bold fs-3">{{ __('ui.damage_dashboard.buildings') }}</h3>
							<div class="ms-1">
								<!--begin::Menu-->
								<button type="button"
									class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
									data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
									<i class="ki-duotone ki-category fs-7 fs-lg-6">
										<span class="path1"></span>
										<span class="path2"></span>
										<span class="path3"></span>
										<span class="path4"></span>
									</i>
								</button>

							</div>
						</div>
						<!--end::Heading-->
						<!--begin::Balance-->
						<div class="d-flex text-center flex-column  pt-8">
							<span class="fw-semibold fs-7">{{ __('ui.damage_dashboard.buildings_not_assessed') }}</span>
							<span class="fw-bold fs-1 fs-lg-2x pt-1">{{ $buildingStats['not_completed'] }}</span>
						</div>
						<!--end::Balance-->
					</div>
					<!--end::Header-->
					<!--begin::Items-->
					<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1 dashboard-summary-body"
						style="margin-top: -100px">

						<!--begin::Item-->
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-document fs-3">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
										{{ __('ui.damage_dashboard.bodies_present') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"></div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $buildingStats['bodies']  }}
									</div>
									<i class="ki-duotone  fs-5 text-danger ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->

						</div>
						<!--end::Item-->
						<!--begin::Item-->
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-document fs-3">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
										{{ __('ui.damage_dashboard.uxo_present') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"></div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $buildingStats['uxo'] }}</div>
									<i class="ki-duotone  fs-5 text-danger ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->

						</div>
						<!--end::Item-->
						<!--begin::Item-->
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-document fs-3">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
										{{ __('ui.damage_dashboard.debris_blocking') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"></div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $buildingStats['debris'] }}
									</div>
									<i class="ki-duotone  fs-5 text-danger ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->

						</div>
						<!--end::Item-->
						<!--begin::Item-->
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-document fs-3">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="#" class="fs-7 text-gray-800 text-hover-primary fw-bold">

										{{ __('ui.damage_dashboard.assessment_blocked') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"></div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
										{{ $buildingStats['security_unsafe'] }}
									</div>
									<i class="ki-duotone  fs-5 text-danger ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->

						</div>
						<!--end::Item-->
					</div>
					<!--end::Items-->
				</div>
				<!--end::Body-->
			</div>
			<!--end::Mixed Widget 1-->
		</div>
		<!--end::Col-->


		<!--begin::Col-->
		<div class="col-sm-6 col-xl-3">
			<!--begin::Mixed Widget 1-->
			<div class="card card-xl-stretch mb-xl-8 dashboard-summary-card">
				<!--begin::Body-->
				<div class="card-body p-0">
					<!--begin::Header-->
					<div style=" background-color: #67986c; "
						class="px-9 pt-7 card-rounded h-275px w-100 dashboard-summary-header">
						<!--begin::Heading-->
						<div class="d-flex flex-stack">
							<h3 class="m-0 text-white fw-bold fs-3">{{ __('ui.damage_dashboard.housing_units') }}</h3>
							<div class="ms-1">
								<!--begin::Menu-->
								<button type="button"
									class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
									data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
									<i class="ki-duotone ki-category fs-7 fs-lg-6">
										<span class="path1"></span>
										<span class="path2"></span>
										<span class="path3"></span>
										<span class="path4"></span>
									</i>
								</button>

								<!--end::Menu-->
							</div>
						</div>
						<!--end::Heading-->
						<!--begin::Balance-->
						<div class="d-flex text-center flex-column text-white pt-8">
							<span class="fw-semibold fs-7">{{ __('ui.damage_dashboard.total_housing_units') }}</span>
							<span class="fw-bold fs-1 fs-lg-2x pt-1">{{ $unitStats['total_units']}}</span>
						</div>
						<!--end::Balance-->
					</div>
					<!--end::Header-->
					<!--begin::Items-->
					<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1 dashboard-summary-body"
						style="margin-top: -100px">

						<!--begin::Item-->
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-compass fs-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['housing']['fully_damaged'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.fully_damaged') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"> </div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $unitStats['fully_damaged'] }}
									</div>
									<i class="ki-duotone  fs-5 text-success ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->
						</div>
						<!--end::Item-->
						<!--begin::Item-->
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-element-11 fs-1">
										<span class="path1"></span>
										<span class="path2"></span>
										<span class="path3"></span>
										<span class="path4"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['housing']['partially_damaged'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.partially_damaged') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"></div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
										{{$unitStats['partially_damaged'] }}
									</div>
									<i class="ki-duotone fs-5 text-danger ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->
						</div>
						<!--end::Item-->
						<!--begin::Item-->
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-graph-up fs-1">
										<span class="path1"></span>
										<span class="path2"></span>
										<span class="path3"></span>
										<span class="path4"></span>
										<span class="path5"></span>
										<span class="path6"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['housing']['committee_review'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.committee_review') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"> </div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
										{{ $unitStats['committee_review'] }}
									</div>
								</div>
								<i class="ki-duotone fs-5 text-success ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->
						<!-- Item 4 -->
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-shield-search fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['housing']['assessment_blocked'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.assessment_blocked') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $unitStats['security_unsafe'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-home fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['housing']['structural_support'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.structural_support') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $unitStats['unit_support_needed'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-graph-up fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['housing']['at_risk_of_collapse'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.at_risk_of_collapse') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $unitStats['unit_stripping'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-home-2 fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['housing']['habitable'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.habitable') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $unitStats['habitable'] }}</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-security-user fs-3"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['housing']['fire_affected'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">{{ __('ui.damage_dashboard.fire_affected') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $unitStats['has_fire'] }}</div>
							</div>
						</div>
					</div>
					<!--end::Item-->
					<!--begin::Item-->

				</div>
				<!--end::Items-->

			</div>
			<!--end::Body-->
		</div>
		<!--end::Mixed Widget 1-->
		<div class="d-none">
			<!--begin::Mixed Widget 1-->
			<div class="card card-xl-stretch mb-xl-8 dashboard-summary-card">
				<!--begin::Body-->
				<div class="card-body p-0">
					<!--begin::Header-->
					<div style=" background-color: #0163ac; "
						class="px-9 pt-7 card-rounded h-275px w-100 dashboard-summary-header">
						<!--begin::Heading-->
						<div class="d-flex flex-stack">
							<h3 class="m-0 text-white fw-bold fs-3">{{ __('ui.damage_dashboard.housing_units') }}</h3>
							<div class="ms-1">
								<!--begin::Menu-->
								<button type="button"
									class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
									data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
									<i class="ki-duotone ki-category fs-7 fs-lg-6">
										<span class="path1"></span>
										<span class="path2"></span>
										<span class="path3"></span>
										<span class="path4"></span>
									</i>
								</button>

								<!--end::Menu-->
							</div>
						</div>
						<!--end::Heading-->
						<!--begin::Balance-->
						<div class="d-flex text-center flex-column text-white pt-8">
							<span class="fw-semibold fs-7">{{ __('ui.damage_dashboard.total_housing_units') }}</span>
							<span
								class="fw-bold fs-1 fs-lg-2x pt-1">{{ $unitStats['fully_damaged'] + $unitStats['partially_damaged'] + $unitStats['committee_review'] + $unitStats['security_unsafe'] }}</span>
						</div>
						<!--end::Balance-->
					</div>
					<!--end::Header-->
					<!--begin::Items-->
					<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1 dashboard-summary-body"
						style="margin-top: -100px">


						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-document fs-3">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ url('damage-assessment/housing') }}?unit_support_needed=yes"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
										{{ __('ui.damage_dashboard.structural_support') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"></div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
										{{ $unitStats['unit_support_needed'] }}
									</div>
									<i class="ki-duotone  fs-5 text-danger ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->

						</div>
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-document fs-3">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
										{{ __('ui.damage_dashboard.at_risk_of_collapse') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"></div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $unitStats['unit_stripping'] }}
									</div>
									<i class="ki-duotone  fs-5 text-danger ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->

						</div>
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-document fs-3">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
										{{ __('ui.damage_dashboard.habitable') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"></div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $unitStats['habitable'] }}</div>
									<i class="ki-duotone  fs-5 text-danger ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->

						</div>
						<div class="d-flex align-items-center mb-6">
							<!--begin::Symbol-->
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten">
									<i class="ki-duotone ki-document fs-3">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</span>
							</div>
							<!--end::Symbol-->
							<!--begin::Description-->
							<div class="d-flex align-items-center flex-wrap w-100">
								<!--begin::Title-->
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
										{{ __('ui.damage_dashboard.fire_affected') }}</a>
									<div class="text-gray-400 fw-semibold fs-7"></div>
								</div>
								<!--end::Title-->
								<!--begin::Label-->
								<div class="d-flex align-items-center">
									<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $unitStats['has_fire'] }}</div>
									<i class="ki-duotone  fs-5 text-danger ms-1">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
								</div>
								<!--end::Label-->
							</div>
							<!--end::Description-->

						</div>
					</div>
					<!--end::Items-->
				</div>
				<!--end::Body-->
			</div>
			<!--end::Mixed Widget 1-->
		</div>
		<div class="col-sm-6 col-xl-3">
			<div class="card card-xl-stretch mb-xl-8 dashboard-summary-card">
				<div class="card-body p-0">
					<div style="background-color: rgb(191 152 7);"
						class="px-9 pt-7 card-rounded h-275px w-100 dashboard-summary-header">
						<div class="d-flex flex-stack">
							<h3 class="m-0 text-white fw-bold fs-3">{{ __('ui.damage_dashboard.public_buildings') }}</h3>
							<div class="ms-1">
								<button type="button"
									class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
									data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
									<i class="ki-duotone ki-category fs-7 fs-lg-6"><span class="path1"></span><span
											class="path2"></span><span class="path3"></span><span class="path4"></span></i>
								</button>
							</div>
						</div>
						<div class="d-flex text-center flex-column text-white pt-8">
							<span
								class="fw-semibold fs-7">{{ __('multilingual.damage_dashboard.total_public_buildings') }}</span>
							<span class="fw-bold fs-1 fs-lg-2x pt-1">{{ $publicBuildingStats['total_surveys'] }}</span>
						</div>
					</div>
					<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1 dashboard-summary-body"
						style="margin-top: -100px">
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-compass fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['public_buildings']['damaged'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.damaged') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $publicBuildingStats['damaged_buildings'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-home fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['public_buildings']['units'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.units') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $publicBuildingStats['total_units'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-map fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['public_buildings']['municipalities'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.municipalities') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $publicBuildingStats['municipalities'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-geolocation fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['public_buildings']['neighborhoods'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.neighborhoods') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $publicBuildingStats['neighborhoods'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-profile-user fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['public_buildings']['assigned_staff'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.assigned_staff') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $publicBuildingStats['assigned_staff'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-people fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['public_buildings']['occupied'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.occupied') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $publicBuildingStats['occupied_buildings'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-security-user fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['public_buildings']['bodies'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.bodies') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $publicBuildingStats['bodies_present'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-shield-search fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['public_buildings']['uxo'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.uxo') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $publicBuildingStats['uxo_present'] }}
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-sm-6 col-xl-3">
			<div class="card card-xl-stretch mb-xl-8 dashboard-summary-card">
				<div class="card-body p-0">
					<div style="background-color: #0f766e;"
						class="px-9 pt-7 card-rounded h-275px w-100 dashboard-summary-header">
						<div class="d-flex flex-stack">
							<h3 class="m-0 text-white fw-bold fs-3">{{ __('ui.damage_dashboard.road_facilities') }}</h3>
							<div class="ms-1">
								<button type="button"
									class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
									data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
									<i class="ki-duotone ki-category fs-7 fs-lg-6"><span class="path1"></span><span
											class="path2"></span><span class="path3"></span><span class="path4"></span></i>
								</button>
							</div>
						</div>
						<div class="d-flex text-center flex-column text-white pt-8">
							<span
								class="fw-semibold fs-7">{{ __('multilingual.damage_dashboard.total_road_facilities') }}</span>
							<span class="fw-bold fs-1 fs-lg-2x pt-1">{{ $roadFacilityStats['total_surveys'] }}</span>
						</div>
					</div>
					<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1 dashboard-summary-body"
						style="margin-top: -100px">
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-compass fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['road_facilities']['damaged'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.damaged') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $roadFacilityStats['damaged_roads'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-element-11 fs-1"><span
											class="path1"></span><span class="path2"></span><span class="path3"></span><span
											class="path4"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['road_facilities']['items'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.items') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">{{ $roadFacilityStats['total_items'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-map fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['road_facilities']['municipalities'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.municipalities') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $roadFacilityStats['municipalities'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-geolocation fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['road_facilities']['neighborhoods'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.neighborhoods') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $roadFacilityStats['neighborhoods'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-abstract-26 fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['road_facilities']['potholes'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.potholes') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $roadFacilityStats['potholes_locations'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-warning-2 fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['road_facilities']['obstacles'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.obstacles') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $roadFacilityStats['obstacle_locations'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-security-user fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['road_facilities']['buried_bodies'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.buried_bodies') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $roadFacilityStats['buried_bodies_locations'] }}
								</div>
							</div>
						</div>
						<div class="d-flex align-items-center mb-6">
							<div class="symbol symbol-25px w-25px me-5">
								<span class="symbol-label bg-lighten"><i class="ki-duotone ki-shield-search fs-1"><span
											class="path1"></span><span class="path2"></span></i></span>
							</div>
							<div class="d-flex align-items-center flex-wrap w-100">
								<div class="mb-1 pe-3 flex-grow-1">
									<a href="{{ $dashboardStatLinks['road_facilities']['uxo'] }}"
										class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">{{ __('ui.damage_dashboard.uxo') }}</a>
								</div>
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									{{ $roadFacilityStats['uxo_locations'] }}
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>


	<!-- Summary Table Row -->
	<div class="row g-5 g-xl-8">
		<div class="col-12">
			<div class="card card-xl-stretch mb-xl-8">
				<div class="card-header border-0 pt-5">
					<h3 class="card-title align-items-start flex-column">
						<span
							class="card-label fw-bold fs-3 mb-1">{{ __('ui.damage_dashboard.buildings_status_summary') }}</span>
						<span
							class="text-muted mt-1 fw-semibold fs-7">{{ __('ui.damage_dashboard.buildings_status_summary_hint') }}</span>
					</h3>
				</div>
				<div class="card-body py-3">
					<div class="table-responsive">
						<table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
							<thead>
								<tr class="fw-bold text-muted">
									<th class="min-w-150px">{{ __('ui.damage_dashboard.category') }}</th>
									<th class="min-w-100px text-end">{{ __('ui.damage_dashboard.count') }}</th>
									<th class="min-w-150px text-end">{{ __('ui.damage_dashboard.percentage') }}</th>
								</tr>
							</thead>
							<tbody>
								@php
									$totalAssessed = $buildingStats['fully_damaged'] + $buildingStats['partially_damaged'] + $buildingStats['committee_review'];

									$getPercent = fn($val, $total) => $total > 0 ? round(($val / $total) * 100, 1) : 0;
								@endphp

								<tr>
									<td><span
											class="text-dark fw-bold text-hover-primary fs-6">{{ __('ui.damage_dashboard.fully_damaged') }}</span>
									</td>
									<td class="text-end text-muted fw-bold">{{ $buildingStats['fully_damaged'] }}</td>
									<td class="text-end">
										<div class="d-flex align-items-center justify-content-end">
											<span
												class="text-muted fw-bold me-2">{{ $getPercent($buildingStats['fully_damaged'], $totalAssessed) }}%</span>
											<div class="progress h-6px w-100px">
												<div class="progress-bar bg-danger" role="progressbar"
													style="width: {{ $getPercent($buildingStats['fully_damaged'], $totalAssessed) }}%">
												</div>
											</div>
										</div>
									</td>
								</tr>

								<tr>
									<td><span
											class="text-dark fw-bold text-hover-primary fs-6">{{ __('ui.damage_dashboard.partially_damaged') }}</span>
									</td>
									<td class="text-end text-muted fw-bold">{{ $buildingStats['partially_damaged'] }}</td>
									<td class="text-end">
										<div class="d-flex align-items-center justify-content-end">
											<span
												class="text-muted fw-bold me-2">{{ $getPercent($buildingStats['partially_damaged'], $totalAssessed) }}%</span>
											<div class="progress h-6px w-100px">
												<div class="progress-bar bg-warning" role="progressbar"
													style="width: {{ $getPercent($buildingStats['partially_damaged'], $totalAssessed) }}%">
												</div>
											</div>
										</div>
									</td>
								</tr>

								<tr>
									<td><span
											class="text-dark fw-bold text-hover-primary fs-6">{{ __('ui.damage_dashboard.committee_review') }}</span>
									</td>
									<td class="text-end text-muted fw-bold">{{ $buildingStats['committee_review'] }}</td>
									<td class="text-end">
										<div class="d-flex align-items-center justify-content-end">
											<span
												class="text-muted fw-bold me-2">{{ $getPercent($buildingStats['committee_review'], $totalAssessed) }}%</span>
											<div class="progress h-6px w-100px">
												<div class="progress-bar bg-primary" role="progressbar"
													style="width: {{ $getPercent($buildingStats['committee_review'], $totalAssessed) }}%">
												</div>
											</div>
										</div>
									</td>
								</tr>

								<tr class="bg-light-secondary">
									<td><span
											class="text-dark fw-bolder fs-6">{{ __('ui.damage_dashboard.total_assessed') }}</span>
									</td>
									<td class="text-end text-dark fw-bolder fs-6">{{ $totalAssessed }}</td>
									<td class="text-end"><span class="badge badge-light-success fw-bold">100%</span></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-5 g-xl-8">
		<div class="col-12">
			<div class="card card-xl-stretch mb-xl-8">
				<div class="card-header border-0 pt-5">
					<h3 class="card-title align-items-start flex-column">
						<span
							class="card-label fw-bold fs-3 mb-1">{{ __('ui.damage_dashboard.housing_status_summary') }}</span>
						<span
							class="text-muted mt-1 fw-semibold fs-7">{{ __('ui.damage_dashboard.housing_status_summary_hint') }}</span>
					</h3>
				</div>
				<div class="card-body py-3">
					<div class="table-responsive">
						<table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
							<thead>
								<tr class="fw-bold text-muted">
									<th class="min-w-150px">{{ __('ui.damage_dashboard.category') }}</th>
									<th class="min-w-100px text-end">{{ __('ui.damage_dashboard.count') }}</th>
									<th class="min-w-150px text-end">{{ __('ui.damage_dashboard.percentage') }}</th>
								</tr>
							</thead>
							<tbody>
								@php
									// Calculating total for housing units
									$uFully = $unitStats['fully_damaged'] ?? 0;
									$uPartially = $unitStats['partially_damaged'] ?? 0;
									$uCommittee = $unitStats['committee_review'] ?? 0;

									$totalUnitsAssessed = $uFully + $uPartially + $uCommittee;

									// Note: getPercent function is already defined in your building block,
									// so we can just call it here.
								@endphp

								<tr>
									<td><span
											class="text-dark fw-bold text-hover-primary fs-6">{{ __('ui.damage_dashboard.units_fully_damaged') }}</span>
									</td>
									<td class="text-end text-muted fw-bold">{{ $uFully }}</td>
									<td class="text-end">
										<div class="d-flex align-items-center justify-content-end">
											<span
												class="text-muted fw-bold me-2">{{ $getPercent($uFully, $totalUnitsAssessed) }}%</span>
											<div class="progress h-6px w-100px">
												<div class="progress-bar bg-danger" role="progressbar"
													style="width: {{ $getPercent($uFully, $totalUnitsAssessed) }}%">
												</div>
											</div>
										</div>
									</td>
								</tr>

								<tr>
									<td><span
											class="text-dark fw-bold text-hover-primary fs-6">{{ __('ui.damage_dashboard.units_partially_damaged') }}</span>
									</td>
									<td class="text-end text-muted fw-bold">{{ $uPartially }}</td>
									<td class="text-end">
										<div class="d-flex align-items-center justify-content-end">
											<span
												class="text-muted fw-bold me-2">{{ $getPercent($uPartially, $totalUnitsAssessed) }}%</span>
											<div class="progress h-6px w-100px">
												<div class="progress-bar bg-warning" role="progressbar"
													style="width: {{ $getPercent($uPartially, $totalUnitsAssessed) }}%">
												</div>
											</div>
										</div>
									</td>
								</tr>

								<tr>
									<td><span
											class="text-dark fw-bold text-hover-primary fs-6">{{ __('ui.damage_dashboard.units_committee_review') }}</span>
									</td>
									<td class="text-end text-muted fw-bold">{{ $uCommittee }}</td>
									<td class="text-end">
										<div class="d-flex align-items-center justify-content-end">
											<span
												class="text-muted fw-bold me-2">{{ $getPercent($uCommittee, $totalUnitsAssessed) }}%</span>
											<div class="progress  h-6px w-100px">
												<div class="progress-bar bg-primary" role="progressbar"
													style="width: {{ $getPercent($uCommittee, $totalUnitsAssessed) }}%">
												</div>
											</div>
										</div>
									</td>
								</tr>

								<tr class="bg-light-secondary">
									<td><span
											class="text-dark fw-bolder fs-6">{{ __('ui.damage_dashboard.total_units_assessed') }}</span>
									</td>
									<td class="text-end text-dark fw-bolder fs-6">{{ $totalUnitsAssessed }}</td>
									<td class="text-end"><span class="badge badge-light-success fw-bold">100%</span></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-5 g-xl-8">
		<!-- Buildings Chart Card -->
		<div class="col-xl-6">
			<div class="card card-xl-stretch mb-xl-8">
				<div class="card-header border-0 pt-5">
					<h3 class="card-title align-items-start flex-column">
						<span
							class="card-label fw-bold fs-3 mb-1">{{ __('ui.damage_dashboard.building_statistics') }}</span>
					</h3>
				</div>
				<div class="card-body">
					<div id="buildings_donut_chart" style="height: 350px"></div>
				</div>
			</div>
		</div>

		<!-- Housing Units Chart Card -->
		<div class="col-xl-6">
			<div class="card card-xl-stretch mb-xl-8">
				<div class="card-header border-0 pt-5">
					<h3 class="card-title align-items-start flex-column">
						<span class="card-label fw-bold fs-3 mb-1">{{ __('ui.damage_dashboard.housing_statistics') }}</span>
					</h3>
				</div>
				<div class="card-body">
					<div id="housing_units_donut_chart" style="height: 350px"></div>
				</div>
			</div>
		</div>
	</div>

	<!--end::Col-->

	<!--begin::Col-->

	<!--end::Col-->


	<div class="row g-5 g-xl-8">
		<div class="card">
			<div class="card-header border-0 pt-6">
				<!--begin::Card title-->

				<!--begin::Card title-->
				<div class="cart-title">


				</div>
				<div class="card-title">
					<!--begin::Search-->
					{{ __('ui.damage_dashboard.aerial_map') }}
				</div>
			</div>
			<!--begin::Body-->
			<div class="card-body p-lg-17">

				<!--begin::Row-->
				<div class="row mb-3">
					<!--begin::Col-->

					<!--end::Col-->
					<!--begin::Col-->

					<!--end::Col-->

					<div class="col-md-12 ps-lg-12">
						<link rel="stylesheet" href="https://js.arcgis.com/4.22/esri/themes/light/main.css">
						<!--begin::Map-->
						<div id="damageMapShell" class="damage-map-shell">
							<div id="viewDiv" class="w-100 rounded mb-2 mb-lg-0 mt-2" style="height: 650px"></div>

							<button type="button" id="damageMapFullscreenButton"
								class="btn btn-sm btn-primary arcgis-map-fullscreen-button">
								<span class="damage-map-fullscreen-label">ملء الشاشة</span>
								<span class="damage-map-exit-label d-none">خروج</span>
							</button>

							<div id="arcgisMapFilterPanel" class="card arcgis-map-filter-panel is-collapsed">
								<div class="card-header min-h-45px px-3 py-2">
									<div class="card-title m-0">
										<span class="fw-bold fs-7">فلترة الخريطة</span>
									</div>
									<div class="card-toolbar gap-2">
										<span class="badge badge-light-primary">
											عدد النتائج:
											<span id="arcgisFilterCount">0</span>
										</span>
										<button type="button" id="arcgisMapFilterToggle"
											class="btn btn-sm btn-icon btn-light arcgis-map-filter-toggle"
											aria-label="Toggle map filters">
											<i class="ki-duotone ki-up fs-3"></i>
										</button>
									</div>
								</div>
								<div class="card-body p-3 arcgis-map-filter-body">
									<div class="mb-2">
										<label class="form-label fw-semibold">المهندس الميداني</label>
										<select id="arcgis_filter_assignedto"
											class="form-select form-select-sm arcgis-map-filter-select"
											data-field="assignedto" data-placeholder="المهندس الميداني"></select>
									</div>
									<div class="mb-5">
										<label class="form-label fw-semibold">
											حالة إلإستبيان
										</label>

										<select id="mapFieldStatusFilter" class="form-select form-select-sm"
											data-control="select2" data-hide-search="true" data-field="field_status"
											data-placeholder="حالة إلإستبيان">

											<option value="">
												الكل
											</option>

											<option value="COMPLETED">
											مكتمل
											</option>

											<option value="Not_Completed">
												فير مكتمل
											</option>
										</select>
									</div>
									<div class="mb-2">
										<label class="form-label fw-semibold">حالة الضرر</label>
										<select id="arcgis_filter_building_damage_status"
											class="form-select form-select-sm arcgis-map-filter-select"
											data-field="building_damage_status" data-placeholder="حالة الضرر"></select>
									</div>
									<div class="mb-2">
										<label class="form-label fw-semibold">البلدية</label>
										<select id="arcgis_filter_municipalitie"
											class="form-select form-select-sm arcgis-map-filter-select"
											data-field="municipalitie" data-placeholder="البلدية"></select>
									</div>
									<div class="mb-2">
										<label class="form-label fw-semibold">الحي</label>
										<select id="arcgis_filter_neighborhood"
											class="form-select form-select-sm arcgis-map-filter-select"
											data-field="neighborhood" data-placeholder="الحي"></select>
									</div>
									<div class="mb-2">
										<label class="form-label fw-semibold" for="arcgis_filter_search">بحث ObjectID /
											GlobalID</label>
										<input type="text" id="arcgis_filter_search" class="form-control form-control-sm"
											placeholder="بحث ObjectID / GlobalID">
									</div>
									<div class="row g-2 mb-3">
										<div class="col-6">
											<label class="form-label fw-semibold" for="arcgis_filter_from_date">من
												تاريخ</label>
											<input type="date" id="arcgis_filter_from_date"
												class="form-control form-control-sm">
										</div>
										<div class="col-6">
											<label class="form-label fw-semibold" for="arcgis_filter_to_date">إلى
												تاريخ</label>
											<input type="date" id="arcgis_filter_to_date"
												class="form-control form-control-sm">
										</div>
									</div>
									<div class="d-flex gap-2">
										<button type="button" id="arcgisFilterApply"
											class="btn btn-sm btn-primary flex-grow-1">
											تطبيق الفلترة
										</button>
										<button type="button" id="arcgisFilterReset"
											class="btn btn-sm btn-light flex-grow-1">
											إعادة تعيين
										</button>
									</div>
								</div>
							</div>
						</div>
						<!--end::Map-->
						<div id="externalLegendDiv"></div>
					</div>
				</div>
				<!--end::Row-->
			</div>
			<!--end::Body-->
		</div>

	</div>


	<div class="row g-5 g-xl-8 mt-2">
		<div class="card">
			<div class="card-header border-0 pt-6">
				<div class="cart-title">
					<div class="d-flex align-items-center position-relative my-1">
						<i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
							<span class="path1"></span>
							<span class="path2"></span>
						</i>
						<input type="text" data-kt-public-building-map-filter="search"
							class="form-control form-control-solid w-250px ps-13"
							placeholder="{{ __('multilingual.damage_dashboard.search_public_buildings') }}" />
					</div>
				</div>
				<div class="card-title">
					{{ __('multilingual.damage_dashboard.public_buildings_map') }}
				</div>
			</div>
			<div class="card-body p-lg-17">
				<div class="row mb-3">
					<div class="col-md-5 pe-lg-10">
						<table class="table table-rounded table-striped align-middle fs-7 fs-lg-6 gy-5"
							id="kt_table_public_building">
							<thead>
								<tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
									<th class="min-w-70px">{{ __('multilingual.damage_dashboard.municipality') }}</th>
									<th class="min-w-70px">{{ __('multilingual.damage_dashboard.neighborhood') }}</th>
									<th class="min-w-70px">{{ __('multilingual.damage_dashboard.object_id') }}</th>
									<th class="min-w-70px">{{ __('ui.damage_dashboard.building_name') }}</th>
									<th class="min-w-70px">{{ __('multilingual.damage_dashboard.damage_status') }}</th>
								</tr>
							</thead>
							<tbody class="text-gray-600 fw-semibold"></tbody>
						</table>
					</div>
					<div class="col-md-7 ps-lg-10">
						<div id="publicBuildingViewDiv" class="w-100 rounded mb-2 mb-lg-0 mt-2" style="height: 650px"></div>
						<div id="publicBuildingLegendDiv"></div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-5 g-xl-8 mt-2">
		<div class="card">
			<div class="card-header border-0 pt-6">
				<div class="cart-title">
					<div class="d-flex align-items-center position-relative my-1">
						<i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
							<span class="path1"></span>
							<span class="path2"></span>
						</i>
						<input type="text" data-kt-road-facility-map-filter="search"
							class="form-control form-control-solid w-250px ps-13"
							placeholder="{{ __('multilingual.damage_dashboard.search_road_facilities') }}" />
					</div>
				</div>
				<div class="card-title">
					{{ __('multilingual.damage_dashboard.road_facilities_map') }}
				</div>
			</div>
			<div class="card-body p-lg-17">
				<div class="row mb-3">
					<div class="col-md-5 pe-lg-10">
						<table class="table table-rounded table-striped align-middle fs-7 fs-lg-6 gy-5"
							id="kt_table_road_facility">
							<thead>
								<tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
									<th class="min-w-70px">{{ __('multilingual.damage_dashboard.municipality') }}</th>
									<th class="min-w-70px">{{ __('multilingual.damage_dashboard.neighborhood') }}</th>
									<th class="min-w-70px">{{ __('multilingual.damage_dashboard.object_id') }}</th>
									<th class="min-w-70px">{{ __('multilingual.damage_dashboard.road_name') }}</th>
									<th class="min-w-70px">{{ __('multilingual.damage_dashboard.damage_level') }}</th>
								</tr>
							</thead>
							<tbody class="text-gray-600 fw-semibold"></tbody>
						</table>
					</div>
					<div class="col-md-7 ps-lg-10">
						<div id="roadFacilityViewDiv" class="w-100 rounded mb-2 mb-lg-0 mt-2" style="height: 650px"></div>
						<div id="roadFacilityLegendDiv"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('script')

	<script src="https://js.arcgis.com/4.22/"></script>

	<script>
		// =========================
		// Charts
		// =========================
		var buildingsChart = null;
		var housingChart = null;

		var buildingsOptions = {
			series: [
									{{ $buildingStats['fully_damaged'] ?? 0 }},
									{{ $buildingStats['partially_damaged'] ?? 0 }},
									{{ $buildingStats['committee_review'] ?? 0 }},
				{{ $buildingStats['security_unsafe'] ?? 0 }}
			],
			chart: {
				type: 'donut',
				height: 350
			},
			labels: [
				@json(__('ui.damage_dashboard.fully_damaged')),
				@json(__('ui.damage_dashboard.partially_damaged')),
				@json(__('ui.damage_dashboard.committee_review')),
				@json(__('ui.damage_dashboard.assessment_blocked'))
			],
			colors: ['#F1416C', '#FFAD0F', '#009EF7', '#7239EA'],
			legend: {
				position: 'bottom'
			},
			dataLabels: {
				enabled: true
			}
		};

		buildingsChart = new ApexCharts(document.querySelector("#buildings_donut_chart"), buildingsOptions);
		buildingsChart.render();

		var housingOptions = {
			series: [
									{{ $unitStats['fully_damaged'] ?? 0 }},
									{{ $unitStats['partially_damaged'] ?? 0 }},
				{{ $unitStats['committee_review'] ?? 0 }}
			],
			chart: {
				type: 'donut',
				height: 350
			},
			labels: [
				@json(__('ui.damage_dashboard.units_fully_damaged')),
				@json(__('ui.damage_dashboard.units_partially_damaged')),
				@json(__('ui.damage_dashboard.units_committee_review'))
			],
			colors: ['#D9214E', '#F1BC00', '#50CD89'],
			legend: {
				position: 'bottom'
			},
			dataLabels: {
				enabled: true
			}
		};

		housingChart = new ApexCharts(document.querySelector("#housing_units_donut_chart"), housingOptions);
		housingChart.render();

		// =========================
		// ArcGIS Map
		// =========================


		require([
			"esri/Map",
			"esri/views/MapView",
			"esri/layers/FeatureLayer",
			"esri/layers/GraphicsLayer",
			"esri/Graphic",
			"esri/identity/IdentityManager",
			"esri/widgets/BasemapToggle",
			"esri/widgets/Legend",
			"esri/widgets/Search",
			"esri/widgets/ScaleBar",
			"esri/geometry/support/webMercatorUtils",
			"esri/geometry/Extent",
			"esri/widgets/Expand"
		], function (
			Map,
			MapView,
			FeatureLayer,
			GraphicsLayer,
			Graphic,
			esriId,
			BasemapToggle,
			Legend,
			Search,
			ScaleBar,
			webMercatorUtils,
			Extent,
			Expand
		) {

			const assessmentBaseUrl = "{{ url('damage-assessment/assessment') }}";
			const canViewAssessmentLink = @json(!auth()->user()->hasRole('MOPWH'));
			const buildingLayerUrl = @json(config('services.arcgis.buildings_url'));
			const arcgisOptionsUrl = window.location.pathname.replace(/\/$/, '') + '/arcgis/options';
			const gazaStripExtent = new Extent({
				xmin: 34.1900,
				ymin: 31.2000,
				xmax: 34.5800,
				ymax: 31.6000,
				spatialReference: {
					wkid: 4326
				}
			});

			const damageRenderer = {
				type: "unique-value",
				field: "building_damage_status",
				defaultSymbol: {
					type: "simple-fill",
					color: [128, 128, 128, 0.9],
					outline: {
						color: "white",
						width: 1
					}
				},
				uniqueValueInfos: [
					{
						value: "committee_review",
						symbol: {
							type: "simple-fill",
							color: [255, 255, 0, 0.5],
							outline: {
								color: "black",
								width: 1
							}
						},
						label: @json(__('ui.damage_dashboard.committee_review'))
					},
					{
						value: "fully_damaged",
						symbol: {
							type: "simple-fill",
							color: [255, 0, 0, 0.5],
							outline: {
								color: "white",
								width: 2
							}
						},
						label: @json(__('ui.damage_dashboard.fully_damaged'))
					},
					{
						value: "partially_damaged",
						symbol: {
							type: "simple-fill",
							color: [0, 255, 0, 0.5],
							outline: {
								color: "white",
								width: 1
							}
						},
						label: @json(__('ui.damage_dashboard.partially_damaged'))
					}
				]
			};

			esriId.registerToken({
				server: buildingLayerUrl,
				token: "{{ $token }}",
				expires: Date.now() + (60 * 60 * 1000)
			});

			const fieldInfos = [
				{ fieldName: "objectid" },
				{ fieldName: "building_name" },
				{ fieldName: "assignedto" },
				{ fieldName: "building_damage_status" }
			];

			const measureThisAction = {
				title: "Measure Length",
				id: "measure-this",
				icon: "measure"
			};

			const featureLayer = new FeatureLayer({
				url: buildingLayerUrl,
				renderer: damageRenderer,
				outFields: ["*"],
				// ADD THESE TWO LINES:
				minScale: 0, // Keeps it visible when zooming out
				maxScale: 0, // Keeps it visible when zooming in (Fixes the Legend)
				definitionExpression: '1=1',
				labelingInfo: [{
					symbol: {
						type: "text",
						color: "white",
						haloColor: "black",
						haloSize: "1px",
						font: {
							family: "Ubuntu Mono",
							size: 12,
							weight: "bold"
						}
					},
					labelPlacement: "always-horizontal",
					labelExpressionInfo: {
						expression: "$feature.building_name"
					}
				}],
				popupTemplate: {
					title: function (event) {
						const attrs = event.graphic.attributes;
						const g = attrs.globalid || attrs.GLOBALID || "";
						const name = attrs.building_name || "";
						const assessmentLink = canViewAssessmentLink && g
							? `<a target="_blank" style="color:red;" href="${assessmentBaseUrl}/${g}">
						${@json(__('ui.damage_dashboard.assessment'))}
					</a>`
							: "";

						return `${@json(__('ui.damage_dashboard.building_name'))}: ${name}
					${assessmentLink}`;
					},

					content: function (event) {
						const graphic = event.graphic;
						const attrs = graphic.attributes || {};
						const coords = getLatLong(graphic);

						const lat = coords?.lat ? Number(coords.lat).toFixed(6) : "-";
						const lng = coords?.lng ? Number(coords.lng).toFixed(6) : "-";
						const googleUrl = coords?.lat && coords?.lng
							? `https://www.google.com/maps?q=${coords.lat},${coords.lng}`
							: "#";

						return `
					<table class="esri-widget__table">
						<tbody>
							<tr>
								<th>objectid</th>
								<td>${attrs.objectid || attrs.OBJECTID || "-"}</td>
							</tr>
							<tr>
								<th>building_name</th>
								<td>${attrs.building_name || "-"}</td>
							</tr>
							<tr>
								<th>AssignedTo</th>
								<td>${attrs.assignedto || attrs.AssignedTo || "-"}</td>
							</tr>
							<tr>
								<th>building_damage_status</th>
								<td>${attrs.building_damage_status || "-"}</td>
							</tr>
							<tr>
								<th>Latitude</th>
								<td>${lat}</td>
							</tr>
							<tr>
								<th>Longitude</th>
								<td>${lng}</td>
							</tr>
						</tbody>
					</table>

					<div class="mt-3 d-flex gap-2">
						<button type="button"
							class="btn btn-sm btn-light-primary"
							onclick="navigator.clipboard.writeText('${lat},${lng}')">
							نسخ الإحداثيات
						</button>

						<a target="_blank"
						   class="btn btn-sm btn-light-success"
						   href="${googleUrl}">
							فتح في Google Maps
						</a>
					</div>
				`;
					},

					actions: [measureThisAction]
				}

			});

			// طبقة خاصة للتحديد الثابت
			const selectionLayer = new GraphicsLayer({
				listMode: "hide"
			});
			const buildingLayer = featureLayer;
			let currentArcgisMapWhere = "1=1";
			let arcgisDateField = null;
			let originalArcgisExtent = null;

			window.addEventListener('damage-dashboard-toolbar:changed', function (event) {
				featureLayer.definitionExpression = combinedArcgisWhere(event.detail);
				selectionLayer.removeAll();
				updateArcgisFilteredCount(featureLayer.definitionExpression);
			});

			const map = new Map({
				basemap: "satellite",
				layers: [featureLayer, selectionLayer]
			});

			const view = new MapView({
				container: "viewDiv",
				map: map,
				center: [34.460987, 31.514266],
				zoom: 18
			});

			function notifyArcgisMap(message, type) {
				if (window.toastr && typeof window.toastr[type] === 'function') {
					window.toastr[type](message);
				}
			}

			function escapeArcgisValue(value) {
				return String(value).replace(/'/g, "''");
			}

			function getArcgisField(fieldName) {
				return buildingLayer.fields.find(function (field) {
					return String(field.name).toLowerCase() === fieldName.toLowerCase();
				}) || null;
			}

			function hasArcgisField(fieldName) {
				return getArcgisField(fieldName) !== null;
			}

			function resolveArcgisDateField() {
				if (arcgisDateField) {
					return arcgisDateField;
				}

				arcgisDateField = getArcgisField('end')
					|| getArcgisField('creationdate')
					|| getArcgisField('editdate');

				if (!arcgisDateField) {
					arcgisDateField = null;
				}

				return arcgisDateField;
			}

			function arcgisFieldExpression(field) {
				const fieldName = field.name;

				return String(fieldName).toLowerCase() === 'end'
					? '"' + fieldName + '"'
					: fieldName;
			}

			function arcgisDateExpression(field, operator, value) {
				const fieldExpression = arcgisFieldExpression(field);

				if (String(field.type).toLowerCase().includes('date')) {
					return fieldExpression + " " + operator + " TIMESTAMP '" + value + " 00:00:00'";
				}

				return fieldExpression + " " + operator + " '" + escapeArcgisValue(value) + "'";
			}

			function buildArcgisWhere() {
				const allowedFields = [
					'assignedto',
					'field_status',
					'building_damage_status',
					'municipalitie',
					'neighborhood'
				];
				const clauses = [];

				allowedFields.forEach(function (field) {
					const element = document.querySelector('[data-field="' + field + '"]');
					const value = element ? $(element).val() : '';

					if (value) {
						clauses.push(field + " = '" + escapeArcgisValue(value) + "'");
					}
				});

				const searchValue = (document.getElementById('arcgis_filter_search')?.value || '').trim();

				if (searchValue !== '') {
					if (/^\d+$/.test(searchValue)) {
						clauses.push('objectid = ' + parseInt(searchValue, 10));
					} else {
						clauses.push("globalid LIKE '%" + escapeArcgisValue(searchValue) + "%'");
					}
				}

				const dateField = resolveArcgisDateField();
				const fromDate = document.getElementById('arcgis_filter_from_date')?.value || '';
				const toDate = document.getElementById('arcgis_filter_to_date')?.value || '';

				if (dateField && fromDate) {
					clauses.push(arcgisDateExpression(dateField, '>=', fromDate));
				}

				if (dateField && toDate) {
					clauses.push(arcgisDateExpression(dateField, '<=', toDate));
				}

				return clauses.length ? clauses.join(' AND ') : '1=1';
			}

			function dashboardArcgisLayerDefinition(filters) {
				const clauses = [];
				const toolbarFilters = filters || getDashboardToolbarFilters();
				const dateField = resolveArcgisDateField();

				if (toolbarFilters.neighborhood) {
					clauses.push("neighborhood = '" + escapeArcgisValue(toolbarFilters.neighborhood) + "'");
				}

				if (dateField && toolbarFilters.from_date) {
					clauses.push(arcgisDateExpression(dateField, '>=', toolbarFilters.from_date));
				}

				if (dateField && toolbarFilters.to_date) {
					clauses.push(arcgisDateExpression(dateField, '<=', toolbarFilters.to_date));
				}

				return clauses.length ? clauses.join(' AND ') : '1=1';
			}

			function combinedArcgisWhere(toolbarFilters) {
				const dashboardWhere = dashboardArcgisLayerDefinition(toolbarFilters || getDashboardToolbarFilters());
				const clauses = [];

				if (dashboardWhere && dashboardWhere !== '1=1') {
					clauses.push('(' + dashboardWhere + ')');
				}

				if (currentArcgisMapWhere && currentArcgisMapWhere !== '1=1') {
					clauses.push('(' + currentArcgisMapWhere + ')');
				}

				return clauses.length ? clauses.join(' AND ') : '1=1';
			}

			function reloadArcgisDatatable() {
				if ($.fn.DataTable && $.fn.DataTable.isDataTable('#kt_table_building')) {
					$('#kt_table_building').DataTable().ajax.reload(null, false);
				}
			}

			function updateArcgisFilteredCount(whereExpression) {
				const query = buildingLayer.createQuery();
				query.where = whereExpression || '1=1';
				query.returnGeometry = false;

				return buildingLayer.queryFeatureCount(query)
					.then(function (count) {
						document.getElementById('arcgisFilterCount').textContent = count;

						return count;
					})
					.catch(function (error) {
						console.error('ArcGIS count query failed:', error);
						document.getElementById('arcgisFilterCount').textContent = '0';

						return 0;
					});
			}

			function applyArcgisFilters() {
				currentArcgisMapWhere = buildArcgisWhere();
				const whereExpression = combinedArcgisWhere();
				const query = buildingLayer.createQuery();
				query.where = whereExpression;
				query.returnGeometry = true;
				buildingLayer.definitionExpression = whereExpression;

				if (typeof clearSelectionGraphic === 'function') {
					clearSelectionGraphic();
				}

				Promise.all([
					buildingLayer.queryFeatureCount(query),
					buildingLayer.queryExtent(query)
				]).then(function (results) {
					const count = results[0];
					const extentResult = results[1];
					document.getElementById('arcgisFilterCount').textContent = count;

					if (count > 0 && extentResult.extent) {
						view.goTo(extentResult.extent.expand(1.2)).catch(function (error) {
							if (error.name !== 'AbortError') {
								console.error('GoTo filtered extent failed:', error);
							}
						});
					}

					reloadArcgisDatatable();
					notifyArcgisMap('تم تطبيق الفلترة', 'success');
				}).catch(function (error) {
					console.error('ArcGIS filter failed:', error);
					notifyArcgisMap('تعذر تطبيق فلترة الخريطة', 'error');
				});
			}

			function resetArcgisFilters() {
				$('.arcgis-map-filter-select').val(null).trigger('change');
				$('#mapFieldStatusFilter').val('').trigger('change');
				document.getElementById('arcgis_filter_search').value = '';
				document.getElementById('arcgis_filter_from_date').value = '';
				document.getElementById('arcgis_filter_to_date').value = '';
				currentArcgisMapWhere = '1=1';
				const whereExpression = combinedArcgisWhere();
				buildingLayer.definitionExpression = whereExpression;
				updateArcgisFilteredCount(whereExpression);
				reloadArcgisDatatable();

				if (typeof clearSelectionGraphic === 'function') {
					clearSelectionGraphic();
				}

				view.goTo(gazaStripExtent).catch(function (error) {
					if (error.name !== 'AbortError') {
						console.error('GoTo Gaza Strip extent failed:', error);
					}
				});
			}

			function toggleMapFullscreen(forceState) {
				const shell = document.getElementById('damageMapShell');
				const button = document.getElementById('damageMapFullscreenButton');
				const fullLabel = button.querySelector('.damage-map-fullscreen-label');
				const exitLabel = button.querySelector('.damage-map-exit-label');
				const isFullscreen = typeof forceState === 'boolean'
					? forceState
					: !shell.classList.contains('damage-map-fullscreen');

				shell.classList.toggle('damage-map-fullscreen', isFullscreen);
				document.body.classList.toggle('damage-map-fullscreen-active', isFullscreen);
				fullLabel.classList.toggle('d-none', isFullscreen);
				exitLabel.classList.toggle('d-none', !isFullscreen);
				localStorage.setItem('damageAssessment.mapFullscreen', isFullscreen ? '1' : '0');

				setTimeout(function () {
					view.resize();
				}, 150);
			}

			function loadArcgisSelectOptions(select) {
				const field = select.data('field');
				const url = new URL(arcgisOptionsUrl, window.location.origin);

				url.searchParams.set('field', field);
				select.prop('disabled', true);

				fetch(url.toString(), {
					headers: {
						'Accept': 'application/json'
					},
					credentials: 'same-origin'
				})
					.then(function (response) {
						if (!response.ok) {
							throw new Error('Options request failed with status ' + response.status);
						}

						return response.json();
					})
					.then(function (data) {
						const options = Array.isArray(data) ? data : (data.results || []);

						select.empty().append(new Option('', '', false, false));
						options.forEach(function (option) {
							select.append(new Option(option.text, option.id, false, false));
						});
						select.prop('disabled', false).trigger('change');
					})
					.catch(function (error) {
						console.error('ArcGIS options failed for ' + field + ':', error);
						select.prop('disabled', false);
					});
			}

			function initializeArcgisFilterSelects() {
				$('.arcgis-map-filter-select').each(function () {
					const select = $(this);

					if ($.fn.select2) {
						select.select2({
							allowClear: true,
							dropdownParent: $('#arcgisMapFilterPanel'),
							placeholder: select.data('placeholder') || '',
							width: '100%'
						});
					}

					loadArcgisSelectOptions(select);
				});
			}

			function initializeArcgisMapControls() {
				document.getElementById('arcgisFilterApply').addEventListener('click', applyArcgisFilters);
				document.getElementById('arcgisFilterReset').addEventListener('click', resetArcgisFilters);
				document.getElementById('damageMapFullscreenButton').addEventListener('click', function (event) {
					event.preventDefault();
					toggleMapFullscreen();
				});
				document.getElementById('arcgisMapFilterPanel').querySelector('.card-header').addEventListener('click', function (event) {
					if (event.target.closest('#arcgisMapFilterToggle') || !event.target.closest('button')) {
						document.getElementById('arcgisMapFilterPanel').classList.toggle('is-collapsed');
					}
				});
				document.getElementById('arcgisMapFilterToggle').addEventListener('click', function (event) {
					event.preventDefault();
					event.stopPropagation();
					document.getElementById('arcgisMapFilterPanel').classList.toggle('is-collapsed');
				});

				initializeArcgisFilterSelects();

				if (localStorage.getItem('damageAssessment.mapFullscreen') === '1') {
					toggleMapFullscreen(true);
				}

				view.whenLayerView(buildingLayer)
					.then(function () {
						return buildingLayer.load();
					})
					.then(function () {
						buildingLayer.definitionExpression = combinedArcgisWhere();
						const query = buildingLayer.createQuery();
						query.where = buildingLayer.definitionExpression || '1=1';
						query.returnGeometry = true;

						return buildingLayer.queryExtent(query);
					})
					.then(function (extentResult) {
						originalArcgisExtent = extentResult.extent || null;
						updateArcgisFilteredCount(buildingLayer.definitionExpression || '1=1');
					})
					.catch(function (error) {
						console.error('ArcGIS map controls initialization failed:', error);
					});
			}

			initializeArcgisMapControls();

			view.popup.dockEnabled = true;
			view.popup.dockOptions = {
				position: "top-left",
				breakpoint: false,
				buttonEnabled: false
			};

			view.popup.collapseEnabled = false;
			view.popup.visibleElements = {
				closeButton: true
			};
			const basemapToggle = new BasemapToggle({
				view: view,
				nextBasemap: "osm"
			});

			view.ui.add(basemapToggle, "top-left");

			// Toggle لإخفاء / إظهار Symbology على الخريطة نفسها
			const symbologyToggleBtn = document.createElement("button");
			symbologyToggleBtn.type = "button";
			symbologyToggleBtn.className = "esri-widget esri-widget--button esri-interactive";
			symbologyToggleBtn.title = "إخفاء الرموز";
			symbologyToggleBtn.innerHTML = `<span style="font-size:16px; font-weight:bold;">S</span>`;

			let symbologyVisible = true; // ظاهر افتراضيًا

			const noSymbologyRenderer = {
				type: "simple",
				symbol: {
					type: "simple-fill",
					color: [0, 0, 0, 0],
					outline: {
						color: [255, 255, 255, 1],
						width: 1
					}
				}
			};

			symbologyToggleBtn.addEventListener("click", function () {
				symbologyVisible = !symbologyVisible;

				featureLayer.renderer = symbologyVisible
					? damageRenderer
					: noSymbologyRenderer;

				symbologyToggleBtn.title = symbologyVisible ? "إخفاء الرموز" : "إظهار الرموز";
				symbologyToggleBtn.style.backgroundColor = symbologyVisible ? "" : "#f8d7da";
			});

			// فوق زر الـ Basemap
			view.ui.add(symbologyToggleBtn, {
				position: "top-left",
				index: 0
			});



			// وضع زر Symbology فوق زر Basemap بدون حذف الكود القديم
			view.ui.add(symbologyToggleBtn, {
				position: "top-left",
				index: 0
			});

			const symbologyDiv = document.createElement("div");
			symbologyDiv.innerHTML = ""; // فارغ لأنك لا تحتاجه فعليًا
			const symbologyExpand = new Expand({
				view: view,
				content: symbologyDiv,
				expanded: false
			});

			const legend = new Legend({
				view: view,
				container: "externalLegendDiv",
				layerInfos: [{
					layer: featureLayer,
					title: "Building Damage Status"
				}]
			});

			const searchWidget = new Search({
				view: view,
				allPlaceholder: @json(__('ui.damage_dashboard.search')),
				includeDefaultSources: false,
				sources: [{
					layer: featureLayer,
					searchFields: ["building_name", "objectid"],
					displayField: "building_name",
					exactMatch: false,
					outFields: ["*"],
					name: "Buildings",
					placeholder: @json(__('ui.damage_dashboard.search_building_name_or_number'))
				}]
			});

			view.ui.add(searchWidget, {
				position: "top-right"
			});

			const scaleBar = new ScaleBar({
				view: view,
				unit: "metric"
			});

			view.ui.add(scaleBar, {
				position: "bottom-left"
			});


			let selectedObjectId = null;
			let selectedFeature = null;
			let selectedGraphic = null;

			function getObjectId(feature) {
				return feature.attributes.OBJECTID || feature.attributes.objectid;
			}

			function clearSelectionGraphic() {
				selectionLayer.removeAll();
				selectedGraphic = null;
			}

			function getLatLong(feature) {
				if (!feature.geometry) return null;

				let point = feature.geometry.extent
					? feature.geometry.extent.center
					: feature.geometry;

				if (point.spatialReference?.isWebMercator) {
					point = webMercatorUtils.webMercatorToGeographic(point);
				}

				return {
					lat: point.latitude ?? point.y,
					lng: point.longitude ?? point.x
				};
			}

			function getSelectionSymbol(geometryType) {
				if (geometryType === "polygon" || geometryType === "extent") {
					return {
						type: "simple-fill",
						color: [0, 0, 0, 0],
						outline: {
							color: [0, 255, 255, 1],
							width: 3
						}
					};
				}

				if (geometryType === "polyline") {
					return {
						type: "simple-line",
						color: [0, 255, 255, 1],
						width: 4
					};
				}

				return {
					type: "simple-marker",
					style: "circle",
					size: 12,
					color: [0, 255, 255, 0.25],
					outline: {
						color: [0, 255, 255, 1],
						width: 2
					}
				};
			}

			function drawPersistentSelection(feature) {
				if (!feature || !feature.geometry) return;

				clearSelectionGraphic();

				selectedGraphic = new Graphic({
					geometry: feature.geometry.clone ? feature.geometry.clone() : feature.geometry,
					attributes: feature.attributes,
					symbol: getSelectionSymbol(feature.geometry.type)
				});

				selectionLayer.add(selectedGraphic);
			}


			function selectFeature(feature, doZoom = true) {
				selectedFeature = feature;
				selectedObjectId = getObjectId(feature);

				drawPersistentSelection(feature);

				view.popup.open({
					features: [feature],
					location: feature.geometry.extent
						? feature.geometry.extent.center
						: feature.geometry
				});

				if (doZoom) {
					const zoomTarget = feature.geometry.extent
						? feature.geometry.extent.expand(1.5)
						: { target: feature.geometry, zoom: 20 };

					view.goTo(zoomTarget, {
						duration: 2000,
						easing: "in-out-expo"
					}).catch(function (error) {
						if (error.name !== "AbortError") {
							console.error("GoTo failed:", error);
						}
					});
				}
			}

			function zoomToFeatureByGlobalId(globalId) {
				const query = featureLayer.createQuery();
				query.where = "GLOBALID = '" + globalId + "'";
				query.returnGeometry = true;
				query.outFields = ["*"];

				featureLayer.queryFeatures(query).then(function (results) {
					if (!results.features.length) {
						console.warn("Feature not found:", globalId);
						return;
					}

					const feature = results.features[0];
					selectFeature(feature, true);
				}).catch(function (error) {
					console.error("Query Error:", error);
				});
			}

			view.on("click", function (event) {
				view.hitTest(event).then(function (response) {
					const result = response.results.find(function (r) {
						return r.graphic && r.graphic.layer === featureLayer;
					});

					if (result) {
						selectFeature(result.graphic, false);
					} else {
						clearSelectionGraphic();
						view.popup.close();
					}
				});
			});


			// عند الضغط من الجدول
			$('#kt_table_building tbody').on('click', 'tr', function () {
				var globalid = this.id;
				if (!globalid) return;

				zoomToFeatureByGlobalId(globalid);
			});

		});


		function dashboardToolbarDate(period) {
			const date = new Date();

			if (period === 'week') {
				date.setDate(date.getDate() - 6);
			}

			if (period === 'day' || period === 'yesterday') {
				date.setDate(date.getDate() - 1);
			}

			return date.toISOString().slice(0, 10);
		}

		function getDashboardToolbarFilters() {
			const activePeriod = document.querySelector('.dashboard-toolbar-period.active');
			const period = activePeriod ? activePeriod.dataset.period : 'day';
			const dateRangeInput = document.getElementById('dashboard_toolbar_date_range');
			const dateRangeParts = dateRangeInput && dateRangeInput.value
				? dateRangeInput.value.split(/\s+(?:-|to)\s+/).map(function (value) {
					return value.trim();
				})
				: [];
			const fromDate = period === 'all' ? '' : (dateRangeParts[0] || dashboardToolbarDate(period));
			const toDate = period === 'all' ? '' : (dateRangeParts[1] || (period === 'day' ? fromDate : new Date().toISOString().slice(0, 10)));

			return {
				period: period,
				from_date: fromDate,
				to_date: toDate,
				neighborhood: $('#dashboard_toolbar_neighborhood').val() || ''
			};
		}

		function addDashboardToolbarFilters(data) {
			const filters = getDashboardToolbarFilters();

			data.period = filters.period;
			data.from_date = filters.from_date;
			data.to_date = filters.to_date;
			data.neighborhood = filters.neighborhood;
		}

		function notifyDashboardToolbarChanged() {
			window.dispatchEvent(new CustomEvent('damage-dashboard-toolbar:changed', {
				detail: getDashboardToolbarFilters()
			}));
		}

		function reloadDashboardWithToolbarFilters() {
			const filters = getDashboardToolbarFilters();
			const url = new URL(window.location.href);

			url.searchParams.set('period', filters.period);
			if (filters.from_date) {
				url.searchParams.set('from_date', filters.from_date);
			} else {
				url.searchParams.delete('from_date');
			}

			if (filters.to_date) {
				url.searchParams.set('to_date', filters.to_date);
			} else {
				url.searchParams.delete('to_date');
			}

			if (filters.neighborhood) {
				url.searchParams.set('neighborhood', filters.neighborhood);
			} else {
				url.searchParams.delete('neighborhood');
			}

			window.location.href = url.toString();
		}

		function dashboardLayerDefinition(filters, dateColumn) {
			const clauses = [];
			const filterDateColumn = dateColumn || 'creationdate';

			if (filters.neighborhood) {
				clauses.push("neighborhood = '" + String(filters.neighborhood).replace(/'/g, "''") + "'");
			}

			if (filters.from_date) {
				clauses.push(filterDateColumn + " >= DATE '" + filters.from_date + "'");
			}

			if (filters.to_date) {
				clauses.push(filterDateColumn + " <= DATE '" + filters.to_date + "'");
			}

			return clauses.length ? clauses.join(' AND ') : '1=1';
		}

		KTUtil.onDOMContentLoaded(function () {
			if (typeof flatpickr !== 'undefined') {
				flatpickr('#dashboard_toolbar_date_range', {
					mode: 'range',
					dateFormat: 'Y-m-d',
					locale: {
						rangeSeparator: ' - '
					},
					defaultDate: @json($dashboardFilters['startDate'] && $dashboardFilters['endDate'] ? [$dashboardFilters['startDate'], $dashboardFilters['endDate']] : null),
					onClose: function (selectedDates, dateStr, instance) {
						if (selectedDates.length === 2) {
							document.querySelectorAll('.dashboard-toolbar-period').forEach(function (item) {
								item.classList.remove('active');
							});

							instance.input.value = instance.formatDate(selectedDates[0], 'Y-m-d') + ' - ' + instance.formatDate(selectedDates[1], 'Y-m-d');
							notifyDashboardToolbarChanged();
							reloadDashboardWithToolbarFilters();
						}
					}
				});
			}

			$('#dashboard_toolbar_neighborhood').on('change', function () {
				notifyDashboardToolbarChanged();
				reloadDashboardWithToolbarFilters();
			});

			document.querySelectorAll('.dashboard-toolbar-period').forEach(function (button) {
				button.addEventListener('click', function () {
					document.querySelectorAll('.dashboard-toolbar-period').forEach(function (item) {
						item.classList.remove('active');
					});

					button.classList.add('active');
					document.getElementById('dashboard_toolbar_date_range').value = button.dataset.period === 'all'
						? ''
						: dashboardToolbarDate(button.dataset.period) + ' - ' + (button.dataset.period === 'day' ? dashboardToolbarDate(button.dataset.period) : new Date().toISOString().slice(0, 10));

					notifyDashboardToolbarChanged();
					reloadDashboardWithToolbarFilters();
				});
			});
		});

		var KTEngineersList = function () {
			var table = document.getElementById('kt_table_building');
			var datatable;

			var initEngineerTable = function () {
				datatable = $(table).DataTable({
					serverSide: true,
					ajax: {
						url: "{{ route('housing-units-map') }}",
						data: function (d) {
							d.hompage_building = 1;
							addDashboardToolbarFilters(d);
						},
						type: "GET"
					},
					dom:
						"<'table-responsive'tr>" +
						"<'row'<'col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start'i>" +
						"<'col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end'p>>",
					info: false,
					order: [],
					pageLength: 10,
					lengthChange: false,
					processing: true,
					columns: [
						{ data: 'neighborhood', name: 'neighborhood', searchable: true },
						{ data: 'objectid', name: 'objectid', searchable: true },
						{ data: 'building_name', name: 'building_name', searchable: true },
						{ data: 'full_name1', name: 'full_name1', searchable: true },
						{ data: 'unit_damage_status', name: 'unit_damage_status', searchable: true }
					],
					createdRow: function (row, data, index) {
						$(row).css('cursor', 'pointer');
						if (data.building_globalid) {
							$(row).attr('id', data.building_globalid);
						}
					}
				});

				datatable.on('draw', function () {
					KTMenu.createInstances();
				});

				window.addEventListener('damage-dashboard-toolbar:changed', function () {
					datatable.ajax.reload();
				});
			};

			var handleSearchDatatable = function () {
				const filterSearch = document.querySelector('[data-kt-engineer-table-filter="search"]');

				if (!filterSearch) return;

				filterSearch.addEventListener('keydown', function (e) {
					if (e.which == 13) {
						e.preventDefault();
						datatable.search(e.target.value).draw();
					}
				});
			};

			return {
				init: function () {
					if (!table) {
						return;
					}

					initEngineerTable();
					handleSearchDatatable();
				}
			};
		}();

		KTUtil.onDOMContentLoaded(function () {
			KTEngineersList.init();
		});

		// =========================
		// Auto Update Charts
		// =========================
		function updateCharts(newBuildingData, newUnitData) {
			if (buildingsChart) {
				buildingsChart.updateSeries([
					newBuildingData.fully_damaged ?? 0,
					newBuildingData.partially_damaged ?? 0,
					newBuildingData.committee_review ?? 0,
					newBuildingData.security_unsafe ?? 0
				]);
			}

			if (housingChart) {
				housingChart.updateSeries([
					newUnitData.fully_damaged ?? 0,
					newUnitData.partially_damaged ?? 0,
					newUnitData.committee_review ?? 0
				]);
			}
		}

		const latestStatsUrl = @json(route('damageAssessment.latest-stats', [], false));

		setInterval(function () {
			fetch(latestStatsUrl, {
				headers: {
					'Accept': 'application/json'
				},
				credentials: 'same-origin'
			})
				.then(response => {
					if (!response.ok) {
						throw new Error('Stats request failed with status ' + response.status);
					}

					return response.json();
				})
				.then(data => {
					updateCharts(data.buildingStats, data.unitStats);
				})
				.catch(error => {
					console.error('Failed to update charts:', error);
				});
		}, 300000);
	</script>

	<script>
		(function () {
			const publicBuildingLayerUrl = @json($publicBuildingLayerUrl);
			const roadFacilityLayerUrl = @json($roadFacilityLayerUrl);
			const publicBuildingShowUrlTemplate = @json(route('public-buildings.show', ['publicBuilding' => '__GLOBALID__']));
			const roadFacilityShowUrlTemplate = @json(route('road-facilities.show', ['roadFacility' => '__GLOBALID__']));
			const arcgisToken = @json($token);

			function initRemoteMapTable(config) {
				const tableElement = document.getElementById(config.tableId);

				if (!tableElement) {
					return;
				}

				const datatable = $(tableElement).DataTable({
					serverSide: true,
					ajax: {
						url: config.ajaxUrl,
						type: 'GET',
						data: function (data) {
							addDashboardToolbarFilters(data);
						}
					},
					dom:
						"<'table-responsive'tr>" +
						"<'row'<'col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start'i>" +
						"<'col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end'p>>",
					info: false,
					order: [],
					pageLength: 10,
					lengthChange: false,
					processing: true,
					columns: config.columns,
					createdRow: function (row, data) {
						$(row).css('cursor', 'pointer');
						$(row).attr('data-objectid', data.objectid);
					}
				});

				datatable.on('draw', function () {
					KTMenu.createInstances();
				});

				window.addEventListener('damage-dashboard-toolbar:changed', function () {
					datatable.ajax.reload();
				});

				const filterSearch = document.querySelector(config.searchSelector);

				if (filterSearch) {
					filterSearch.addEventListener('keydown', function (event) {
						if (event.which === 13) {
							event.preventDefault();
							datatable.search(event.target.value).draw();
						}
					});
				}
			}

			require([
				'esri/Map',
				'esri/views/MapView',
				'esri/layers/FeatureLayer',
				'esri/layers/GraphicsLayer',
				'esri/Graphic',
				'esri/identity/IdentityManager',
				'esri/widgets/BasemapToggle',
				'esri/widgets/Legend',
				'esri/widgets/Search',
				'esri/widgets/ScaleBar'
			], function (
				Map,
				MapView,
				FeatureLayer,
				GraphicsLayer,
				Graphic,
				esriId,
				BasemapToggle,
				Legend,
				Search,
				ScaleBar
			) {
				function buildPopupTitle(event, nameField, urlTemplate) {
					const attrs = event.graphic.attributes;
					const recordName = attrs[nameField] || attrs.NAME || 'Record';
					const globalId = attrs.globalid || attrs.GlobalID || attrs.GLOBALID;
					const detailsUrl = globalId ? urlTemplate.replace('__GLOBALID__', globalId) : '#';

					return `${recordName} <a target="_blank" style="color:red;" href="${detailsUrl}">Open</a>`;
				}

				function createSelectionSymbol(geometryType) {
					if (geometryType === 'polygon' || geometryType === 'extent') {
						return {
							type: 'simple-fill',
							color: [0, 0, 0, 0],
							outline: {
								color: [0, 255, 255, 1],
								width: 3
							}
						};
					}

					if (geometryType === 'polyline') {
						return {
							type: 'simple-line',
							color: [0, 255, 255, 1],
							width: 4
						};
					}

					return {
						type: 'simple-marker',
						style: 'circle',
						size: 12,
						color: [0, 255, 255, 0.25],
						outline: {
							color: [0, 255, 255, 1],
							width: 2
						}
					};
				}

				function initFeatureMap(config) {
					if (!config.layerUrl) {
						return;
					}

					esriId.registerToken({
						server: config.layerUrl,
						token: arcgisToken,
						expires: Date.now() + (60 * 60 * 1000)
					});

					const featureLayer = new FeatureLayer({
						url: config.layerUrl,
						renderer: config.renderer,
						outFields: ['*'],
						minScale: 0,
						maxScale: 0,
						definitionExpression: dashboardLayerDefinition(getDashboardToolbarFilters()),
						popupTemplate: {
							title: function (event) {
								return buildPopupTitle(event, config.nameField, config.showUrlTemplate);
							},
							content: [{
								type: 'fields',
								fieldInfos: config.fieldInfos
							}]
						}
					});

					const selectionLayer = new GraphicsLayer({ listMode: 'hide' });
					const map = new Map({
						basemap: 'satellite',
						layers: [featureLayer, selectionLayer]
					});

					const view = new MapView({
						container: config.mapContainer,
						map: map,
						center: [34.460987, 31.514266],
						zoom: 14
					});

					window.addEventListener('damage-dashboard-toolbar:changed', function (event) {
						featureLayer.definitionExpression = dashboardLayerDefinition(event.detail);
						selectionLayer.removeAll();
					});

					view.popup.dockEnabled = true;
					view.popup.dockOptions = {
						position: 'top-left',
						breakpoint: false,
						buttonEnabled: false
					};

					view.ui.add(new BasemapToggle({ view: view, nextBasemap: 'osm' }), 'top-left');
					view.ui.add(new Legend({
						view: view,
						container: config.legendContainer,
						layerInfos: [{
							layer: featureLayer,
							title: config.legendTitle
						}]
					}), 'bottom-right');
					view.ui.add(new Search({
						view: view,
						allPlaceholder: 'Search',
						includeDefaultSources: false,
						sources: [{
							layer: featureLayer,
							searchFields: config.searchFields,
							displayField: config.nameField,
							exactMatch: false,
							outFields: ['*'],
							name: config.legendTitle,
							placeholder: config.searchPlaceholder
						}]
					}), { position: 'top-right' });
					view.ui.add(new ScaleBar({ view: view, unit: 'metric' }), { position: 'bottom-left' });

					function drawPersistentSelection(feature) {
						selectionLayer.removeAll();

						selectionLayer.add(new Graphic({
							geometry: feature.geometry.clone ? feature.geometry.clone() : feature.geometry,
							attributes: feature.attributes,
							symbol: createSelectionSymbol(feature.geometry.type)
						}));
					}

					function selectFeature(feature, doZoom) {
						drawPersistentSelection(feature);

						view.popup.open({
							features: [feature],
							location: feature.geometry.extent ? feature.geometry.extent.center : feature.geometry
						});

						if (doZoom) {
							const zoomTarget = feature.geometry.extent
								? feature.geometry.extent.expand(1.5)
								: { target: feature.geometry, zoom: 18 };

							view.goTo(zoomTarget, {
								duration: 2000,
								easing: 'in-out-expo'
							}).catch(function (error) {
								if (error.name !== 'AbortError') {
									console.error('GoTo failed:', error);
								}
							});
						}
					}

					function zoomToFeatureByObjectId(objectId) {
						const parsedObjectId = parseInt(objectId, 10);

						if (Number.isNaN(parsedObjectId)) {
							return;
						}

						const query = featureLayer.createQuery();
						query.where = `OBJECTID = ${parsedObjectId}`;
						query.returnGeometry = true;
						query.outFields = ['*'];

						featureLayer.queryFeatures(query).then(function (results) {
							if (!results.features.length) {
								return;
							}

							selectFeature(results.features[0], true);
						});
					}

					view.on('click', function (event) {
						view.hitTest(event).then(function (response) {
							const result = response.results.find(function (item) {
								return item.graphic && item.graphic.layer === featureLayer;
							});

							if (result) {
								selectFeature(result.graphic, false);
							} else {
								selectionLayer.removeAll();
							}
						});
					});

					$('#' + config.tableId + ' tbody').on('click', 'tr', function () {
						const objectId = $(this).attr('data-objectid');

						if (objectId) {
							zoomToFeatureByObjectId(objectId);
						}
					});
				}

				initFeatureMap({
					mapContainer: 'publicBuildingViewDiv',
					legendContainer: 'publicBuildingLegendDiv',
					tableId: 'kt_table_public_building',
					layerUrl: publicBuildingLayerUrl,
					renderer: {
						type: 'unique-value',
						field: 'building_damage_status',
						defaultSymbol: {
							type: 'simple-fill',
							color: [128, 128, 128, 0.9],
							outline: { color: 'white', width: 1 }
						},
						uniqueValueInfos: [
							{ value: 'committee_review', symbol: { type: 'simple-fill', color: [255, 255, 0, 0.5], outline: { color: 'black', width: 1 } }, label: @json(__('ui.damage_dashboard.committee_review')) },
							{ value: 'fully_damaged', symbol: { type: 'simple-fill', color: [255, 0, 0, 0.5], outline: { color: 'white', width: 2 } }, label: @json(__('ui.damage_dashboard.fully_damaged')) },
							{ value: 'partially_damaged', symbol: { type: 'simple-fill', color: [0, 255, 0, 0.5], outline: { color: 'white', width: 1 } }, label: @json(__('ui.damage_dashboard.partially_damaged')) }
						]
					},
					legendTitle: @json(__('multilingual.damage_dashboard.public_building_damage_status')),
					nameField: 'building_name',
					showUrlTemplate: publicBuildingShowUrlTemplate,
					fieldInfos: [
						{ fieldName: 'objectid', label: @json(__('multilingual.damage_dashboard.object_id')) },
						{ fieldName: 'building_name', label: @json(__('ui.damage_dashboard.building_name')) },
						{ fieldName: 'municipalitie', label: @json(__('multilingual.damage_dashboard.municipality')) },
						{ fieldName: 'neighborhood', label: @json(__('multilingual.damage_dashboard.neighborhood')) },
						{ fieldName: 'building_damage_status', label: @json(__('multilingual.damage_dashboard.damage_status')) }
					],
					searchFields: ['building_name', 'objectid', 'municipalitie', 'neighborhood'],
					searchPlaceholder: @json(__('multilingual.damage_dashboard.search_public_buildings'))
				});

				initFeatureMap({
					mapContainer: 'roadFacilityViewDiv',
					legendContainer: 'roadFacilityLegendDiv',
					tableId: 'kt_table_road_facility',
					layerUrl: roadFacilityLayerUrl,
					renderer: {
						type: 'unique-value',
						field: 'road_damage_level',
						defaultSymbol: {
							type: 'simple-line',
							color: [128, 128, 128, 1],
							width: 3
						},
						uniqueValueInfos: [
							{ value: 'destroyed', symbol: { type: 'simple-line', color: [255, 0, 0, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.destroyed')) },
							{ value: 'severe', symbol: { type: 'simple-line', color: [255, 94, 0, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.severe')) },
							{ value: 'moderate', symbol: { type: 'simple-line', color: [255, 193, 7, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.moderate')) },
							{ value: 'minor', symbol: { type: 'simple-line', color: [40, 167, 69, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.minor')) },
							{ value: 'No_Damage', symbol: { type: 'simple-line', color: [0, 123, 255, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.no_damage')) }
						]
					},
					legendTitle: @json(__('multilingual.damage_dashboard.road_damage_level')),
					nameField: 'str_name',
					showUrlTemplate: roadFacilityShowUrlTemplate,
					fieldInfos: [
						{ fieldName: 'objectid', label: @json(__('multilingual.damage_dashboard.object_id')) },
						{ fieldName: 'str_name', label: @json(__('multilingual.damage_dashboard.road_name')) },
						{ fieldName: 'municipalitie', label: @json(__('multilingual.damage_dashboard.municipality')) },
						{ fieldName: 'neighborhood', label: @json(__('multilingual.damage_dashboard.neighborhood')) },
						{ fieldName: 'road_damage_level', label: @json(__('multilingual.damage_dashboard.damage_level')) }
					],
					searchFields: ['str_name', 'objectid', 'municipalitie', 'neighborhood'],
					searchPlaceholder: @json(__('multilingual.damage_dashboard.search_road_facilities'))
				});
			});

			KTUtil.onDOMContentLoaded(function () {
				initRemoteMapTable({
					tableId: 'kt_table_public_building',
					ajaxUrl: @json(route('public-buildings-map')),
					searchSelector: '[data-kt-public-building-map-filter="search"]',
					columns: [
						{ data: 'municipalitie', name: 'municipalitie', searchable: true },
						{ data: 'neighborhood', name: 'neighborhood', searchable: true },
						{ data: 'objectid', name: 'objectid', searchable: true },
						{ data: 'building_name', name: 'building_name', searchable: true },
						{ data: 'building_damage_status', name: 'building_damage_status', searchable: true }
					]
				});

				initRemoteMapTable({
					tableId: 'kt_table_road_facility',
					ajaxUrl: @json(route('road-facilities-map')),
					searchSelector: '[data-kt-road-facility-map-filter="search"]',
					columns: [
						{ data: 'municipalitie', name: 'municipalitie', searchable: true },
						{ data: 'neighborhood', name: 'neighborhood', searchable: true },
						{ data: 'objectid', name: 'objectid', searchable: true },
						{ data: 'str_name', name: 'str_name', searchable: true },
						{ data: 'road_damage_level', name: 'road_damage_level', searchable: true }
					]
				});
			});
		})();
	</script>
@endsection
