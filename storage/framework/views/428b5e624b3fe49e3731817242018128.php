<?php $__env->startSection('title', 'المباني'); ?>
<?php $__env->startSection('pageName', 'المباني'); ?>


<?php $__env->startSection('content'); ?>


<div class="card mb-12">
	<div class="card shadow-sm">
		<div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse"
			data-bs-target="#kt_building_filter">
			<h3 class="card-title">فلتر</h3>
			<div class="card-toolbar rotate-180">
				<i class="ki-duotone ki-down fs-1"></i>
			</div>
		</div>
		<form id="filter_buliding_form" class="form" data-kt-Building-table-filter="form" action="#">

			<div id="kt_building_filter" class="collapse show">


				<div class="card-body">
					<div class="row g-9 mb-8">
						<!--begin::Col-->
						<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterName; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>

						<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Schema::hasColumn('buildings', $filter)): ?>

						<div class="col-md-3 fv-row">
							<label class="fs-6 fw-semibold mb-2"><?php echo e($filter); ?></label>
							<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
								data-hide-search="false" data-placeholder="<?php echo e($filter); ?>"
								name="<?php echo e($filter); ?>">

								<option value=""></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = App\Models\Filter::where('list_name', $filter)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
								<option value="<?php echo e($option->name); ?>"><?php echo e($option->label); ?></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
							</select>
						</div>
						<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
						<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
						<div class="col-md-3 fv-row">

							<label class="fs-6 fw-semibold mb-2">neighborhood</label>
							<select data-allow-clear="true" class="form-select form-select-solid" data-control="select2"
								data-hide-search="false" data-placeholder="neighborhood"
								name="neighborhood">

								<option value=""></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $neighborhoods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
								<option value="<?php echo e($value); ?>"><?php echo e($value); ?></option>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
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
						class="form-control form-control-solid w-250px ps-13" placeholder="بحث" />
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
						تحديث</button>
					<!--begin::Menu 1-->

					<!--begin::Export-->
					<button type="button" class="btn btn-light-primary me-3" data-bs-toggle="modal"
						data-bs-target="#kt_modal_export_buildings">
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
					data-kt-Building-table-toolbar="selected">
					<div class="fw-bold me-5">
						<span class="me-2" data-kt-Building-table-select="selected_count"></span>Selected
					</div>
					<button type="button" class="btn btn-danger" data-kt-Building-table-select="delete_selected">حذف
						المحدد</button>
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
								<h2 class="fw-bold">تصدير المباني </h2>
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
									<input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
									<!--begin::Input group-->
									<div class="fv-row mb-10">
										<!--begin::Label-->
										<label class="fs-6 fw-semibold form-label mb-2"> تحديد الأعمدة </label>
										<!--end::Label-->
										<!--begin::Input-->
										<select multiple data-allow-clear="true" data-close-on-select="false"
											name="building_columns[]" data-control="select2"
											data-placeholder="تحديد الأعمدة" data-hide-search="false"
											class="form-select form-select-solid fw-bold">
											<option value=""></option>
											<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $assessments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
											<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Schema::hasColumn('buildings', $value->name)): ?>
											<option value="<?php echo e($value->name); ?>">
												<?php echo e($value->label.' '.$value->hint); ?>

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
											data-kt-buildings-modal-action="close">إلغاء</button>
										<button type="submit" class="btn btn-primary"
											data-kt-buildings-modal-action="submit">
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
				id="kt_table_Building">
				<thead>
					<tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">

						<th class="min-w-70px"> إسم الباحث</th>
						<th class="min-w-70px"> حالط الاستبيان </th>
						<th class="min-w-70px">رقم المبنى </th>
						<th class="min-w-70px">اسم المبنى </th>
						<th class="min-w-70px">رقم الزون </th>
						<th class="min-w-70px">عدد الوحدات المتضررة </th>
						<th class="min-w-70px">البلدية </th>
						<th class="min-w-70px">الحي </th>
						<th class="min-w-70px">تاريخ التعديل </th>
						<th class="text-end min-w-100px">إجراء</th>
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
	var post_export_url = "<?php echo e(url('export_building')); ?>"
</script>
<script src="<?php echo e(url('')); ?>/assets/js/custom/DamageAssessment/export-buildings.js"></script>

<script>
	var KTBuildingsList = function() {
		var table = document.getElementById('kt_table_Building');
		var datatable;
		const filterForm = document.querySelector('[data-kt-Building-table-filter="form"]');

		var initBuildingTable = function() {
			if (!table) return;

			datatable = $(table).DataTable({
				serverSide: true,
				processing: true,
				pageLength: 10, // Increased from 4 for better UX
				ajax: {
					url: "<?php echo e(url('building/show')); ?>",
					// Dynamic data collection: Replaces 15+ manual lines with a loop
					data: function(d) {
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

			datatable.on('draw', function() {
				KTMenu.createInstances(); // Vital for Metronic dropdowns
			});
		};

		var handleSearchDatatable = () => {
			const filterSearch = document.querySelector('[data-kt-Building-table-filter="search"]');
			if (!filterSearch) return;

			filterSearch.addEventListener('keyup', function(e) {
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

			filterButton.addEventListener('click', function() {
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

			resetButton.addEventListener('click', function() {
				const filterForm = document.querySelector('[data-kt-Building-table-filter="form"]');

				// Efficiently reset all inputs and Select2 dropdowns
				$(filterForm).find('select').val('').trigger('change');
				$(filterForm).find('input').val('');

				datatable.search('').ajax.reload();
			});
		};

		return {
			init: function() {
				initBuildingTable();
				handleSearchDatatable();
				handleFilterDatatable();
				handleResetForm();
			}
		};
	}();

	KTUtil.onDOMContentLoaded(function() {
		KTBuildingsList.init();
	});
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/buildings.blade.php ENDPATH**/ ?>