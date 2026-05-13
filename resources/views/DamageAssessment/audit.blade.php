@extends('layouts.app')
@section('title', __('ui.audit.title'))
@section('pageName', __('ui.audit.title'))


@section('content')
	<style>
		table.dataTable thead th.sorting,
		table.dataTable thead th.sorting_asc,
		table.dataTable thead th.sorting_desc {
			padding-right: 15px !important;
		}

		.container-loader {
			display: none !important;
		}

		.audit-table-wrapper {
			overflow-x: auto;
			width: 100%;
			-webkit-overflow-scrolling: touch;
		}

		#kt_datatable_audits {
			width: 100% !important;
			min-width: 1180px;
			table-layout: auto;
		}

		#kt_datatable_audits th,
		#kt_datatable_audits td {
			padding: clamp(0.65rem, 0.45rem + 0.35vw, 1rem) clamp(0.55rem, 0.35rem + 0.35vw, 0.95rem) !important;
			vertical-align: middle;
		}

		#kt_datatable_audits thead th,
		#kt_datatable_audits tbody td {
			text-align: center;
		}

		#kt_datatable_audits thead th {
			font-size: clamp(0.78rem, 0.68rem + 0.18vw, 0.94rem);
			line-height: 1.45;
			white-space: normal;
			overflow-wrap: anywhere;
		}

		#kt_datatable_audits tbody td {
			font-size: clamp(0.84rem, 0.76rem + 0.16vw, 0.98rem);
			line-height: 1.55;
		}

		#kt_datatable_audits .audit-cell-text,
		#kt_datatable_audits .audit-cell-name {
			display: block;
			line-height: 1.55;
			white-space: normal;
			overflow-wrap: anywhere;
			word-break: normal;
		}

		#kt_datatable_audits .audit-cell-ltr {
			direction: ltr;
			text-align: center;
			unicode-bidi: plaintext;
		}

		#kt_datatable_audits .audit-cell-date {
			display: block;
			direction: ltr;
			line-height: 1.45;
			white-space: normal;
		}

		#kt_datatable_audits .badge {
			display: inline-flex;
			max-width: 100%;
			min-height: 30px;
			padding: 0.4rem 0.6rem;
			justify-content: center;
			align-items: center;
			font-size: clamp(0.78rem, 0.7rem + 0.14vw, 0.9rem);
			line-height: 1.35;
			text-align: center;
			white-space: normal;
		}

		#kt_datatable_audits .form-check {
			min-height: 1rem;
		}

		#kt_datatable_audits .btn {
			padding-inline: 0.65rem;
			white-space: normal;
		}

		@media (min-width: 1600px) {
			#kt_datatable_audits {
				min-width: 100%;
			}
		}

		@media (max-width: 991.98px) {
			.audit-table-wrapper {
				margin-inline: -0.75rem;
				padding-inline: 0.75rem;
			}
		}
	</style>

	<div class="row mb-5">
		<div class="col-md-12">
			<div class="card card-flush shadow-sm">
				<div class="card-header pt-6">
					<div class="card-title">
						<i class="ki-duotone ki-filter fs-1 me-3 text-primary"></i>
						<h3 class="fw-bold m-0">{{ __('ui.audit.filters') }}</h3>
					</div>

					<div class="card-toolbar">
						<button type="button" class="btn btn-sm btn-light-danger" id="resetFilters">
							{{ __('ui.audit.reset') }}
						</button>
					</div>
				</div>

				<div class="card-body">
					<div class="row g-5">
						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.search_building_name') }}</label>
							<input type="text" id="filter_building_name" class="form-control form-control-solid"
								placeholder="{{ __('ui.audit.building_name_placeholder') }}" />
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">ObjectID</label>
							<input type="text" id="filter_objectid" class="form-control form-control-solid"
								placeholder="ObjectID" />
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.engineer') }}</label>
							<select id="filter_engineer" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-close-on-select="false" multiple
								data-placeholder="{{ __('ui.audit.select_engineer') }}">
								@foreach($engineers as $engineer)
									<option value="{{ $engineer->id }}">{{ $engineer->name }}</option>
								@endforeach
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.lawyer') }}</label>
							<select id="filter_lawyer" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-close-on-select="false" multiple
								data-placeholder="{{ __('ui.audit.select_lawyer') }}">
								@foreach($lawyers as $lawyer)
									<option value="{{ $lawyer->id }}">{{ $lawyer->name }}</option>
								@endforeach
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.engineering_status') }}</label>
							<select id="filter_eng_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-close-on-select="false" multiple
								data-placeholder="{{ __('ui.audit.select_status') }}">
								<option value="pending">Pending</option>
								<option value="accepted_by_engineer">Accepted By Engineer</option>
								<option value="rejected_by_engineer">Rejected By Engineer</option>
								<option value="assigned_to_engineer">Assigned To Engineer</option>
								<option value="need_review">Need Review</option>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.legal_status') }}</label>
							<select id="filter_legal_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-close-on-select="false" multiple
								data-placeholder="{{ __('ui.audit.select_status') }}">
								<option value="pending">Pending</option>
								<option value="assigned_to_lawyer">Assigned To Lawyer</option>
								<option value="accepted_by_lawyer">Accepted By Lawyer</option>
								<option value="legal_notes">Legal Notes</option>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.final_approval') }}</label>
							<select id="filter_final_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-close-on-select="false" multiple
								data-placeholder="{{ __('ui.audit.select_status') }}">
								<option value="pending">Pending</option>
								<option value="approved">Approved</option>
								<option value="rejected">Rejected</option>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.area') }}</label>
							<input type="text" id="filter_area" class="form-control form-control-solid"
								placeholder="{{ __('ui.audit.area_placeholder') }}" />
						</div>
						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.field_engineer') }}</label>
							<select id="filter_field_engineer" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-close-on-select="false" multiple
								data-placeholder="{{ __('ui.audit.select_field_engineer') }}">
								@foreach($assignedTo as $eng)
									<option value="{{ $eng->assignedto }}">{{ $eng->assignedto }}</option>
								@endforeach
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.damage_status') }}</label>
							<select id="filter_damage_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-close-on-select="false" multiple
								data-placeholder="{{ __('ui.audit.select_status') }}">
								<option value="fully_damaged">Fully Damaged</option>
								<option value="partially_damaged">Partially Damaged</option>
								<option value="committee_review">Committee Review </option>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.from_creation_date') }}</label>
							<input type="date" id="filter_from_date" placeholder="{{ __('ui.audit.from_creation_date') }}"
								class="form-control form-control-solid">
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.to_creation_date') }}</label>
							<input type="date" id="filter_to_date" placeholder="{{ __('ui.audit.to_creation_date') }}"
								class="form-control form-control-solid">
						</div>
						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.from_status_date') }}</label>
							<input type="date" id="filter_status_from_date" placeholder="{{ __('ui.audit.from_status_date') }}"
								class="form-control form-control-solid">
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.to_status_date') }}</label>
							<input type="date" id="filter_status_to_date" placeholder="{{ __('ui.audit.to_status_date') }}"
								class="form-control form-control-solid">
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
								placeholder="{{ __('ui.audit.search_buildings') }}" />
						</div>
					</div>
					<div class="card-toolbar gap-3 flex-wrap justify-content-end">
						<button type="button" class="btn btn-light-success btn-sm" data-bs-toggle="modal"
							data-bs-target="#auditExportModal">
							تصدير Excel <i class="ki-duotone ki-file-down"></i>
						</button>
						<button onclick="refreshTable(this)" class="btn btn-success btn-sm">
							{{ __('ui.audit.refresh') }} <i class="ki-duotone ki-update-file"></i>
						</button>
						<button type="button" id="toggle_select_column" class="btn btn-light-primary btn-sm"
							data-select-visible="false">
							إظهار التحديد <i class="ki-duotone ki-check-square"></i>
						</button>
						@unless(auth()->user()->hasRole('Area Manager'))
							<button id="btn_final_approve" class="btn btn-warning btn-sm">
								{{ __('ui.audit.approve_final') }} <i class="ki-duotone ki-check-circle"></i>
							</button>

							@hasanyrole('Database Officer|undp-Project Manager')
							<button id="btn_undp_final_approve" class="btn btn-light-primary btn-sm">
								UNDP Final Approve <i class="ki-duotone ki-check-circle"></i>
							</button>
							@endhasanyrole

							<button id="btn_assign_to_lawyer" class="btn btn-primary btn-sm">
								{{ __('ui.audit.assign_to_lawyer') }} <i class="ki-duotone ki-plus"></i>
							</button>

							<button id="btn_assign_to_engineer" class="btn btn-info btn-sm">
								{{ __('ui.audit.assign_to_engineer') }} <i class="ki-duotone ki-plus"></i>
							</button>
							<button id="btn_import_final_approve" class="btn btn-dark btn-sm">
								ObjectIDs Final Approve
								<i class="ki-duotone ki-file-up"></i>
							</button>
						@endunless
					</div>
				</div>

				<div class="card-body pt-0">
					<div class="audit-table-wrapper">
						<table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_datatable_audits">
							<thead>
								<tr class="text-muted fw-bold fs-7 text-uppercase gs-0">
									<th class="w-10px pe-2">
										<div class="form-check form-check-sm form-check-custom form-check-solid me-3">
											<input class="form-check-input" type="checkbox" data-kt-check="true"
												data-kt-check-target="#kt_datatable_audits .form-check-input" value="1" />
										</div>
									</th>
									<th>{{ __('ui.audit.building_name') }}</th>
									<th>{{ __('ui.audit.total_cases_col') }}</th>
									<th>{{ __('ui.audit.field_engineer_col') }}</th>
									<th>{{ __('ui.audit.engineer_col') }}</th>
									<th>{{ __('ui.audit.lawyer_col') }}</th>
									<th>{{ __('ui.audit.eng_status_col') }}</th>
									<th>{{ __('ui.audit.legal_status_col') }}</th>
									<th>{{ __('ui.audit.final_approval_col') }}</th>
<!-- 									<th>{{ __('ui.audit.creation_date_col') }}</th>
 -->									<th>{{ __('ui.audit.actions') }}</th>
								</tr>
							</thead>
							<tbody class="text-gray-600 fw-semibold">
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="modal fade" id="kt_modal_assign" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered mw-650px">
			<div class="modal-content">
				<form id="kt_modal_assign_form">
					<div class="modal-header">
						<h2 class="fw-bold" id="modal_title">{{ __('ui.audit.assign_buildings') }}</h2>
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
							<label id="user_label"
								class="required fs-6 fw-semibold mb-2">{{ __('ui.audit.select_engineer') }}</label>

							<select name="user_id" id="assign_user_id" class="form-select form-select-solid"
								data-control="select2" data-placeholder="{{ __('ui.audit.select_user') }}"
								data-dropdown-parent="#kt_modal_assign">
								<option></option>
							</select>
						</div>

						<script>
							const assignEngineers = @json($assignEngineers ?? $engineers);
							const assignLawyers = @json($assignLawyers ?? $lawyers);
						</script>
					</div>

					<div class="modal-footer flex-center">
						<button type="reset" class="btn btn-light me-3"
							data-bs-dismiss="modal">{{ __('ui.buttons.cancel') }}</button>
						<button type="submit" class="btn btn-primary" id="kt_modal_assign_submit">
							<span class="indicator-label">{{ __('ui.audit.agree') }}</span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<div class="modal fade" id="notesHistoryModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered mw-1000px mw-lg-1400px">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="fw-bold" id="notesHistoryModalTitle">{{ __('ui.audit.status_history') }}</h2>
					<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
						<i class="ki-duotone ki-cross fs-1"></i>
					</div>
				</div>

				<div class="modal-body">
					<div class="table-responsive">
						<table class="table table-row-bordered table-striped gy-5 gs-7">
							<thead>
								<tr class="fw-bold fs-4 text-gray-800">
									<th>{{ __('ui.audit.status') }}</th>
									<th>{{ __('ui.audit.user') }}</th>
									<th>{{ __('ui.audit.role') }}</th>
									<th>{{ __('ui.audit.notes') }}</th>
									<th>{{ __('ui.audit.date') }}</th>
									<th>{{ __('ui.audit.actions') }}</th>

								</tr>
							</thead>
							<tbody id="buildingHistoryTableBody">
								<tr>
									<td colspan="6" class="text-center text-muted">{{ __('ui.audit.no_data') }}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('ui.audit.close') }}</button>
				</div>
			</div>
		</div>
	</div>
	<div class="modal fade" id="importFinalApproveModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered mw-650px">
			<div class="modal-content">
				<form id="importFinalApproveForm" enctype="multipart/form-data">
					@csrf

					<div class="modal-header">
						<h2 class="fw-bold">استيراد ObjectIDs لاعتماد نهائي</h2>
						<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
							<i class="ki-duotone ki-cross fs-1"></i>
						</div>
					</div>

					<div class="modal-body">
						<div class="alert alert-info">
							ملف Excel يجب أن يحتوي عمود باسم:
							<strong>objectid</strong>
							أو
							<strong>objectid</strong>
						</div>

						<div class="mb-5">
							<label class="form-label required">ملف Excel</label>
							<input type="file" name="file" id="final_approve_file" class="form-control form-control-solid"
								accept=".xlsx,.xls,.csv" required>
						</div>
					</div>

					<div class="modal-footer">
						<button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>

						<button type="submit" class="btn btn-warning" id="btn_submit_import_final_approve">
							<span class="indicator-label">اعتماد نهائي من Excel</span>
							<span class="indicator-progress">
								الرجاء الانتظار...
								<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
							</span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="auditExportModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered mw-900px">
			<div class="modal-content">
				<form id="auditExportForm">
					<div class="modal-header">
						<h2 class="fw-bold">تصدير بيانات التدقيق</h2>
						<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
							<i class="ki-duotone ki-cross fs-1"></i>
						</div>
					</div>

					<div class="modal-body scroll-y mx-5 mx-xl-10 my-7">
						<div class="mb-7">
							<label class="form-label fw-semibold required">نوع التصدير</label>
							<select name="export_type" id="audit_export_type" class="form-select form-select-solid"
								data-control="select2" data-dropdown-parent="#auditExportModal">
								<option value="buildings">المباني فقط</option>
								<option value="buildings_with_units">المباني مع الوحدات السكنية</option>
							</select>
						</div>

						<div class="separator separator-dashed my-6"></div>

						<div class="d-flex flex-stack flex-wrap gap-3 mb-4">
							<div>
								<h4 class="fw-bold mb-1">أعمدة المباني</h4>
								<div class="text-muted fs-7">اختر الأعمدة التي تريد ظهورها في ملف Excel.</div>
							</div>
							<div class="d-flex gap-2">
								<button type="button" class="btn btn-sm btn-light-primary audit-column-toggle"
									data-target=".audit-building-column" data-action="select">تحديد الكل</button>
								<button type="button" class="btn btn-sm btn-light audit-column-toggle"
									data-target=".audit-building-column" data-action="clear">إلغاء الكل</button>
							</div>
						</div>

						<div class="row g-4 mb-8">
							@foreach ($buildingExportColumns as $columnKey => $columnLabel)
								<div class="col-md-4">
									<label class="form-check form-check-custom form-check-solid">
										<input class="form-check-input audit-building-column" type="checkbox"
											name="building_columns[]" value="{{ $columnKey }}" checked>
										<span class="form-check-label fw-semibold">{{ $columnLabel }}</span>
									</label>
								</div>
							@endforeach
						</div>

						<div id="audit_housing_columns_wrapper" class="d-none">
							<div class="separator separator-dashed my-6"></div>
							<div class="d-flex flex-stack flex-wrap gap-3 mb-4">
								<div>
									<h4 class="fw-bold mb-1">أعمدة الوحدات السكنية</h4>
									<div class="text-muted fs-7">تظهر هذه الأعمدة عند اختيار التصدير مع الوحدات.</div>
								</div>
								<div class="d-flex gap-2">
									<button type="button" class="btn btn-sm btn-light-primary audit-column-toggle"
										data-target=".audit-housing-column" data-action="select">تحديد الكل</button>
									<button type="button" class="btn btn-sm btn-light audit-column-toggle"
										data-target=".audit-housing-column" data-action="clear">إلغاء الكل</button>
								</div>
							</div>

							<div class="row g-4">
								@foreach ($housingExportColumns as $columnKey => $columnLabel)
									<div class="col-md-4">
										<label class="form-check form-check-custom form-check-solid">
											<input class="form-check-input audit-housing-column" type="checkbox"
												name="housing_columns[]" value="{{ $columnKey }}" checked>
											<span class="form-check-label fw-semibold">{{ $columnLabel }}</span>
										</label>
									</div>
								@endforeach
							</div>
						</div>
					</div>

					<div class="modal-footer">
						<button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
						<button type="submit" class="btn btn-success" id="auditExportSubmit">
							<span class="indicator-label">تصدير Excel</span>
							<span class="indicator-progress">يرجى الانتظار...
								<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
							</span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="failedUnitsModal" tabindex="-1">
		<div class="modal-dialog modal-dialog-centered mw-900px">
			<div class="modal-content">
				<div class="modal-header">
					<h3 class="fw-bold">تفاصيل الوحدات غير المقبولة</h3>
					<div class="btn btn-icon btn-sm" data-bs-dismiss="modal">
						✕
					</div>
				</div>

				<div class="modal-body">
					<div id="failedUnitsContainer"></div>
				</div>

				<div class="modal-footer">
					<button class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
				</div>
			</div>
		</div>
	</div>
@endsection





@section('script')




	<script>
		$(document).ready(function () {

			$('#btn_import_final_approve').on('click', function () {
				$('#importFinalApproveForm')[0].reset();
				$('#importFinalApproveModal').modal('show');
			});

			$('#importFinalApproveForm').on('submit', function (e) {
				e.preventDefault();

				let formData = new FormData(this);
				let btn = $('#btn_submit_import_final_approve');

				$.ajax({
					url: "{{ route('audit.building.finalApprove.import') }}",
					type: "POST",
					data: formData,
					processData: false,
					contentType: false,
					beforeSend: function () {
						btn.attr('data-kt-indicator', 'on');
						btn.prop('disabled', true);
					},

					success: function (response) {
						$('#importFinalApproveModal').modal('hide');

						if (response.blocked_buildings && response.blocked_buildings.length > 0) {
							let html = `
				<div class="alert alert-danger mb-5 fw-bold">
					عدد المباني غير المعتمدة: ${response.blocked_buildings.length}
				</div>
			`;

							response.blocked_buildings.forEach(function (b) {
								html += `
					<div class="mb-7 border border-danger border-dashed p-4 rounded bg-light-danger">
						<div class="d-flex justify-content-between align-items-start mb-3">
							<div>
								<h5 class="text-danger mb-1">Building ID: ${b.building_id}</h5>
								<div class="text-dark fw-bold">اسم المبنى: ${b.building_name ?? '-'}</div>
								<div class="text-muted fs-7">GlobalID: ${b.building_globalid ?? '-'}</div>
							</div>
							<span class="badge badge-light-danger">${b.engineer_status ?? '-'}</span>
						</div>
				`;

								if (b.failed_units && b.failed_units.length > 0) {
									html += `
						<div class="table-responsive">
							<table class="table table-row-bordered table-striped align-middle">
								<thead>
									<tr>
										<th>ObjectID</th>
										<th>GlobalID</th>
										<th>اسم المالك</th>
										<th>Status</th>
										<th>Reason</th>
									</tr>
								</thead>
								<tbody>
					`;

									b.failed_units.forEach(function (u) {
										html += `
							<tr>
								<td>${u.objectid ?? '-'}</td>
								<td>${u.globalid ?? '-'}</td>
								<td>${u.owner_name ?? '-'}</td>
								<td><span class="badge badge-light-danger">${u.engineer_status ?? '-'}</span></td>
								<td class="text-danger fw-bold">${u.reason ?? '-'}</td>
							</tr>
						`;
									});

									html += `</tbody></table></div>`;
								} else {
									html += `
						<div class="alert alert-warning fw-bold mb-0">
							${b.reason ?? 'لا يوجد سبب واضح'}
						</div>
					`;
								}

								html += `</div>`;
							});

							$('#failedUnitsContainer').html(html);
							$('#failedUnitsModal').modal('show');
						}

						Swal.fire({
							icon: response.approved_count > 0 ? 'success' : 'warning',
							title: 'تمت العملية',
							html: response.message || 'تمت العملية',
							confirmButtonText: 'موافق',
							buttonsStyling: false,
							customClass: {
								confirmButton: "btn btn-primary"
							}
						}).then(function () {
							$('#kt_datatable_audits').DataTable().ajax.reload(null, false);
						});
					},
					error: function (xhr) {
						let message = 'فشل استيراد الملف';

						if (xhr.responseJSON && xhr.responseJSON.message) {
							message = xhr.responseJSON.message;
						}

						Swal.fire({
							icon: 'error',
							text: message,
							confirmButtonText: 'موافق',
							buttonsStyling: false,
							customClass: {
								confirmButton: "btn btn-primary"
							}
						});
					},
					complete: function () {
						btn.removeAttr('data-kt-indicator');
						btn.prop('disabled', false);
					}
				});
			});
			let fromPicker = flatpickr("#filter_from_date", {
				dateFormat: "Y-m-d",
				allowInput: true,
				onChange: function (selectedDates) {
					toPicker.set('minDate', selectedDates[0]);
				}
			});

			let toPicker = flatpickr("#filter_to_date", {
				dateFormat: "Y-m-d",
				allowInput: true,
				onChange: function (selectedDates) {
					fromPicker.set('maxDate', selectedDates[0]);
				}
			});
			let statusFromPicker = flatpickr("#filter_status_from_date", {
				dateFormat: "Y-m-d",
				allowInput: true,
				onChange: function (selectedDates) {
					statusToPicker.set('minDate', selectedDates[0]);
				}
			});

			let statusToPicker = flatpickr("#filter_status_to_date", {
				dateFormat: "Y-m-d",
				allowInput: true,
				onChange: function (selectedDates) {
					statusFromPicker.set('maxDate', selectedDates[0]);
				}
			});
			const escapeAuditCell = function (value) {
				return $('<div>').text(value ?? '-').html();
			};
			const renderAuditTextCell = function (data) {
				return `<span class="audit-cell-text">${escapeAuditCell(data)}</span>`;
			};
			const renderAuditLtrCell = function (data) {
				return `<span class="audit-cell-text audit-cell-ltr">${escapeAuditCell(data)}</span>`;
			};
			const auditFilterPayload = function () {
				return {
					building_name: $('#filter_building_name').val(),
					objectid: $('#filter_objectid').val(),
					engineer_id: $('#filter_engineer').val(),
					lawyer_id: $('#filter_lawyer').val(),
					eng_status: $('#filter_eng_status').val(),
					legal_status: $('#filter_legal_status').val(),
					final_status: $('#filter_final_status').val(),
					area: $('#filter_area').val(),
					field_engineer: $('#filter_field_engineer').val(),
					damage_status: $('#filter_damage_status').val(),
					filter_from_date: $('#filter_from_date').val(),
					filter_to_date: $('#filter_to_date').val(),
					status_from_date: $('#filter_status_from_date').val(),
					status_to_date: $('#filter_status_to_date').val()
				};
			};
			const appendAuditExportParams = function (params, key, value) {
				if (Array.isArray(value)) {
					value.forEach(function (item) {
						if (item !== null && item !== undefined && item !== '') {
							params.append(`${key}[]`, item);
						}
					});
					return;
				}

				if (value !== null && value !== undefined && value !== '') {
					params.append(key, value);
				}
			};

			$('#audit_export_type').select2({
				dir: 'rtl',
				width: '100%',
				dropdownParent: $('#auditExportModal'),
				minimumResultsForSearch: Infinity
			});

			$('#audit_export_type').on('change', function () {
				$('#audit_housing_columns_wrapper').toggleClass('d-none', $(this).val() !== 'buildings_with_units');
			});

			$('.audit-column-toggle').on('click', function () {
				const checked = $(this).data('action') === 'select';
				$($(this).data('target')).prop('checked', checked);
			});

			$('#auditExportForm').on('submit', function (event) {
				event.preventDefault();

				const submitButton = $('#auditExportSubmit');
				const selectedBuildingColumns = $('.audit-building-column:checked');
				const selectedHousingColumns = $('.audit-housing-column:checked');
				const exportType = $('#audit_export_type').val();

				if (selectedBuildingColumns.length === 0) {
					Swal.fire({
						icon: 'warning',
						text: 'يرجى اختيار عمود واحد على الأقل من أعمدة المباني.',
						confirmButtonText: @json(__('ui.buttons.ok')),
						buttonsStyling: false,
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
					return;
				}

				if (exportType === 'buildings_with_units' && selectedHousingColumns.length === 0) {
					Swal.fire({
						icon: 'warning',
						text: 'يرجى اختيار عمود واحد على الأقل من أعمدة الوحدات السكنية.',
						confirmButtonText: @json(__('ui.buttons.ok')),
						buttonsStyling: false,
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
					return;
				}

				const params = new URLSearchParams();
				appendAuditExportParams(params, 'export_type', exportType);

				selectedBuildingColumns.each(function () {
					params.append('building_columns[]', $(this).val());
				});

				if (exportType === 'buildings_with_units') {
					selectedHousingColumns.each(function () {
						params.append('housing_columns[]', $(this).val());
					});
				}

				const filters = auditFilterPayload();
				Object.keys(filters).forEach(function (key) {
					appendAuditExportParams(params, key, filters[key]);
				});

				submitButton.attr('data-kt-indicator', 'on').prop('disabled', true);
				window.location.href = "{{ route('audit.export') }}?" + params.toString();

				setTimeout(function () {
					submitButton.removeAttr('data-kt-indicator').prop('disabled', false);
					bootstrap.Modal.getOrCreateInstance(document.getElementById('auditExportModal')).hide();
				}, 800);
			});
			var table = $('#kt_datatable_audits').DataTable({
				processing: true,
				serverSide: true,
				ajax: {
					url: "{{ route('audit.index') }}",
					data: function (d) {
						Object.assign(d, auditFilterPayload());
					}
				},
				lengthMenu: [[10, 20, 25, 50, 100], [10, 20, 25, 50, 100]],
				pageLength: 20,
				autoWidth: false,
				scrollX: true,
				responsive: false,
				columnDefs: [
					{
						targets: 0,
						visible: false,
						orderable: false,
						searchable: false,
						width: '3%',
						className: 'text-center'
					},
					{
						targets: 1,
						width: '16%',
						className: 'text-start'
					},
					{
						targets: 2,
						width: '7%',
						className: 'text-center'
					},
					{
						targets: 3,
						width: '10%',
						className: 'audit-cell-ltr'
					},
					{
						targets: [4, 5],
						width: '9%',
						className: 'text-center'
					},
					{
						targets: [6, 7, 8],
						width: '10%',
						className: 'text-center'
					},
					/* {
						targets: 9,
						width: '8%',
						className: 'text-center'
					}, */
					{
						targets: 10,
						orderable: false,
						searchable: false,
						width: '4%',
						className: 'text-center'
					}
				],
				//order: [[9, 'desc']],
				columns: [
					{
						data: 'objectid',
						name: 'objectid',
						orderable: false,
						searchable: false,
						render: (data) => `
																																														<div class="form-check form-check-sm form-check-custom form-check-solid me-3">
																																															<input class="form-check-input" type="checkbox"
																																																data-kt-check-target="#kt_datatable_audits .form-check-input" value="${data}" />
																																														</div>`
					},
					{
						data: 'building_name',
						name: 'building_name',
						render: (data) => `<span class="audit-cell-name">${data ?? '-'}</span>`
					},
					{
						data: 'housing_status_progress',
						name: 'housing_status_progress',
						searchable: false,
						orderable: false,
						render: renderAuditTextCell
					},
					{ data: 'assignedto', name: 'assignedto', render: renderAuditLtrCell },
					{ data: 'engineer', name: 'engineer', searchable: false, render: renderAuditTextCell },
					{ data: 'lawyer', name: 'lawyer', searchable: false, render: renderAuditTextCell },
					{ data: 'eng_status', name: 'eng_status' },
					{ data: 'law_status', name: 'law_status' },
					{ data: 'finalApproval' },
					/* {
						data: 'creationdate',
						render: (data) => `<span class="audit-cell-date">${escapeAuditCell(data)}</span>`
					}, */
					{ data: 'actions' }
				],
				language: {
					url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json'
				},
				createdRow: (row, data, index) => {
					$(row).css('cursor', 'pointer');

					$(row).on('click', function (e) {
						if ($(e.target).closest('input, button, a').length) {
							return;
						}
						e.preventDefault();
						var url_eng = "{{ url('showAssessmentAudit/') }}/" + data.globalid;
						window.open(url_eng, '_blank');
					});
				},

			});

			$('#toggle_select_column').on('click', function () {
				const button = $(this);
				const selectColumn = table.column(0);
				const shouldShow = !selectColumn.visible();

				selectColumn.visible(shouldShow);
				$("[type='checkbox']").prop('checked', false);
				button.attr('data-select-visible', shouldShow ? 'true' : 'false');
				button.html((shouldShow ? 'إخفاء التحديد' : 'إظهار التحديد') + ' <i class="ki-duotone ki-check-square"></i>');
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
			let filterReloadTimer = null;
			let isResettingFilters = false;
			let scheduleFilterReload = function () {
				if (isResettingFilters) {
					return;
				}

				clearTimeout(filterReloadTimer);
				filterReloadTimer = setTimeout(function () {
					table.ajax.reload(null, true);
				}, 350);
			};

			$('#filter_engineer, #filter_lawyer, #filter_eng_status, #filter_legal_status, #filter_final_status, #filter_field_engineer, #filter_damage_status')
				.on('change', scheduleFilterReload);

			$('#filter_building_name, #filter_objectid, #filter_area, #filter_from_date, #filter_to_date, #filter_status_from_date, #filter_status_to_date')
				.on('input change', scheduleFilterReload);
			$('#resetFilters').on('click', function () {
				/* 			$('#filter_building_name').val('');
							$('#filter_engineer').val(null).trigger('change');
							$('#filter_lawyer').val(null).trigger('change');
							$('#filter_eng_status').val(null).trigger('change');
							$('#filter_legal_status').val(null).trigger('change');
							$('#filter_final_status').val(null).trigger('change');
							$('#filter_area').val(''); */
				isResettingFilters = true;
				$('select').val(null).trigger('change');
				$('input').val('');
				isResettingFilters = false;
				table.ajax.reload(null, true);


			});
			// Link custom search input
			$('#tableSearch').keyup(function () {
				table.search($(this).val()).draw();
			});
			$('#btn_assign_to_engineer').on('click', function () {
				const selectedIds = [];

				$('#kt_datatable_audits tbody input[type="checkbox"]:checked').each(function () {
					selectedIds.push($(this).val());
				});

				if (selectedIds.length === 0) {
					Swal.fire({
						text: @json(__('ui.audit.select_at_least_one_building')),
						icon: "warning",
						buttonsStyling: false,
						confirmButtonText: @json(__('ui.buttons.ok')),
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
					return;
				}

				$('#modal_title').text(@json(__('ui.audit.assign_engineer')));
				$('#assign_type').val('QC/QA Engineer');
				$('#assign_status_id').val(2);

				const container = $('#selected_buildings_container');
				container.empty();

				selectedIds.forEach(id => {
					container.append(`<input type="hidden" name="building_ids[]" value="${id}">`);
				});

				loadAssignUsers('QC/QA Engineer');

				$('#kt_modal_assign').modal('show');
			});

			$('#btn_assign_to_lawyer').on('click', function () {
				const selectedIds = [];

				$('#kt_datatable_audits tbody input[type="checkbox"]:checked').each(function () {
					selectedIds.push($(this).val());
				});

				if (selectedIds.length === 0) {
					Swal.fire({
						text: @json(__('ui.audit.select_at_least_one_building')),
						icon: "warning",
						buttonsStyling: false,
						confirmButtonText: @json(__('ui.buttons.ok')),
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
					return;
				}

				$('#modal_title').text(@json(__('ui.audit.assign_lawyer')));
				$('#assign_type').val('Legal Auditor');
				$('#assign_status_id').val(6);

				const container = $('#selected_buildings_container');
				container.empty();

				selectedIds.forEach(id => {
					container.append(`<input type="hidden" name="building_ids[]" value="${id}">`);
				});

				loadAssignUsers('Legal Auditor');

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
							text: @json(__('ui.audit.assigned_success')),
							icon: "success",
							buttonsStyling: false,
							confirmButtonText: @json(__('ui.buttons.ok')),
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
							text: @json(__('ui.audit.error_try_later')),
							icon: "error",
							buttonsStyling: false,
							confirmButtonText: @json(__('ui.buttons.ok')),
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


			$(document).on('click', '.btn-show-history', function (e) {
				e.preventDefault();
				e.stopPropagation();

				let globalid = $(this).data('globalid');
				let buildingName = $(this).data('building-name') || @json(__('ui.audit.default_building'));

				$('#notesHistoryModalTitle').text(@json(__('ui.audit.status_history')) + ' - ' + buildingName);
				$('#buildingHistoryTableBody').html(`
																															<tr>
																																<td colspan="6" class="text-center">${@json(__('ui.audit.loading'))}</td>
																															</tr>
																														`);

				$('#notesHistoryModal').modal('show');

				$.ajax({
					url: "{{ route('audit.building.history') }}",
					type: "GET",
					data: { globalid: globalid },
					success: function (response) {
						let rows = '';

						if (response.status && response.history.length > 0) {
							response.history.forEach(function (item) {
								rows += `
																																			<tr>
																																				<td>${item.status_name}</td>
																																				<td>${item.user_name}</td>
																																				<td>${item.role_name}</td>
																																				<td>${item.notes}</td>
																																				<td>${item.created_at}</td>
																																				<td>
																																					${item.can_delete ? `
																																						<button type="button"
																																							class="btn btn-sm btn-light-danger btn-delete-history"
																																							data-id="${item.id}">
																																							${@json(__('ui.audit.delete_record'))}
																																						</button>
																																					` : '-'}
																																				</td>
																																			</tr>
																																		`;
							});
						} else {
							rows = `
																																		<tr>
																																			<td colspan="6" class="text-center text-muted">${@json(__('ui.audit.no_status_history'))}</td>
																																		</tr>
																																	`;
						}

						$('#buildingHistoryTableBody').html(rows);
					},
					error: function () {
						$('#buildingHistoryTableBody').html(`
																																	<tr>
																																		<td colspan="6" class="text-center text-danger">${@json(__('ui.audit.failed_load_history'))}</td>
																																	</tr>
																																`);
					}
				});
			});


			$(document).on('click', '.btn-delete-history', function () {
				let id = $(this).data('id');
				let button = $(this);

				if (!confirm(@json(__('ui.audit.confirm_delete_record')))) {
					return;
				}

				$.ajax({
					url: "{{ route('audit.building.history.delete') }}",
					type: "POST",
					data: {
						_token: "{{ csrf_token() }}",
						id: id
					},
					success: function (response) {
						if (response.status) {
							toastr.success(response.message || @json(__('ui.audit.record_deleted')));
							button.closest('tr').remove();

							if ($('#buildingHistoryTableBody tr').length === 0) {
								$('#buildingHistoryTableBody').html(`
																																	<tr>
																																		<td colspan="6" class="text-center text-muted">${@json(__('ui.audit.no_status_history'))}</td>
																																	</tr>
																																`);
							}
						} else {
							toastr.error(response.message || @json(__('ui.audit.delete_failed')));
						}
					},
					error: function (xhr) {
						let message = @json(__('ui.audit.delete_failed'));

						if (xhr.responseJSON && xhr.responseJSON.message) {
							message = xhr.responseJSON.message;
						}

						toastr.error(message);
					}
				});
			});
			$('#btn_final_approve').on('click', function () {
				const selectedIds = [];

				$('#kt_datatable_audits tbody input[type="checkbox"]:checked').each(function () {
					selectedIds.push($(this).val());
				});

				if (selectedIds.length === 0) {
					Swal.fire({
						text: @json(__('ui.audit.select_at_least_one_building')),
						icon: "warning",
						buttonsStyling: false,
						confirmButtonText: @json(__('ui.buttons.ok')),
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
					return;
				}

				Swal.fire({
					title: @json(__('ui.audit.final_approval_title')),
					text: @json(__('ui.audit.final_approval_confirm')),
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: @json(__('ui.audit.yes_approve')),
					cancelButtonText: @json(__('ui.buttons.cancel')),
					buttonsStyling: false,
					customClass: {
						confirmButton: "btn btn-warning",
						cancelButton: "btn btn-light"
					}
				}).then(function (result) {
					if (!result.isConfirmed) return;

					$.ajax({
						url: "{{ route('audit.building.finalApprove') }}",
						type: "POST",
						data: {
							_token: "{{ csrf_token() }}",
							building_ids: selectedIds
						},
						beforeSend: function () {
							$('#btn_final_approve').attr('data-kt-indicator', 'on');
							$('#btn_final_approve').prop('disabled', true);
						},

						success: function (response) {

							if (response.blocked_buildings && response.blocked_buildings.length > 0) {

								let html = `
						<div class="alert alert-danger mb-5">
							عدد المباني غير المعتمدة: ${response.blocked_buildings.length}
						</div>
					`;

								response.blocked_buildings.forEach(function (b) {

									html += `
							<div class="mb-7 border border-danger border-dashed p-4 rounded bg-light-danger">
								<div class="d-flex justify-content-between align-items-start mb-3">
									<div>
										<h5 class="text-danger mb-1">
											Building ID: ${b.building_id}
										</h5>

										<div class="text-dark fw-bold">
											اسم المبنى: ${b.building_name ?? '-'}
										</div>

										<div class="text-muted fs-7">
											GlobalID: ${b.building_globalid ?? '-'}
										</div>
									</div>

									<span class="badge badge-light-danger">
										${b.engineer_status ?? '-'}
									</span>
								</div>
						`;

									if (b.failed_units && b.failed_units.length > 0) {

										html += `
								<div class="table-responsive">
									<table class="table table-row-bordered table-striped align-middle">
										<thead>
											<tr class="fw-bold text-gray-800">
												<th>ObjectID</th>
												<th>GlobalID</th>
												<th>اسم المالك</th>
												<th>Status</th>
												<th>Reason</th>
											</tr>
										</thead>
										<tbody>
							`;

										b.failed_units.forEach(function (u) {

											let statusColor = 'badge-light-danger';

											if (u.engineer_status === 'accepted_by_engineer') {
												statusColor = 'badge-light-success';
											} else if (u.engineer_status === 'need_review') {
												statusColor = 'badge-light-warning';
											} else if (u.engineer_status === 'assigned_to_engineer') {
												statusColor = 'badge-light-info';
											}

											html += `
									<tr>
										<td>${u.objectid ?? '-'}</td>
										<td>${u.globalid ?? '-'}</td>
										<td class="fw-bold text-dark">${u.owner_name ?? '-'}</td>
										<td>
											<span class="badge ${statusColor}">
												${u.engineer_status ?? '-'}
											</span>
										</td>
										<td class="text-danger fw-bold">${u.reason ?? '-'}</td>
									</tr>
								`;
										});

										html += `
										</tbody>
									</table>
								</div>
							`;

									} else {
										html += `
								<div class="alert alert-warning mb-0 fw-bold">
									${b.reason ?? 'لا يوجد سبب واضح'}
								</div>
							`;
									}

									html += `</div>`;
								});

								$('#failedUnitsContainer').html(html);
								$('#failedUnitsModal').modal('show');
							}

							Swal.fire({
								text: response.message || 'تمت العملية',
								icon: response.approved_count > 0 ? "success" : "warning",
								confirmButtonText: "موافق",
								buttonsStyling: false,
								customClass: {
									confirmButton: "btn btn-primary"
								}
							}).then(() => {
								$('#kt_datatable_audits').DataTable().ajax.reload(null, false);
								$("[type='checkbox']").prop('checked', false);
							});
						},
						error: function (xhr) {
							let message = @json(__('ui.audit.final_approval_failed'));

							if (xhr.responseJSON && xhr.responseJSON.message) {
								message = xhr.responseJSON.message;
							}

							Swal.fire({
								text: message,
								icon: "error",
								buttonsStyling: false,
								confirmButtonText: @json(__('ui.buttons.ok')),
								customClass: {
									confirmButton: "btn btn-primary"
								}
							});
						},
						complete: function () {
							$('#btn_final_approve').removeAttr('data-kt-indicator');
							$('#btn_final_approve').prop('disabled', false);
						}
					});
				});
			});

			$('#btn_undp_final_approve').on('click', function () {
				const selectedIds = [];

				$('#kt_datatable_audits tbody input[type="checkbox"]:checked').each(function () {
					selectedIds.push($(this).val());
				});

				if (selectedIds.length === 0) {
					Swal.fire({
						text: @json(__('ui.audit.select_at_least_one_building')),
						icon: "warning",
						buttonsStyling: false,
						confirmButtonText: @json(__('ui.buttons.ok')),
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
					return;
				}

				Swal.fire({
					title: 'UNDP Final Approve',
					text: 'سيتم اعتماد المباني المحددة كـ UNDP Final Approve بعد التحقق من الاعتماد النهائي.',
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: 'اعتماد UNDP',
					cancelButtonText: @json(__('ui.buttons.cancel')),
					buttonsStyling: false,
					customClass: {
						confirmButton: "btn btn-primary",
						cancelButton: "btn btn-light"
					}
				}).then(function (result) {
					if (!result.isConfirmed) return;

					$.ajax({
						url: "{{ route('audit.building.undpFinalApprove') }}",
						type: "POST",
						data: {
							_token: "{{ csrf_token() }}",
							building_ids: selectedIds
						},
						beforeSend: function () {
							$('#btn_undp_final_approve').attr('data-kt-indicator', 'on');
							$('#btn_undp_final_approve').prop('disabled', true);
						},
						success: function (response) {
							if (response.blocked_buildings && response.blocked_buildings.length > 0) {
								let html = `
									<div class="alert alert-danger mb-5">
										عدد المباني غير المعتمدة UNDP: ${response.blocked_buildings.length}
									</div>
								`;

								response.blocked_buildings.forEach(function (b) {
									html += `
										<div class="mb-7 border border-danger border-dashed p-4 rounded bg-light-danger">
											<h5 class="text-danger mb-1">Building ID: ${b.building_id}</h5>
											<div class="text-dark fw-bold">اسم المبنى: ${b.building_name ?? '-'}</div>
											<div class="text-muted fs-7 mb-3">GlobalID: ${b.building_globalid ?? '-'}</div>
											<div class="alert alert-warning mb-0 fw-bold">${b.reason ?? '-'}</div>
										</div>
									`;
								});

								$('#failedUnitsContainer').html(html);
								$('#failedUnitsModal').modal('show');
							}

							Swal.fire({
								text: response.message || 'تمت العملية',
								icon: response.approved_count > 0 ? "success" : "warning",
								confirmButtonText: "موافق",
								buttonsStyling: false,
								customClass: {
									confirmButton: "btn btn-primary"
								}
							}).then(() => {
								$('#kt_datatable_audits').DataTable().ajax.reload(null, false);
								$("[type='checkbox']").prop('checked', false);
							});
						},
						error: function (xhr) {
							Swal.fire({
								text: xhr.responseJSON?.message || 'حدث خطأ أثناء اعتماد UNDP Final Approve',
								icon: "error",
								buttonsStyling: false,
								confirmButtonText: @json(__('ui.buttons.ok')),
								customClass: {
									confirmButton: "btn btn-primary"
								}
							});
						},
						complete: function () {
							$('#btn_undp_final_approve').removeAttr('data-kt-indicator');
							$('#btn_undp_final_approve').prop('disabled', false);
						}
					});
				});
			});

		});
		function filterAssignUsers(roleType) {
			let $select = $('#assign_user_id');

			$select.val('').trigger('change');

			$select.find('option').each(function () {
				let optionRole = $(this).data('role');

				if (!optionRole || optionRole === roleType) {
					$(this).prop('hidden', false);
				} else {
					$(this).prop('hidden', true);
				}
			});

			$select.trigger('change.select2');
		}
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

		function loadAssignUsers(type) {
			let users = [];
			let label = @json(__('ui.audit.select_user'));

			if (type === 'QC/QA Engineer') {
				users = assignEngineers;
				label = @json(__('ui.audit.select_engineer'));
			} else if (type === 'Legal Auditor') {
				users = assignLawyers;
				label = @json(__('ui.audit.select_lawyer'));
			}

			$('#user_label').text(label);

			const $select = $('#assign_user_id');
			$select.empty().append('<option></option>');

			users.forEach(user => {
				$select.append(`<option value="${user.id}">${user.name}</option>`);
			});

			$select.val(null).trigger('change');
		}
	</script>
@endsection
