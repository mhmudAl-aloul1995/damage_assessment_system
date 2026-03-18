<?php $__env->startSection('title', 'الباحثين'); ?>
<?php $__env->startSection('pageName', 'الباحثين'); ?>


<?php $__env->startSection('content'); ?>



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
						<input type="text" data-kt-engineer-table-filter="search"
							class="form-control form-control-solid w-250px ps-13" placeholder="بحث" />
					</div>
					<!--end::Search-->
				</div>
				<!--begin::Card title-->
				<!--begin::Card toolbar-->
				<div class="card-toolbar">
					<!--begin::Toolbar-->
					<div class="d-flex justify-content-end" data-kt-Engineer-table-toolbar="base">
						<!--begin::Filter-->
						<button type="button" class="btn btn-light-primary me-3"
							onclick="$('#kt_table_engineer').DataTable().ajax.reload()">
							<i class=" ki-reload fs-2">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
							تحديث</button>
						<!--begin::Menu 1-->



					</div>




					<!--end::Modal - Add task-->
				</div>
				<!--end::Card toolbar-->
			</div>
			<!--end::Card header-->
			<!--begin::Card body-->
			<div class="card-body py-4">
				<!--begin::Table-->
				<table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_table_engineer">
					<thead>
						<tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">

							<th class="min-w-70px"> إسم الباحث</th>
							<th class="min-w-70px"> إجمالي الإستبيانات </th>
							<th class="min-w-70px"> إجمالي الإستبيانات المكتملة</th>
							<th class="min-w-70px"> إجمالي الإستبيانات غير مكتملة</th>
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
		var KTEngineersList = function () {
			// Define shared variables
			var table = document.getElementById('kt_table_engineer');
			var kt_engineer_filter = document.getElementById('kt_engineer_filter');
			var datatable;
			var toolbarBase;
			var toolbarSelected;
			var selectedCount;

			// Private functions
			var initEngineerTable = function () {
				// Set date data order
				const tableRows = table.querySelectorAll('tbody tr');



				// Init datatable --- more info on datatables: https://datatables.net/manual/
				datatable = $(table).DataTable({
					serverSide: true,
					ajax: {
						url: "<?php echo e(url('engineer/show')); ?>",
						data: function (d) {


						},
					},

					"info": true,
					'order': [],
					"pageLength": 10,
					"lengthChange": true,
					processing: true,
					columns: [
						{ data: 'assignedto', name: 'assignedto', searchable: true },
						{ data: 'all', name: 'all', searchable: false },
						{ data: 'complete', name: 'complete', searchable: false },
						{ data: 'in_complete', name: 'in_complete', searchable: false },



					],
					createdRow: (row, data, index) => {
						$(row).css('cursor', 'pointer')
						$(row).on('click', function (e) {
							e.preventDefault()
							var url_eng="<?php echo e(url('engineerAssessments')); ?>/"+data.assignedto
							    window.open(url_eng, '_blank');


						});

					}
				});

				// Re-init functions on every table re-draw -- more info: https://datatables.net/reference/event/draw
				datatable.on('draw', function () {

					KTMenu.createInstances(); // For Metronic		

				});
			}



			var handleSearchDatatable = () => {
				const filterSearch = document.querySelector('[data-kt-engineer-table-filter="search"]');

				filterSearch.addEventListener('keyup', function (e) {
					datatable.search(e.target.value).draw();
				});
			}

			return {
				// Public functions  
				init: function () {
					if (!table) {
						return;
					}

					initEngineerTable();
					handleSearchDatatable();

				}
			}
		}();


		KTUtil.onDOMContentLoaded(function () {
			KTEngineersList.init();


		});



	</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/engineers.blade.php ENDPATH**/ ?>