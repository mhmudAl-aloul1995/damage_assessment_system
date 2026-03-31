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

	<div class="row mb-5">
		<div class="col-md-12">
			<div class="card card-flush shadow-sm">
				<div class="card-header pt-6">
					<div class="card-title">
						<i class="ki-duotone ki-filter fs-1 me-3 text-primary"></i>
						<h3 class="fw-bold m-0">الفلاتر</h3>
					</div>

					<div class="card-toolbar">
						<button type="button" class="btn btn-sm btn-light-danger" id="resetFilters">
							إعادة تعيين
						</button>
					</div>
				</div>

				<div class="card-body">
					<div class="row g-5">
						<div class="col-md-3">
							<label class="form-label fw-semibold">بحث باسم المبنى</label>
							<input type="text" id="filter_building_name" class="form-control form-control-solid"
								placeholder="اسم المبنى" />
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">المهندس</label>
							<select id="filter_engineer" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="اختر المهندس">
								<option></option>
								@foreach($engineers as $engineer)
									<option value="{{ $engineer->id }}">{{ $engineer->name }}</option>
								@endforeach
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">المحامي</label>
							<select id="filter_lawyer" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="اختر المحامي">
								<option></option>
								@foreach($lawyers as $lawyer)
									<option value="{{ $lawyer->id }}">{{ $lawyer->name }}</option>
								@endforeach
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">الحالة الهندسية</label>
							<select id="filter_eng_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="اختر الحالة">
								<option></option>
								<option value="pending">Pending</option>
								<option value="accepted_by_engineer">Accepted By Engineer</option>
								<option value="rejected_by_engineer">Rejected By Engineer</option>
								<option value="assigned_to_engineer">Assigned To Engineer</option>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">الحالة القانونية</label>
							<select id="filter_legal_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="اختر الحالة">
								<option></option>
								<option value="pending">Pending</option>
								<option value="accepted_by_lawyer">Accepted By Lawyer</option>
								<option value="rejected_by_lawyer">Rejected By Lawyer</option>
								<option value="assigned_to_lawyer">Assigned To Lawyer</option>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">الاعتماد النهائي</label>
							<select id="filter_final_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="اختر الحالة">
								<option></option>
								<option value="pending">Pending</option>
								<option value="approved">Approved</option>
								<option value="rejected">Rejected</option>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">منطقة/حي</label>
							<input type="text" id="filter_area" class="form-control form-control-solid"
								placeholder="المنطقة أو الحي" />
						</div>
						<div class="col-md-3">
							<label class="form-label fw-semibold">المهندس الميداني </label>
							<select id="filter_field_engineer" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="اختر Field Engineer">
								<option></option>
								@foreach($assignedTo as $eng)
									<option value="{{ $eng->assignedto }}">{{ $eng->assignedto }}</option>
								@endforeach
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">حالة الضرر</label>
							<select id="filter_damage_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="اختر الحالة">
								<option></option>
								<option value="fully_damaged">Fully Damaged</option>
								<option value="partially_damaged">Partially Damaged</option>
								<option value="minor_damaged">Minor Damaged</option>
								<option value="no_damage">No Damage</option>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label fw-semibold">من تاريخ الإنشاء</label>
							<input type="date" id="filter_from_date" placehoder="من تاريخ الإنشاء" class="form-control form-control-solid">
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">إلى تاريخ الإنشاء</label>
							<input type="date" id="filter_to_date" placehoder="إلى تاريخ الإنشاء" class="form-control form-control-solid">
						</div>
						<div class="col-md-3 d-flex align-items-end">
							<button type="button" class="btn btn-primary w-100" id="applyFilters">
								تطبيق الفلاتر
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-md-12">
			<div class="card card-flush shadow-sm">
				<div class="card-header align-items-center py-5 gap-2">
					<div class="card-title">
						<div class="d-flex align-items-center position-relative my-1">
							<i class="ki-duotone ki-magnifier fs-3 position-absolute ms-4"></i>
							<input type="text" id="tableSearch" class="form-control form-control-solid w-250px ps-12"
								placeholder="بحث المباني" />
						</div>
					</div>
					<div class="card-toolbar gap-3">
						<button onclick="refreshTable(this)" class="btn btn-success btn-sm">تحديث <i
								class="ki-duotone ki-update-file"></i></button>
						<button id="btn_assign_to_lawyer" class="btn btn-primary btn-sm">تعيين للمحامي <i
								class="ki-duotone ki-plus"></i></button>
						<button id="btn_assign_to_engineer" class="btn btn-info btn-sm">تعيين للمهندس <i
								class="ki-duotone ki-plus"></i></button>
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
								<th> Field Engineer</th>
								<th>Engineer</th>
								<th>Lawyer</th>
								<th>Eng Status</th>
								<th>Legal Status</th>
								<th>Final Approval</th>
								<th>creationdate</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody class="text-gray-600 fw-semibold">
						</tbody>
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
							<select name="user_id" class="form-select form-select-solid" data-control="select2"
								data-placeholder="إختر الإسم..." data-dropdown-parent="#kt_modal_assign">
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
		$(document).ready(function () {
			flatpickr("#filter_from_date", {
				dateFormat: "Y-m-d",
				'placeholder': 'من تاريخ الإنشاء'

			});
			flatpickr("#filter_to_date", {
				dateFormat: "Y-m-d",
				'placeholder': 'إلى تاريخ الإنشاء'
			});
			var table = $('#kt_datatable_audits').DataTable({
				processing: true,
				serverSide: true,


				ajax: {
					url: "{{ route('audit.index') }}",
					data: function (d) {
						d.building_name = $('#filter_building_name').val();
						d.engineer_id = $('#filter_engineer').val();
						d.lawyer_id = $('#filter_lawyer').val();
						d.eng_status = $('#filter_eng_status').val();
						d.legal_status = $('#filter_legal_status').val();
						d.final_status = $('#filter_final_status').val();
						d.area = $('#filter_area').val();
						d.field_engineer = $('#filter_field_engineer').val();
						d.damage_status = $('#filter_damage_status').val();
						d.filter_from_date = $('#filter_from_date').val();
						d.filter_to_date = $('#filter_to_date').val();

					}
				},

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

				{
					data: 'building_name',
					name: 'building_name'
				},
				{
					data: 'assignedto',
					name: 'assignedto'
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
					data: 'creationdate'
				},
				{

					data: 'actions'

				}
				],
				language: {
					url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json' // لتعريب الجدول
				},
				createdRow: (row, data, index) => {
					$(row).css('cursor', 'pointer')



					$(row).on('click', function (e) {
						if ($(e.target).closest('input, button, a').length) {
							return;
						}
						e.preventDefault()
						var url_eng = "{{url('showAssessmentAudit/')  }}/" + data.globalid
						window.open(url_eng, '_blank');


					});

				},
				dom: "<'row'<'col-sm-12'tr>><'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
			});

			$('#applyFilters').on('click', function () {

				let btn = $(this);

				btn.attr('data-kt-indicator', 'on');
				btn.prop('disabled', true);

				table.ajax.reload(function () {
					// ✅ يرجع الزر طبيعي بعد التحميل
					btn.removeAttr('data-kt-indicator');
					btn.prop('disabled', false);
				});

			});
			$('#resetFilters').on('click', function () {
				$('#filter_building_name').val('');
				$('#filter_engineer').val(null).trigger('change');
				$('#filter_lawyer').val(null).trigger('change');
				$('#filter_eng_status').val(null).trigger('change');
				$('#filter_legal_status').val(null).trigger('change');
				$('#filter_final_status').val(null).trigger('change');
				$('#filter_area').val('');

			});
			// Link custom search input
			$('#tableSearch').keyup(function () {
				table.search($(this).val()).draw();
			});


			// Function to handle the button click
			$('#btn_assign_to_engineer').on('click', function () {
				const selectedIds = [];

				// 1. Get all checked IDs from the datatable
				$('#kt_datatable_audits tbody input[type="checkbox"]:checked').each(function () {
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
				$('#assign_type').val('QC/QA Engineer');
				$('#assign_status_id').val(2);

				const container = $('#selected_buildings_container');
				container.empty();
				selectedIds.forEach(id => {
					container.append(`<input type="hidden" name="building_ids[]" value="${id}">`);
				});

				// 4. Show the Modal
				$('#kt_modal_assign').modal('show');
			});

			$('#btn_assign_to_lawyer').on('click', function () {
				const selectedIds = [];

				// 1. Get all checked IDs from the datatable
				$('#kt_datatable_audits tbody input[type="checkbox"]:checked').each(function () {
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

			$('#kt_modal_assign_form').on('submit', function (e) {
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
					success: function (response) {

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
						}).then(function () {

							$('#kt_datatable_audits').DataTable().ajax.reload()
						});
					},
					error: function (xhr) {
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
					complete: function () {
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
			setTimeout(function () {
				$(refresh).removeAttr('data-kt-indicator');
				$(refresh).prop('disabled', false);
			}, 700); // 3000 milliseconds = 3 seconds

		}
		$('#kt_datatable_audits').on('draw.dt', function () {
			KTMenu.createInstances();
		});
	</script>
@endsection