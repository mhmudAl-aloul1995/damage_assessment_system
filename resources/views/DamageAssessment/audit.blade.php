@extends('layouts.app')
@section('title', 'الإستبيان')
@section('pageName', 'الإستبيان')


@section('content')
<style>
	/* Reduce the gap on the right of the text */
	table.dataTable thead th.sorting,
	table.dataTable thead th.sorting_asc,
	table.dataTable thead th.sorting_desc {
		padding-right: 15px !important;
		/* Adjust this value as needed */
	}
</style>
<div class="row">
	<div class="card mb-12">
		<div class="card card-flush">
			<div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse"
				data-bs-target="#kt_building_filter">
				<h3 class="card-title">فلتر</h3>
				<div class="card-toolbar rotate-180">
					<i class="ki-duotone ki-down fs-1"></i>
				</div>
			</div>
			<form id="filter_buliding_form" class="form" data-kt-Building-table-filter="form" action="#">

				<div id="kt_building_filter" class="collapse hide">


					<div class="card-body">
						<div class="row g-9 mb-8">
							<!--begin::Col-->
							@foreach ($filterName as $filter)

							@if (Schema::hasColumn('buildings', $filter))

							<div class="col-md-3 fv-row">
								<label class="fs-6 fw-semibold mb-2">{{ $filter }}</label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder="{{ $filter }}"
									name="{{ $filter }}">

									<option value=""></option>
									@foreach (App\Models\Filter::where('list_name', $filter)->get() as $option)
									<option value="{{ $option->name }}">{{ $option->label }}</option>
									@endforeach
								</select>
							</div>
							@endif
							@endforeach
							<div class="col-md-3 fv-row">

								<label class="fs-6 fw-semibold mb-2">neighborhood</label>
								<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
									data-hide-search="false" data-placeholder="neighborhood"
									name="neighborhood">

									<option value=""></option>
									@foreach ($neighborhoods as $value )
									<option value="{{ $value }}">{{ $value }}</option>
									@endforeach
								</select>

							</div>
						</div>


					</div>
					<div class="card-footer">
						<div class="text-center">
							<button type="reset" class="btn btn-light me-3" data-kt-Buildings-filter-action="reset">إعادة
								تعيين</button>
							<button onclick="$('#kt_table_Building').DataTable().ajax.reload()" type="submit"
								class="btn btn-primary" data-kt-Building-table-filter="filter">
								<span class="indicator-label">بحث</span>
								<span class="indicator-progress">يرجى الإنتظار...
									<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
							</button>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>


<div class="row">
	<div class="col-md-12 ">
		<div class="card card-flush">
			<div class="card-header align-items-center py-5 gap-2">
				<div class="card-title">
					<div class="d-flex align-items-center position-relative my-1">
						<i class="ki-duotone ki-magnifier fs-3 position-absolute ms-4"></i>
						<input type="text" id="tableSearch" class="form-control form-control-solid w-250px ps-12" placeholder="بحث المباني" />
					</div>
				</div>
				<div class="card-toolbar gap-3">
					<button onclick="refreshTable(this)" class="btn btn-success btn-sm">تحديث<i class="ki-duotone ki-update-file"></i></button>
					<button id="btn_assign_to_lawyer" class="btn btn-primary btn-sm">تعيين للمحامي <i class="ki-duotone ki-plus"></i></button>
					<button id="btn_assign_to_engineer" class="btn btn-info btn-sm">تعيين للمهندس <i class="ki-duotone ki-plus"></i></button>
				</div>
			</div>

			<div class="card-body pt-0">
				<table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_datatable_audits">
					<thead>
						<tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
							<th class="w-10px pe-2">
								<div class="form-check form-check-sm form-check-custom form-check-solid me-3">
									<input class="form-check-input" type="checkbox" data-kt-check="true"
										data-kt-check-target="#kt_datatable_audits .form-check-input" value="1" />
								</div>
							</th>

							<th>Building Name</th>
							<th>Engineer</th>
							<th>Lawyer</th>
							<th>Eng Status</th>
							<th>Legal Status</th>
							<th>Final Approval</th>
							<th>Actions </th>
						</tr>
					</thead>
				</table>
			</div>
		</div>

	</div>


</div>
<div class="modal fade" id="kt_modal_assign" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered mw-650px">
		<div class="modal-content">
			<form id="kt_modal_assign_form">
				<div class="modal-header">
					<h2 class="fw-bold" id="modal_title">تعيين المباني</h2>
					<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
						<i class="ki-duotone ki-cross fs-1"></i>
					</div>
				</div>

				{{ csrf_field() }}
				<div class="modal-body py-10 px-lg-17">
					<input type="hidden" name="type" id="assign_type">
					<input type="hidden" name="status_id" id="assign_status_id">
					<!-- Placeholder for selected IDs -->
					<div id="selected_buildings_container"></div>

					<div class="fv-row mb-7">
						<label id="user_label" class="required fs-6 fw-semibold mb-2">إختر المهندس </label>
						<select name="user_id" class="form-select form-select-solid" data-control="select2" data-placeholder="إختر الإسم..." data-dropdown-parent="#kt_modal_assign">
							<option></option>
							@foreach($users as $user)
							<option value="{{ $user->id }}">{{ $user->name }}</option>
							@endforeach
						</select>
					</div>
				</div>

				<div class="modal-footer flex-center">
					<button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">إلغاء</button>
					<button type="submit" class="btn btn-primary" id="kt_modal_assign_submit">
						<span class="indicator-label">موافق</span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

@endsection





@section('script')




<script>
	$(document).ready(function() {
		var table = $('#kt_datatable_audits').DataTable({
			processing: true,
			serverSide: true,
			ajax: "{{ route('audit.index') }}",

			columnDefs: [{
				targets: 0, // Targets the first column (checkboxes)
				orderable: false, // Disables the sorting arrow
				searchable: false
			}],
			order: [
				[1, 'desc']
			],
			columns: [{
					data: 'objectid',
					name: 'objectid',
					orderable: false,
					searchable: false,
					render: (data) => `<div class="form-check form-check-sm form-check-custom form-check-solid me-3">
									<input class="form-check-input" type="checkbox" 
										data-kt-check-target="#kt_datatable_audits .form-check-input" value="${data}" />
								</div>`
				},
				/* {
					data: 'actions',
					name: 'actions',
					orderable: false,
					searchable: false
				}, */
				{
					data: 'building_name',
					name: 'building_name'
				},
				{
					data: 'engineer',
					name: 'engineer',
					searchable: false
				},
				{
					data: 'lawyer',
					name: 'lawyer',
					searchable: false
				},
				{
					data: 'eng_status',
					name: 'eng_status'
				},
				{
					data: 'law_status',
					name: 'law_status'
				},
				{
					data: 'finalApproval',
					//render: () => '<span class="badge badge-light-warning">Pending</span>'
				},
				{

					data: 'actions'

				}
			],
			language: {
				url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json' // لتعريب الجدول
			},
			dom: "<'row'<'col-sm-12'tr>><'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
		});

		// Link custom search input
		$('#tableSearch').keyup(function() {
			table.search($(this).val()).draw();
		});


		// Function to handle the button click
		$('#btn_assign_to_engineer').on('click', function() {
			const selectedIds = [];

			// 1. Get all checked IDs from the datatable
			$('#kt_datatable_audits tbody input[type="checkbox"]:checked').each(function() {
				selectedIds.push($(this).val());
			});

			if (selectedIds.length === 0) {
				Swal.fire({
					text: "يرجى اختيار مبنى واحد على الأقل.",
					icon: "warning",
					buttonsStyling: false,
					confirmButtonText: "موافق",
					customClass: {
						confirmButton: "btn btn-primary"
					}
				});
				return;
			}

			// 2. Set Modal Details
			$('#modal_title,#user_label').text('تعيين للمهندس');
			$('#assign_type').val('Engineering Auditor');
			$('#assign_status_id').val(2);

			const container = $('#selected_buildings_container');
			container.empty();
			selectedIds.forEach(id => {
				container.append(`<input type="hidden" name="building_ids[]" value="${id}">`);
			});

			// 4. Show the Modal
			$('#kt_modal_assign').modal('show');
		});

		$('#btn_assign_to_lawyer').on('click', function() {
			const selectedIds = [];

			// 1. Get all checked IDs from the datatable
			$('#kt_datatable_audits tbody input[type="checkbox"]:checked').each(function() {
				selectedIds.push($(this).val());
			});

			if (selectedIds.length === 0) {
				Swal.fire({
					text: "يرجى اختيار مبنى واحد على الأقل.",
					icon: "warning",
					buttonsStyling: false,
					confirmButtonText: "موافق",
					customClass: {
						confirmButton: "btn btn-primary"
					}
				});
				return;
			}

			// 2. Set Modal Details
			$('#modal_title,#user_label').text('تعيين للمحامي');
			$('#assign_type').val('Legal Auditor'); // Hidden input to tell backend the role
			$('#assign_status_id').val(6);

			// 3. Clear and Fill IDs in a hidden container inside the form
			const container = $('#selected_buildings_container');
			container.empty();
			selectedIds.forEach(id => {
				container.append(`<input type="hidden" name="building_ids[]" value="${id}">`);
			});

			// 4. Show the Modal
			$('#kt_modal_assign').modal('show');
		});

		$('#kt_modal_assign_form').on('submit', function(e) {
			e.preventDefault(); // منع الصفحة من التحديث

			var form = $(this);
			var submitButton = $('#kt_modal_assign_submit');

			// تفعيل وضع التحميل على الزر
			submitButton.attr('data-kt-indicator', 'on');
			submitButton.prop('disabled', true);

			$.ajax({
				url: "{{route('audit.assign')  }}",
				method: 'POST',
				data: form.serialize(),
				success: function(response) {

					$('#kt_modal_assign').modal('hide');

					// تنبيه بالنجاح
					Swal.fire({
						text: "تمت عملية التعيين بنجاح!",
						icon: "success",
						buttonsStyling: false,
						confirmButtonText: "موافق",
						customClass: {
							confirmButton: "btn btn-primary"
						}
					}).then(function() {

						$('#kt_datatable_audits').DataTable().ajax.reload()
					});
				},
				error: function(xhr) {
					// تنبيه بالخطأ
					Swal.fire({
						text: "حدث خطأ ما، يرجى المحاولة لاحقاً.",
						icon: "error",
						buttonsStyling: false,
						confirmButtonText: "حسناً",
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
				},
				complete: function() {
					// إلغاء وضع التحميل
					submitButton.removeAttr('data-kt-indicator');
					submitButton.prop('disabled', false);
					$('#kt_datatable_audits').DataTable().ajax.reload()
					$("[type='checkbox']").prop('checked', false);
				}
			});
		});


	});

	function refreshTable(refresh) {

		$('#kt_datatable_audits').DataTable().ajax.reload()
		$(refresh).attr('data-kt-indicator', 'on');
		$(refresh).prop('disabled', true);
		setTimeout(function() {
			$(refresh).removeAttr('data-kt-indicator');
			$(refresh).prop('disabled', false);
		}, 700); // 3000 milliseconds = 3 seconds

	}
	$('#kt_datatable_audits').on('draw.dt', function() {
		KTMenu.createInstances();
	});
</script>
@endsection