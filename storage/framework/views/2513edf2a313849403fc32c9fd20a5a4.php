<?php $__env->startSection('title', __('ui.audit.title')); ?>
<?php $__env->startSection('pageName', __('ui.audit.title')); ?>


<?php $__env->startSection('content'); ?>
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
						<h3 class="fw-bold m-0"><?php echo e(__('ui.audit.filters')); ?></h3>
					</div>

					<div class="card-toolbar">
						<button type="button" class="btn btn-sm btn-light-danger" id="resetFilters">
							<?php echo e(__('ui.audit.reset')); ?>

						</button>
					</div>
				</div>

				<div class="card-body">
					<div class="row g-5">
						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.search_building_name')); ?></label>
							<input type="text" id="filter_building_name" class="form-control form-control-solid"
								placeholder="<?php echo e(__('ui.audit.building_name_placeholder')); ?>" />
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.engineer')); ?></label>
							<select id="filter_engineer" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="<?php echo e(__('ui.audit.select_engineer')); ?>">
								<option></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $engineers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $engineer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
									<option value="<?php echo e($engineer->id); ?>"><?php echo e($engineer->name); ?></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.lawyer')); ?></label>
							<select id="filter_lawyer" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="<?php echo e(__('ui.audit.select_lawyer')); ?>">
								<option></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $lawyers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lawyer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
									<option value="<?php echo e($lawyer->id); ?>"><?php echo e($lawyer->name); ?></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.engineering_status')); ?></label>
							<select id="filter_eng_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="<?php echo e(__('ui.audit.select_status')); ?>">
								<option></option>
								<option value="pending">Pending</option>
								<option value="accepted_by_engineer">Accepted By Engineer</option>
								<option value="rejected_by_engineer">Rejected By Engineer</option>
								<option value="assigned_to_engineer">Assigned To Engineer</option>
								<option value="need_review">Need Review</option>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.legal_status')); ?></label>
							<select id="filter_legal_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="<?php echo e(__('ui.audit.select_status')); ?>">
								<option></option>
								<option value="pending">Pending</option>
								<option value="assigned_to_lawyer">Assigned To Lawyer</option>
								<option value="accepted_by_lawyer">Accepted By Lawyer</option>
								<option value="legal_notes">Legal Notes</option>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.final_approval')); ?></label>
							<select id="filter_final_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="<?php echo e(__('ui.audit.select_status')); ?>">
								<option></option>
								<option value="pending">Pending</option>
								<option value="approved">Approved</option>
								<option value="rejected">Rejected</option>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.area')); ?></label>
							<input type="text" id="filter_area" class="form-control form-control-solid"
								placeholder="<?php echo e(__('ui.audit.area_placeholder')); ?>" />
						</div>
						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.field_engineer')); ?></label>
							<select id="filter_field_engineer" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="<?php echo e(__('ui.audit.select_field_engineer')); ?>">
								<option></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $assignedTo; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eng): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
									<option value="<?php echo e($eng->assignedto); ?>"><?php echo e($eng->assignedto); ?></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
							</select>
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.damage_status')); ?></label>
							<select id="filter_damage_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-placeholder="<?php echo e(__('ui.audit.select_status')); ?>">
								<option></option>
								<option value="fully_damaged">Fully Damaged</option>
								<option value="partially_damaged">Partially Damaged</option>
								<option value="committee_review">Committee Review </option>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.from_creation_date')); ?></label>
							<input type="date" id="filter_from_date" placeholder="<?php echo e(__('ui.audit.from_creation_date')); ?>"
								class="form-control form-control-solid">
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold"><?php echo e(__('ui.audit.to_creation_date')); ?></label>
							<input type="date" id="filter_to_date" placeholder="<?php echo e(__('ui.audit.to_creation_date')); ?>"
								class="form-control form-control-solid">
						</div>
						<div class="col-md-3 d-flex align-items-end">
							<button type="button" class="btn btn-primary w-100" id="applyFilters">
								<?php echo e(__('ui.audit.apply_filters')); ?>

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
								placeholder="<?php echo e(__('ui.audit.search_buildings')); ?>" />
						</div>
					</div>
					<div class="card-toolbar gap-3">
						<button onclick="refreshTable(this)" class="btn btn-success btn-sm">
							<?php echo e(__('ui.audit.refresh')); ?> <i class="ki-duotone ki-update-file"></i>
						</button>

						<button id="btn_final_approve" class="btn btn-warning btn-sm">
							<?php echo e(__('ui.audit.approve_final')); ?> <i class="ki-duotone ki-check-circle"></i>
						</button>

						<button id="btn_assign_to_lawyer" class="btn btn-primary btn-sm">
							<?php echo e(__('ui.audit.assign_to_lawyer')); ?> <i class="ki-duotone ki-plus"></i>
						</button>

						<button id="btn_assign_to_engineer" class="btn btn-info btn-sm">
							<?php echo e(__('ui.audit.assign_to_engineer')); ?> <i class="ki-duotone ki-plus"></i>
						</button>
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
						<h2 class="fw-bold" id="modal_title"><?php echo e(__('ui.audit.assign_buildings')); ?></h2>
						<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
							<i class="ki-duotone ki-cross fs-1"></i>
						</div>
					</div>

					<?php echo e(csrf_field()); ?>

					<div class="modal-body py-10 px-lg-17">
						<input type="hidden" name="type" id="assign_type">
						<input type="hidden" name="status_id" id="assign_status_id">
						<!-- Placeholder for selected IDs -->
						<div id="selected_buildings_container"></div>

						<div class="fv-row mb-7">
							<label id="user_label"
								class="required fs-6 fw-semibold mb-2"><?php echo e(__('ui.audit.select_engineer')); ?></label>

							<select name="user_id" id="assign_user_id" class="form-select form-select-solid"
								data-control="select2" data-placeholder="<?php echo e(__('ui.audit.select_user')); ?>"
								data-dropdown-parent="#kt_modal_assign">
								<option></option>
							</select>
						</div>

						<script>
							const assignEngineers = <?php echo json_encode($assignEngineers ?? $engineers, 15, 512) ?>;
							const assignLawyers = <?php echo json_encode($assignLawyers ?? $lawyers, 15, 512) ?>;
						</script>
					</div>

					<div class="modal-footer flex-center">
						<button type="reset" class="btn btn-light me-3"
							data-bs-dismiss="modal"><?php echo e(__('ui.buttons.cancel')); ?></button>
						<button type="submit" class="btn btn-primary" id="kt_modal_assign_submit">
							<span class="indicator-label"><?php echo e(__('ui.audit.agree')); ?></span>
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
					<h2 class="fw-bold" id="notesHistoryModalTitle"><?php echo e(__('ui.audit.status_history')); ?></h2>
					<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
						<i class="ki-duotone ki-cross fs-1"></i>
					</div>
				</div>

				<div class="modal-body">
					<div class="table-responsive">
						<table class="table table-row-bordered table-striped gy-5 gs-7">
							<thead>
								<tr class="fw-bold fs-6 text-gray-800">
									<th><?php echo e(__('ui.audit.status')); ?></th>
									<th><?php echo e(__('ui.audit.user')); ?></th>
									<th><?php echo e(__('ui.audit.role')); ?></th>
									<th><?php echo e(__('ui.audit.notes')); ?></th>
									<th><?php echo e(__('ui.audit.date')); ?></th>
									<th><?php echo e(__('ui.audit.actions')); ?></th>

								</tr>
							</thead>
							<tbody id="buildingHistoryTableBody">
								<tr>
									<td colspan="6" class="text-center text-muted"><?php echo e(__('ui.audit.no_data')); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal"><?php echo e(__('ui.audit.close')); ?></button>
				</div>
			</div>
		</div>
	</div>
<?php $__env->stopSection(); ?>





<?php $__env->startSection('script'); ?>




	<script>
		$(document).ready(function () {
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
			var table = $('#kt_datatable_audits').DataTable({
				processing: true,
				serverSide: true,
				ajax: {
					url: "<?php echo e(route('audit.index')); ?>",
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
				lengthMenu: [[10, 20, 25, 50, -1], [10, 20, 25, 50, "All"]],
				pageLength: 20,
				columnDefs: [{
					targets: 0,
					orderable: false,
					searchable: false
				}],
				order: [[8, 'desc']],
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
					{ data: 'building_name', name: 'building_name' },
					{ data: 'assignedto', name: 'assignedto' },
					{ data: 'engineer', name: 'engineer', searchable: false },
					{ data: 'lawyer', name: 'lawyer', searchable: false },
					{ data: 'eng_status', name: 'eng_status' },
					{ data: 'law_status', name: 'law_status' },
					{ data: 'finalApproval' },
					{ data: 'creationdate' },
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
						var url_eng = "<?php echo e(url('showAssessmentAudit/')); ?>/" + data.globalid;
						window.open(url_eng, '_blank');
					});
				},

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
				/* 			$('#filter_building_name').val('');
							$('#filter_engineer').val(null).trigger('change');
							$('#filter_lawyer').val(null).trigger('change');
							$('#filter_eng_status').val(null).trigger('change');
							$('#filter_legal_status').val(null).trigger('change');
							$('#filter_final_status').val(null).trigger('change');
							$('#filter_area').val(''); */
				$('select').val(null).trigger('change');
				$('input').val('');


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
						text: <?php echo json_encode(__('ui.audit.select_at_least_one_building'), 15, 512) ?>,
						icon: "warning",
						buttonsStyling: false,
						confirmButtonText: <?php echo json_encode(__('ui.buttons.ok'), 15, 512) ?>,
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
					return;
				}

				$('#modal_title').text(<?php echo json_encode(__('ui.audit.assign_engineer'), 15, 512) ?>);
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
						text: <?php echo json_encode(__('ui.audit.select_at_least_one_building'), 15, 512) ?>,
						icon: "warning",
						buttonsStyling: false,
						confirmButtonText: <?php echo json_encode(__('ui.buttons.ok'), 15, 512) ?>,
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
					return;
				}

				$('#modal_title').text(<?php echo json_encode(__('ui.audit.assign_lawyer'), 15, 512) ?>);
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
					url: "<?php echo e(route('audit.assign')); ?>",
					method: 'POST',
					data: form.serialize(),
					success: function (response) {

						$('#kt_modal_assign').modal('hide');

						// تنبيه بالنجاح
						Swal.fire({
							text: <?php echo json_encode(__('ui.audit.assigned_success'), 15, 512) ?>,
							icon: "success",
							buttonsStyling: false,
							confirmButtonText: <?php echo json_encode(__('ui.buttons.ok'), 15, 512) ?>,
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
							text: <?php echo json_encode(__('ui.audit.error_try_later'), 15, 512) ?>,
							icon: "error",
							buttonsStyling: false,
							confirmButtonText: <?php echo json_encode(__('ui.buttons.ok'), 15, 512) ?>,
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
				let buildingName = $(this).data('building-name') || <?php echo json_encode(__('ui.audit.default_building'), 15, 512) ?>;

				$('#notesHistoryModalTitle').text(<?php echo json_encode(__('ui.audit.status_history'), 15, 512) ?> + ' - ' + buildingName);
				$('#buildingHistoryTableBody').html(`
																		<tr>
																			<td colspan="6" class="text-center">${<?php echo json_encode(__('ui.audit.loading'), 15, 512) ?>}</td>
																		</tr>
																	`);

				$('#notesHistoryModal').modal('show');

				$.ajax({
					url: "<?php echo e(route('audit.building.history')); ?>",
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
																										${<?php echo json_encode(__('ui.audit.delete_record'), 15, 512) ?>}
																									</button>
																								` : '-'}
																							</td>
																						</tr>
																					`;
							});
						} else {
							rows = `
																					<tr>
																						<td colspan="6" class="text-center text-muted">${<?php echo json_encode(__('ui.audit.no_status_history'), 15, 512) ?>}</td>
																					</tr>
																				`;
						}

						$('#buildingHistoryTableBody').html(rows);
					},
					error: function () {
						$('#buildingHistoryTableBody').html(`
																				<tr>
																					<td colspan="6" class="text-center text-danger">${<?php echo json_encode(__('ui.audit.failed_load_history'), 15, 512) ?>}</td>
																				</tr>
																			`);
					}
				});
			});


			$(document).on('click', '.btn-delete-history', function () {
				let id = $(this).data('id');
				let button = $(this);

				if (!confirm(<?php echo json_encode(__('ui.audit.confirm_delete_record'), 15, 512) ?>)) {
					return;
				}

				$.ajax({
					url: "<?php echo e(route('audit.building.history.delete')); ?>",
					type: "POST",
					data: {
						_token: "<?php echo e(csrf_token()); ?>",
						id: id
					},
					success: function (response) {
						if (response.status) {
							toastr.success(response.message || <?php echo json_encode(__('ui.audit.record_deleted'), 15, 512) ?>);
							button.closest('tr').remove();

							if ($('#buildingHistoryTableBody tr').length === 0) {
								$('#buildingHistoryTableBody').html(`
																				<tr>
																					<td colspan="6" class="text-center text-muted">${<?php echo json_encode(__('ui.audit.no_status_history'), 15, 512) ?>}</td>
																				</tr>
																			`);
							}
						} else {
							toastr.error(response.message || <?php echo json_encode(__('ui.audit.delete_failed'), 15, 512) ?>);
						}
					},
					error: function (xhr) {
						let message = <?php echo json_encode(__('ui.audit.delete_failed'), 15, 512) ?>;

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
						text: <?php echo json_encode(__('ui.audit.select_at_least_one_building'), 15, 512) ?>,
						icon: "warning",
						buttonsStyling: false,
						confirmButtonText: <?php echo json_encode(__('ui.buttons.ok'), 15, 512) ?>,
						customClass: {
							confirmButton: "btn btn-primary"
						}
					});
					return;
				}

				Swal.fire({
					title: <?php echo json_encode(__('ui.audit.final_approval_title'), 15, 512) ?>,
					text: <?php echo json_encode(__('ui.audit.final_approval_confirm'), 15, 512) ?>,
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: <?php echo json_encode(__('ui.audit.yes_approve'), 15, 512) ?>,
					cancelButtonText: <?php echo json_encode(__('ui.buttons.cancel'), 15, 512) ?>,
					buttonsStyling: false,
					customClass: {
						confirmButton: "btn btn-warning",
						cancelButton: "btn btn-light"
					}
				}).then(function (result) {
					if (!result.isConfirmed) return;

					$.ajax({
						url: "<?php echo e(route('audit.building.finalApprove')); ?>",
						type: "POST",
						data: {
							_token: "<?php echo e(csrf_token()); ?>",
							building_ids: selectedIds
						},
						beforeSend: function () {
							$('#btn_final_approve').attr('data-kt-indicator', 'on');
							$('#btn_final_approve').prop('disabled', true);
						},
						success: function (response) {
							Swal.fire({
								text: response.message || <?php echo json_encode(__('ui.audit.approved_success'), 15, 512) ?>,
								icon: "success",
								buttonsStyling: false,
								confirmButtonText: <?php echo json_encode(__('ui.buttons.ok'), 15, 512) ?>,
								customClass: {
									confirmButton: "btn btn-primary"
								}
							}).then(function () {
								$('#kt_datatable_audits').DataTable().ajax.reload(null, false);
								$("[type='checkbox']").prop('checked', false);
							});
						},
						error: function (xhr) {
							let message = <?php echo json_encode(__('ui.audit.final_approval_failed'), 15, 512) ?>;

							if (xhr.responseJSON && xhr.responseJSON.message) {
								message = xhr.responseJSON.message;
							}

							Swal.fire({
								text: message,
								icon: "error",
								buttonsStyling: false,
								confirmButtonText: <?php echo json_encode(__('ui.buttons.ok'), 15, 512) ?>,
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
			let label = <?php echo json_encode(__('ui.audit.select_user'), 15, 512) ?>;

			if (type === 'QC/QA Engineer') {
				users = assignEngineers;
				label = <?php echo json_encode(__('ui.audit.select_engineer'), 15, 512) ?>;
			} else if (type === 'Legal Auditor') {
				users = assignLawyers;
				label = <?php echo json_encode(__('ui.audit.select_lawyer'), 15, 512) ?>;
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
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/audit.blade.php ENDPATH**/ ?>