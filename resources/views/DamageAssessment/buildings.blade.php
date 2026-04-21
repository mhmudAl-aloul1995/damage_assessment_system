@extends('layouts.app')
@section('title', __('ui.buildings_page.title'))
@section('pageName', __('ui.buildings_page.title'))


@section('content')


	<div class="card mb-12">
		<div class="card shadow-sm">
			<div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse"
				data-bs-target="#kt_building_filter">
				<h3 class="card-title">{{ __('ui.buildings_page.filter') }}</h3>
				<div class="card-toolbar rotate-180">
					<i class="ki-duotone ki-down fs-1"></i>
				</div>
			</div>
			<form id="filter_buliding_form" class="form" data-kt-Building-table-filter="form" action="#">

				<div id="kt_building_filter" class="collapse show">


					<div class="card-body">
						<div class="row g-9 mb-8">
							<!--begin::Col-->
							@foreach ($filterName as $filter => $value)

								@if (Schema::hasColumn('buildings', $value))

									<div class="col-md-3 fv-row">
										<label class="fs-6 fw-semibold mb-2">{{ $filter }}</label>
										<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
											data-hide-search="false" data-placeholder="{{ $filter }}" name="{{ $value }}">

											<option value=""></option>
											@foreach (($groupedFilters[$value] ?? collect()) as $option)
												<option value="{{ $option->name }}">{{ $option->label }}</option>
											@endforeach
										</select>
									</div>
								@endif
							@endforeach
							<div class="col-md-3 fv-row">

								<label class="fs-6 fw-semibold mb-2">{{ __('ui.buildings_page.neighborhood') }}</label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder="{{ __('ui.buildings_page.neighborhood') }}" name="neighborhood">

									<option value=""></option>
									@foreach ($neighborhoods as $value)
										<option value="{{ $value }}">{{ $value }}</option>
									@endforeach
								</select>

							</div>
						</div>


					</div>
					<div class="card-footer">
						<div class="text-center">
							<button type="reset" class="btn btn-light me-3" data-kt-Buildings-filter-action="reset">{{ __('ui.buildings_page.reset') }}</button>
							<button onclick="$('#kt_table_Building').DataTable().ajax.reload()" type="submit"
								class="btn btn-primary" data-kt-Building-table-filter="filter">
								<span class="indicator-label">{{ __('ui.buildings_page.search') }}</span>
								<span class="indicator-progress">{{ __('ui.buildings_page.please_wait') }}
									<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
							</button>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<div class="card mb-12">

		<div class="card">
			<!--begin::Card header-->
			<div class="card-header border-0 pt-6">
				<!--begin::Card title-->
				<div class="card-title">
					<!--begin::Search-->
					<div class="d-flex align-items-center position-relative my-1">
						<i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
							<span class="path1"></span>
							<span class="path2"></span>
						</i>
						<input type="text" data-kt-Building-table-filter="search"
							class="form-control form-control-solid w-250px ps-13" placeholder="{{ __('ui.buildings_page.search') }}" />
					</div>
					<!--end::Search-->
				</div>
				<!--begin::Card title-->
				<!--begin::Card toolbar-->
				<div class="card-toolbar">
					<!--begin::Toolbar-->
					<div class="d-flex justify-content-end" data-kt-Building-table-toolbar="base">
						<!--begin::Filter-->
						<button type="button" class="btn btn-light-primary me-3"
							onclick="$('#kt_table_Building').DataTable().ajax.reload()">
							<i class=" ki-reload fs-2">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
							{{ __('ui.buildings_page.refresh') }}</button>
						<!--begin::Menu 1-->

						<!--begin::Export-->
						<button type="button" class="btn btn-light-primary me-3" data-bs-toggle="modal"
							data-bs-target="#kt_modal_export_buildings">
							<i class="ki-duotone ki-exit-up fs-2">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>{{ __('ui.buildings_page.export') }}</button>
						<!--end::Export-->
						<!--begin::Add Building-->
						<!-- <button type="button" class="btn btn-primary" data-bs-toggle="modal" onclick="resetFormValidation()"
																																																																data-bs-target="#kt_modal_Building">
																																																																<i class="ki-duotone ki-plus fs-2"></i> إضافة جديد</button> -->
						<!--end::Add Building-->
					</div>
					<!--end::Toolbar-->
					<!--begin::Group actions-->
					<div class="d-flex justify-content-end align-items-center d-none"
						data-kt-Building-table-toolbar="selected">
						<div class="fw-bold me-5">
							<span class="me-2" data-kt-Building-table-select="selected_count"></span>Selected
						</div>
						<button type="button" class="btn btn-danger" data-kt-Building-table-select="delete_selected">{{ __('ui.buildings_page.delete_selected') }}</button>
					</div>
					<!--end::Group actions-->
					<!--begin::Modal - Adjust Balance-->
					<div class="modal fade" id="kt_modal_export_buildings" tabindex="-1" aria-hidden="true">
						<!--begin::Modal dialog-->
						<div class="modal-dialog modal-dialog-centered mw-650px">
							<!--begin::Modal content-->
							<div class="modal-content">
								<!--begin::Modal header-->
								<div class="modal-header">
									<!--begin::Modal title-->
									<h2 class="fw-bold">{{ __('ui.buildings_page.export_buildings') }}</h2>
									<!--end::Modal title-->
									<!--begin::Close-->
									<div class="btn btn-icon btn-sm btn-active-icon-primary"
										data-kt-userss-modal-action="close">
										<i class="ki-duotone ki-cross fs-1">
											<span class="path1"></span>
											<span class="path2"></span>
										</i>
									</div>
									<!--end::Close-->
								</div>
								<!--end::Modal header-->
								<!--begin::Modal body-->
								<div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
									<!--begin::Form-->
									<form id="kt_modal_export_buildings_form" class="form" action="#">
										<input type="hidden" name="_method" value="get">
										<input type="hidden" name="_token" value="{{csrf_token()}}">
										<!--begin::Input group-->
										<div class="fv-row mb-10">
											<!--begin::Label-->
											<label class="fs-6 fw-semibold form-label mb-2">{{ __('ui.buildings_page.select_columns') }}</label>
											<!--end::Label-->
											<!--begin::Input-->
											<select multiple data-allow-clear="true" data-close-on-select="false"
												name="building_columns[]" data-control="select2"
												data-placeholder="{{ __('ui.buildings_page.select_columns') }}" data-hide-search="false"
												class="form-select form-select-solid fw-bold">
												<option value=""></option>
												@foreach ($assessments as $value)
													@if(Schema::hasColumn('buildings', $value->name))
														<option value="{{ $value->name }}">
															{{ $value->label . ' ' . $value->hint }}
														</option>
													@endif
												@endforeach

											</select>
											<!--end::Input-->
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="fv-row mb-10">
											<!--begin::Label-->
											<label class="required fs-6 fw-semibold form-label mb-2">
												{{ __('ui.buildings_page.export_format') }}:</label>
											<!--end::Label-->
											<!--begin::Input-->
											<select name="format" data-control="select2"
												data-placeholder="{{ __('ui.buildings_page.export_format') }}" data-hide-search="false"
												class="form-select form-select-solid fw-bold">
												<option></option>
												<option value="XLSX">Excel</option>
												<option value="pdf">PDF</option>
												<option value="csv">CSV</option>
											</select>
											<!--end::Input-->
										</div>
										<!--end::Input group-->
										<!--begin::Actions-->
										<div class="text-center">
											<button type="reset" class="btn btn-light me-3"
												data-kt-buildings-modal-action="close">{{ __('ui.buildings_page.cancel') }}</button>
											<button type="submit" class="btn btn-primary"
												data-kt-buildings-modal-action="submit">
												<span class="indicator-label">{{ __('ui.buildings_page.export') }}</span>
												<span class="indicator-progress">{{ __('ui.buildings_page.please_wait') }}
													<span
														class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
											</button>
										</div>
										<!--end::Actions-->
									</form>
									<!--end::Form-->
								</div>
								<!--end::Modal body-->
							</div>
							<!--end::Modal content-->
						</div>
						<!--end::Modal dialog-->
					</div>
					<!--end::Modal - New Card-->
					<!--begin::Modal - Add task-->


					<!--end::Modal - Add task-->
				</div>
				<!--end::Card toolbar-->
			</div>
			<!--end::Card header-->
			<!--begin::Card body-->
			<div class="card-body py-4">
				<!--begin::Table-->
				<table class="table  table-rounded  table-striped align-middle table-row-dashed fs-6 gy-5"
					id="kt_table_Building">
					<thead>
						<tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">

							<th class="min-w-70px">{{ __('ui.buildings_page.researcher_name') }}</th>
							<th class="min-w-70px">{{ __('ui.buildings_page.survey_status') }}</th>
							<th class="min-w-70px">{{ __('ui.buildings_page.building_number') }}</th>
							<th class="min-w-70px">{{ __('ui.buildings_page.building_name') }}</th>
							<th class="min-w-70px">{{ __('ui.buildings_page.zone_number') }}</th>
							<th class="min-w-70px">{{ __('ui.buildings_page.damaged_units_count') }}</th>
							<th class="min-w-70px">{{ __('ui.buildings_page.municipality') }}</th>
							<th class="min-w-70px">{{ __('ui.buildings_page.district') }}</th>
							<th class="min-w-70px">{{ __('ui.buildings_page.updated_at') }}</th>
							<th class="text-end min-w-100px">{{ __('ui.buildings_page.action') }}</th>
						</tr>
					</thead>
					<tbody class="text-gray-600 fw-semibold"></tbody>



				</table>
				<!--end::Table-->
			</div>
			<!--end::Card body-->
		</div>
	</div>

@endsection





@section('script')



	<script>
		var url_phc = "{{ url('') }}";
		var post_export_url = "{{ url('export_building') }}"
	</script>
	<script src="{{ url('') }}/assets/js/custom/DamageAssessment/export-buildings.js"></script>

	<script>
		var KTBuildingsList = function () {
			var table = document.getElementById('kt_table_Building');
			var datatable;
			const filterForm = document.querySelector('[data-kt-Building-table-filter="form"]');
			const initialQueryParams = new URLSearchParams(window.location.search);

			if (filterForm) {
				initialQueryParams.forEach((value, key) => {
					if (key === 'search') {
						return;
					}

					const field = filterForm.querySelector(`[name="${key}"]`);

					if (field) {
						field.value = value;
						return;
					}

					const hiddenInput = document.createElement('input');
					hiddenInput.type = 'hidden';
					hiddenInput.name = key;
					hiddenInput.value = value;
					filterForm.appendChild(hiddenInput);
				});
			}

			var initBuildingTable = function () {
				if (!table) return;

				datatable = $(table).DataTable({
					serverSide: true,
					processing: true,
					pageLength: 10, // Increased from 4 for better UX
					order: [[8, 'desc']], // 👈 العمود رقم 3 (building_name)

					ajax: {
						url: "{{url('building/show')}}",
						// Dynamic data collection: Replaces 15+ manual lines with a loop
						data: function (d) {
							if (filterForm) {
								const formData = new FormData(filterForm);
								formData.forEach((value, key) => {
									d[key] = value;
								});
							}
						},
					},
					columns: [{
						data: 'assignedto',
						name: 'assignedto'
					},
					{
						data: 'field_status',
						name: 'field_status'
					},
					{
						data: 'objectid',
						name: 'objectid'
					},
					{
						data: 'building_name',
						name: 'building_name'
					},
					{
						data: 'zone_code',
						name: 'zone_code'
					},
					{
						data: 'units_nos',
						name: 'units_nos'
					},
					{
						data: 'municipalitie',
						name: 'municipalitie'
					},
					{
						data: 'neighborhood',
						name: 'neighborhood'
					},
					{
						data: 'editdate',
						name: 'editdate'
					},
					{
						data: 'action',
						responsivePriority: -1,
						className: 'text-end',
						orderable: false,
						searchable: false
					},
					],
				});

				datatable.on('draw', function () {
					KTMenu.createInstances(); // Vital for Metronic dropdowns
				});

				const initialSearch = initialQueryParams.get('search');

				if (initialSearch) {
					const searchInput = document.querySelector('[data-kt-Building-table-filter="search"]');

					if (searchInput) {
						searchInput.value = initialSearch;
					}

					datatable.search(initialSearch).draw();
				}
			};

			var handleSearchDatatable = () => {
				const filterSearch = document.querySelector('[data-kt-Building-table-filter="search"]');
				if (!filterSearch) return;

				filterSearch.addEventListener('keyup', function (e) {
					datatable.search(e.target.value).draw();
				});
			};

			var handleFilterDatatable = () => {
				const filterForm = document.querySelector('[data-kt-Building-table-filter="form"]');

				// Check 1: Does the form/container exist?
				if (!filterForm) return;

				const filterButton = filterForm.querySelector('[data-kt-Building-table-filter="filter"]');

				// Check 2: Does the button exist inside that form?
				if (!filterButton) {
					console.warn('Filter button "[data-kt-Building-table-filter=\"filter\"]" not found inside the form.');
					return;
				}

				filterButton.addEventListener('click', function () {
					filterButton.setAttribute('data-kt-indicator', 'on');
					filterButton.disabled = true;

					datatable.ajax.reload(() => {
						filterButton.removeAttribute('data-kt-indicator');
						filterButton.disabled = false;
					}, false); // 'false' maintains current paging
				});
			};


			var handleResetForm = () => {
				const resetButton = document.querySelector('[data-kt-buildings-filter-action="reset"]');
				if (!resetButton) return;

				resetButton.addEventListener('click', function () {
					const filterForm = document.querySelector('[data-kt-Building-table-filter="form"]');

					// Efficiently reset all inputs and Select2 dropdowns
					$(filterForm).find('select').val('').trigger('change');
					$(filterForm).find('input').val('');

					datatable.search('').ajax.reload();
				});
			};

			return {
				init: function () {
					initBuildingTable();
					handleSearchDatatable();
					handleFilterDatatable();
					handleResetForm();
				}
			};
		}();

		KTUtil.onDOMContentLoaded(function () {
			KTBuildingsList.init();
		});
	</script>
@endsection


