<?php $__env->startSection('title', 'الإستبيان'); ?>
<?php $__env->startSection('pageName', 'الإستبيان'); ?>


<?php $__env->startSection('content'); ?>
<style>
	#externalLegendDiv {
		padding: 10px;
		background-color: white;
		border: 1px solid #ccc;
		max-height: 400px;
		overflow-y: auto;
	}

	.esri-popup {
		z-index: 999 !important;
	}

	/* Specifically for the main container if it still hides */
	.esri-popup__main-container {
		z-index: 1000 !important;
	}

	.esri-legend__layer-caption {
		display: none;
	}

	td {

		font-size: 11px !important;

	}

	/* Force pagination buttons to stay on one line */
	#kt_table_building_wrapper .dataTables_paginate ul.pagination {
		display: flex !important;
		flex-wrap: nowrap !important;
		margin-bottom: 0;
	}

	/* Ensure the footer row uses full width */
	#kt_table_building_wrapper .row {
		width: 100%;
		margin-right: 0;
		margin-left: 0;
	}



	.esri-scale-bar__label {
		color: white !important;
		background-color: black !important;


	}
</style>
<div class="row g-5 g-xl-8">
	<!--begin::Col-->
	<!-- 1. Changed to responsive column: col-sm-6 col-xl-3 -->
	<div class="col-sm-6 col-xl-3 mb-5">
		<div class="card card-xl-stretch mb-xl-8">
			<div class="card-body p-0">
				<!-- 2. Changed h-275px to min-h-275px to allow expansion if text wraps -->
				<div style="background-color: #ad3d3d;" class="px-9 pt-7 card-rounded min-h-275px w-100">
					<div class="d-flex flex-stack">
						<h3 class="m-0 text-white fw-bold fs-3">المباني</h3>
						<div class="ms-1">
							<button type="button"
								class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
								data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
								<i class="ki-duotone ki-category fs-7 fs-lg-6"><span class="path1"></span><span
										class="path2"></span><span class="path3"></span><span class="path4"></span></i>
							</button>
						</div>
					</div>
					<div class="d-flex text-center flex-column text-white pt-8">
						<!-- Added text-wrap here -->
						<span class="fw-semibold fs-7 text-wrap">مباني تم تقييمها</span>
						<span
							class="fw-bold fs-1 fs-lg-2x pt-1"><?php echo e($buildingStats['fully_damaged'] + $buildingStats['partially_damaged'] + $buildingStats['committee_review'] + $buildingStats['security_unsafe']); ?></span>
					</div>
				</div>

				<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1"
					style="margin-top: -100px">
					<!-- Item 1 -->
					<div class="d-flex align-items-center mb-6">
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten"><i class="ki-duotone ki-compass fs-1"><span
										class="path1"></span><span class="path2"></span></i></span>
						</div>
						<div class="d-flex align-items-center flex-wrap w-100">
							<div class="mb-1 pe-3 flex-grow-1">
								<!-- Added text-wrap -->
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">ضرر
									كلي</a>
							</div>
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									<?php echo e($buildingStats['fully_damaged']); ?>

								</div>
							</div>
						</div>
					</div>

					<!-- Item 2 -->
					<div class="d-flex align-items-center mb-6">
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten"><i class="ki-duotone ki-element-11 fs-1"><span
										class="path1"></span><span class="path2"></span><span class="path3"></span><span
										class="path4"></span></i></span>
						</div>
						<div class="d-flex align-items-center flex-wrap w-100">
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">ضرر
									جزئي</a>
							</div>
							<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
								<?php echo e($buildingStats['partially_damaged']); ?>

							</div>
						</div>
					</div>

					<!-- Item 3 -->
					<div class="d-flex align-items-center mb-6">
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten"><i class="ki-duotone ki-graph-up fs-1"><span
										class="path1"></span><span class="path2"></span><span class="path3"></span><span
										class="path4"></span><span class="path5"></span><span
										class="path6"></span></i></span>
						</div>
						<div class="d-flex align-items-center flex-wrap w-100">
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#"
									class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">لجنة
									فنية</a>
							</div>
							<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
								<?php echo e($buildingStats['committee_review']); ?>

							</div>
						</div>
					</div>

					<!-- Item 4 -->
					<div class="d-flex align-items-center mb-6">
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten"><i class="ki-duotone ki-document fs-3"><span
										class="path1"></span><span class="path2"></span></i></span>
						</div>
						<div class="d-flex align-items-center flex-wrap w-100">
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#"
									class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">تعيق
									التقييم</a>
							</div>
							<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1"><?php echo e($buildingStats['security_unsafe']); ?>

							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!--end::Col-->
	<!--begin::Col-->
	<div class="col-xl-3">
		<!--begin::Mixed Widget 1-->
		<div class="card card-xl-stretch mb-xl-8">
			<!--begin::Body-->
			<div class="card-body p-0">
				<!--begin::Header-->
				<div style="  background-color: #ccb050; " class="px-9 pt-7 text-white card-rounded h-275px w-100 ">
					<!--begin::Heading-->
					<div class="d-flex flex-stack">
						<h3 class="m-0  text-white fw-bold fs-3">المباني </h3>
						<div class="ms-1">
							<!--begin::Menu-->
							<button type="button"
								class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
								data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
								<i class="ki-duotone ki-category fs-7 fs-lg-6">
									<span class="path1"></span>
									<span class="path2"></span>
									<span class="path3"></span>
									<span class="path4"></span>
								</i>
							</button>

						</div>
					</div>
					<!--end::Heading-->
					<!--begin::Balance-->
					<div class="d-flex text-center flex-column  pt-8">
						<span class="fw-semibold fs-7"> مباني لم يتم تقييمها </span>
						<span class="fw-bold fs-1 fs-lg-2x pt-1"><?php echo e($buildingStats['not_completed']); ?></span>
					</div>
					<!--end::Balance-->
				</div>
				<!--end::Header-->
				<!--begin::Items-->
				<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1"
					style="margin-top: -100px">

					<!--begin::Item-->
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-document fs-3">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
									وجود جثث فيها </a>
								<div class="text-gray-400 fw-semibold fs-7"></div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1"><?php echo e($buildingStats['bodies']); ?>

								</div>
								<i class="ki-duotone  fs-5 text-danger ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->

					</div>
					<!--end::Item-->
					<!--begin::Item-->
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-document fs-3">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
									ذخائر غير منفجرة </a>
								<div class="text-gray-400 fw-semibold fs-7"></div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1"><?php echo e($buildingStats['uxo']); ?></div>
								<i class="ki-duotone  fs-5 text-danger ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->

					</div>
					<!--end::Item-->
					<!--begin::Item-->
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-document fs-3">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
									ركام يعيق الوصول </a>
								<div class="text-gray-400 fw-semibold fs-7"></div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1"><?php echo e($buildingStats['debris']); ?>

								</div>
								<i class="ki-duotone  fs-5 text-danger ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->

					</div>
					<!--end::Item-->
					<!--begin::Item-->
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-document fs-3">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-7 text-gray-800 text-hover-primary fw-bold">

									صعوبة التقييم</a>
								<div class="text-gray-400 fw-semibold fs-7"></div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									<?php echo e($buildingStats['security_unsafe']); ?>

								</div>
								<i class="ki-duotone  fs-5 text-danger ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->

					</div>
					<!--end::Item-->
				</div>
				<!--end::Items-->
			</div>
			<!--end::Body-->
		</div>
		<!--end::Mixed Widget 1-->
	</div>
	<!--end::Col-->


	<!--begin::Col-->
	<div class="col-xl-3">
		<!--begin::Mixed Widget 1-->
		<div class="card card-xl-stretch mb-xl-8">
			<!--begin::Body-->
			<div class="card-body p-0">
				<!--begin::Header-->
				<div style=" background-color: #67986c; " class="px-9 pt-7 card-rounded h-275px w-100">
					<!--begin::Heading-->
					<div class="d-flex flex-stack">
						<h3 class="m-0 text-white fw-bold fs-3">الوحدات السكانية </h3>
						<div class="ms-1">
							<!--begin::Menu-->
							<button type="button"
								class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
								data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
								<i class="ki-duotone ki-category fs-7 fs-lg-6">
									<span class="path1"></span>
									<span class="path2"></span>
									<span class="path3"></span>
									<span class="path4"></span>
								</i>
							</button>

							<!--end::Menu-->
						</div>
					</div>
					<!--end::Heading-->
					<!--begin::Balance-->
					<div class="d-flex text-center flex-column text-white pt-8">
						<span class="fw-semibold fs-7">إجمالي الوحدات السكانية </span>
						<span
							class="fw-bold fs-1 fs-lg-2x pt-1"><?php echo e($unitStats['fully_damaged'] + $unitStats['partially_damaged'] + $unitStats['committee_review'] + $unitStats['security_unsafe']); ?></span>
					</div>
					<!--end::Balance-->
				</div>
				<!--end::Header-->
				<!--begin::Items-->
				<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1"
					style="margin-top: -100px">

					<!--begin::Item-->
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-compass fs-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">ضرر كلي</a>
								<div class="text-gray-400 fw-semibold fs-7"> </div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1"><?php echo e($unitStats['fully_damaged']); ?>

								</div>
								<i class="ki-duotone  fs-5 text-success ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->
					</div>
					<!--end::Item-->
					<!--begin::Item-->
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-element-11 fs-1">
									<span class="path1"></span>
									<span class="path2"></span>
									<span class="path3"></span>
									<span class="path4"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">ضرر جزئي</a>
								<div class="text-gray-400 fw-semibold fs-7"></div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									<?php echo e($unitStats['partially_damaged']); ?>

								</div>
								<i class="ki-duotone fs-5 text-danger ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->
					</div>
					<!--end::Item-->
					<!--begin::Item-->
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-graph-up fs-1">
									<span class="path1"></span>
									<span class="path2"></span>
									<span class="path3"></span>
									<span class="path4"></span>
									<span class="path5"></span>
									<span class="path6"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold"> لجنة
									فنية</a>
								<div class="text-gray-400 fw-semibold fs-7"> </div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									<?php echo e($unitStats['committee_review']); ?>

								</div>
							</div>
							<i class="ki-duotone fs-5 text-success ms-1">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
						</div>
						<!--end::Label-->
					</div>
					<!--end::Description-->
					<!-- Item 4 -->
					<div class="d-flex align-items-center mb-6">
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten"><i class="ki-duotone ki-document fs-3"><span
										class="path1"></span><span class="path2"></span></i></span>
						</div>
						<div class="d-flex align-items-center flex-wrap w-100">
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#"
									class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold text-wrap">تعيق
									التقييم</a>
							</div>
							<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1"><?php echo e($unitStats['security_unsafe']); ?>

							</div>
						</div>
					</div>
				</div>
				<!--end::Item-->
				<!--begin::Item-->

			</div>
			<!--end::Items-->

		</div>
		<!--end::Body-->
	</div>
	<!--end::Mixed Widget 1-->
	<div class="col-xl-3">
		<!--begin::Mixed Widget 1-->
		<div class="card card-xl-stretch mb-xl-8">
			<!--begin::Body-->
			<div class="card-body p-0">
				<!--begin::Header-->
				<div style=" background-color: #0163ac; " class="px-9 pt-7 card-rounded h-275px w-100 ">
					<!--begin::Heading-->
					<div class="d-flex flex-stack">
						<h3 class="m-0 text-white fw-bold fs-3">الوحدات السكانية </h3>
						<div class="ms-1">
							<!--begin::Menu-->
							<button type="button"
								class="btn btn-sm btn-icon btn-color-white btn-active-white border-0 me-n3"
								data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
								<i class="ki-duotone ki-category fs-7 fs-lg-6">
									<span class="path1"></span>
									<span class="path2"></span>
									<span class="path3"></span>
									<span class="path4"></span>
								</i>
							</button>

							<!--end::Menu-->
						</div>
					</div>
					<!--end::Heading-->
					<!--begin::Balance-->
					<div class="d-flex text-center flex-column text-white pt-8">
						<span class="fw-semibold fs-7">إجمالي الوحدات السكانية </span>
						<span
							class="fw-bold fs-1 fs-lg-2x pt-1"><?php echo e($unitStats['fully_damaged'] + $unitStats['partially_damaged'] + $unitStats['committee_review'] + $unitStats['security_unsafe']); ?></span>
					</div>
					<!--end::Balance-->
				</div>
				<!--end::Header-->
				<!--begin::Items-->
				<div class="bg-body shadow-sm card-rounded mx-9 mb-9 px-6 py-9 position-relative z-index-1"
					style="margin-top: -100px">


					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-document fs-3">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="<?php echo e(url('housing')); ?>?unit_support_needed=yes"
									class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
									تدعيم هيكلي</a>
								<div class="text-gray-400 fw-semibold fs-7"></div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1">
									<?php echo e($unitStats['unit_support_needed']); ?>

								</div>
								<i class="ki-duotone  fs-5 text-danger ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->

					</div>
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-document fs-3">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
									قابل للإنهيار </a>
								<div class="text-gray-400 fw-semibold fs-7"></div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1"><?php echo e($unitStats['unit_stripping']); ?>

								</div>
								<i class="ki-duotone  fs-5 text-danger ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->

					</div>
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-document fs-3">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
									مناسبة للسكن</a>
								<div class="text-gray-400 fw-semibold fs-7"></div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1"><?php echo e($unitStats['habitable']); ?></div>
								<i class="ki-duotone  fs-5 text-danger ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->

					</div>
					<div class="d-flex align-items-center mb-6">
						<!--begin::Symbol-->
						<div class="symbol symbol-25px w-25px me-5">
							<span class="symbol-label bg-lighten">
								<i class="ki-duotone ki-document fs-3">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</span>
						</div>
						<!--end::Symbol-->
						<!--begin::Description-->
						<div class="d-flex align-items-center flex-wrap w-100">
							<!--begin::Title-->
							<div class="mb-1 pe-3 flex-grow-1">
								<a href="#" class="fs-10 fs-lg-7 text-gray-800 text-hover-primary fw-bold">
									متأثرة بالحريق </a>
								<div class="text-gray-400 fw-semibold fs-7"></div>
							</div>
							<!--end::Title-->
							<!--begin::Label-->
							<div class="d-flex align-items-center">
								<div class="fw-bold fs-7 fs-lg-7 text-gray-800 pe-1"><?php echo e($unitStats['has_fire']); ?></div>
								<i class="ki-duotone  fs-5 text-danger ms-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
							<!--end::Label-->
						</div>
						<!--end::Description-->

					</div>
				</div>
				<!--end::Items-->
			</div>
			<!--end::Body-->
		</div>
		<!--end::Mixed Widget 1-->
	</div>
</div>


<!-- Summary Table Row -->
<div class="row g-5 g-xl-8">
	<div class="col-12">
		<div class="card card-xl-stretch mb-xl-8">
			<div class="card-header border-0 pt-5">
				<h3 class="card-title align-items-start flex-column">
					<span class="card-label fw-bold fs-3 mb-1">ملخص حالة المباني</span>
					<span class="text-muted mt-1 fw-semibold fs-7">تفاصيل إحصائية لعملية التقييم</span>
				</h3>
			</div>
			<div class="card-body py-3">
				<div class="table-responsive">
					<table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
						<thead>
							<tr class="fw-bold text-muted">
								<th class="min-w-150px">الحالة (Category)</th>
								<th class="min-w-100px text-end">العدد (Count)</th>
								<th class="min-w-150px text-end">النسبة (Percentage)</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$totalAssessed = $buildingStats['fully_damaged'] + $buildingStats['partially_damaged'] + $buildingStats['committee_review'] + $buildingStats['security_unsafe'];

							function getPercent($val, $total)
							{
							return $total > 0 ? round(($val / $total) * 100, 1) : 0;
							}
							?>

							<tr>
								<td><span class="text-dark fw-bold text-hover-primary fs-6">ضرر كلي</span></td>
								<td class="text-end text-muted fw-bold"><?php echo e($buildingStats['fully_damaged']); ?></td>
								<td class="text-end">
									<div class="d-flex align-items-center justify-content-end">
										<span
											class="text-muted fw-bold me-2"><?php echo e(getPercent($buildingStats['fully_damaged'], $totalAssessed)); ?>%</span>
										<div class="progress h-6px w-100px">
											<div class="progress-bar bg-danger" role="progressbar"
												style="width: <?php echo e(getPercent($buildingStats['fully_damaged'], $totalAssessed)); ?>%">
											</div>
										</div>
									</div>
								</td>
							</tr>

							<tr>
								<td><span class="text-dark fw-bold text-hover-primary fs-6">ضرر جزئي</span></td>
								<td class="text-end text-muted fw-bold"><?php echo e($buildingStats['partially_damaged']); ?></td>
								<td class="text-end">
									<div class="d-flex align-items-center justify-content-end">
										<span
											class="text-muted fw-bold me-2"><?php echo e(getPercent($buildingStats['partially_damaged'], $totalAssessed)); ?>%</span>
										<div class="progress h-6px w-100px">
											<div class="progress-bar bg-warning" role="progressbar"
												style="width: <?php echo e(getPercent($buildingStats['partially_damaged'], $totalAssessed)); ?>%">
											</div>
										</div>
									</div>
								</td>
							</tr>

							<tr>
								<td><span class="text-dark fw-bold text-hover-primary fs-6">لجنة فنية</span></td>
								<td class="text-end text-muted fw-bold"><?php echo e($buildingStats['committee_review']); ?></td>
								<td class="text-end">
									<div class="d-flex align-items-center justify-content-end">
										<span
											class="text-muted fw-bold me-2"><?php echo e(getPercent($buildingStats['committee_review'], $totalAssessed)); ?>%</span>
										<div class="progress h-6px w-100px">
											<div class="progress-bar bg-primary" role="progressbar"
												style="width: <?php echo e(getPercent($buildingStats['committee_review'], $totalAssessed)); ?>%">
											</div>
										</div>
									</div>
								</td>
							</tr>

							<tr class="bg-light-secondary">
								<td><span class="text-dark fw-bolder fs-6">الإجمالي المقيّم</span></td>
								<td class="text-end text-dark fw-bolder fs-6"><?php echo e($totalAssessed); ?></td>
								<td class="text-end"><span class="badge badge-light-success fw-bold">100%</span></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row g-5 g-xl-8">
	<div class="col-12">
		<div class="card card-xl-stretch mb-xl-8">
			<div class="card-header border-0 pt-5">
				<h3 class="card-title align-items-start flex-column">
					<span class="card-label fw-bold fs-3 mb-1">ملخص حالة الوحدات السكنية</span>
					<span class="text-muted mt-1 fw-semibold fs-7">تفاصيل إحصائية لتقييم الوحدات</span>
				</h3>
			</div>
			<div class="card-body py-3">
				<div class="table-responsive">
					<table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
						<thead>
							<tr class="fw-bold text-muted">
								<th class="min-w-150px">الحالة (Category)</th>
								<th class="min-w-100px text-end">العدد (Count)</th>
								<th class="min-w-150px text-end">النسبة (Percentage)</th>
							</tr>
						</thead>
						<tbody>
							<?php
							// Calculating total for housing units
							$uFully = $unitStats['fully_damaged'] ?? 0;
							$uPartially = $unitStats['partially_damaged'] ?? 0;
							$uCommittee = $unitStats['committee_review'] ?? 0;

							$totalUnitsAssessed = $uFully + $uPartially + $uCommittee;

							// Note: getPercent function is already defined in your building block,
							// so we can just call it here.
							?>

							<tr>
								<td><span class="text-dark fw-bold text-hover-primary fs-6">وحدات - ضرر كلي</span></td>
								<td class="text-end text-muted fw-bold"><?php echo e($uFully); ?></td>
								<td class="text-end">
									<div class="d-flex align-items-center justify-content-end">
										<span
											class="text-muted fw-bold me-2"><?php echo e(getPercent($uFully, $totalUnitsAssessed)); ?>%</span>
										<div class="progress h-6px w-100px">
											<div class="progress-bar bg-danger" role="progressbar"
												style="width: <?php echo e(getPercent($uFully, $totalUnitsAssessed)); ?>%">
											</div>
										</div>
									</div>
								</td>
							</tr>

							<tr>
								<td><span class="text-dark fw-bold text-hover-primary fs-6">وحدات - ضرر جزئي</span></td>
								<td class="text-end text-muted fw-bold"><?php echo e($uPartially); ?></td>
								<td class="text-end">
									<div class="d-flex align-items-center justify-content-end">
										<span
											class="text-muted fw-bold me-2"><?php echo e(getPercent($uPartially, $totalUnitsAssessed)); ?>%</span>
										<div class="progress h-6px w-100px">
											<div class="progress-bar bg-warning" role="progressbar"
												style="width: <?php echo e(getPercent($uPartially, $totalUnitsAssessed)); ?>%">
											</div>
										</div>
									</div>
								</td>
							</tr>

							<tr>
								<td><span class="text-dark fw-bold text-hover-primary fs-6">وحدات - لجنة فنية </span>
								</td>
								<td class="text-end text-muted fw-bold"><?php echo e($uCommittee); ?></td>
								<td class="text-end">
									<div class="d-flex align-items-center justify-content-end">
										<span
											class="text-muted fw-bold me-2"><?php echo e(getPercent($uCommittee, $totalUnitsAssessed)); ?>%</span>
										<div class="progress  h-6px w-100px">
											<div class="progress-bar bg-primary" role="progressbar"
												style="width: <?php echo e(getPercent($uCommittee, $totalUnitsAssessed)); ?>%">
											</div>
										</div>
									</div>
								</td>
							</tr>

							<tr class="bg-light-secondary">
								<td><span class="text-dark fw-bolder fs-6">إجمالي الوحدات المقيّمة</span></td>
								<td class="text-end text-dark fw-bolder fs-6"><?php echo e($totalUnitsAssessed); ?></td>
								<td class="text-end"><span class="badge badge-light-success fw-bold">100%</span></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row g-5 g-xl-8">
	<!-- Buildings Chart Card -->
	<div class="col-xl-6">
		<div class="card card-xl-stretch mb-xl-8">
			<div class="card-header border-0 pt-5">
				<h3 class="card-title align-items-start flex-column">
					<span class="card-label fw-bold fs-3 mb-1">إحصائيات المباني</span>
				</h3>
			</div>
			<div class="card-body">
				<div id="buildings_donut_chart" style="height: 350px"></div>
			</div>
		</div>
	</div>

	<!-- Housing Units Chart Card -->
	<div class="col-xl-6">
		<div class="card card-xl-stretch mb-xl-8">
			<div class="card-header border-0 pt-5">
				<h3 class="card-title align-items-start flex-column">
					<span class="card-label fw-bold fs-3 mb-1">إحصائيات الوحدات السكنية</span>
				</h3>
			</div>
			<div class="card-body">
				<div id="housing_units_donut_chart" style="height: 350px"></div>
			</div>
		</div>
	</div>
</div>

<!--end::Col-->

<!--begin::Col-->

<!--end::Col-->


<div class="row g-5 g-xl-8">
	<div class="card">
		<div class="card-header border-0 pt-6">
			<!--begin::Card title-->

			<!--begin::Card title-->
			<div class="cart-title">

				<div class="d-flex align-items-center position-relative my-1">
					<i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
						<span class="path1"></span>
						<span class="path2"></span>
					</i>
					<input type="text" data-kt-engineer-table-filter="search"
						class="form-control form-control-solid w-250px ps-13" placeholder="بحث" />
				</div>
			</div>
			<div class="card-title">
				<!--begin::Search-->
				الخريطة الجوية
			</div>
		</div>
		<!--begin::Body-->
		<div class="card-body p-lg-17">

			<!--begin::Row-->
			<div class="row mb-3">
				<!--begin::Col-->

				<!--end::Col-->
				<!--begin::Col-->

				<!--end::Col-->
				<div class="col-md-5 pe-lg-10">

					<!--begin::Table-->
					<table class="table  table-rounded  table-striped align-middle fs-7 fs-lg-6 gy-5"
						id="kt_table_building">
						<thead>
							<tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">

								<!-- 										<th class="min-w-70px"> إسم الباحث</th>
																																								 -->
								<th class="min-w-70px"> المنطقة </th>
								<th class="min-w-70px"> رقم المبنى </th>
								<th class="min-w-70px"> إسم المبنى </th>
								<th class="min-w-70px"> إسم المالك </th>
								<th class="min-w-70px"> الزون </th>

							</tr>
						</thead>
						<tbody class="text-gray-600 fw-semibold"></tbody>



					</table>
					<!--end::Table-->
				</div>
				<div class="col-md-7 ps-lg-10">
					<link rel="stylesheet" href="https://js.arcgis.com/4.22/esri/themes/light/main.css">
					<!--begin::Map-->
					<div id="viewDiv" class="w-100 rounded mb-2 mb-lg-0 mt-2" style="height: 486px"></div>
					<!--end::Map-->
					<div id="externalLegendDiv"></div>
				</div>
			</div>
			<!--end::Row-->
		</div>
		<!--end::Body-->
	</div>

</div>


<?php $__env->stopSection(); ?>





<?php $__env->startSection('script'); ?>



<script src="https://js.arcgis.com/4.22/"></script>
<script src="https://jsdelivr.net"></script>

<script>
	require([
		"esri/Map",
		"esri/views/MapView",
		"esri/layers/FeatureLayer",
		"esri/identity/OAuthInfo",
		"esri/identity/IdentityManager",
		"esri/layers/OpenStreetMapLayer",
		"esri/widgets/BasemapToggle",
		"esri/widgets/Legend",
		"esri/widgets/Expand",
		"esri/widgets/Search",
		"esri/widgets/ScaleBar"
	], function(Map, MapView, FeatureLayer, OAuthInfo, esriId, OpenStreetMapLayer, BasemapToggle, Legend, Expand, Search, ScaleBar) {

		const damageRenderer = {
			type: "unique-value", // autocasts as new UniqueValueRenderer()
			field: "building_damage_status",
			// Default symbol for values not defined below
			defaultSymbol: {
				type: "simple-fill",
				color: [128, 128, 128, 0.9], // Gray
				outline: {
					color: "white",
					width: 1
				}
			},
			uniqueValueInfos: [{
					value: "committee_review",
					symbol: {
						type: "simple-fill",
						color: [255, 255, 0, 0.5], // Yellow
						outline: {
							color: "black",
							width: 1
						}
					},
					label: "Committee Review"
				},
				{
					value: "fully_damaged",
					symbol: {
						type: "simple-fill",
						color: [255, 0, 0, 0.5], // Red
						outline: {
							color: "white",
							width: 2
						}
					},
					label: "Fully Damaged"
				},
				{
					value: "partially_damaged",
					symbol: {
						type: "simple-fill",
						color: [0, 255, 0, 0.5], // Green
						outline: {
							color: "white",
							width: 1
						}
					},
					label: "Partially Damaged"
				}
			]
		};


		esriId.registerToken({
			// Use the portal's sharing base URL instead of a specific FeatureServer URL
			server: "https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0", // or your ArcGIS Server URL
			token: "<?php echo e($token); ?>",
			expires: 1678886400000 // Ensure this matches your token's actual expiration
		});

		const fieldInfos = [{
			fieldName: "objectid"
		}, {
			fieldName: "building_name"
		}, {
			fieldName: "assignedto"
		}, {
			fieldName: "building_damage_status"
		}];
		const measureThisAction = {
			title: "Measure Length",
			id: "measure-this",
			icon: "measure",
		};
		const featureLayer = new FeatureLayer({
			// Point to the Warren Wilson College tree carbon storage service.
			url: "https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0",
			renderer: damageRenderer,
			labelingInfo: [{
				symbol: {
					type: "text", // autocasts as new TextSymbol()
					color: "white",
					haloColor: "black",
					haloSize: "1px",
					font: {
						family: "Ubuntu Mono",
						size: 10,
						weight: "bold"
					}
				},
				labelPlacement: "always-horizontal",
				labelExpressionInfo: {
					expression: "$feature.building_name" // Displays the building name
				}
			}],
			popupTemplate: {
				dockEnabled: true, // Enables docking
				dockOptions: {
					// Choose a position: "top-left", "top-right", "bottom-left", "bottom-right", "top-center", "bottom-center"
					position: "top-left",
					// Set a breakpoint (e.g., in pixels) below which the popup automatically docks
					breakpoint: false
				},
				title: "المبنى: {building_name} <a target='_blank' style='color:red;' href='<?php echo e(url('assessment')); ?>/{globalid}'>الإستبيان</a>",
				content: [{
						type: "fields",
						fieldInfos: fieldInfos // Ensure fieldInfos is defined elsewhere
					},
					{
						type: 'text',
						text: '<a style="color:red;" href="<?php echo e(url("assessment")); ?>/{globalid}">الإستبيان</a>'
					}
				],

				actions: [measureThisAction],
			},
		});
		const map = new Map({
			basemap: 'satellite',
			layers: [featureLayer] // Add the layer to the map
		});
		const view = new MapView({
			container: "viewDiv", // The DOM element ID for the view
			map: map,
			center: [34.460987, 31.514266], // Center the view
			zoom: 16.5 // Zoom level
				,
		});
		const basemapToggle = new BasemapToggle({
			view: view,
			nextBasemap: "osm" // The basemap it switches to when clicked
		});
		const legend = new Legend({
			view: view,
			container: "externalLegendDiv",
			layerInfos: [{
				layer: featureLayer,
				title: "Building Damage Status"
			}]
		});


		view.watch("extent", function() {

			view.whenLayerView(featureLayer).then(function(layerView) {
				layerView.filter = {
					geometry: view.extent,
					spatialRelationship: "intersects"
				};
			});
		});

		view.ui.add(basemapToggle, "top-left");

		//view.ui.add(legend, "bottom-right");

		let highlightHandle = null; // 1. Variable to store the current highlight
		const searchWidget = new Search({
			view: view,
			allPlaceholder: "بحث ",
			includeDefaultSources: false, // Disables the generic world address search
			sources: [{
				layer: featureLayer, // Your building layer
				searchFields: ["building_name", "objectid"], // Field to search in
				displayField: "building_name", // Field to show in suggestions
				exactMatch: false,
				outFields: ["*"],
				name: "Buildings",
				placeholder: "بحث عن المبنى بالاسم أو الرقم"
			}]
		});
		view.ui.add(searchWidget, {
			position: "top-right"
		});

		const scaleBar = new ScaleBar({
			view: view,
			unit: "metric", // Options: "metric", "non-metric", or "dual"
			expandIconClass: "esri-icon-measure"
		});

		// Add the widget to the bottom-left corner of the view
		view.ui.add(scaleBar, {
			position: "bottom-left"
		});

		function zoomToFeatureByGlobalId(globalId) {
			const query = {
				where: `globalid = '${globalId}'`,
				returnGeometry: true,
				outFields: ["*"]
			};

			featureLayer.queryFeatures(query).then(function(results) {
				if (results.features.length > 0) {
					const feature = results.features[0];


					/* const zoomTarget = feature.geometry.extent
						? feature.geometry.extent.expand(1,0)
						: {target: feature.geometry, zoom: 20 }; */

					view.goTo(feature.geometry.extent.expand(1, 1) || feature.geometry, {
						duration: 2000,
						easing: "in-out-expo",
						popupEnabled: false
					}).catch(function(error) {
						if (error.name !== "AbortError") console.error("GoTo failed:", error);
					});


					// 2. Highlight the feature using its LayerView
					view.whenLayerView(featureLayer).then(function(layerView) {
						// Remove previous highlight if it exists
						if (highlightHandle) {
							highlightHandle.remove();
						}
						// Apply new highlight
						highlightHandle = layerView.highlight(feature);



					});

				} else {
					console.warn("Feature not found:", globalId);
				}
			}).catch(function(error) {
				console.error("Query Error:", error);
			});


		}


		$('#kt_table_building').on('click', 'tr', function() {
			var globalid = this.id; // Accesses the 'id' attribute of the clicked row
			zoomToFeatureByGlobalId(globalid);

		});


	});

	var KTEngineersList = function() {
		// Define shared variables
		var table = document.getElementById('kt_table_building');
		var kt_engineer_filter = document.getElementById('kt_engineer_filter');
		var datatable;
		var toolbarBase;
		var toolbarSelected;
		var selectedCount;

		// Private functions
		var initEngineerTable = function() {
			// Set date data order
			const tableRows = table.querySelectorAll('tbody tr');

			datatable = $(table).DataTable({
				serverSide: true,
				ajax: {
					url: "<?php echo e(url('building/show')); ?>",
					data: function(d) {
						d.hompage_building = 1

					},
				},
				dom: "<'table-responsive'tr>" + // Table body
					"<'row'<'col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start'i>" + // Info (left)
					"<'col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end'p>>", // Pagination (right)

				"info": false,
				'order': [],
				"pageLength": 10,
				"lengthChange": false,
				processing: true,
				columns: [
					/* 							{data: 'assignedto', name: 'assignedto', searchable: true },
					 */
					{
						data: 'neighborhood',
						name: 'neighborhood',
						searchable: true
					},
					{
						data: 'objectid',
						name: 'objectid',
						searchable: true
					},
					{
						data: 'building_name',
						name: 'building_name',
						searchable: true
					},
					{
						data: 'owner_name',
						name: 'owner_name',
						searchable: true
					},
					{
						data: 'zone_code',
						name: 'zone_code',
						searchable: true
					},

				],
				createdRow: (row, data, index) => {
					$(row).css('cursor', 'pointer')


				}
			});

			// Re-init functions on every table re-draw -- more info: https://datatables.net/reference/event/draw
			datatable.on('draw', function() {

				KTMenu.createInstances(); // For Metronic

			});
		}


		var handleSearchDatatable = () => {
			const filterSearch = document.querySelector('[data-kt-engineer-table-filter="search"]');

			filterSearch.addEventListener('keydown', function(e) {
				if (e.which == 13) {
					// Prevent the default action (e.g., form submission and page refresh)
					e.preventDefault();
					datatable.search(e.target.value).draw();
				}
			});
		}

		return {
			// Public functions
			init: function() {
				if (!table) {
					return;
				}

				initEngineerTable();
				handleSearchDatatable();


			}
		}
	}();


	KTUtil.onDOMContentLoaded(function() {
		KTEngineersList.init();
// 1. Buildings Donut Chart
	var buildingsOptions = {
		series: [
			<?php echo e($buildingStats['fully_damaged'] ?? 0); ?>,
			<?php echo e($buildingStats['partially_damaged'] ?? 0); ?>,
			<?php echo e($buildingStats['committee_review'] ?? 0); ?>,
			<?php echo e($buildingStats['security_unsafe'] ?? 0); ?>

		],
		chart: {
			type: 'donut',
			height: 350
		},
		labels: ['ضرر كلي', 'ضرر جزئي', 'لجنة فنية', 'صعوبة في التقييم'],
		colors: ['#F1416C', '#FFAD0F', '#009EF7', '#7239EA'],
		legend: {
			position: 'bottom'
		},
		dataLabels: {
			enabled: true
		}
	};
	new ApexCharts(document.querySelector("#buildings_donut_chart"), buildingsOptions).render();

	// 2. Housing Units Donut Chart
	var housingOptions = {
		series: [
			<?php echo e($unitStats['fully_damaged'] ?? 0); ?>,
			<?php echo e($unitStats['partially_damaged'] ?? 0); ?>,
			<?php echo e($unitStats['committee_review'] ?? 0); ?>

		],
		chart: {
			type: 'donut',
			height: 350
		},
		labels: ['ضرر كلي (وحدات)', 'ضرر جزئي (وحدات)', 'لجنة فنية'],
		colors: ['#D9214E', '#F1BC00', '#50CD89'],
		legend: {
			position: 'bottom'
		},
		dataLabels: {
			enabled: true
		}
	};
	new ApexCharts(document.querySelector("#housing_units_donut_chart"), housingOptions).render();
	});
</script>



<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/damageAssessment.blade.php ENDPATH**/ ?>