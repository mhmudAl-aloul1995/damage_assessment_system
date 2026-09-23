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
			min-height: 430px;
			background:
				linear-gradient(135deg, rgba(0, 158, 247, .14), transparent 36%),
				linear-gradient(45deg, transparent 0 20%, rgba(126, 130, 153, .18) 20% 20.5%, transparent 20.5% 48%, rgba(126, 130, 153, .18) 48% 48.5%, transparent 48.5%),
				var(--bs-gray-100);
			border: 1px solid var(--bs-gray-300);
			border-radius: var(--preview-radius);
			position: relative;
			overflow: hidden;
		}

		.damage-dashboard-preview .preview-map::before {
			content: "";
			position: absolute;
			inset: 13% 17% 15% 18%;
			border: 2px solid rgba(0, 158, 247, .42);
			border-radius: 44% 38% 48% 32%;
			transform: rotate(-9deg);
		}

		.damage-dashboard-preview .preview-map::after {
			content: "";
			position: absolute;
			inset: 29% 31% 31% 27%;
			border: 3px dashed rgba(241, 65, 108, .55);
			border-radius: 50%;
			transform: rotate(18deg);
		}

		.damage-dashboard-preview .preview-map-pin {
			position: absolute;
			width: 14px;
			height: 14px;
			border-radius: 999px;
			background: var(--preview-pin-color);
			box-shadow: 0 0 0 7px color-mix(in srgb, var(--preview-pin-color) 18%, transparent);
			z-index: 2;
		}

		.damage-dashboard-preview .preview-map-pin-one {
			inset-inline-start: 34%;
			inset-block-start: 30%;
		}

		.damage-dashboard-preview .preview-map-pin-two {
			inset-inline-start: 55%;
			inset-block-start: 46%;
		}

		.damage-dashboard-preview .preview-map-pin-three {
			inset-inline-start: 43%;
			inset-block-start: 64%;
		}

		.damage-dashboard-preview .preview-map-tools {
			position: absolute;
			inset-inline-start: 1rem;
			inset-block-start: 1rem;
			z-index: 3;
		}

		.damage-dashboard-preview .preview-map-legend {
			position: absolute;
			inset-inline-end: 1rem;
			inset-block-end: 1rem;
			z-index: 3;
		}

		.damage-dashboard-preview .preview-alert-card {
			border-inline-start: 4px solid var(--preview-alert-color);
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
			<div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-4">
				<div>
					<div class="badge badge-light-primary fw-bold mb-3">Preview</div>
					<h2 class="fw-bold text-gray-900 mb-1">معاينة الصفحة الرئيسية حسب تحليل النظام</h2>
					<div class="text-muted fw-semibold">نفس محاور صفحة تقييم الأضرار: المباني، الوحدات السكانية، منظمات المجتمع المدني، المباني العامة، والطرق.</div>
				</div>

				<div class="d-flex flex-wrap align-items-center gap-3">
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
					<div class="btn-group" role="group" aria-label="فترة العرض">
						<button type="button" class="btn btn-sm btn-light">أمس</button>
						<button type="button" class="btn btn-sm btn-light">الأسبوع</button>
						<button type="button" class="btn btn-sm btn-primary">اليوم</button>
						<button type="button" class="btn btn-sm btn-light">الكل</button>
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

		<div class="card mb-6">
			<div class="card-header border-0 pt-6">
				<div class="card-title flex-column align-items-start">
					<span class="card-label fw-bold fs-3 mb-1">خرائط ArcGIS المرتبطة بالجداول</span>
					<span class="text-muted fw-semibold fs-7">نفس منطق الصفحة الحالية: جدول على اليسار وخريطة على اليمين</span>
				</div>
				<div class="card-toolbar">
					<div class="btn-group" role="group" aria-label="نوع الخريطة">
						<button type="button" class="btn btn-sm btn-primary" data-preview-map-tab="public">المباني العامة</button>
						<button type="button" class="btn btn-sm btn-light" data-preview-map-tab="roads">الطرق</button>
					</div>
				</div>
			</div>
			<div class="card-body p-lg-8">
				<div class="row g-6 align-items-stretch">
					<div class="col-lg-5">
						<div class="table-responsive">
							<table class="table table-rounded table-striped align-middle fs-7 gy-5 mb-0">
								<thead>
									<tr class="text-muted fw-bold text-uppercase">
										<th>البلدية</th>
										<th>الحي</th>
										<th>OBJECTID</th>
										<th data-preview-map-heading>اسم المبنى</th>
										<th>الحالة</th>
									</tr>
								</thead>
								<tbody class="text-gray-600 fw-semibold" data-preview-map-rows>
									<tr>
										<td>غزة</td>
										<td>الرمال</td>
										<td>PB-204</td>
										<td>مدرسة الرمال</td>
										<td><span class="badge badge-light-danger fw-bold">متضرر</span></td>
									</tr>
									<tr>
										<td>خانيونس</td>
										<td>المحطة</td>
										<td>PB-188</td>
										<td>مركز صحي</td>
										<td><span class="badge badge-light-warning fw-bold">لجنة</span></td>
									</tr>
									<tr>
										<td>دير البلح</td>
										<td>البلد</td>
										<td>PB-151</td>
										<td>مبنى بلدي</td>
										<td><span class="badge badge-light-success fw-bold">مكتمل</span></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
					<div class="col-lg-7">
						<div class="preview-map">
							<div class="preview-map-tools d-grid gap-2">
								<span class="btn btn-sm btn-icon btn-light">+</span>
								<span class="btn btn-sm btn-icon btn-light">-</span>
								<span class="btn btn-sm btn-icon btn-light"><i class="ki-duotone ki-magnifier fs-4"><span class="path1"></span><span class="path2"></span></i></span>
							</div>
							<span class="preview-map-pin preview-map-pin-one" style="--preview-pin-color: #f1416c"></span>
							<span class="preview-map-pin preview-map-pin-two" style="--preview-pin-color: #ffc700"></span>
							<span class="preview-map-pin preview-map-pin-three" style="--preview-pin-color: #50cd89"></span>
							<div class="preview-map-legend bg-body border rounded p-4 shadow-sm">
								<div class="d-flex align-items-center mb-2"><span class="bullet bullet-dot bg-danger me-3"></span><span class="fw-semibold text-muted">ضرر عالي</span></div>
								<div class="d-flex align-items-center mb-2"><span class="bullet bullet-dot bg-warning me-3"></span><span class="fw-semibold text-muted">متوسط/لجنة</span></div>
								<div class="d-flex align-items-center"><span class="bullet bullet-dot bg-success me-3"></span><span class="fw-semibold text-muted">مكتمل</span></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="row g-5 g-xl-8">
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
	</div>
@endsection

@section('script')
	<script>
		KTUtil.onDOMContentLoaded(function () {
			const tabs = document.querySelectorAll('[data-preview-map-tab]');
			const heading = document.querySelector('[data-preview-map-heading]');
			const rows = document.querySelector('[data-preview-map-rows]');
			const data = {
				public: {
					heading: 'اسم المبنى',
					rows: [
						['غزة', 'الرمال', 'PB-204', 'مدرسة الرمال', 'badge-light-danger', 'متضرر'],
						['خانيونس', 'المحطة', 'PB-188', 'مركز صحي', 'badge-light-warning', 'لجنة'],
						['دير البلح', 'البلد', 'PB-151', 'مبنى بلدي', 'badge-light-success', 'مكتمل'],
					],
				},
				roads: {
					heading: 'اسم الطريق',
					rows: [
						['غزة', 'النصر', 'RD-991', 'شارع النصر', 'badge-light-danger', 'مدمر'],
						['رفح', 'الجنينة', 'RD-774', 'طريق الخدمات', 'badge-light-warning', 'أضرار جسيمة'],
						['الشمال', 'جباليا', 'RD-630', 'شارع السوق', 'badge-light-primary', 'أضرار متوسطة'],
					],
				},
			};

			function renderMapTable(type) {
				const selected = data[type] || data.public;

				heading.textContent = selected.heading;
				rows.replaceChildren();

				selected.rows.forEach(function (row) {
					const tr = document.createElement('tr');

					row.slice(0, 4).forEach(function (value) {
						const td = document.createElement('td');
						td.textContent = value;
						tr.appendChild(td);
					});

					const statusCell = document.createElement('td');
					const statusBadge = document.createElement('span');
					statusBadge.className = 'badge ' + row[4] + ' fw-bold';
					statusBadge.textContent = row[5];
					statusCell.appendChild(statusBadge);
					tr.appendChild(statusCell);
					rows.appendChild(tr);
				});
			}

			tabs.forEach(function (tab) {
				tab.addEventListener('click', function () {
					tabs.forEach(function (item) {
						item.classList.remove('btn-primary');
						item.classList.add('btn-light');
					});

					tab.classList.add('btn-primary');
					tab.classList.remove('btn-light');
					renderMapTable(tab.dataset.previewMapTab);
				});
			});
		});
	</script>
@endsection
