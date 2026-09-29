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
				'key' => 'buildings',
                'tone' => 'primary',
                'route' => 'building.index',
                'layer' => 'buildings',
                'map_note' => 'المباني',
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
				'key' => 'housing',
                'tone' => 'success',
                'route' => 'housing.index',
                'layer' => 'buildings',
                'map_note' => 'الوحدات السكانية: تظهر مواقع المباني الحاوية لها',
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
				'key' => 'cso',
                'tone' => 'info',
                'route' => 'cso-surveys.index',
                'layer' => 'csoSurveys',
                'map_note' => 'منظمات المجتمع المدني',
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
				'key' => 'public',
                'tone' => 'warning',
                'route' => 'public-buildings.index',
                'layer' => 'publicBuildings',
                'map_note' => 'المباني العامة',
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
				'key' => 'roads',
                'tone' => 'dark',
                'route' => 'road-facilities.index',
                'layer' => 'roadFacilities',
                'map_note' => 'الطرق',
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

    <div class="damage-dashboard-preview" lang="ar" dir="rtl">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-7">
            <div>
                <div class="d-flex align-items-center flex-wrap gap-3 mb-2">
                    <h2 class="fw-bold text-gray-900 mb-0">لوحة متابعة تقييم الأضرار</h2>
                    <span class="badge badge-light-primary">معاينة Metronic</span>
                </div>
                <span class="text-muted fw-semibold fs-7">ملخص العمل وأولويات المتابعة، مع تفاصيل مستقلة لكل قطاع.</span>
            </div>
            <a class="btn btn-sm btn-light-primary" href="{{ route('damageAssessment.index') }}">العودة للصفحة الحالية <i class="ki-outline ki-arrow-left fs-4 ms-2" aria-hidden="true"></i></a>
        </div>

        <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4 mb-6" role="note">
            <i class="ki-outline ki-information-5 fs-2 text-warning me-3" aria-hidden="true"></i>
            <div class="fs-7 text-gray-700"><strong class="me-2">معاينة تصميم · بيانات توضيحية</strong>الأرقام والرسوم توضيحية ولا تمثل تقارير النظام. الخريطة تعرض الطبقات الفعلية عند توفر الاتصال.</div>
        </div>

        <section aria-label="نظرة على نطاق العمل" class="mb-7">
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-5">
                @foreach ($summaryCards as $summaryCard)
                    <div class="col">
                        <div class="card card-flush h-100 border border-gray-200">
                            <div class="card-body p-5">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-5">
                                    <span class="symbol symbol-40px"><span class="symbol-label bg-light-{{ $summaryCard['tone'] }}"><i class="ki-outline {{ $summaryCard['icon'] }} fs-2 text-{{ $summaryCard['tone'] }}" aria-hidden="true"></i></span></span>
                                    <span class="badge badge-light fs-9">توضيحي</span>
                                </div>
                                <div class="fs-2hx fw-bold text-gray-900 lh-1 mb-3"><bdi>{{ $summaryCard['total'] }}</bdi></div>
                                <h3 class="fs-7 fw-bold text-gray-800 mb-2">{{ $summaryCard['title'] }}</h3>
                                <span class="text-muted fs-8">{{ $summaryCard['subtitle'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="card mb-7">
            <div class="card-header border-0 px-6">
                <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-7 fw-bold gap-5" role="tablist" aria-label="قطاعات تقييم الأضرار">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-active-primary active py-5" id="preview-tab-overview" data-bs-toggle="tab" data-bs-target="#preview-pane-overview" data-preview-sector="all" data-preview-map-note="جميع القطاعات" type="button" role="tab" aria-controls="preview-pane-overview" aria-selected="true">نظرة عامة</button>
                    </li>
                    @foreach ($summaryCards as $summaryCard)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-active-primary py-5" id="preview-tab-{{ $summaryCard['key'] }}" data-bs-toggle="tab" data-bs-target="#preview-pane-{{ $summaryCard['key'] }}" data-preview-sector="{{ $summaryCard['layer'] }}" data-preview-map-note="{{ $summaryCard['map_note'] }}" type="button" role="tab" aria-controls="preview-pane-{{ $summaryCard['key'] }}" aria-selected="false" tabindex="-1">{{ $summaryCard['title'] }}</button>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body border-top border-gray-200 p-6">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="preview-pane-overview" role="tabpanel" aria-labelledby="preview-tab-overview" tabindex="0">
                        <div class="row align-items-center g-6">
                            <div class="col-lg-7">
                                <h3 class="fs-5 fw-bold text-gray-900 mb-3">المشهد العام في مكان واحد</h3>
                                <p class="text-muted fs-7 mb-4">ابدأ بالخريطة وأولويات المتابعة، أو اختر قطاعاً للاطلاع على مؤشراته وفتح سجلاته.</p>
                                <div class="d-flex flex-wrap gap-3"><span class="badge badge-light-primary">5 قطاعات</span><span class="badge badge-light-success">4 طبقات مكانية</span><span class="badge badge-light-warning">3 أولويات للمتابعة</span></div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border border-dashed border-gray-300 rounded p-4">
                                    <div class="d-flex justify-content-between gap-3 mb-3"><span class="text-gray-700 fs-7 fw-semibold">مبانٍ بضرر كلي أو جزئي</span><span class="fw-bold text-primary">69.2%</span></div>
                                    <div class="progress h-6px bg-light-primary"><div class="progress-bar bg-primary" style="width: 69.2%" role="progressbar" aria-label="المباني ذات الضرر الكلي أو الجزئي في المعاينة" aria-valuenow="69.2" aria-valuemin="0" aria-valuemax="100"></div></div>
                                    <div class="text-muted fs-8 mt-3">12,754 من أصل 18,420 مبنى · مثال توضيحي</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @foreach ($summaryCards as $summaryCard)
                        <div class="tab-pane fade" id="preview-pane-{{ $summaryCard['key'] }}" role="tabpanel" aria-labelledby="preview-tab-{{ $summaryCard['key'] }}" tabindex="0">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
                                <div><h3 class="fs-5 fw-bold mb-2">مؤشرات {{ $summaryCard['title'] }}</h3><span class="text-muted fs-8">تفاصيل توضيحية للقطاع؛ الفئات قد تتداخل ولا تُجمع للحصول على الإجمالي.</span></div>
                                <a href="{{ route($summaryCard['route']) }}" class="btn btn-sm btn-light-primary">فتح السجلات الفعلية <i class="ki-outline ki-arrow-left fs-5 ms-1" aria-hidden="true"></i></a>
                            </div>
                            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-4">
                                @foreach ($summaryCard['items'] as $item)
                                    <div class="col"><div class="border border-dashed border-gray-300 rounded p-4 h-100"><span class="bullet bullet-dot bg-{{ $item['class'] }} me-2" aria-hidden="true"></span><span class="fs-8 text-gray-600">{{ $item['label'] }}</span><div class="fw-bold fs-2 text-gray-900 mt-3"><bdi>{{ $item['value'] }}</bdi></div></div></div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row g-6 mb-7">
            <div class="col-lg-8">
                <section class="card card-flush h-100" aria-labelledby="preview-map-title">
                    <div class="card-header pt-5">
                        <div class="card-title flex-column align-items-start"><h3 id="preview-map-title" class="fw-bold fs-4 mb-2">خريطة GIS شاملة</h3><span class="text-muted fs-8" data-preview-map-context aria-live="polite">جميع القطاعات</span></div>
                        <div class="card-toolbar"><span class="badge badge-light-primary">ArcGIS</span></div>
                    </div>
                    <div class="card-body pt-4 px-6 pb-5">
                        <div class="d-flex flex-wrap gap-2 mb-4" role="group" aria-label="طبقات الخريطة">
                            @foreach (['all' => 'عرض الكل', 'buildings' => 'كل المباني', 'roadFacilities' => 'الطرق', 'csoSurveys' => 'منظمات المجتمع المدني', 'publicBuildings' => 'المباني العامة'] as $layerKey => $layerLabel)
                                <button type="button" class="btn btn-sm btn-light btn-active-light-primary {{ $loop->first ? 'active' : '' }}" data-preview-layer="{{ $layerKey }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $layerLabel }}</button>
                            @endforeach
                        </div>
                        <div class="position-relative rounded overflow-hidden bg-light">
                            <div class="h-400px w-100" id="dashboard_preview_gis_map" aria-label="خريطة مواقع التقييم"></div>
                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-light d-flex align-items-center justify-content-center p-5" data-preview-map-loading role="status" aria-live="polite">
                                <div class="text-center"><span class="spinner-border text-primary" aria-hidden="true"></span><div class="text-gray-600 fs-7 mt-4">جارٍ تحميل الخريطة المكانية…</div></div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 text-muted fs-8 mt-4"><i class="ki-outline ki-information-5 fs-5" aria-hidden="true"></i>الخريطة مستقلة عن أرقام المعاينة؛ اختيار القطاع يحدد الطبقة المعروضة.</div>
                    </div>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="card card-flush h-100" aria-labelledby="preview-followup-title">
                    <div class="card-header pt-5"><div class="card-title flex-column align-items-start"><h3 id="preview-followup-title" class="fw-bold fs-4 mb-2">تحتاج انتباهك</h3><span class="text-muted fs-8">أولويات المتابعة · أمثلة توضيحية</span></div><div class="card-toolbar"><span class="badge badge-light-danger">3</span></div></div>
                    <div class="card-body pt-4">
                        <div class="notice d-flex bg-light-danger rounded border-danger border border-dashed p-4 mb-6"><i class="ki-outline ki-shield-cross fs-2 text-danger me-3" aria-hidden="true"></i><span class="fs-8 text-gray-700 lh-lg">مراجعة عوائق التقييم ومخاطر المواقع قبل جدولة الزيارات الميدانية.</span></div>
                        <div class="d-flex align-items-center mb-5"><span class="bullet bullet-vertical h-40px bg-danger me-4" aria-hidden="true"></span><div class="flex-grow-1"><h4 class="text-gray-800 fs-7 fw-bold mb-2">مبانٍ تعذّر تقييمها</h4><a href="{{ route('building.index', ['assessment_obstacle' => 'yes']) }}" class="text-muted text-hover-primary fs-8">فتح السجلات الفعلية ←</a></div><span class="badge badge-light-danger fs-5">196</span></div>
                        <div class="separator separator-dashed mb-5"></div>
                        <div class="d-flex align-items-center mb-5"><span class="bullet bullet-vertical h-40px bg-warning me-4" aria-hidden="true"></span><div class="flex-grow-1"><h4 class="text-gray-800 fs-7 fw-bold mb-2">مبانٍ عامة فيها ذخائر</h4><a href="{{ route('public-buildings.index', ['uxo_only' => 1]) }}" class="text-muted text-hover-primary fs-8">فتح السجلات الفعلية ←</a></div><span class="badge badge-light-warning fs-5">13</span></div>
                        <div class="separator separator-dashed mb-5"></div>
                        <div class="d-flex align-items-center mb-6"><span class="bullet bullet-vertical h-40px bg-info me-4" aria-hidden="true"></span><div class="flex-grow-1"><h4 class="text-gray-800 fs-7 fw-bold mb-2">استبيانات منظمات بدون وحدات</h4><a href="{{ route('cso-surveys.index', ['without_units' => 1]) }}" class="text-muted text-hover-primary fs-8">فتح السجلات الفعلية ←</a></div><span class="badge badge-light-info fs-5">73</span></div>
                        <div class="bg-light rounded p-4 text-muted fs-8 lh-lg">الروابط تفتح سجلات النظام الفعلية. أعداد المعاينة أعلاه أمثلة للتصميم فقط.</div>
                    </div>
                </section>
            </div>
        </div>

        <section class="card mb-6" aria-labelledby="preview-analysis-title">
            <div class="card-header"><div class="card-title"><h3 id="preview-analysis-title" class="fw-bold fs-4 mb-0">قراءة في الأضرار</h3></div><div class="card-toolbar"><span class="badge badge-light">بيانات توضيحية</span></div></div>
            <div class="card-body">
                <div class="row g-8">
                    <div class="col-lg-6">
                        <h4 class="fs-6 fw-bold mb-5">توزيع حالات المباني</h4>
                        @foreach ([['ضرر كلي', '4,218', '22.9', 'danger'], ['ضرر جزئي', '8,536', '46.3', 'warning'], ['لجنة فنية حالية', '742', '4.0', 'primary'], ['باقي الحالات', '4,924', '26.7', 'secondary']] as [$label, $count, $percent, $color])
                            <div class="mb-5"><div class="d-flex justify-content-between fs-7 mb-2"><span class="text-gray-600">{{ $label }}</span><span class="fw-bold text-gray-800">{{ $count }} <span class="text-muted fw-normal fs-8">· {{ $percent }}%</span></span></div><div class="progress h-6px bg-light"><div class="progress-bar bg-{{ $color }}" style="width: {{ $percent }}%" role="progressbar" aria-label="{{ $label }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div></div></div>
                        @endforeach
                    </div>
                    <div class="col-lg-6">
                        <h4 class="fs-6 fw-bold mb-5">ملخص الوحدات السكانية</h4>
                        <div class="table-responsive"><table class="table table-row-dashed align-middle gs-0 gy-3 mb-0"><caption class="text-muted fs-8">من أصل 67,500 وحدة في المعاينة؛ النسب مقربة.</caption><thead><tr class="text-muted fs-8 fw-bold"><th scope="col">حالة الضرر</th><th scope="col" class="text-end">الوحدات</th><th scope="col" class="text-end">النسبة</th></tr></thead><tbody>
                            @foreach ([['ضرر كلي', '12,804', '19.0', 'danger'], ['ضرر جزئي', '24,118', '35.7', 'warning'], ['لجنة فنية', '1,294', '1.9', 'primary'], ['باقي الحالات', '29,284', '43.4', 'secondary']] as [$label, $count, $percent, $color])
                                <tr><th scope="row" class="fw-semibold text-gray-700 fs-7"><span class="bullet bullet-dot bg-{{ $color }} me-2" aria-hidden="true"></span>{{ $label }}</th><td class="text-end fw-bold text-gray-800 fs-7">{{ $count }}</td><td class="text-end"><span class="badge badge-light-{{ $color }}">{{ $percent }}%</span></td></tr>
                            @endforeach
                        </tbody></table></div>
                    </div>
                </div>
            </div>
        </section>
        <div class="d-flex flex-wrap justify-content-between gap-3 text-muted fs-8 mb-5"><span>معاينة الصفحة الرئيسية · نظرة عامة وتفاصيل القطاعات</span><span>Metronic · مكوّنات القالب الحالي</span></div>
    </div>
@endsection

@section('script')
	<script src="https://js.arcgis.com/4.22/"></script>
	<script>
		KTUtil.onDOMContentLoaded(function () {
			const layerUrls = @json($previewLayerUrls);
			const arcgisToken = @json($token);
			const layerButtons = document.querySelectorAll('[data-preview-layer]');
			const sectorTabs = document.querySelectorAll('[data-preview-sector]');
			const mapContext = document.querySelector('[data-preview-map-context]');
			let selectedLayer = 'all';
			let updateMapLayers = function () {};

			function selectPreviewLayer(layerKey, label) {
				selectedLayer = layerKey;
				layerButtons.forEach(function (button) {
					const active = button.dataset.previewLayer === layerKey;
					button.classList.toggle('active', active);
					button.setAttribute('aria-pressed', String(active));
				});
				mapContext.textContent = label;
				updateMapLayers(layerKey);
			}

			sectorTabs.forEach(function (tab) {
				tab.addEventListener('shown.bs.tab', function () {
					selectPreviewLayer(tab.dataset.previewSector, tab.dataset.previewMapNote);
				});
			});

			layerButtons.forEach(function (button) {
				button.addEventListener('click', function () {
					selectPreviewLayer(button.dataset.previewLayer, button.textContent.trim());
				});
			});
			const loadingElement = document.querySelector('[data-preview-map-loading]');
			const showMapError = function () {
				if (loadingElement) {
					loadingElement.textContent = 'تعذّر تحميل الخريطة. تحقق من الاتصال ثم أعد تحميل الصفحة.';
				}
			};

			if (typeof require !== 'function') {
				showMapError();
				return;
			}

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

				updateMapLayers = setVisibleLayer;
				updateMapLayers(selectedLayer);

				view.when(function () {
					if (loadingElement) {
						loadingElement.remove();
					}
				}, showMapError);
			}, showMapError);
		});
	</script>
@endsection
