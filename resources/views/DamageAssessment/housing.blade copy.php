@extends('layouts.app')
@section('title', 'الوحدات السكنية')
@section('pageName', 'الوحدات السكنية')


@section('content')

	<div class="card mb-12">
		<div class="card shadow-sm">
			<div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse"
				data-bs-target="#kt_housing_filter">
				<h3 class="card-title">فلتر</h3>
				<div class="card-toolbar rotate-180">
					<i class="ki-duotone ki-down fs-1"></i>
				</div>
			</div>
			<div id="kt_housing_filter" data-kt-Housing-table-filter="form" class="collapse show">


				<div class="card-body">
					<form id="filter_housing_form" class="form" action="#">
						<div class="row g-9 mb-8">

							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2">الإسم الأول</label>
								<input type="text" class="form-control form-control-solid" placeholder="الإسم الأول"
									name="q_9_3_1_first_name">
							</div>
							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2">إسم الأب</label>
								<input type="text" class="form-control form-control-solid" placeholder="إسم الأب"
									name="q_9_3_2_second_name__father">
							</div>
							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2">إسم الجد</label>
								<input type="text" class="form-control form-control-solid" placeholder="إسم الجد"
									name="q_9_3_3_third_name__grandfather">
							</div>
							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2">الإسم الأخير</label>
								<input type="text" class="form-control form-control-solid" placeholder="الإسم الأخير"
									name="q_9_3_4_last_name">
							</div>

						</div>
						<div class="row g-9 mb-8">

							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2">رقم المبنى</label>
								<input type="number" class="form-control form-control-solid" placeholder="رقم المبنى"
									name="objectid">
							</div>


							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2">رقم الزون</label>
								<input type="text" class="form-control form-control-solid" placeholder="رقم الزون"
									name="zone_code">
							</div>
							<!--begin::Col-->
							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2">نوع الوحدة السكنية</label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder="نوع الوحدة السكنية" name="housing_unit_type">
									<option value=""></option>
									<option value="basement">بدروم / basement</option>
									<option value="apartment">شقة / apartment</option>
									<option value="roof">لروف / roof</option>
									<option value="warehouse">لحاصل / warehouse</option>
									<option value="canopy2">لمظلة / canopy</option>
									<option value="mezzanine">سدة / mezzanine</option>

								</select>
							</div>
							<!--end::Col-->
							<!--begin::Col-->
							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2">حالة الضرر</label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder="حالة الضرر" name="unit_damage_status">
									<option value=""></option>
									<option value="no_damage"> No damage لا يوجد ضرر</option>
									<option value="damaged">Damaged يوجد ضرر</option>

								</select>
							</div>
							<!--end::Col-->


						</div>
						<div class="row g-9 mb-8">
							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2"> وظيفة المالك </label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder=" وظيفة المالك" name="owner_job">
									<option value=""></option>
									<option value="employed"> موظف / Employed</option>
									<option value="freelancer"> عمل حر / Freelancer</option>
									<option value="unemployed2">غير موظف / Unemployed</option>
									<option value="other_job"> أخرى / Other</option>

								</select>
							</div>
							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2"> الحالة الاجتماعية للمالك</label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder="الحالة الاجتماعية للمالك"
									name="marital_status">
									<option value=""></option>
									<option value="Single2"> Single / أعزب</option>
									<option value="Divorced">Divorced / مطلق/ة</option>
									<option value="Widow">Widow / أرمل/ة</option>
									<option value="Married">Married / متزوج/ة</option>
								</select>
							</div>
							<!--begin::Col-->
							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2"> محل الإقامة الحالي</label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder="محل الإقامة الحالي" name="current_residence">
									<option value=""></option>
									<option value="rented2">Rented accommodation سكن مستأجر</option>
									<option value="hosted2">With relatives / hosted عند أقارب / مستضاف</option>
									<option value="tent2">Tent خيمة</option>
									<option value="collective_shelter2">Collective shelter مركز إيواء جماعي</option>
									<option value="public_facility2">Public facility مرفق عام</option>
									<option value="informal2">Informal shelter سكن غير رسمي</option>
									<option value="other_current2">Other أخرى</option>
								</select>
							</div>
							<!--end::Col-->

							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2"> نوع استخدام الوحدة المتضررة</label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder=" نوع استخدام الوحدة المتضررة"
									name="infra_type2">
									<option value=""></option>
									<option value="Housing"> Housing سكني</option>
									<option value="Economic">Economic اقتصادي</option>
									<option value="Social">Social اجتماعي</option>


								</select>
							</div>


						</div>
						<div class="row g-9 mb-8">

							<div class="col-md-3 fv-row">
								<label class="required fs-6 fw-semibold mb-2">نوع المأوى</label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder=" نوع المأوى" name="shelter_type">
									<option value=""></option>
									<option value="school"> School مدرسة</option>
									<option value="public_building">Public building مبنى عام</option>
									<option value="hospital">Hospital مستشفى</option>
									<option value="public_service_facility">Public service facility مرافق عامة</option>
									<option value="park">Park حديقة</option>
									<option value="Private_Land"> أرض خاصة / Private Land</option>
									<option value="playground">Playground ملعب</option>
									<option value="camp"> مخيم / Camp</option>
									<option value="other_shelter">Other أخرى</option>

								</select>
							</div>
							
						</div>
					</form>
				</div>
				<div class="card-footer">
					<div class="text-center">
						<button type="reset" class="btn btn-light me-3" data-kt-housing-filter-action="reset">إعادة
							تعيين</button>
						<button onclick="$('#kt_table_Housing').DataTable().ajax.reload()" type="submit"
							class="btn btn-primary" data-kt-Housing-table-filter="filter">
							<span class="indicator-label">بحث</span>
							<span class="indicator-progress">يرجى الإنتظار...
								<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
						</button>
					</div>
				</div>
			</div>
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
						<input type="text" data-kt-Housing-table-filter="search"
							class="form-control form-control-solid w-250px ps-13" placeholder="بحث" />
					</div>
					<!--end::Search-->
				</div>
				<!--begin::Card title-->
				<!--begin::Card toolbar-->
				<div class="card-toolbar">
					<!--begin::Toolbar-->
					<div class="d-flex justify-content-end" data-kt-Housing-table-toolbar="base">
						<!--begin::Filter-->
						<button type="button" class="btn btn-light-primary me-3"
							onclick="$('#kt_table_Housing').DataTable().ajax.reload()">
							<i class=" ki-reload fs-2">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
							تحديث</button>
						<!--begin::Menu 1-->

						<!--begin::Export-->
						<button type="button" class="btn btn-light-primary me-3" data-bs-toggle="modal"
							data-bs-target="#kt_modal_export_housing">
							<i class="ki-duotone ki-exit-up fs-2">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>تصدير</button>
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
						data-kt-Housing-table-toolbar="selected">
						<div class="fw-bold me-5">
							<span class="me-2" data-kt-Housing-table-select="selected_count"></span>Selected
						</div>
						<button type="button" class="btn btn-danger" data-kt-Housing-table-select="delete_selected">حذف
							المحدد</button>
					</div>
					<!--end::Group actions-->
					<!--begin::Modal - Adjust Balance-->
					<div class="modal fade" id="kt_modal_export_housing" tabindex="-1" aria-hidden="true">
						<!--begin::Modal dialog-->
						<div class="modal-dialog modal-dialog-centered mw-650px">
							<!--begin::Modal content-->
							<div class="modal-content">
								<!--begin::Modal header-->
								<div class="modal-header">
									<!--begin::Modal title-->
									<h2 class="fw-bold">تصدير المباني </h2>
									<!--end::Modal title-->
									<!--begin::Close-->
									<div class="btn btn-icon btn-sm btn-active-icon-primary"
										data-kt-housing-modal-action="close">
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
									<form id="kt_modal_export_housing_form" class="form" action="#">
										<input type="hidden" name="_method" value="get">
										<input type="hidden" name="_token" value="{{csrf_token()}}">
										<!--begin::Input group-->
										<div class="fv-row mb-10">
											<!--begin::Label-->
											<label class="fs-6 fw-semibold form-label mb-2"> تحديد الأعمدة </label>
											<!--end::Label-->
											<!--begin::Input-->
											<select multiple data-allow-clear="true" data-close-on-select="false"
												name="housing_columns[]" data-control="select2"
												data-placeholder="تحديد الأعمدة" data-hide-search="false"
												class="form-select form-select-solid fw-bold">
												<option value=""></option>
												@foreach ($assessments as $value)
													@if(Schema::hasColumn('housing_units', $value->name))
														<option value="{{ $value->name }}">
															{{ $value->hint ? $value->hint : $value->label }}
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
												تحديد شكل التصدير:</label>
											<!--end::Label-->
											<!--begin::Input-->
											<select name="format" data-control="select2"
												data-placeholder="تحديد شكل التصدير" data-hide-search="false"
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
												data-kt-housing-modal-action="close">إلغاء</button>
											<button type="submit" class="btn btn-primary"
												data-kt-housing-modal-action="submit">
												<span class="indicator-label">تصدير</span>
												<span class="indicator-progress">يرجى الإنتظار...
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
					id="kt_table_Housing">
					<thead>
						<tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">

							<th class="min-w-10px">إسم الباحث  </th>
							<th class="min-w-10px">رقم المبنى </th>
							<th class="min-w-70px">إسم المالك </th>
							<th class="min-w-70px">نوع الوحدة السكنية</th>
							<th class="min-w-70px">حالة الضرر</th>
							<th class="min-w-20px"> رقم الطابق </th>
							<th class="min-w-100px"> رقم الوحدة السكنية</th>
							<th class="min-w-100px"> إتجاه الوحدة السكنية</th>
							<th class="min-w-100px">مساحة الوحدة المتضررة</th>
							<th class="min-w-100px">نوع استخدام الوحدة المتضررة </th>
							<th class="min-w-100px">نوع ملكية الوحدة المتضررة </th>
							<th class="text-end min-w-100px">  الإجراءات</th>
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
		var post_export_url = "{{ url('export_housings') }}" </script>
	<script src="{{ url('') }}/assets/js/custom/DamageAssessment/export-housings.js"></script>

	<script>
		var KTHousingList = function () {
			// Define shared variables
			var table = document.getElementById('kt_table_Housing');
			var kt_housing_filter = document.getElementById('kt_housing_filter');
			var datatable;
			var toolbarBase;
			var toolbarSelected;
			var selectedCount;

			// Private functions
			var initHousingTable = function () {
				// Set date data order
				const tableRows = table.querySelectorAll('tbody tr');
				const globalid = '{{$globalid  }}';


				// Init datatable --- more info on datatables: https://datatables.net/manual/
				datatable = $(table).DataTable({
					serverSide: true,
					ajax: {
						url: "{{url('housing/show')}}",
						data: function (d) {
							d.housing_unit_type = $("[name='housing_unit_type']").val()
							d.unit_damage_status = $("[name='unit_damage_status']").val()
							d.infra_type2 = $("[name='infra_type2']").val()
							d.owner_job = $("[name='owner_job']").val()
							d.marital_status = $("[name='marital_status']").val()
							d.locality = $("[name='locality']").val()
							d.current_residence = $("[name='current_residence']").val()
							d.governorate = $("[name='governorate']").val()
							d.neighborhood = $("[name='neighborhood']").val()
							d.objectid = $("[name='objectid']").val()
							d.parentglobalid = globalid 
							d.zone_code = $("[name='zone_code']").val()
							d.shelter_type = $("[name='shelter_type']").val()
							d.q_9_3_1_first_name = $("[name='q_9_3_1_first_name']").val()
							d.q_9_3_2_second_name__father = $("[name='q_9_3_2_second_name__father']").val()
							d.q_9_3_3_third_name__grandfather = $("[name='q_9_3_3_third_name__grandfather']").val()
							d.q_9_3_4_last_name = $("[name='q_9_3_4_last_name']").val()



						},
					},
					"info": true,
					'order': [],
					"pageLength": 4,
					"lengthChange": true,
					columns: [
						{ data: 'assignedto', name: 'assignedto', searchable: true },
						{ data: 'objectid', name: 'objectid', searchable: true },
						{ data: 'full_name', name: 'full_name', searchable: false },
						{ data: 'housing_unit_type', name: 'housing_unit_type', searchable: true },
						{ data: 'unit_damage_status', name: 'unit_damage_status', searchable: true },
						{ data: 'floor_number', name: 'floor_number', searchable: true },
						{ data: 'housing_unit_number', name: 'housing_unit_number', searchable: true },
						{ data: 'unit_direction', name: 'unit_direction', searchable: true },
						{ data: 'damaged_area_m2', name: 'damaged_area_m2', searchable: true },
						{ data: 'infra_type2', name: 'infra_type2', searchable: true },
						{ data: 'house_unit_ownership', name: 'house_unit_ownership', searchable: true },
						{ data: 'action', name: 'action', searchable: false },

					],
				});

				// Re-init functions on every table re-draw -- more info: https://datatables.net/reference/event/draw
				datatable.on('draw', function () {

					KTMenu.createInstances(); // For Metronic		

				});
			}



			var handleSearchDatatable = () => {
				const filterSearch = document.querySelector('[data-kt-Housing-table-filter="search"]');
				filterSearch.addEventListener('keyup', function (e) {
					datatable.search(e.target.value).draw();
				});
			}

			// Filter Datatable
			var handleFilterDatatable = () => {
				// Select filter options
				const filterForm = document.querySelector('[data-kt-Housing-table-filter="form"]');
				const filterButton = filterForm.querySelector('[data-kt-Housing-table-filter="filter"]');
				const selectOptions = filterForm.querySelectorAll('select');

				// Filter datatable on submit
				filterButton.addEventListener('click', function () {
					filterButton.setAttribute('data-kt-indicator', 'on');
					filterButton.disabled = true;
					setTimeout(function () {
						filterButton.removeAttribute('data-kt-indicator', 'on');
						filterButton.disabled = false;
						datatable.search('').draw();

					}, 1000)



				});
			}

			// Reset Filter
			var handleResetForm = () => {
				// Select reset button
				const resetButton = document.querySelector('[data-kt-housing-filter-action="reset"]');


				// Reset datatable
				resetButton.addEventListener('click', function () {

					// Select filter options
					const filterForm = document.querySelector('[data-kt-Housing-table-filter="form"]');
					const selectOptions = filterForm.querySelectorAll('select');
					const inputs = filterForm.querySelectorAll('input');
					resetButton.setAttribute('data-kt-indicator', 'on');
					resetButton.disabled = true;

					setTimeout(function () {
						selectOptions.forEach(select => {
							$(select).val('').trigger('change');
						});
						inputs.forEach(inputs => {
							$(inputs).val('');
						});
						datatable.search('').draw();
						resetButton.removeAttribute('data-kt-indicator', 'on');
						resetButton.disabled = false;
						datatable.search('').draw();

					}, 20)
					// Reset select2 values -- more info: https://select2.org/programmatic-control/add-select-clear-items


					// Reset datatable --- official docs reference: https://datatables.net/reference/api/search()
				});
			}

			return {
				// Public functions  
				init: function () {
					if (!table) {
						return;
					}

					initHousingTable();
					handleSearchDatatable();
					handleResetForm();
					handleFilterDatatable();

				}
			}
		}();


		KTUtil.onDOMContentLoaded(function () {
			KTHousingList.init();


		});



	</script>
@endsection