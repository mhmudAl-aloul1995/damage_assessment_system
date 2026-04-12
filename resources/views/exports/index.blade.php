@extends('layouts.app')

@section('content')
	<style>
		.card-toolbar .dropdown-menu .dropdown-item {
			font-size: 13px;
			padding: 0.65rem 1rem;
			transition: 0.2s ease;
		}

		.card-toolbar .dropdown-menu .dropdown-item:hover {
			background-color: #f8f9fa;
		}
	</style>
	<div class="container py-4">
		<div id="exportResult" class="mt-4"></div>
		<div class="card shadow-sm">
			<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
				<h3 class="mb-0">تصدير بيانات المباني والوحدات السكنية</h3>


			</div>

			<div class="card-body">
				@if(session('error'))
					<div class="alert alert-danger">
						{{ session('error') }}
					</div>
				@endif

				<form id="exportForm" method="POST">
					@csrf

					{{-- FILTERS --}}
					<div class="card card-bordered mb-5">
						<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
							<h3 class="card-title mb-0">الفلاتر</h3>

							<div class="d-flex gap-2">
								<button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="collapse"
									data-bs-target="#filtersCollapse" aria-expanded="true" aria-controls="filtersCollapse"
									id="toggleFiltersBtn">
									<i class="fas fa-chevron-down me-1"></i>
									إظهار
								</button>

								<button type="button" class="btn btn-sm btn-light-danger" onclick="resetFilters()">
									<i class="fas fa-times me-1"></i>
									مسح الفلاتر
								</button>
								<button type="button"
									class="btn btn-light-primary btn-sm dropdown-toggle d-flex align-items-center gap-1"
									data-bs-toggle="dropdown" aria-expanded="false">

									<i class="ki-duotone ki-exit-down fs-5">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
									تصدير
								</button>

								<div class="dropdown-menu dropdown-menu-end shadow-sm border-0">
									<button class="dropdown-item d-flex align-items-center gap-2 export-btn" type="button"
										data-type="excel">
										<i class="ki-duotone ki-file-down fs-4 text-success">
											<span class="path1"></span>
											<span class="path2"></span>
										</i>
										<span>Excel (.xlsx)</span>
									</button>

									<button class="dropdown-item d-flex align-items-center gap-2 export-btn" type="button"
										data-type="pdf">
										<i class="ki-duotone ki-file-down fs-4 text-danger">
											<span class="path1"></span>
											<span class="path2"></span>
										</i>
										<span>PDF (.pdf)</span>
									</button>
								</div>
							</div>

						</div>

						<div class="collapse" id="filtersCollapse">
							<div class="card-body">

								<div class="mb-5">
									<div class="input-group">
										<span class="input-group-text">
											<i class="fas fa-search"></i>
										</span>

										<input type="text" id="filterSearch" class="form-control form-control-solid"
											placeholder="ابحث عن الفلتر... مثل neighborhood أو locality"
											onkeyup="filterFilterCards()">

										<button type="button" class="btn btn-light" onclick="clearFilterSearch()">
											مسح
										</button>
									</div>

									<div class="text-muted fs-7 mt-2">
										عدد الفلاتر الظاهرة:
										<span id="filterCardsCounter">{{ count($filters) }}</span>
										/ {{ count($filters) }}
									</div>
								</div>

								<div class="row" id="filtersCardsList">
									@foreach($filters as $listName => $items)
										<div class="col-md-4 mb-4 filter-card-item">
											<label class="form-label fw-bold searchable-filter-name">
												{{ $assessmentLabels[$listName] ?? ucwords(str_replace('_', ' ', $listName)) }}
											</label>

											<select name="filters[{{ $listName }}][]"
												class="form-select form-select-solid filter-select2" multiple
												data-placeholder="اختر {{ $assessmentLabels[$listName] ?? ucwords(str_replace('_', ' ', $listName)) }}">
												@foreach($items as $item)
													<option value="{{ $item->name }}">
														{{ $item->label ?? $item->name }}
													</option>
												@endforeach
											</select>
										</div>
									@endforeach

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label class="form-label fw-bold searchable-filter-name">عدد أفراد الأسرة من</label>
										<input type="number" name="family_members_from"
											class="form-control form-control-solid" min="0"
											placeholder="عدد أفراد الأسرة من" value="{{ old('family_members_from') }}">
									</div>

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label class="form-label fw-bold searchable-filter-name">عدد أفراد الأسرة
											إلى</label>
										<input type="number" name="family_members_to" placeholder="عدد أفراد الأسرة إلى"
											class="form-control form-control-solid" min="0"
											value="{{ old('family_members_to') }}">
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="row">
						{{-- Buildings --}}

						<div class="col-lg-6 mb-4">
							<div class="card card-bordered h-100">
								<div class="card-header border-0 pt-4">
									<div class="card-title">
										<h4 class="mb-0">حقول جدول المبنى</h4>
									</div>

									<div class="card-toolbar d-flex gap-2">
										<button type="button" class="btn btn-sm btn-primary"
											onclick="toggleVisibleGroup('buildingColumnsList', 'building_columns[]', true)">
											تحديد الكل
										</button>

										<button type="button" class="btn btn-sm btn-light-danger"
											onclick="toggleVisibleGroup('buildingColumnsList', 'building_columns[]', false)">
											إلغاء الكل
										</button>
									</div>
								</div>

								<div class="card-body pt-2">
									<div class="mb-4">
										<div class="input-group">
											<span class="input-group-text">
												<i class="fas fa-search"></i>
											</span>

											<input type="text" id="buildingSearch" class="form-control form-control-solid"
												placeholder="ابحث بالاسم أو الـ label أو الـ hint..."
												onkeyup="filterColumns('buildingSearch', 'buildingColumnsList', 'buildingCounter')">

											<button type="button" class="btn btn-light"
												onclick="clearSearch('buildingSearch', 'buildingColumnsList', 'buildingCounter')">
												مسح
											</button>
										</div>

										<div class="text-muted fs-7 mt-2">
											عدد النتائج الكلية:
											<span id="buildingCounter">{{ count($buildingColumns) }}</span>
											/ {{ count($buildingColumns) }}
										</div>
									</div>

									<div class="row" id="buildingColumnsList">
										@forelse($buildingColumns as $column)
											<div class="col-md-6 mb-3 column-item">
												<label
													class="form-check form-check-custom form-check-solid align-items-start w-100 border rounded p-3">
													<input class="form-check-input mt-1" type="checkbox"
														name="building_columns[]" value="{{ $column }}">

													<span class="form-check-label ms-3 w-100">
														<strong class="d-block fs-6 searchable-label">
															{{ $assessmentMeta[$column]['label'] ?? ucwords(str_replace('_', ' ', $column)) }}
														</strong>

														<small class="text-muted d-block mt-1 searchable-column">
															{{ $assessmentMeta[$column]['hint'] ?? '' }}
														</small>

														@if(!empty($assessmentMeta[$column]['hint']) && trim($assessmentMeta[$column]['hint']) !== '')
															<small class="text-info d-block mt-2 searchable-hint">
																{{ $column }}
															</small>
														@endif
													</span>
												</label>
											</div>
										@empty
											<div class="col-12">
												<div class="alert alert-warning mb-0">
													لا توجد حقول متاحة في جدول buildings
												</div>
											</div>
										@endforelse
									</div>
								</div>
							</div>
						</div>

						{{-- Housing --}}
						<div class="col-lg-6 mb-4">
							<div class="card card-bordered h-100">
								<div class="card-header border-0 pt-4">
									<div class="card-title">
										<h4 class="mb-0">حقول جدول الوحد السكانية</h4>
									</div>

									<div class="card-toolbar d-flex gap-2">
										<button type="button" class="btn btn-sm btn-primary"
											onclick="toggleVisibleGroup('housingColumnsList', 'housing_columns[]', true)">
											تحديد الكل
										</button>

										<button type="button" class="btn btn-sm btn-light-danger"
											onclick="toggleVisibleGroup('housingColumnsList', 'housing_columns[]', false)">
											إلغاء الكل
										</button>
									</div>
								</div>

								<div class="card-body pt-2">
									<div class="mb-4">
										<div class="input-group">
											<span class="input-group-text">
												<i class="fas fa-search"></i>
											</span>

											<input type="text" id="housingSearch" class="form-control form-control-solid"
												placeholder="ابحث بالاسم أو الـ label أو الـ hint..."
												onkeyup="filterColumns('housingSearch', 'housingColumnsList', 'housingCounter')">

											<button type="button" class="btn btn-light"
												onclick="clearSearch('housingSearch', 'housingColumnsList', 'housingCounter')">
												مسح
											</button>
										</div>

										<div class="text-muted fs-7 mt-2">
											عدد النتائج الكلية:
											<span id="housingCounter">{{ count($housingColumns) }}</span>
											/ {{ count($housingColumns) }}
										</div>
									</div>

									<div class="row" id="housingColumnsList">
										@forelse($housingColumns as $column)
											<div class="col-md-6 mb-3 column-item">
												<label
													class="form-check form-check-custom form-check-solid align-items-start w-100 border rounded p-3">
													<input class="form-check-input mt-1" type="checkbox"
														name="housing_columns[]" value="{{ $column }}">

													<span class="form-check-label ms-3 w-100">
														<strong class="d-block fs-6 searchable-label">
															{{ $assessmentMeta[$column]['label'] ?? ucwords(str_replace('_', ' ', $column)) }}
														</strong>

														<small class="text-muted d-block mt-1 searchable-column">
															{{ $assessmentMeta[$column]['hint'] ?? '' }}
														</small>

														@if(!empty($assessmentMeta[$column]['hint']) && trim($assessmentMeta[$column]['hint']) !== '')
															<small class="text-info d-block mt-2 searchable-hint">
																{{ $column }}
															</small>
														@endif
													</span>
												</label>
											</div>
										@empty
											<div class="col-12">
												<div class="alert alert-warning mb-0">
													لا توجد حقول متاحة في جدول housing_units
												</div>
											</div>
										@endforelse
									</div>
								</div>
							</div>
						</div>
					</div>



				</form>
			</div>
		</div>
	</div>


@endsection

@section('script')


	<script>
		let exportInterval = null;
		let isDownloaded = false;

		function resetFilters() {
			$('.filter-select2').val(null).trigger('change');
		}

		function toggleVisibleGroup(listId, inputName, checked) {
			const list = document.getElementById(listId);
			if (!list) return;

			const visibleItems = list.querySelectorAll('.column-item');

			visibleItems.forEach(function (item) {
				if (item.style.display !== 'none') {
					const checkbox = item.querySelector('input[name="' + inputName + '"]');
					if (checkbox) {
						checkbox.checked = checked;
					}
				}
			});
		}

		function filterColumns(inputId, listId, counterId) {
			const input = document.getElementById(inputId);
			const list = document.getElementById(listId);
			const counter = document.getElementById(counterId);

			if (!input || !list || !counter) return;

			const filter = input.value.toLowerCase().trim();
			const items = list.querySelectorAll('.column-item');

			let visibleCount = 0;

			items.forEach(function (item) {
				const text = item.innerText.toLowerCase();

				if (text.includes(filter)) {
					item.style.display = '';
					visibleCount++;
				} else {
					item.style.display = 'none';
				}
			});

			counter.innerText = visibleCount;
		}

		function clearSearch(inputId, listId, counterId) {
			const input = document.getElementById(inputId);
			if (!input) return;

			input.value = '';
			filterColumns(inputId, listId, counterId);
			input.focus();
		}

		function filterFilterCards() {
			const input = document.getElementById('filterSearch');
			const counter = document.getElementById('filterCardsCounter');
			if (!input || !counter) return;

			const filter = input.value.toLowerCase().trim();
			const items = document.querySelectorAll('#filtersCardsList .filter-card-item');

			let visibleCount = 0;

			items.forEach(function (item) {
				const text = item.innerText.toLowerCase();

				if (text.includes(filter)) {
					item.style.display = '';
					visibleCount++;
				} else {
					item.style.display = 'none';
				}
			});

			counter.innerText = visibleCount;
		}

		function clearFilterSearch() {
			const input = document.getElementById('filterSearch');
			if (!input) return;

			input.value = '';
			filterFilterCards();
			input.focus();
		}

		function stopExportInterval() {
			if (exportInterval) {
				clearInterval(exportInterval);
				exportInterval = null;
			}
		}

		function enableExportButtons() {
			$('.export-btn').prop('disabled', false);
		}

		function disableExportButtons() {
			$('.export-btn').prop('disabled', true);
		}

		function showPreparingCard() {
			$('#exportResult').html(`
				<div class="card p-4 text-center">
					<h5 class="mb-3">
						⏳ جاري تجهيز الملف...
						<span class="spinner-border spinner-border-sm ms-2"></span>
					</h5>

					<div class="progress mb-3" style="height: 25px;">
						<div id="progressBar"
							 class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
							 style="width: 0%">
							0%
						</div>
					</div>

					<div id="processedCount" class="text-muted small mt-2"></div>
				</div>
			`);
		}

		function showError(message) {
			$('#exportResult').html(`
				<div class="alert alert-danger text-center">
					${message}
				</div>
			`);

			enableExportButtons();
			stopExportInterval();
			isDownloaded = false;
		}

		function showSuccess(fileUrl) {
			$('#exportResult').html(`
				<div class="alert alert-success text-center">
					<div class="mb-3">✅ تم تجهيز الملف بنجاح</div>
					<a href="${fileUrl}" class="btn btn-success" target="_blank">
						تحميل الملف
					</a>
				</div>
			`);

			enableExportButtons();
			stopExportInterval();
			isDownloaded = true;
		}

		function updateProgress(progress, processed) {
			progress = parseInt(progress || 0);
			processed = parseInt(processed || 0);

			$('#progressBar')
				.css('width', progress + '%')
				.text(progress + '%');

			$('#processedCount').text('عدد السجلات المعالجة: ' + processed);
		}

		function startCheckingExport(exportId) {
			stopExportInterval();

			exportInterval = setInterval(function () {
				$.ajax({
					url: "/exports/check/" + exportId,
					type: "GET",
					success: function (response) {
						updateProgress(response.progress, response.processed);

						if (response.status === 'finished' && response.file) {
							showSuccess(response.file);
						} else if (response.status === 'failed') {
							showError('فشل التصدير.');
						} else if (response.status === 'cancelled') {
							showError('تم إلغاء التصدير.');
						}
					},
					error: function () {
						showError('تعذر التحقق من حالة التصدير.');
					}
				});
			}, 2000);
		}

		function restartExport(formData) {
			$.ajax({
				url: "{{ url('exports/start') }}",
				type: "POST",
				data: formData,
				success: function (newRes) {
					if (newRes.status) {
						toastr.success(newRes.message || 'تم بدء التصدير');
						startCheckingExport(newRes.export_id);
					} else {
						enableExportButtons();
						toastr.error(newRes.message || 'فشل بدء التصدير');
					}
				},
				error: function (xhr) {
					enableExportButtons();
					toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء إعادة بدء التصدير');
				}
			});
		}

		document.addEventListener('DOMContentLoaded', function () {
			filterColumns('buildingSearch', 'buildingColumnsList', 'buildingCounter');
			filterColumns('housingSearch', 'housingColumnsList', 'housingCounter');
			filterFilterCards();

			$('.filter-select2').select2({
				width: '100%',
				dir: 'rtl',
				closeOnSelect: false,
				placeholder: 'اختر القيم'
			});

			const collapse = document.getElementById('filtersCollapse');
			const btn = document.getElementById('toggleFiltersBtn');

			if (collapse && btn) {
				collapse.addEventListener('shown.bs.collapse', function () {
					btn.innerHTML = '<i class="fas fa-chevron-down me-1"></i> إخفاء';
				});

				collapse.addEventListener('hidden.bs.collapse', function () {
					btn.innerHTML = '<i class="fas fa-chevron-left me-1"></i> إظهار';
				});
			}

			$('.export-btn').on('click', function (e) {
				e.preventDefault();

				if ($('.export-btn').prop('disabled')) return;

				const exportType = $(this).data('type');
				const formData = $('#exportForm').serializeArray();
				formData.push({ name: 'export_type', value: exportType });

				disableExportButtons();
				isDownloaded = false;
				stopExportInterval();
				showPreparingCard();

				$.ajax({
					url: "{{ url('exports/start') }}",
					type: "POST",
					data: formData,
					success: function (response) {
						if (response.status) {
							toastr.success(response.message || 'تم بدء التصدير');
							startCheckingExport(response.export_id);
						} else {
							enableExportButtons();
							toastr.error(response.message || 'فشل بدء التصدير');
						}
					},
					error: function (xhr) {
						const res = xhr.responseJSON;

						if (xhr.status === 409 && res?.needs_cancel) {
							stopExportInterval();

							Swal.fire({
								title: 'يوجد تصدير جارٍ',
								html: `
									<div class="text-center">
										<p>${res.message}</p>
										<p>التقدم الحالي: ${res.running_export.progress ?? 0}%</p>
									</div>
								`,
								icon: 'warning',
								showCancelButton: true,
								confirmButtonText: 'إلغاء التصدير القديم وبدء الجديد',
								cancelButtonText: 'إغلاق'
							}).then((result) => {
								if (result.isConfirmed) {
									$.ajax({
										url: "/exports/" + res.running_export.id + "/cancel",
										type: "POST",
										data: {
											_token: "{{ csrf_token() }}"
										},
										beforeSend: function () {
											Swal.showLoading();
										},
										success: function (cancelRes) {
											toastr.success(cancelRes.message || 'تم إلغاء التصدير القديم');
											showPreparingCard();
											restartExport(formData);
										},
										error: function (cancelXhr) {
											enableExportButtons();
											toastr.error(cancelXhr.responseJSON?.message || 'فشل إلغاء التصدير القديم');
										}
									});
								} else {
									enableExportButtons();
									$('#exportResult').html('');
								}
							});

							return;
						}

						enableExportButtons();
						toastr.error(res?.message || 'حدث خطأ غير متوقع');
					}
				});
			});
		});
	</script>
@endsection