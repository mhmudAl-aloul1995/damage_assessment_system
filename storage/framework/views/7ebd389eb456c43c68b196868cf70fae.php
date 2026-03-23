<?php $__env->startSection('title', 'الوحدات السكنية'); ?>
<?php $__env->startSection('pageName', 'الوحدات السكنية'); ?>


<?php $__env->startSection('content'); ?>

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
							<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterName; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>

								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Schema::hasColumn('housing_units', $filter)): ?>

									<?php
										if ($filter == 'governorate' || $filter == 'municipalitie' || $filter == 'neighborhood' || $filter == 'locality') {

											continue;
										}
									?>


									<div class="col-md-3 fv-row">
										<label class="fs-6 fw-semibold mb-2"><?php echo e($filter); ?></label>
										<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
											data-hide-search="false" data-placeholder="<?php echo e($filter); ?>" name="<?php echo e($filter); ?>">

											<option value=""></option>
											<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = App\Models\Filter::where('list_name', $filter)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
												<option value="<?php echo e($option->name); ?>"><?php echo e($option->label); ?></option>
											<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
										</select>
									</div>
								<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
							<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>







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
										<input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
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
												<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $assessments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
													<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Schema::hasColumn('housing_units', $value->name)): ?>
														<option value="<?php echo e($value->name); ?>">
															<?php echo e($value->hint ? $value->hint : $value->label); ?>

														</option>
													<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
												<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

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

							<th class="min-w-10px">إسم الباحث </th>
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
							<th class="text-end min-w-100px"> الإجراءات</th>
						</tr>
					</thead>
					<tbody class="text-gray-600 fw-semibold"></tbody>



				</table>
				<!--end::Table-->
			</div>
			<!--end::Card body-->
		</div>
	</div>

<?php $__env->stopSection(); ?>





<?php $__env->startSection('script'); ?>



	<script>

		var url_phc = "<?php echo e(url('')); ?>";
		var post_export_url = "<?php echo e(url('export_housings')); ?>" </script>
	<script src="<?php echo e(url('')); ?>/assets/js/custom/DamageAssessment/export-housings.js"></script>

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
				const globalid = '<?php echo e($globalid); ?>';
				var filterForm = document.getElementById('filter_housing_form');

				// Init datatable --- more info on datatables: https://datatables.net/manual/
				datatable = $(table).DataTable({
					serverSide: true,
					ajax: {
						url: "<?php echo e(url('housing/show')); ?>",
						data: function (d) {

							if (filterForm) {
								const formData = new FormData(filterForm);
								formData.forEach((value, key) => {
									d[key] = value;
								});
							}
							d.unit_support_needed = "<?php echo e(request('unit_support_needed')); ?>";



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
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/housing.blade.php ENDPATH**/ ?>