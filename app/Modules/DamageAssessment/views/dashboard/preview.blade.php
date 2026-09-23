@extends('layouts.app')

@section('title', 'معاينة الصفحة الرئيسية')
@section('pageName', 'معاينة الصفحة الرئيسية')

@section('content')
	@php
		$summaryCards = [
			[
				'title' => 'المباني',
				'subtitle' => 'مباني تم تقييمها',
				'total' => '18,420',
				'icon' => 'ki-home',
				'color' => '#ad3d3d',
				'items' => [
					['label' => 'ضرر كلي', 'value' => '4,218', 'class' => 'danger'],
					['label' => 'ضرر جزئي', 'value' => '8,536', 'class' => 'warning'],
					['label' => 'لجنة فنية حالية', 'value' => '742', 'class' => 'primary'],
					['label' => 'لجان فنية سابقة', 'value' => '311', 'class' => 'info'],
					['label' => 'يوجد عائق', 'value' => '196', 'class' => 'dark'],
				],
			],
			[
				'title' => 'الوحدات السكانية',
				'subtitle' => 'إجمالي الوحدات السكانية',
				'total' => '67,500',
				'icon' => 'ki-home-2',
				'color' => '#5f9867',
				'items' => [
					['label' => 'ضرر كلي', 'value' => '12,804', 'class' => 'danger'],
					['label' => 'ضرر جزئي', 'value' => '24,118', 'class' => 'warning'],
					['label' => 'لجنة فنية حالية', 'value' => '1,294', 'class' => 'primary'],
					['label' => 'مناسبة للسكن', 'value' => '7,981', 'class' => 'success'],
					['label' => 'متأثرة بالحريق', 'value' => '543', 'class' => 'dark'],
				],
			],
			[
				'title' => 'منظمات المجتمع المدني',
				'subtitle' => 'إجمالي استبيانات المنظمات',
				'total' => '1,086',
				'icon' => 'ki-people',
				'color' => '#7239ea',
				'items' => [
					['label' => 'مكتمل', 'value' => '934', 'class' => 'success'],
					['label' => 'متضررة', 'value' => '618', 'class' => 'danger'],
					['label' => 'المنظمات', 'value' => '412', 'class' => 'primary'],
					['label' => 'بدون وحدات', 'value' => '73', 'class' => 'warning'],
					['label' => 'تعيق التقييم', 'value' => '26', 'class' => 'dark'],
				],
			],
			[
				'title' => 'المباني العامة',
				'subtitle' => 'إجمالي المباني العامة',
				'total' => '642',
				'icon' => 'ki-office-bag',
				'color' => '#009ef7',
				'items' => [
					['label' => 'متضررة', 'value' => '338', 'class' => 'danger'],
					['label' => 'الوحدات', 'value' => '1,227', 'class' => 'success'],
					['label' => 'البلديات', 'value' => '17', 'class' => 'primary'],
					['label' => 'مشغولة', 'value' => '91', 'class' => 'warning'],
					['label' => 'ذخائر', 'value' => '13', 'class' => 'dark'],
				],
			],
			[
				'title' => 'الطرق',
				'subtitle' => 'إجمالي الطرق',
				'total' => '1,473',
				'icon' => 'ki-map',
				'color' => '#3f4254',
				'items' => [
					['label' => 'مقيّمة', 'value' => '884', 'class' => 'danger'],
					['label' => 'تعيق التقييم', 'value' => '121', 'class' => 'warning'],
					['label' => 'طول الشوارع', 'value' => '238.4 كم', 'class' => 'success'],
					['label' => 'جدول الكميات', 'value' => '3,940', 'class' => 'primary'],
					['label' => 'الحفر', 'value' => '219', 'class' => 'dark'],
				],
			],
		];
	@endphp

	<link rel="stylesheet" href="https://js.arcgis.com/4.22/esri/themes/light/main.css">

	<style>
		.damage-dashboard-preview {
			--preview-radius: .85rem;
			--preview-muted: #7e8299;
		}

		.damage-dashboard-preview .preview-toolbar {
			background: var(--bs-body-bg);
			border: 1px solid var(--bs-gray-200);
			border-radius: var(--preview-radius);
			box-shadow: 0 6px 18px rgba(15, 23, 42, .04);
		}

		.damage-dashboard-preview .preview-summary-card {
			overflow: hidden;
		}

		.damage-dashboard-preview .preview-summary-header {
			min-height: 175px;
			background: var(--preview-card-color);
		}

		.damage-dashboard-preview .preview-floating-body {
			margin: -3.25rem 1.15rem 1.15rem;
			position: relative;
			z-index: 1;
		}

		.damage-dashboard-preview .preview-map {
			height: 560px;
			background: var(--bs-gray-100);
			border-radius: var(--preview-radius);
			position: relative;
			overflow: hidden;
		}

		.damage-dashboard-preview .preview-map .esri-view-root {
			border-radius: var(--preview-radius);
		}

		.damage-dashboard-preview .preview-map-loading {
			position: absolute;
			inset: 0;
			z-index: 2;
			display: flex;
			align-items: center;
			justify-content: center;
			background: rgba(245, 248, 250, .72);
			backdrop-filter: blur(2px);
		}

		.damage-dashboard-preview .preview-gis-source {
			border: 1px dashed var(--bs-gray-300);
			border-radius: var(--preview-radius);
		}

		.damage-dashboard-preview .preview-layer-button.active {
			background: var(--bs-primary);
			color: #fff;
		}

		.damage-dashboard-preview .esri-popup {
			z-index: 10;
		}

		.damage-dashboard-preview .preview-alert-card {
			border-inline-start: 4px solid var(--preview-alert-color);
		}

		.damage-dashboard-preview .preview-command-mode {
			border: 1px solid var(--bs-gray-200);
			border-radius: 999px;
			background: var(--bs-gray-100);
			padding: .35rem;
		}

		.damage-dashboard-preview .preview-filter-pill {
			border: 1px solid var(--bs-gray-300);
			border-radius: 999px;
			background: var(--bs-body-bg);
			color: var(--bs-gray-700);
		}

		.damage-dashboard-preview .preview-map-card {
			border: 1px solid rgba(0, 158, 247, .2);
			box-shadow: 0 16px 42px rgba(15, 23, 42, .07);
		}

		.damage-dashboard-preview .preview-map-insight {
			background: linear-gradient(135deg, rgba(0, 158, 247, .1), rgba(80, 205, 137, .1));
			border-radius: var(--preview-radius);
		}

		@media (min-width: 1400px) {
			.damage-dashboard-preview .preview-summary-col {
				flex: 0 0 20%;
				max-width: 20%;
			}
		}
	</style>

	<div class="damage-dashboard-preview">
		<div class="preview-toolbar p-5 mb-6">
			<div class="d-flex flex-column flex-xxl-row align-items-xxl-center justify-content-between gap-5">
				<div>
					<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
						<div class="badge badge-light-primary fw-bold">Command Center Preview</div>
						<div class="badge badge-light-success fw-bold">آخر تحديث: اليوم 10:45 صباحاً</div>
					</div>
					<h2 class="fw-bold text-gray-900 mb-1">معاينة الصفحة الرئيسية حسب تحليل النظام</h2>
					<div class="text-muted fw-semibold">ملخص تشغيلي واحد يجمع مؤشرات التقييم مع خريطة GIS للمباني، الطرق، منظمات المجتمع المدني، والمباني العامة.</div>
				</div>

				<div class="d-flex flex-wrap align-items-center gap-3">
					<div class="preview-command-mode d-flex flex-wrap gap-1" role="group" aria-label="أوضاع عرض الصفحة">
						<button type="button" class="btn btn-sm btn-primary rounded-pill">نظرة عامة</button>
						<button type="button" class="btn btn-sm btn-light rounded-pill">المخاطر</button>
						<button type="button" class="btn btn-sm btn-light rounded-pill">الإنتاجية</button>
						<button type="button" class="btn btn-sm btn-light rounded-pill">GIS</button>
					</div>
					<select class="form-select form-select-sm w-175px">
						<option>كل المحافظات</option>
						<option>غزة</option>
						<option>خانيونس</option>
					</select>
					<select class="form-select form-select-sm w-175px">
						<option>كل الأحياء</option>
						<option>الرمال</option>
						<option>المحطة</option>
					</select>
					<input class="form-control form-control-sm w-225px" value="2026-09-01 - 2026-09-22" readonly>
				</div>
			</div>
			<div class="d-flex flex-wrap align-items-center gap-2 mt-5" aria-label="الفلاتر النشطة">
				<span class="preview-filter-pill px-4 py-2 fw-semibold fs-7">كل المحافظات</span>
				<span class="preview-filter-pill px-4 py-2 fw-semibold fs-7">كل الأحياء</span>
				<span class="preview-filter-pill px-4 py-2 fw-semibold fs-7">الفترة: 2026-09-01 إلى 2026-09-22</span>
				<span class="preview-filter-pill px-4 py-2 fw-semibold fs-7">الطبقات: كل GIS</span>
				<button type="button" class="btn btn-sm btn-light-primary rounded-pill">تصدير التقرير</button>
			</div>
		</div>

		<div class="row g-5 g-xl-8 mb-6">
			<div class="col-md-6 col-xl-3">
				<div class="card preview-alert-card h-100" style="--preview-alert-color: #f1416c">
					<div class="card-body">
						<div class="fw-bold fs-2 text-danger mb-1">196</div>
						<div class="fw-semibold text-gray-800">مبانٍ يوجد عائق يمنع التقييم</div>
					</div>
				</div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card preview-alert-card h-100" style="--preview-alert-color: #3f4254">
					<div class="card-body">
						<div class="fw-bold fs-2 text-dark mb-1">13</div>
						<div class="fw-semibold text-gray-800">مواقع مبانٍ عامة فيها ذخائر</div>
					</div>
				</div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card preview-alert-card h-100" style="--preview-alert-color: #ffc700">
					<div class="card-body">
						<div class="fw-bold fs-2 text-warning mb-1">73</div>
						<div class="fw-semibold text-gray-800">استبيانات منظمات بدون وحدات</div>
					</div>
				</div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card preview-alert-card h-100" style="--preview-alert-color: #009ef7">
					<div class="card-body">
						<div class="fw-bold fs-2 text-primary mb-1">238.4 كم</div>
						<div class="fw-semibold text-gray-800">طول شوارع مكتمل إدخالها</div>
					</div>
				</div>
			</div>
		</div>

		<div class="card preview-map-card mb-6">
			<div class="card-header border-0 pt-6">
				<div class="card-title flex-column align-items-start">
					<span class="badge badge-light-info fw-bold mb-2">GIS Decision Layer</span>
					<span class="card-label fw-bold fs-3 mb-1">خريطة GIS شاملة</span>
					<span class="text-muted fw-semibold fs-7">طبقات ArcGIS الفعلية للمباني، الطرق، منظمات المجتمع المدني، والمباني العامة</span>
				</div>
				<div class="card-toolbar">
					<div class="d-flex flex-wrap gap-2" role="group" aria-label="طبقات الخريطة">
						<button type="button" class="btn btn-sm btn-light-primary preview-layer-button active" data-preview-layer="all">عرض الكل</button>
						<button type="button" class="btn btn-sm btn-light preview-layer-button" data-preview-layer="buildings">كل المباني</button>
						<button type="button" class="btn btn-sm btn-light preview-layer-button" data-preview-layer="roadFacilities">الطرق</button>
						<button type="button" class="btn btn-sm btn-light preview-layer-button" data-preview-layer="csoSurveys">منظمات المجتمع المدني</button>
						<button type="button" class="btn btn-sm btn-light preview-layer-button" data-preview-layer="publicBuildings">المباني العامة</button>
					</div>
				</div>
			</div>
			<div class="card-body p-lg-8">
				<div class="row g-6">
					<div class="col-lg-4">
						<div class="preview-map-insight p-5 mb-4">
							<div class="fw-bold text-gray-900 fs-5 mb-1">قراءة سريعة للخريطة</div>
							<div class="text-muted fw-semibold fs-7">ابدأ من المناطق ذات كثافة الضرر، ثم افتح طبقة الطرق لمعرفة قابلية الوصول قبل توزيع فرق التقييم.</div>
						</div>
						<div class="d-grid gap-4">
							<div class="preview-gis-source p-4">
								<div class="d-flex align-items-center justify-content-between mb-2">
									<div class="fw-bold text-gray-900">كل المباني</div>
									<span class="badge badge-light-danger fw-bold">Buildings GIS</span>
								</div>
								<div class="text-muted fw-semibold fs-7">طبقة المباني الأساسية من ArcGIS مع تصنيف حالة الضرر.</div>
							</div>
							<div class="preview-gis-source p-4">
								<div class="d-flex align-items-center justify-content-between mb-2">
									<div class="fw-bold text-gray-900">الطرق</div>
									<span class="badge badge-light-dark fw-bold">Roads GIS</span>
								</div>
								<div class="text-muted fw-semibold fs-7">طبقة مرافق الطرق، وتظهر كخطوط حسب مستوى الضرر.</div>
							</div>
							<div class="preview-gis-source p-4">
								<div class="d-flex align-items-center justify-content-between mb-2">
									<div class="fw-bold text-gray-900">منظمات المجتمع المدني</div>
									<span class="badge badge-light-info fw-bold">CSO GIS</span>
								</div>
								<div class="text-muted fw-semibold fs-7">استبيانات منظمات المجتمع المدني من طبقة CSO Survey.</div>
							</div>
							<div class="preview-gis-source p-4">
								<div class="d-flex align-items-center justify-content-between mb-2">
									<div class="fw-bold text-gray-900">المباني العامة</div>
									<span class="badge badge-light-primary fw-bold">Public GIS</span>
								</div>
								<div class="text-muted fw-semibold fs-7">طبقة المباني العامة من ArcGIS مع الحالة والموقع.</div>
							</div>
						</div>
					</div>
					<div class="col-lg-8">
						<div class="preview-map" id="dashboard_preview_gis_map">
							<div class="preview-map-loading" data-preview-map-loading>
								<div class="text-center">
									<span class="spinner-border text-primary"></span>
									<div class="fw-bold text-gray-800 mt-3">تحميل طبقات GIS...</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>


		<div class="row g-5 g-xl-6 mb-6">
			@foreach ($summaryCards as $summaryCard)
				<div class="col-sm-6 col-xl-4 preview-summary-col">
					<div class="card card-xl-stretch preview-summary-card h-100">
						<div class="preview-summary-header px-7 pt-6 text-white" style="--preview-card-color: {{ $summaryCard['color'] }}">
							<div class="d-flex justify-content-between align-items-start">
								<h3 class="fw-bold text-white fs-4 mb-0">{{ $summaryCard['title'] }}</h3>
								<i class="ki-duotone {{ $summaryCard['icon'] }} fs-2x text-white">
									<span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
								</i>
							</div>
							<div class="text-center pt-8">
								<div class="fw-semibold opacity-75">{{ $summaryCard['subtitle'] }}</div>
								<div class="fw-bold fs-1 pt-1">{{ $summaryCard['total'] }}</div>
							</div>
						</div>

						<div class="preview-floating-body bg-body shadow-sm card-rounded px-5 py-5">
							@foreach ($summaryCard['items'] as $item)
								<div class="d-flex align-items-center justify-content-between gap-3 mb-4">
									<div class="d-flex align-items-center min-w-0">
										<span class="bullet bullet-dot bg-{{ $item['class'] }} me-3"></span>
										<span class="text-gray-800 fw-bold text-truncate">{{ $item['label'] }}</span>
									</div>
									<span class="fw-bold text-gray-900 text-nowrap">{{ $item['value'] }}</span>
								</div>
							@endforeach
						</div>
					</div>
				</div>
			@endforeach
		</div>

		<div class="row g-5 g-xl-8 mb-6">
			<div class="col-xl-6">
				<div class="card card-xl-stretch h-100">
					<div class="card-header border-0 pt-5">
						<h3 class="card-title align-items-start flex-column">
							<span class="card-label fw-bold fs-3 mb-1">ملخص حالة المباني</span>
							<span class="text-muted mt-1 fw-semibold fs-7">تفاصيل إحصائية لعملية التقييم</span>
						</h3>
					</div>
					<div class="card-body pt-2">
						<div class="table-responsive">
							<table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4 mb-0">
								<thead>
									<tr class="fw-bold text-muted">
										<th>الفئة</th>
										<th class="text-end">العدد</th>
										<th class="text-end min-w-150px">النسبة</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td class="fw-bold text-gray-800">ضرر كلي</td>
										<td class="text-end fw-bold text-muted">4,218</td>
										<td class="text-end">
											<div class="d-flex align-items-center justify-content-end">
												<span class="text-muted fw-bold me-2">30%</span>
												<div class="progress h-6px w-100px">
													<div class="progress-bar bg-danger" style="width: 30%"></div>
												</div>
											</div>
										</td>
									</tr>
									<tr>
										<td class="fw-bold text-gray-800">ضرر جزئي</td>
										<td class="text-end fw-bold text-muted">8,536</td>
										<td class="text-end">
											<div class="d-flex align-items-center justify-content-end">
												<span class="text-muted fw-bold me-2">61%</span>
												<div class="progress h-6px w-100px">
													<div class="progress-bar bg-warning" style="width: 61%"></div>
												</div>
											</div>
										</td>
									</tr>
									<tr>
										<td class="fw-bold text-gray-800">لجنة فنية</td>
										<td class="text-end fw-bold text-muted">742</td>
										<td class="text-end">
											<div class="d-flex align-items-center justify-content-end">
												<span class="text-muted fw-bold me-2">5%</span>
												<div class="progress h-6px w-100px">
													<div class="progress-bar bg-primary" style="width: 5%"></div>
												</div>
											</div>
										</td>
									</tr>
									<tr>
										<td class="fw-bold text-gray-800">غير مصنف</td>
										<td class="text-end fw-bold text-muted">511</td>
										<td class="text-end">
											<div class="d-flex align-items-center justify-content-end">
												<span class="text-muted fw-bold me-2">4%</span>
												<div class="progress h-6px w-100px">
													<div class="progress-bar bg-secondary" style="width: 4%"></div>
												</div>
											</div>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="col-xl-6">
				<div class="card card-xl-stretch h-100">
					<div class="card-header border-0 pt-5">
						<h3 class="card-title align-items-start flex-column">
							<span class="card-label fw-bold fs-3 mb-1">ملخص حالة الوحدات السكانية</span>
							<span class="text-muted mt-1 fw-semibold fs-7">ضرر كلي، ضرر جزئي، ولجان فنية</span>
						</h3>
					</div>
					<div class="card-body">
						<div class="d-flex flex-column flex-md-row align-items-center justify-content-center gap-8">
							<div class="position-relative w-175px h-175px rounded-circle"
								style="background: conic-gradient(#f1416c 0 34%, #ffc700 34% 57%, #009ef7 57% 74%, #e4e6ef 74% 100%);">
								<div class="position-absolute top-50 start-50 translate-middle bg-body rounded-circle w-100px h-100px d-flex flex-column align-items-center justify-content-center">
									<span class="fw-bold fs-2 text-gray-900">56%</span>
									<span class="text-muted fs-8">مصنفة</span>
								</div>
							</div>
							<div class="d-grid gap-4">
								<div class="d-flex align-items-center"><span class="bullet bullet-dot bg-danger me-3"></span><span class="fw-bold text-gray-800">ضرر كلي: 12,804</span></div>
								<div class="d-flex align-items-center"><span class="bullet bullet-dot bg-warning me-3"></span><span class="fw-bold text-gray-800">ضرر جزئي: 24,118</span></div>
								<div class="d-flex align-items-center"><span class="bullet bullet-dot bg-primary me-3"></span><span class="fw-bold text-gray-800">لجنة فنية: 1,294</span></div>
								<div class="d-flex align-items-center"><span class="bullet bullet-dot bg-secondary me-3"></span><span class="fw-bold text-gray-800">باقي/غير مصنف</span></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>



	</div>
@endsection

@section('script')
	<script src="https://js.arcgis.com/4.22/"></script>
	<script>
		KTUtil.onDOMContentLoaded(function () {
			const layerUrls = @json($previewLayerUrls);
			const arcgisToken = @json($token);
			const layerButtons = document.querySelectorAll('[data-preview-layer]');
			const loadingElement = document.querySelector('[data-preview-map-loading]');

			require([
				'esri/Map',
				'esri/views/MapView',
				'esri/layers/FeatureLayer',
				'esri/identity/IdentityManager',
				'esri/widgets/BasemapToggle',
				'esri/widgets/LayerList',
				'esri/widgets/Legend',
				'esri/widgets/Search',
				'esri/widgets/ScaleBar',
				'esri/widgets/Expand',
			], function (
				Map,
				MapView,
				FeatureLayer,
				esriId,
				BasemapToggle,
				LayerList,
				Legend,
				Search,
				ScaleBar,
				Expand
			) {
				const layerConfigs = {
					buildings: {
						title: 'كل المباني',
						url: layerUrls.buildings,
						color: [241, 65, 108, 0.55],
						searchFields: ['building_name', 'owner_name', 'objectid', 'municipalitie', 'neighborhood'],
						displayField: 'building_name',
						popupFields: [
							{ fieldName: 'objectid', label: 'OBJECTID' },
							{ fieldName: 'building_name', label: 'اسم المبنى' },
							{ fieldName: 'owner_name', label: 'المالك' },
							{ fieldName: 'municipalitie', label: 'البلدية' },
							{ fieldName: 'neighborhood', label: 'الحي' },
							{ fieldName: 'building_damage_status', label: 'حالة الضرر' },
						],
					},
					roadFacilities: {
						title: 'الطرق',
						url: layerUrls.roadFacilities,
						color: [63, 66, 84, 0.9],
						searchFields: ['str_name', 'objectid', 'municipalitie', 'neighborhood'],
						displayField: 'str_name',
						popupFields: [
							{ fieldName: 'objectid', label: 'OBJECTID' },
							{ fieldName: 'str_name', label: 'اسم الطريق' },
							{ fieldName: 'municipalitie', label: 'البلدية' },
							{ fieldName: 'neighborhood', label: 'الحي' },
							{ fieldName: 'road_damage_level', label: 'مستوى الضرر' },
						],
					},
					csoSurveys: {
						title: 'منظمات المجتمع المدني',
						url: layerUrls.csoSurveys,
						color: [114, 57, 234, 0.65],
						searchFields: ['organization_name', 'cso_name', 'objectid', 'municipalitie', 'neighborhood'],
						displayField: 'organization_name',
						popupFields: [
							{ fieldName: 'objectid', label: 'OBJECTID' },
							{ fieldName: 'organization_name', label: 'اسم المنظمة' },
							{ fieldName: 'cso_name', label: 'المنظمة' },
							{ fieldName: 'municipalitie', label: 'البلدية' },
							{ fieldName: 'neighborhood', label: 'الحي' },
							{ fieldName: 'building_damage_status', label: 'حالة الضرر' },
						],
					},
					publicBuildings: {
						title: 'المباني العامة',
						url: layerUrls.publicBuildings,
						color: [0, 158, 247, 0.55],
						searchFields: ['building_name', 'objectid', 'municipalitie', 'neighborhood'],
						displayField: 'building_name',
						popupFields: [
							{ fieldName: 'objectid', label: 'OBJECTID' },
							{ fieldName: 'building_name', label: 'اسم المبنى' },
							{ fieldName: 'municipalitie', label: 'البلدية' },
							{ fieldName: 'neighborhood', label: 'الحي' },
							{ fieldName: 'building_damage_status', label: 'حالة الضرر' },
						],
					},
				};

				Object.values(layerConfigs).forEach(function (config) {
					if (config.url && arcgisToken) {
						esriId.registerToken({
							server: config.url,
							token: arcgisToken,
							expires: Date.now() + (60 * 60 * 1000),
						});
					}
				});

				function rendererForLayer(layer, config) {
					if (layer.geometryType === 'polyline') {
						return {
							type: 'simple',
							symbol: {
								type: 'simple-line',
								color: config.color,
								width: 3,
							},
						};
					}

					if (layer.geometryType === 'point' || layer.geometryType === 'multipoint') {
						return {
							type: 'simple',
							symbol: {
								type: 'simple-marker',
								size: 9,
								color: config.color,
								outline: {
									color: [255, 255, 255, 0.85],
									width: 1,
								},
							},
						};
					}

					return {
						type: 'simple',
						symbol: {
							type: 'simple-fill',
							color: config.color,
							outline: {
								color: [255, 255, 255, 0.85],
								width: 1,
							},
						},
					};
				}

				const layers = Object.entries(layerConfigs)
					.filter(function ([, config]) {
						return Boolean(config.url);
					})
					.map(function ([key, config]) {
						const layer = new FeatureLayer({
							url: config.url,
							title: config.title,
							outFields: ['*'],
							visible: true,
							popupTemplate: {
								title: config.title + ' - {' + config.displayField + '}',
								content: [{
									type: 'fields',
									fieldInfos: config.popupFields,
								}],
							},
						});

						layer.previewKey = key;
						layer.when(function () {
							layer.renderer = rendererForLayer(layer, config);
						});

						return layer;
					});

				const map = new Map({
					basemap: 'satellite',
					layers: layers,
				});

				const view = new MapView({
					container: 'dashboard_preview_gis_map',
					map: map,
					center: [34.460987, 31.514266],
					zoom: 12,
				});

				const searchSources = layers.map(function (layer) {
					const config = layerConfigs[layer.previewKey];

					return {
						layer: layer,
						searchFields: config.searchFields,
						displayField: config.displayField,
						exactMatch: false,
						outFields: ['*'],
						name: config.title,
						placeholder: 'بحث في ' + config.title,
					};
				});

				view.ui.add(new BasemapToggle({ view: view, nextBasemap: 'osm' }), 'top-left');
				view.ui.add(new Search({
					view: view,
					allPlaceholder: 'بحث في طبقات GIS',
					includeDefaultSources: false,
					sources: searchSources,
				}), 'top-right');
				view.ui.add(new ScaleBar({ view: view, unit: 'metric' }), 'bottom-left');
				view.ui.add(new Expand({
					view: view,
					content: new LayerList({ view: view }),
					expanded: false,
				}), 'top-left');
				view.ui.add(new Expand({
					view: view,
					content: new Legend({ view: view }),
					expanded: false,
				}), 'bottom-right');

				function setVisibleLayer(layerKey) {
					layers.forEach(function (layer) {
						layer.visible = layerKey === 'all' || layer.previewKey === layerKey;
					});
				}

				layerButtons.forEach(function (button) {
					button.addEventListener('click', function () {
						layerButtons.forEach(function (item) {
							item.classList.remove('active', 'btn-primary');
							item.classList.add('btn-light');
						});

						button.classList.add('active', 'btn-primary');
						button.classList.remove('btn-light');
						setVisibleLayer(button.dataset.previewLayer);
					});
				});

				view.when(function () {
					if (loadingElement) {
						loadingElement.remove();
					}
				});
			});
		});
	</script>
@endsection
