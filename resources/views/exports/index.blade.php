@extends('layouts.app')

@section('content')
	<div class="container py-4">

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

				<form action="{{ route('export.data.download') }}" method="POST">
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
									عرض
								</button>

								<button type="button" class="btn btn-sm btn-light-danger" onclick="resetFilters()">
									<i class="fas fa-times me-1"></i>
									مسح الفلاتر
								</button>
							</div>
						</div>

						<div class="collapse " id="filtersCollapse">
							<div class="card-body">
								<div class="row">
									@foreach($filters as $listName => $items)
										<div class="col-md-4 mb-4">
											<label class="form-label fw-bold">
												{{ ucwords(str_replace('_', ' ', $listName)) }}
											</label>

											<select name="filters[{{ $listName }}][]"
												class="form-select form-select-solid filter-select2" multiple
												data-placeholder="اختر {{ ucwords(str_replace('_', ' ', $listName)) }}">
												@foreach($items as $item)
													<option value="{{ $item->name }}">
														{{ $item->label ?? $item->name }}
													</option>
												@endforeach
											</select>
										</div>
									@endforeach
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

					<div class="d-flex justify-content-end mt-4">
						<button type="submit" class="btn btn-success">
							تصدير CSV
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>


@endsection

@section('script')
	<script>
		function resetFilters() {
			$('.filter-select2').val(null).trigger('change');
		}

		function toggleVisibleGroup(listId, inputName, checked) {
			const list = document.getElementById(listId);
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
			const filter = input.value.toLowerCase().trim();
			const list = document.getElementById(listId);
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

			document.getElementById(counterId).innerText = visibleCount;
		}

		function clearSearch(inputId, listId, counterId) {
			const input = document.getElementById(inputId);
			input.value = '';
			filterColumns(inputId, listId, counterId);
			input.focus();
		}

		document.addEventListener('DOMContentLoaded', function () {
			filterColumns('buildingSearch', 'buildingColumnsList', 'buildingCounter');
			filterColumns('housingSearch', 'housingColumnsList', 'housingCounter');

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
					btn.innerHTML = '<i class="fas fa-chevron-down me-1"></i> عرض';
				});

				collapse.addEventListener('hidden.bs.collapse', function () {
					btn.innerHTML = '<i class="fas fa-chevron-left me-1"></i> عرض';
				});
			}
		});
	</script>
@endsection