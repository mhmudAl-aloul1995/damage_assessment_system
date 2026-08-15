@extends('layouts.app')

@section('content')
	<style>
		.card-toolbar .dropdown-menu .dropdown-item {
			font-size: 13px;
			padding: 0.65rem 1rem;
			transition: 0.2s ease;
		}

		.card-toolbar .dropdown-menu .dropdown-item:hover {
			background-color: #f8f9fa;
		}

		.container-loader {
			display: none !important
		}
	</style>


	<div class="container py-4">
		<div id="exportResult" class="mt-4"></div>
		<div class="card shadow-sm">
			<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
				<h3 class="mb-0">{{ __('ui.exports.title') }}</h3>
				<div class="d-flex align-items-center flex-wrap gap-2">
					<button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal"
						data-bs-target="#importObjectIdsModal">
						<i class="ki-duotone ki-file-up fs-5">
							<span class="path1"></span>
							<span class="path2"></span>
						</i>
						{{ __('ui.exports.import_objectids') }}
					</button>

					<button type="button" class="btn btn-sm btn-light-danger {{ empty($importedObjectIds) ? 'd-none' : '' }}"
						id="resetObjectIdsFilterBtn">
						<i class="ki-duotone ki-cross-circle fs-5">
							<span class="path1"></span>
							<span class="path2"></span>
						</i>
						{{ __('ui.exports.reset_objectid_import_filter') }}
					</button>
				</div>

			</div>

			<div class="card-body">
				@if(session('error'))
					<div class="alert alert-danger">
						{{ session('error') }}
					</div>
				@endif

					<div id="objectIdsFilterSummary"
						class="alert alert-info flex-column flex-md-row align-items-md-center justify-content-between gap-3 {{ empty($importedObjectIds) ? 'd-none' : 'd-flex' }}">
						<div>
							<strong>{{ __('ui.exports.objectid_import_active') }}</strong>
							<div class="text-muted fs-7 mt-1" id="objectIdsFilterSummaryText">
								{{ __('ui.exports.objectid_import_active_count', ['count' => count($importedObjectIds)]) }}
								{{ __('ui.exports.objectid_import_active_target', ['target' => __('ui.exports.objectid_target_' . $importedObjectIdTarget)]) }}
							</div>
						</div>
						<div class="d-flex flex-wrap gap-2" id="objectIdsFilterBadges">
							@foreach(array_slice($importedObjectIds, 0, 8) as $objectId)
								<span class="badge badge-light-primary">{{ $objectId }}</span>
							@endforeach
							@if(count($importedObjectIds) > 8)
								<span class="badge badge-light">+{{ count($importedObjectIds) - 8 }}</span>
							@endif
						</div>
					</div>

				<form id="exportForm" method="POST">
					@csrf
					<div id="importedObjectIdsInputs">
						@foreach($importedObjectIds as $objectId)
							<input type="hidden" name="imported_object_ids[]" value="{{ $objectId }}">
						@endforeach
						@if(!empty($importedObjectIds))
							<input type="hidden" name="imported_object_id_target" value="{{ $importedObjectIdTarget }}">
						@endif
					</div>

					{{-- FILTERS --}}
					<div class="card card-bordered mb-5">
						<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
							<h3 class="card-title mb-0">{{ __('ui.exports.filters') }}</h3>

							<div class="d-flex gap-2">
								<button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="collapse"
									data-bs-target="#filtersCollapse" aria-expanded="true" aria-controls="filtersCollapse"
									id="toggleFiltersBtn">
									<i class="fas fa-chevron-down me-1"></i>
									{{ __('ui.exports.show') }}
								</button>

								<button type="button" class="btn btn-sm btn-light-danger" onclick="resetFilters()">
									<i class="fas fa-times me-1"></i>
									{{ __('ui.exports.clear_filters') }}
								</button>

								<button type="button" class="btn btn-sm btn-light-info d-flex align-items-center gap-2"
									id="selectedColumnsStatusBtn">
									<i class="ki-duotone ki-check-square fs-5">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
									<span>الأعمدة المختارة</span>
									<span class="badge badge-primary" id="selectedColumnsCount">0</span>
								</button>

								<button type="button"
									class="btn btn-light-primary btn-sm dropdown-toggle d-flex align-items-center gap-1"
									data-bs-toggle="dropdown" aria-expanded="false">

									<i class="ki-duotone ki-exit-down fs-5">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
									{{ __('ui.exports.export') }}
								</button>

								<div class="dropdown-menu dropdown-menu-end shadow-sm border-0">
									<button class="dropdown-item d-flex align-items-center gap-2 export-btn" type="button"
										data-type="excel">
										<i class="ki-duotone ki-file-down fs-4 text-success">
											<span class="path1"></span>
											<span class="path2"></span>
										</i>
										<span>Excel (.xlsx)</span>
									</button>

									<button class="dropdown-item d-flex align-items-center gap-2 export-btn" type="button"
										data-type="pdf">
										<i class="ki-duotone ki-file-down fs-4 text-danger">
											<span class="path1"></span>
											<span class="path2"></span>
										</i>
										<span>PDF (.pdf)</span>
									</button>

									<button class="dropdown-item d-flex align-items-center gap-2 export-btn" type="button"
										data-type="zip">
										<i class="ki-duotone ki-folder-down fs-4 text-warning">
											<span class="path1"></span>
											<span class="path2"></span>
										</i>
										<span>ZIP مرفقات</span>
									</button>
								</div>
							</div>

						</div>

						<div class="collapse" id="filtersCollapse">
							<div class="card-body">

								<div class="mb-5">
									<div class="input-group">
										<span class="input-group-text">
											<i class="fas fa-search"></i>
										</span>

										<input type="text" id="filterSearch" class="form-control form-control-solid"
											placeholder="{{ __('ui.exports.search_filter') }}"
											onkeyup="filterFilterCards()">

										<button type="button" class="btn btn-light" onclick="clearFilterSearch()">
											{{ __('ui.exports.clear') }}
										</button>
									</div>

									<div class="text-muted fs-7 mt-2">
										{{ __('ui.exports.visible_filters_count') }}
										<span id="filterCardsCounter">{{ count($filters) }}</span>
										/ {{ count($filters) }}
									</div>
								</div>

								<div class="row" id="filtersCardsList">
									@foreach($filters as $listName => $items)
										<div class="col-md-4 mb-4 filter-card-item">
											<label class="form-label fw-bold searchable-filter-name">
												{{ $assessmentLabels[$listName] ?? ucwords(str_replace('_', ' ', $listName)) }}
											</label>

											<select name="filters[{{ $listName }}][]"
												class="form-select form-select-solid filter-select2" multiple
												data-placeholder="{{ __('ui.exports.select', ['label' => $assessmentLabels[$listName] ?? ucwords(str_replace('_', ' ', $listName))]) }}">
												@foreach($items as $item)
													<option value="{{ $item->name }}">
														{{ $item->label ?? $item->name }}
													</option>
												@endforeach
											</select>
										</div>
									@endforeach

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label
											class="form-label fw-bold searchable-filter-name">{{ __('ui.exports.family_members_from') }}</label>
										<input type="number" name="family_members_from"
											class="form-control form-control-solid" min="0"
											placeholder="{{ __('ui.exports.family_members_from') }}"
											value="{{ old('family_members_from') }}">
									</div>

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label
											class="form-label fw-bold searchable-filter-name">{{ __('ui.exports.family_members_to') }}</label>
										<input type="number" name="family_members_to"
											placeholder="{{ __('ui.exports.family_members_to') }}"
											class="form-control form-control-solid" min="0"
											value="{{ old('family_members_to') }}">
									</div>

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label
											class="form-label fw-bold searchable-filter-name">{{ __('ui.exports.building_end_from') }}</label>
										<input type="date" name="building_end_from"
											class="form-control form-control-solid"
											value="{{ old('building_end_from') }}">
									</div>

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label
											class="form-label fw-bold searchable-filter-name">{{ __('ui.exports.building_end_to') }}</label>
										<input type="date" name="building_end_to"
											class="form-control form-control-solid"
											value="{{ old('building_end_to') }}">
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="card card-bordered mb-5">
						<div class="card-header">
							<h3 class="card-title mb-0">نوع التصدير</h3>
						</div>

						<div class="card-body">
							<div class="btn-group w-100 mb-6" role="group" aria-label="نوع التصدير">
								<input type="radio" class="btn-check export-mode-option" name="export_mode" id="exportModeData"
									value="data" autocomplete="off" checked>
								<label class="btn btn-outline btn-outline-dashed btn-active-light-primary" for="exportModeData">
									بيانات فقط
								</label>

								<input type="radio" class="btn-check export-mode-option" name="export_mode" id="exportModeAttachments"
									value="attachments" autocomplete="off">
								<label class="btn btn-outline btn-outline-dashed btn-active-light-primary" for="exportModeAttachments">
									مرفقات فقط
								</label>

								<input type="radio" class="btn-check export-mode-option" name="export_mode" id="exportModeDataAttachments"
									value="data_with_attachments" autocomplete="off">
								<label class="btn btn-outline btn-outline-dashed btn-active-light-primary" for="exportModeDataAttachments">
									بيانات + مرفقات
								</label>
							</div>

							<div class="border rounded p-4 mb-6 bg-light-primary">
								<label class="form-check form-check-custom form-check-solid align-items-start">
									<input type="hidden" name="update_housing_names_from_civil_registry" value="0">
									<input class="form-check-input mt-1" type="checkbox"
										name="update_housing_names_from_civil_registry" value="1">
									<span class="form-check-label ms-3">
										<strong class="d-block">تحديث أسماء المالك/الزوجات من السجل المدني قبل التصدير</strong>
										<small class="text-muted d-block mt-1">
											يطبق فقط على الوحدات المطابقة للفلاتر الحالية وعلى أعمدة الهوية/الاسم المختارة، دون تعديل حقول الاسم المفصلة q_9_3.
										</small>
									</span>
								</label>
							</div>

							<div id="auditNotesExportOptions" class="border rounded p-4 mb-6">
								<h4 class="fw-bold mb-4">ملاحظات التدقيق</h4>
								<div class="row">
									<div class="col-lg-6 mb-4">
										<label class="form-check form-check-custom form-check-solid mb-3">
											<input type="hidden" name="include_legal_notes" value="0">
											<input class="form-check-input" type="checkbox" id="exportIncludeLegalNotes"
												name="include_legal_notes" value="1">
											<span class="form-check-label ms-3">تضمين الملاحظات القانونية + اسم المدقق القانوني</span>
										</label>

										<label class="form-label fw-bold" for="legalNotesFilter">فلتر الملاحظات القانونية</label>
										<select id="legalNotesFilter" name="legal_notes_filter"
											class="form-select form-select-solid audit-notes-select2">
											<option value="">كل السجلات القانونية</option>
											<option value="with_notes">يوجد ملاحظة قانونية</option>
											<option value="without_notes">لا يوجد ملاحظة قانونية</option>
										</select>
									</div>

									<div class="col-lg-6 mb-4">
										<label class="form-check form-check-custom form-check-solid mb-3">
											<input type="hidden" name="include_engineering_notes" value="0">
											<input class="form-check-input" type="checkbox" id="exportIncludeEngineeringNotes"
												name="include_engineering_notes" value="1">
											<span class="form-check-label ms-3">تضمين الملاحظات الهندسية + اسم المدقق الهندسي</span>
										</label>

										<label class="form-label fw-bold" for="engineeringNotesFilter">فلتر الملاحظات الهندسية</label>
										<select id="engineeringNotesFilter" name="engineering_notes_filter"
											class="form-select form-select-solid audit-notes-select2">
											<option value="">كل السجلات الهندسية</option>
											<option value="with_notes">يوجد ملاحظة هندسية</option>
											<option value="without_notes">لا يوجد ملاحظة هندسية</option>
										</select>
									</div>
								</div>
							</div>

							<div id="attachmentExportOptions" class="border rounded p-4 d-none">
								<div class="row">
									<div class="col-lg-4 mb-4">
										<label class="form-label fw-bold">نوع المرفق</label>
										<div class="d-flex flex-column gap-3">
											<label class="form-check form-check-custom form-check-solid">
												<input class="form-check-input" type="checkbox" name="attachment_sources[]"
													value="building_arcgis" checked>
												<span class="form-check-label ms-3">مرفقات المباني</span>
											</label>

											<label class="form-check form-check-custom form-check-solid">
												<input class="form-check-input" type="checkbox" name="attachment_sources[]"
													value="housing_unit_arcgis" checked>
												<span class="form-check-label ms-3">مرفقات الوحد السكنية</span>
											</label>
										</div>
									</div>

									<div class="col-lg-4 mb-4">
										<label class="form-label fw-bold" for="attachmentTypeFilters">تصنيف المرفق</label>
										<select id="attachmentTypeFilters" name="attachment_type_filters[]"
											class="form-select form-select-solid attachment-type-select2" multiple
											data-placeholder="كل المرفقات">
											<option value="all" selected>كل المرفقات</option>
											<option value="images">صور فقط</option>
											<option value="pdf">PDF فقط</option>
											<option value="damage_photos">صور الضرر</option>
											<option value="identity">الهوية</option>
											<option value="ownership">وثائق الملكية</option>
											<option value="permit">رخصة البلدية</option>
											<option value="other_documents">مستندات أخرى</option>
										</select>
									</div>

									<div class="col-lg-4 mb-4">
										<label class="form-label fw-bold" for="attachmentGrouping">طريقة التجميع</label>
										<select id="attachmentGrouping" name="attachment_grouping"
											class="form-select form-select-solid">
											<option value="by_building">مجلد لكل مبنى</option>
											<option value="by_housing_unit">مجلد لكل وحدة سكنية</option>
											<option value="flat">كل الملفات في مجلد واحد</option>
										</select>
									</div>

									<div class="col-lg-4 mb-4">
										<label class="form-label fw-bold" for="attachmentFilenameStrategy">تسمية الملفات</label>
										<select id="attachmentFilenameStrategy" name="attachment_filename_strategy"
											class="form-select form-select-solid">
											<option value="objectid_type">ObjectID + نوع المرفق</option>
											<option value="globalid">GlobalID</option>
											<option value="owner_name">اسم المالك</option>
										</select>
									</div>

									<div class="col-lg-4 mb-4">
										<label class="form-check form-check-custom form-check-solid mt-8">
											<input type="hidden" name="include_attachment_excel_columns" value="0">
											<input class="form-check-input" type="checkbox" name="include_attachment_excel_columns"
												value="1">
											<span class="form-check-label ms-3">إضافة المرفقات كأعمدة داخل Excel</span>
										</label>
									</div>

									<div class="col-lg-4 mb-4">
										<label class="form-label fw-bold" for="attachmentExcelDisplay">عرض المرفق داخل Excel</label>
										<select id="attachmentExcelDisplay" name="attachment_excel_display"
											class="form-select form-select-solid">
											<option value="links">روابط</option>
											<option value="images">صور داخل Excel</option>
										</select>
									</div>
								</div>

								<label class="form-check form-check-custom form-check-solid">
									<input type="hidden" name="include_attachment_index" value="0">
									<input class="form-check-input" type="checkbox" name="include_attachment_index" value="1" checked>
									<span class="form-check-label ms-3">تضمين ملف فهرس للمرفقات داخل ZIP</span>
								</label>
							</div>
						</div>
					</div>

					@php
						$buildingGroupMap = [
							'0. Introduction' => [
								'objectid',
								'field_status',
								'parcel_no1',
								'block_no1',
								'owner_na',
								'units_count',
								'assignedto',
								'groupnumber',
								'zone_code',
								'start',
								'end',
								'today',
								'username',
								'simserial',
								'weather',
								'security_situation',
								'security_info',
								'building_name',
								'governorate',
								'neighborhood',
								'housing_units_count',
							],
							'1. Building Information' => [
								'building_damage_status',
								'building_type',
								'building_type_other',
								'building_use',
								'date_of_damage',
								'building_material',
								'other_material',
								'building_age',
								'floor_nos',
								'ground_floor_area__m2',
								'floor_area_m2',
								'units_nos',
								'damaged_units_nos',
								'occupied_units_nos',
								'vacant_units_nos',
								'is_damaged_before',
								'if_damaged',
								'building_debris_exist',
								'building_debris_qty',
								'building_debris_blocking',
								'uxo_present',
								'bodies_present',
								'estimated_number_of_bodies',
								'building_status_visit',
							],
							'1.18 Building Status at the Time of Visit' => [
								'building_roof_type',
								'clay_tile_area',
								'concrete_area',
								'aspestos_area',
								'scorite_area',
								'other_roof',
								'other_roof_area',
							],
							'2. Ownership Information' => [
								'building_ownership',
								'owner_status',
								'building_responsible',
								'building_authorization',
								'land_fully_owned',
								'owner_name',
								'owner_id',
								'owner_mobile',
								'board1_name',
								'board1_id',
								'board1_number',
								'board2_name',
								'board2_id',
								'board2_number',
								'has_authorization_if_not_owner',
								'authorization_details',
								'is_rented',
								'tenant_names',
								'agreement_type',
								'agreement_duration',
							],
							'3. Building Attachments' => [
								'has_documents',
								'doc_types_available',
								'doc_types_other',
								'no_documents_reason',
								'need_renew_docs',
								'doc_challenges',
								'doc_challenges_other',
								'has_dispute',
								'dispute_types',
								'dispute_other',
								'attach_one_photo_for_each_of_the_following_documents',
								'select_document',
								'id_number_photo',
								'land_ownership_photo',
								'municipal_permit_photo',
								'other_documents_photo',
							],
							'4. Building Services' => [
								'has_elevator',
								'elevator_number',
								'elevator_status',
								'elevator_box',
								'elevator_motor',
								'has_solar',
								'solar_damage_status',
								'has_well',
								'well_damage_status',
								'has_fence',
								'fence_damage_status',
								'fence_length',
								'has_electric_room',
								'electric_room_damage_status',
								'has_sewage',
								'sewage_damage_status',
								'service_ownership',
								'service_ownership_name',
								'has_other_service',
								'other_service_details',
								'building_services_notes',
							],
							'5. Building Accessories' => [
								'staircase_status',
								'staircase_widt',
								'has_parking',
								'parking_status',
								'garage_area',
								'garage_type',
								'has_canopy',
								'canopy_status',
								'carport_length',
								'carport_width',
								'carport_height',
								'has_basement',
								'basement_status',
								'basement_area',
								'has_mezzanine',
								'mezzanine_status',
								'roof_terrace_area',
							],
							'6. Engineer Comments' => [
								'comments_recommendations',
								'break01_note',
								'building_image',
								'building_image2',
							],
						];

						$housingGroupMap = [
							'7. Unit Introduction' => ['attachments', 'housing_unit_group', 'housing_unit_type', 'unit_damage_status'],
							'8. Unit Information' => ['page8', 'floor_number', 'housing_unit_number', 'unit_direction', 'damaged_area_m2', 'infra_type2', 'house_unit_ownership', 'other_ownership', 'occupied', 'number_of_rooms'],
							'9. Household and Unit Information' => ['page9', 'identity_type1', 'id_number1', 'passport1', 'other_id1', 'unit_owner', 'q_9_3_1_first_name', 'q_9_3_2_second_name__father', 'q_9_3_3_third_name__grandfather', 'q_9_3_4_last_name', 'sex', 'mobile_number', 'additional_mobile', 'owner_job', 'other_job', 'age', 'marital_status', 'ownership_image'],
							'10. Spouses and Disability Information' => ['page10', 'no_spouses', 'spouse1', 'spouse1_id', 'spouse2', 'spouse2_id', 'spouse3', 'spouse3_id', 'spouse4', 'spouse4_id', 'are_there_people_with_disability', 'number_of_people_with_disability', 'handicapped_type', 'other_handicapped', 'is_refugee', 'unrwa_registration_number'],
							'11. Family Size' => ['page11', 'number_of_nuclear_families', 'mchildren_001', 'myoung', 'melderly', 'fchildren', 'fyoung_001', 'felderly', 'pregnant', 'lactating'],
							'12. Current Residence and Refugee Status' => ['page12', 'the_unit_resident', 'current_address', 'current_residence', 'current_residence_other', 'shelter_name', 'shelter_type', 'shelter_type_other', 'governorate', 'locality', 'neighborhood', 'street', 'closest_facility2'],
							'13. Household and Rentee' => ['page13', 'identity_type2', 'rentee_id_passport_number', 'rentee_resident_full_name', 'q_13_3_1_first_name', 'q_13_3_2_second_name__father', 'q_13_3_3_third_name__grandfather', 'q_13_3_4_last_name__family', 'rentee_mobile_number', 'work_type', 'other_work'],
							'14. Unit Finishing and Internal Damaged' => ['page14', 'external_finishing_of_the_unit', 'other_external_finishing', 'is_finished', 'internal_finishing_of_the_unit', 'finishing_extent', 'finishing_partial_types', 'has_fire', 'fire_extent', 'fire_severity', 'fire_locations', 'fire_rooms_count', 'fire_area', 'furniture_ownership', 'percentage_of_damaged_furniture', 'unit_stripping', 'unit_stripping_details', 'stripping_area', 'stripping_locations', 'rubble_removal_is_needed', 'activation_of_uxo_ha_d_material_clearance', 'unit_support_needed', 'is_the_housing_unit_or_living_habitable'],
							'15. Mental Health and Psychosocial Support (MHPSS)' => ['mhpss', 'mhpss_experinced', 'other_mhpss_exp', 'mhpss_support', 'other_mhpss_support', 'community_participation'],
							'16. Community Needs and Preferences Survey' => ['ce', 'ce1', 'prefab_moving', 'prefab_moving_maybe', 'prefab_types', 'other_prefab_types', 'prefab_pref', 'ce2', 'reh_kitchen', 'reh_bathroom', 'reh_type', 'ce3', 'additional_comments'],
							'17. Techncial-BOQ' => ['techncial_boq', 'tech_boq', 'pv_note'],
							'18. Attachments & Final Comments' => ['final_comments'],
						];

						$boqGroupPrefixes = [
							'dm' => '17. Techncial-BOQ / Demolishing Works',
							'bl' => '17. Techncial-BOQ / Blocks Works',
							'co' => '17. Techncial-BOQ / Concrete Works',
							'fn' => '17. Techncial-BOQ / Finishing Works',
							'al' => '17. Techncial-BOQ / Aluminum Works',
							'wd' => '17. Techncial-BOQ / Wood Works',
							'mt' => '17. Techncial-BOQ / Metal Works',
							'cm' => '17. Techncial-BOQ / Combined Works',
							'pm' => '17. Techncial-BOQ / Plumbing Works',
							'el' => '17. Techncial-BOQ / Electrical Works',
							'pv' => '17. Techncial-BOQ / PV System Works',
							'item' => '17. Techncial-BOQ / Miscellaneous Works',
							'quant' => '17. Techncial-BOQ / Miscellaneous Works',
						];

						foreach ($housingColumns ?? [] as $column) {
							foreach ($boqGroupPrefixes as $prefix => $groupName) {
								if ($column === $prefix || preg_match('/^' . preg_quote($prefix, '/') . '\d+$/', $column) || preg_match('/^' . preg_quote($prefix, '/') . '_\d+$/', $column)) {
									$housingGroupMap[$groupName][] = $column;
									break;
								}
							}
						}

						$groupColumns = function ($availableColumns, $map) {
							$grouped = [];

							foreach ($map as $groupName => $columns) {
								foreach (array_unique($columns) as $column) {
									if (in_array($column, $availableColumns ?? [])) {
										$grouped[$groupName][] = $column;
									}
								}
							}

							$used = collect($grouped)->flatten()->toArray();
							$other = array_values(array_diff($availableColumns ?? [], $used));

							if (!empty($other)) {
								$grouped['Other'] = $other;
							}

							return $grouped;
						};

						$groupedBuilding = $groupColumns($buildingColumns ?? [], $buildingGroupMap);
						$groupedHousing = $groupColumns($housingColumns ?? [], $housingGroupMap);
					@endphp

					<div class="row">

						{{-- BUILDING --}}
						<div class="col-lg-6 mb-4">
							<div class="card card-bordered h-100">
								<div class="card-header">
									<h4>{{ __('ui.exports.building_table_fields') }}</h4>
								</div>

								<div class="card-body">
									<div class="mb-4">
										<div class="d-flex gap-2 flex-wrap mb-3">

											<button type="button" class="btn btn-sm btn-light-primary"
												onclick="toggleVisibleGroup('buildingColumnsList','building_columns[]',true)">
												{{ __('ui.exports.select_all') }}
											</button>

											<button type="button" class="btn btn-sm btn-light-danger"
												onclick="toggleVisibleGroup('buildingColumnsList','building_columns[]',false)">
												{{ __('ui.exports.deselect_all') }}
											</button>
										</div>

										<div class="input-group">
											<span class="input-group-text">
												<i class="fas fa-search"></i>
											</span>

											<input type="text" id="buildingSearch" class="form-control form-control-solid"
												placeholder="{{ __('ui.exports.search_fields') }}"
												onkeyup="filterColumns('buildingSearch','buildingColumnsList','buildingCounter')">

											<button type="button" class="btn btn-light"
												onclick="clearSearch('buildingSearch','buildingColumnsList','buildingCounter')">
												{{ __('ui.exports.clear') }}
											</button>
										</div>

										<div class="text-muted fs-7 mt-2">
											{{ __('ui.exports.total_results') }}
											<span id="buildingCounter">{{ count($buildingColumns) }}</span>
											/ {{ count($buildingColumns) }}
										</div>
									</div>

									<div id="buildingColumnsList">
										@foreach($groupedBuilding as $group => $columns)
											<div class="mb-5 column-group">
												<div
													class="d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom pb-2 mb-4">
													<h5 class="fw-bold text-primary mb-0">{{ $group }}</h5>

													<div class="d-flex gap-2 flex-wrap">
														<button type="button" class="btn btn-sm btn-light-primary"
															onclick="toggleColumnGroup(this,'building_columns[]',true)">
															{{ __('ui.exports.select_all') }}
														</button>

														<button type="button" class="btn btn-sm btn-light-danger"
															onclick="toggleColumnGroup(this,'building_columns[]',false)">
															{{ __('ui.exports.deselect_all') }}
														</button>
													</div>
												</div>

												<div class="row">
													@foreach($columns as $column)
														<div class="col-md-6 mb-3 column-item">
															<label
																class="form-check form-check-custom form-check-solid border rounded p-3 w-100">
																<input class="form-check-input mt-1" type="checkbox"
																	name="building_columns[]" value="{{ $column }}"
																	data-export-column-checkbox>

																<span class="form-check-label ms-3">
																	<strong class="d-block">
																		{{ $assessmentMeta[$column]['label'] ?? ucwords(str_replace('_', ' ', $column)) }}
																	</strong>
																	<small class="text-muted">{{ $column }}</small>
																</span>
															</label>
														</div>
													@endforeach
												</div>
											</div>
										@endforeach
									</div>
								</div>
							</div>
						</div>

						{{-- HOUSING --}}
						<div class="col-lg-6 mb-4">
							<div class="card card-bordered h-100">
								<div class="card-header">
									<h4>{{ __('ui.exports.housing_table_fields') }}</h4>
								</div>

								<div class="card-body">


									<div class="mb-4">
										<div class="d-flex gap-2 flex-wrap mb-3">

											<button type="button" class="btn btn-sm btn-light-primary"
												onclick="toggleVisibleGroup('housingColumnsList','housing_columns[]',true)">
												{{ __('ui.exports.select_all') }}
											</button>

											<button type="button" class="btn btn-sm btn-light-danger"
												onclick="toggleVisibleGroup('housingColumnsList','housing_columns[]',false)">
												{{ __('ui.exports.deselect_all') }}
											</button>
										</div>

										<div class="input-group">
											<span class="input-group-text">
												<i class="fas fa-search"></i>
											</span>

											<input type="text" id="housingSearch" class="form-control form-control-solid"
												placeholder="{{ __('ui.exports.search_fields') }}"
												onkeyup="filterColumns('housingSearch','housingColumnsList','housingCounter')">

											<button type="button" class="btn btn-light"
												onclick="clearSearch('housingSearch','housingColumnsList','housingCounter')">
												{{ __('ui.exports.clear') }}
											</button>
										</div>

										<div class="text-muted fs-7 mt-2">
											{{ __('ui.exports.total_results') }}
											<span id="housingCounter">{{ count($housingColumns) }}</span>
											/ {{ count($housingColumns) }}
										</div>
									</div>

									<div id="housingColumnsList">
										@foreach($groupedHousing as $group => $columns)
											<div class="mb-5 column-group">
												<div
													class="d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom pb-2 mb-4">
													<h5 class="fw-bold text-success mb-0">{{ $group }}</h5>

													<div class="d-flex gap-2 flex-wrap">
														<button type="button" class="btn btn-sm btn-light-primary"
															onclick="toggleColumnGroup(this,'housing_columns[]',true)">
															{{ __('ui.exports.select_all') }}
														</button>

														<button type="button" class="btn btn-sm btn-light-danger"
															onclick="toggleColumnGroup(this,'housing_columns[]',false)">
															{{ __('ui.exports.deselect_all') }}
														</button>
													</div>
												</div>

												<div class="row">
													@foreach($columns as $column)
														<div class="col-md-6 mb-3 column-item">
															<label
																class="form-check form-check-custom form-check-solid border rounded p-3 w-100">
																<input class="form-check-input mt-1" type="checkbox"
																	name="housing_columns[]" value="{{ $column }}"
																	data-export-column-checkbox>

																<span class="form-check-label ms-3">
																	<strong class="d-block">
																		{{ $assessmentMeta[$column]['label'] ?? ucwords(str_replace('_', ' ', $column)) }}
																	</strong>
																	<small class="text-muted">{{ $column }}</small>
																</span>
															</label>
														</div>
													@endforeach
												</div>
											</div>
										@endforeach
									</div>
								</div>
							</div>
						</div>
					</div>

			</div>
			</form>
		</div>
	</div>
	</div>

	<div class="modal fade" id="importObjectIdsModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<form id="importObjectIdsForm" enctype="multipart/form-data">
					@csrf
					<div class="modal-header">
						<h2 class="fw-bold">{{ __('ui.exports.import_objectids') }}</h2>
						<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
							<i class="ki-duotone ki-cross fs-1">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
						</div>
					</div>

					<div class="modal-body py-10 px-lg-17">
						<div class="mb-7">
							<label class="required fw-semibold fs-6 mb-2 d-block">{{ __('ui.exports.objectid_import_target_label') }}</label>
							<div class="btn-group w-100" role="group" aria-label="{{ __('ui.exports.objectid_import_target_label') }}">
								<input type="radio" class="btn-check" name="objectid_filter_target" id="objectidTargetBuilding"
									value="building" autocomplete="off" {{ ($importedObjectIdTarget ?? 'building') === 'building' ? 'checked' : '' }}>
								<label class="btn btn-outline btn-outline-dashed btn-active-light-primary" for="objectidTargetBuilding">
									{{ __('ui.exports.objectid_target_building') }}
								</label>

								<input type="radio" class="btn-check" name="objectid_filter_target" id="objectidTargetHousingUnit"
									value="housing_unit" autocomplete="off" {{ ($importedObjectIdTarget ?? 'building') === 'housing_unit' ? 'checked' : '' }}>
								<label class="btn btn-outline btn-outline-dashed btn-active-light-primary" for="objectidTargetHousingUnit">
									{{ __('ui.exports.objectid_target_housing_unit') }}
								</label>
							</div>
						</div>

						<div class="mb-7">
							<label class="required fw-semibold fs-6 mb-2 d-block">{{ __('ui.exports.objectid_import_method_label') }}</label>
							<div class="btn-group w-100" role="group" aria-label="{{ __('ui.exports.objectid_import_method_label') }}">
								<input type="radio" class="btn-check" name="objectid_input_method" id="objectidInputFile"
									value="file" autocomplete="off" checked>
								<label class="btn btn-outline btn-outline-dashed btn-active-light-primary" for="objectidInputFile">
									{{ __('ui.exports.objectid_input_file') }}
								</label>

								<input type="radio" class="btn-check" name="objectid_input_method" id="objectidInputText"
									value="text" autocomplete="off">
								<label class="btn btn-outline btn-outline-dashed btn-active-light-primary" for="objectidInputText">
									{{ __('ui.exports.objectid_input_text') }}
								</label>
							</div>
						</div>

						<div class="mb-7" id="objectidsFileInputWrap">
							<label class="fw-semibold fs-6 mb-2 d-block">{{ __('ui.exports.objectid_import_file_label') }}</label>
							<input type="file" name="objectids_file" id="objectids_file"
								class="form-control form-control-solid" accept=".xlsx,.xls,.csv" />
							<div class="form-text">{{ __('ui.exports.objectid_import_file_help') }}</div>
							<div class="invalid-feedback d-block" id="objectids-file-error" style="display: none;"></div>
						</div>

						<div class="mb-7 d-none" id="objectidsTextInputWrap">
							<label class="fw-semibold fs-6 mb-2 d-block">{{ __('ui.exports.objectid_import_text_label') }}</label>
							<textarea name="objectids_text" id="objectids_text" class="form-control form-control-solid"
								rows="8" placeholder="{{ __('ui.exports.objectid_import_text_placeholder') }}"></textarea>
							<div class="form-text">{{ __('ui.exports.objectid_import_text_help') }}</div>
							<div class="invalid-feedback d-block" id="objectids-text-error" style="display: none;"></div>
						</div>
					</div>

					<div class="modal-footer flex-center">
						<button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">
							{{ __('ui.buttons.cancel') }}
						</button>
						<button type="submit" class="btn btn-primary" id="importObjectIdsSubmitBtn">
							<span class="indicator-label">{{ __('ui.exports.import_objectids') }}</span>
							<span class="indicator-progress">{{ __('ui.auth.please_wait') }}
								<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
							</span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>


@endsection

@section('script')


	<script>
		let exportInterval = null;
		let isDownloaded = false;
		let activeExportId = null;

		function resetFilters() {
			$('.filter-select2').val(null).trigger('change');
			$('.audit-notes-select2').val('').trigger('change');
		}

		function selectedExportMode() {
			return $('input[name="export_mode"]:checked').val() || 'data';
		}

		function checkedColumnValues(inputName) {
			return $('input[name="' + inputName + '"]:checked').map(function () {
				return this.value;
			}).get().filter(Boolean);
		}

		function selectedDataColumnValues() {
			return [
				...checkedColumnValues('building_columns[]'),
				...checkedColumnValues('housing_columns[]')
			].filter(Boolean);
		}

		function selectedDataColumnCount(formData = null) {
			const selectedColumns = new Set(selectedDataColumnValues());

			if (Array.isArray(formData)) {
				formData.forEach(function (field) {
					if ((field.name === 'building_columns[]' || field.name === 'housing_columns[]') && field.value) {
						selectedColumns.add(field.value);
					}
				});
			}

			return selectedColumns.size;
		}

		function updateSelectedColumnsStatus() {
			const count = selectedDataColumnCount();
			const button = $('#selectedColumnsStatusBtn');

			$('#selectedColumnsCount').text(count);
			button.toggleClass('btn-light-info', count === 0);
			button.toggleClass('btn-light-success', count > 0);
		}

		function appendMissingFormFields(formData, inputName, values) {
			const existingValues = formData
				.filter(function (field) {
					return field.name === inputName;
				})
				.map(function (field) {
					return field.value;
				});

			values.forEach(function (value) {
				if (!existingValues.includes(value)) {
					formData.push({ name: inputName, value: value });
				}
			});
		}

		function syncSelectedColumnsIntoForm() {
			$('.selected-export-column-mirror').remove();

			[
				{ name: 'building_columns[]', values: checkedColumnValues('building_columns[]') },
				{ name: 'housing_columns[]', values: checkedColumnValues('housing_columns[]') }
			].forEach(function (group) {
				group.values.forEach(function (value) {
					$('<input>')
						.attr('type', 'hidden')
						.attr('name', group.name)
						.addClass('selected-export-column-mirror')
						.val(value)
						.appendTo('#exportForm');
				});
			});
		}

		function exportFormData() {
			syncSelectedColumnsIntoForm();

			const formData = $('#exportForm').serializeArray();

			appendMissingFormFields(formData, 'building_columns[]', checkedColumnValues('building_columns[]'));
			appendMissingFormFields(formData, 'housing_columns[]', checkedColumnValues('housing_columns[]'));

			return formData;
		}

		function hasSelectedDataColumns(formData) {
			if (selectedDataColumnCount(formData) > 0) {
				return true;
			}

			return formData.some(function (field) {
				return (
					(field.name === 'building_columns[]' || field.name === 'housing_columns[]') && field.value
				) || (
					(field.name === 'include_legal_notes' || field.name === 'include_engineering_notes') && field.value === '1'
				) || (
					(field.name === 'legal_notes_filter' || field.name === 'engineering_notes_filter') && field.value
				);
			});
		}

		function requiresDataColumnsForExport() {
			return selectedExportMode() !== 'attachments';
		}

		function syncAttachmentExcelDisplay() {
			if ($('#attachmentExcelDisplay').val() === 'images') {
				$('input[name="include_attachment_excel_columns"][value="1"]').prop('checked', true);
			}
		}

		function syncAttachmentExportOptions() {
			$('#attachmentExportOptions').removeClass('d-none');
			syncAttachmentExcelDisplay();
		}

		function toggleVisibleGroup(listId, inputName, checked) {
			const list = document.getElementById(listId);
			if (!list) return;

			const visibleItems = list.querySelectorAll('.column-item');

			visibleItems.forEach(function (item) {
				if (item.style.display !== 'none') {
					const checkbox = item.querySelector('input[name="' + inputName + '"]');
					if (checkbox) {
						checkbox.checked = checked;
					}
				}
			});

			updateSelectedColumnsStatus();
		}

		function toggleColumnGroup(button, inputName, checked) {
			const group = button.closest('.column-group');
			if (!group) return;

			const visibleItems = group.querySelectorAll('.column-item');

			visibleItems.forEach(function (item) {
				if (item.style.display !== 'none') {
					const checkbox = item.querySelector('input[name="' + inputName + '"]');
					if (checkbox) {
						checkbox.checked = checked;
					}
				}
			});

			updateSelectedColumnsStatus();
		}

		function filterColumns(inputId, listId, counterId) {
			const input = document.getElementById(inputId);
			const list = document.getElementById(listId);
			const counter = document.getElementById(counterId);

			if (!input || !list || !counter) return;

			const filter = input.value.toLowerCase().trim();
			const items = list.querySelectorAll('.column-item');

			let visibleCount = 0;

			items.forEach(function (item) {
				const text = item.innerText.toLowerCase();

				if (text.includes(filter)) {
					item.style.display = '';
					visibleCount++;
				} else {
					item.style.display = 'none';
				}
			});

			counter.innerText = visibleCount;
		}

		function clearSearch(inputId, listId, counterId) {
			const input = document.getElementById(inputId);
			if (!input) return;

			input.value = '';
			filterColumns(inputId, listId, counterId);
			input.focus();
		}

		function filterFilterCards() {
			const input = document.getElementById('filterSearch');
			const counter = document.getElementById('filterCardsCounter');
			if (!input || !counter) return;

			const filter = input.value.toLowerCase().trim();
			const items = document.querySelectorAll('#filtersCardsList .filter-card-item');

			let visibleCount = 0;

			items.forEach(function (item) {
				const text = item.innerText.toLowerCase();

				if (text.includes(filter)) {
					item.style.display = '';
					visibleCount++;
				} else {
					item.style.display = 'none';
				}
			});

			counter.innerText = visibleCount;
		}

		function clearFilterSearch() {
			const input = document.getElementById('filterSearch');
			if (!input) return;

			input.value = '';
			filterFilterCards();
			input.focus();
		}

		function stopExportInterval() {
			if (exportInterval) {
				clearInterval(exportInterval);
				exportInterval = null;
			}
		}

		function enableExportButtons() {
			$('.export-btn').prop('disabled', false);
		}

		function disableExportButtons() {
			$('.export-btn').prop('disabled', true);
		}

		function setImportObjectIdsLoading(isLoading) {
			const button = $('#importObjectIdsSubmitBtn');
			if (!button.length) return;

			if (isLoading) {
				button.attr('data-kt-indicator', 'on');
				button.prop('disabled', true);
			} else {
				button.removeAttr('data-kt-indicator');
				button.prop('disabled', false);
			}
		}

		function syncObjectIdInputMethod() {
			const method = $('input[name="objectid_input_method"]:checked').val() || 'file';
			const fileWrap = $('#objectidsFileInputWrap');
			const textWrap = $('#objectidsTextInputWrap');
			const fileInput = $('#objectids_file');
			const textInput = $('#objectids_text');

			if (method === 'text') {
				fileWrap.addClass('d-none');
				textWrap.removeClass('d-none');
				fileInput.prop('disabled', true);
				textInput.prop('disabled', false);
			} else {
				fileWrap.removeClass('d-none');
				textWrap.addClass('d-none');
				fileInput.prop('disabled', false);
				textInput.prop('disabled', true);
			}
		}

		function normalizeObjectIdsForDisplay(objectIds) {
			const values = Array.isArray(objectIds)
				? objectIds
				: Object.values(objectIds || {});

			return [...new Set(values
				.map(function (value) {
					const match = String(value || '').trim().match(/^\d+(?:\.0+)?$/);

					return match ? String(parseInt(match[0], 10)) : '';
				})
				.filter(Boolean))];
		}

		function objectIdsFromImportResponse(response, formData) {
			const responseIds = normalizeObjectIdsForDisplay(response.object_ids || response.objectIds || []);

			if (responseIds.length > 0) {
				return responseIds;
			}

			const pastedText = formData.get('objectids_text') || '';
			const matches = String(pastedText).match(/\d+(?:\.0+)?/g) || [];

			return normalizeObjectIdsForDisplay(matches);
		}

		function renderImportedObjectIds(objectIds, target) {
			const ids = normalizeObjectIdsForDisplay(objectIds);
			const targetLabels = {
				building: @json(__('ui.exports.objectid_target_building')),
				housing_unit: @json(__('ui.exports.objectid_target_housing_unit'))
			};

			$('#objectIdsFilterSummary')
				.toggleClass('d-none', ids.length === 0)
				.toggleClass('d-flex', ids.length > 0);
			$('#resetObjectIdsFilterBtn').toggleClass('d-none', ids.length === 0);

			const summaryText = @json(__('ui.exports.objectid_import_active_count', ['count' => '__COUNT__']))
				.replace('__COUNT__', ids.length)
				+ ' '
				+ @json(__('ui.exports.objectid_import_active_target', ['target' => '__TARGET__']))
					.replace('__TARGET__', targetLabels[target] || targetLabels.building);

			$('#objectIdsFilterSummaryText').text(summaryText);

			const badges = $('#objectIdsFilterBadges').empty();
			ids.slice(0, 8).forEach(function (objectId) {
				$('<span>')
					.addClass('badge badge-light-primary')
					.text(objectId)
					.appendTo(badges);
			});

			if (ids.length > 8) {
				$('<span>')
					.addClass('badge badge-light')
					.text('+' + (ids.length - 8))
					.appendTo(badges);
			}

			const inputs = $('#importedObjectIdsInputs').empty();
			ids.forEach(function (objectId) {
				$('<input>')
					.attr('type', 'hidden')
					.attr('name', 'imported_object_ids[]')
					.val(objectId)
					.appendTo(inputs);
			});

			if (ids.length > 0) {
				$('<input>')
					.attr('type', 'hidden')
					.attr('name', 'imported_object_id_target')
					.val(target || 'building')
					.appendTo(inputs);
			}
		}

		function showPreparingCard() {
			$('#exportResult').html(`
																<div class="card p-4 text-center">
																	<h5 class="mb-3">
																		{{ __('ui.exports.preparing_file') }}
																		<span class="spinner-border spinner-border-sm ms-2"></span>
																	</h5>

																	<div class="progress mb-3" style="height: 25px;">
																		<div id="progressBar"
																			 class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
																			 style="width: 0%">
																			0%
																		</div>
																	</div>

																	<div id="processedCount" class="text-muted small mt-2"></div>

																	<div class="mt-4">
																		<button type="button" class="btn btn-sm btn-light-danger" id="cancelCurrentExportBtn" disabled>
																			<i class="ki-duotone ki-cross-circle fs-5">
																				<span class="path1"></span>
																				<span class="path2"></span>
																			</i>
																			إيقاف التحميل
																		</button>
																	</div>
																</div>
															`);
		}

		function syncCancelExportButton() {
			$('#cancelCurrentExportBtn').prop('disabled', !activeExportId);
		}

		function showError(message) {
			$('#exportResult').html(`
																<div class="alert alert-danger text-center">
																	${message}
																</div>
															`);

			enableExportButtons();
			stopExportInterval();
			isDownloaded = false;
			activeExportId = null;
		}

		function showSuccess(fileUrl) {
			$('#exportResult').html(`
																<div class="alert alert-success text-center">
																	<div class="mb-3">{{ __('ui.exports.file_ready') }}</div>
																	<a href="${fileUrl}" class="btn btn-success" target="_blank">
																		{{ __('ui.exports.download_file') }}
																	</a>
																</div>
															`);

			enableExportButtons();
			stopExportInterval();
			isDownloaded = true;
			activeExportId = null;
		}

		function updateProgress(progress, processed, totalRows = null) {
			progress = parseInt(progress || 0);
			processed = parseInt(processed || 0);
			totalRows = parseInt(totalRows || 0);

			$('#progressBar')
				.css('width', progress + '%')
				.text(progress + '%');

			if (totalRows > 0) {
				$('#processedCount').text('تم تجهيز ' + processed + ' من ' + totalRows + ' صف');
			} else {
				$('#processedCount').text(@json(__('ui.exports.processed_records', ['count' => '__COUNT__'])).replace('__COUNT__', processed));
			}
		}

		function startCheckingExport(exportId) {
			stopExportInterval();
			activeExportId = exportId;
			syncCancelExportButton();

			exportInterval = setInterval(function () {
				$.ajax({
					url: "{{ url('damage-assessment/exports/check') }}/" + exportId,
					type: "GET",
					success: function (response) {
						updateProgress(response.progress, response.processed, response.total_rows);

						if (response.status === 'finished' && response.file) {
							showSuccess(response.file);
						} else if (response.status === 'failed') {
							showError(@json(__('ui.exports.export_failed')));
						} else if (response.status === 'cancelled') {
							showError(@json(__('ui.exports.export_cancelled')));
						}
						if (response.status === 'done' && response.file && !isDownloaded) {
							isDownloaded = true;
							window.open(response.file, '_blank');
							showSuccess(response.file);
						}
					},
					error: function () {
						showError(@json(__('ui.exports.export_status_failed')));
					}
				});
			}, 2000);
		}

		function restartExport(formData) {
			$.ajax({
				url: "{{ url('damage-assessment/exports/start') }}",
				type: "POST",
				data: formData,
				success: function (newRes) {
					if (newRes.status) {
						toastr.success(newRes.message || @json(__('ui.exports.export_started')));
						activeExportId = newRes.export_id;
						syncCancelExportButton();
						startCheckingExport(newRes.export_id);
					} else {
						enableExportButtons();
						toastr.error(newRes.message || @json(__('ui.exports.export_start_failed')));
					}
				},
				error: function (xhr) {
					enableExportButtons();
					toastr.error(xhr.responseJSON?.message || @json(__('ui.exports.export_restart_failed')));
				}
			});
		}

		document.addEventListener('DOMContentLoaded', function () {
			filterColumns('buildingSearch', 'buildingColumnsList', 'buildingCounter');
			filterColumns('housingSearch', 'housingColumnsList', 'housingCounter');
			filterFilterCards();
			syncObjectIdInputMethod();
			syncAttachmentExportOptions();
			updateSelectedColumnsStatus();

			$('.filter-select2').select2({
				width: '100%',
				dir: 'rtl',
				closeOnSelect: false,
				placeholder: @json(__('ui.exports.select_values'))
			});

			$('.attachment-type-select2').select2({
				width: '100%',
				dir: 'rtl',
				closeOnSelect: false,
				placeholder: 'كل المرفقات'
			});

			$('.audit-notes-select2').select2({
				width: '100%',
				dir: 'rtl',
				minimumResultsForSearch: Infinity
			});

			$('#legalNotesFilter').on('change', function () {
				if ($(this).val()) {
					$('#exportIncludeLegalNotes').prop('checked', true);
				}
			});

			$('#engineeringNotesFilter').on('change', function () {
				if ($(this).val()) {
					$('#exportIncludeEngineeringNotes').prop('checked', true);
				}
			});

			$('input[data-export-column-checkbox]').on('change', updateSelectedColumnsStatus);

			$('#selectedColumnsStatusBtn').on('click', function () {
				document.getElementById('buildingColumnsList')?.scrollIntoView({
					behavior: 'smooth',
					block: 'start'
				});
			});

			$('.attachment-type-select2').on('change', function () {
				const selected = $(this).val() || [];

				if (selected.length > 1 && selected.includes('all')) {
					$(this).val(selected.filter((value) => value !== 'all')).trigger('change.select2');
				}
			});

			const collapse = document.getElementById('filtersCollapse');
			const btn = document.getElementById('toggleFiltersBtn');

			if (collapse && btn) {
				collapse.addEventListener('shown.bs.collapse', function () {
					btn.innerHTML = '<i class="fas fa-chevron-down me-1"></i> {{ __('ui.exports.hide') }}';
				});

				collapse.addEventListener('hidden.bs.collapse', function () {
					btn.innerHTML = '<i class="fas fa-chevron-left me-1"></i> {{ __('ui.exports.show') }}';
				});
			}

			$('#importObjectIdsForm').on('submit', function (e) {
				e.preventDefault();

				const form = this;
				syncObjectIdInputMethod();
				const formData = new FormData(form);

				$('#objectids-file-error').hide().text('');
				$('#objectids-text-error').hide().text('');
				setImportObjectIdsLoading(true);

				$.ajax({
					url: @json(route('export.data.objectids.import')),
					type: 'POST',
					data: formData,
					dataType: 'json',
					processData: false,
					contentType: false,
					success: function (response) {
						toastr.success(response.message);
						renderImportedObjectIds(objectIdsFromImportResponse(response, formData), response.target || 'building');
						const modalElement = document.getElementById('importObjectIdsModal');
						const modalInstance = bootstrap.Modal.getInstance(modalElement);
						if (modalInstance) {
							modalInstance.hide();
						}
						form.reset();
					},
					error: function (xhr) {
						const message = xhr.responseJSON?.message || @json(__('ui.exports.objectid_import_failed'));
						const fieldMessage = xhr.responseJSON?.errors?.objectids_file?.[0];
						const textMessage = xhr.responseJSON?.errors?.objectids_text?.[0];

						if (fieldMessage) {
							$('#objectids-file-error').text(fieldMessage).show();
						}

						if (textMessage) {
							$('#objectids-text-error').text(textMessage).show();
						}

						toastr.error(message);
					},
					complete: function () {
						setImportObjectIdsLoading(false);
					}
				});
			});

			$('input[name="objectid_input_method"]').on('change', syncObjectIdInputMethod);
			$('input[name="export_mode"]').on('change', syncAttachmentExportOptions);
			$('#attachmentExcelDisplay').on('change', syncAttachmentExcelDisplay);

			$('#resetObjectIdsFilterBtn').on('click', function () {
				$.ajax({
					url: @json(route('export.data.objectids.reset')),
					type: 'POST',
					data: {
						_token: @json(csrf_token())
					},
					success: function (response) {
						toastr.success(response.message);
						renderImportedObjectIds([], 'building');
					},
					error: function (xhr) {
						toastr.error(xhr.responseJSON?.message || @json(__('ui.exports.objectid_import_reset_failed')));
					}
				});
			});

			$(document).on('click', '#cancelCurrentExportBtn', function () {
				if (!activeExportId) {
					return;
				}

				const button = $(this);
				button.prop('disabled', true);

				$.ajax({
					url: "{{ url('damage-assessment/exports') }}/" + activeExportId + "/cancel",
					type: "POST",
					data: {
						_token: "{{ csrf_token() }}"
					},
					success: function (response) {
						toastr.success(response.message || 'تم إيقاف التحميل.');
						showError(@json(__('ui.exports.export_cancelled')));
					},
					error: function (xhr) {
						button.prop('disabled', false);
						toastr.error(xhr.responseJSON?.message || 'تعذر إيقاف التحميل.');
					}
				});
			});

			$('.export-btn').on('click', function (e) {
				e.preventDefault();

				if ($('.export-btn').prop('disabled')) return;

				let exportType = $(this).data('type');
				if (exportType === 'zip' && selectedExportMode() === 'data') {
					$('#exportModeAttachments').prop('checked', true);
					syncAttachmentExportOptions();
				}

				if (['attachments', 'data_with_attachments'].includes(selectedExportMode())) {
					exportType = 'zip';
				}

				syncAttachmentExcelDisplay();

				const formData = exportFormData();

				if (requiresDataColumnsForExport() && !hasSelectedDataColumns(formData)) {
					toastr.error('يرجى اختيار عمود واحد على الأقل من أعمدة البيانات قبل التصدير.');
					return;
				}

				formData.push({ name: 'export_type', value: exportType });

				disableExportButtons();
				isDownloaded = false;
				stopExportInterval();
				showPreparingCard();

				$.ajax({
					url: "{{ url('damage-assessment/exports/start') }}",
					type: "POST",
					data: formData,
					success: function (response) {
						if (response.status) {
							toastr.success(response.message || @json(__('ui.exports.export_started')));
							activeExportId = response.export_id;
							syncCancelExportButton();
							startCheckingExport(response.export_id);
						} else {
							enableExportButtons();
							toastr.error(response.message || @json(__('ui.exports.export_start_failed')));
						}
					},
					error: function (xhr) {
						const res = xhr.responseJSON;

						if (xhr.status === 409 && res?.needs_cancel) {
							stopExportInterval();

							Swal.fire({
								title: @json(__('ui.exports.running_export_title')),
								html: `
																					<div class="text-center">
																						<p>${res.message}</p>
																						<p>${@json(__('ui.exports.running_export_progress', ['progress' => '__PROGRESS__'])).replace('__PROGRESS__', res.running_export.progress ?? 0)}</p>
																						${res.running_export.total_rows ? `<p>تم تجهيز ${res.running_export.processed ?? 0} من ${res.running_export.total_rows} صف</p>` : ''}
																					</div>
																				`,
								icon: 'warning',
								showCancelButton: true,
								confirmButtonText: @json(__('ui.exports.cancel_old_and_start_new')),
								cancelButtonText: @json(__('ui.exports.close'))
							}).then((result) => {
								if (result.isConfirmed) {
									$.ajax({
										url: "{{ url('damage-assessment/exports') }}/" + res.running_export.id + "/cancel",
										type: "POST",
										data: {
											_token: "{{ csrf_token() }}"
										},
										beforeSend: function () {
											Swal.showLoading();
										},
										success: function (cancelRes) {
											toastr.success(cancelRes.message || @json(__('ui.exports.old_export_cancelled')));
											showPreparingCard();
											restartExport(formData);
										},
										error: function (cancelXhr) {
											enableExportButtons();
											toastr.error(cancelXhr.responseJSON?.message || @json(__('ui.exports.old_export_cancel_failed')));
										}
									});
								} else {
									enableExportButtons();
									$('#exportResult').html('');
								}
							});

							return;
						}

						enableExportButtons();
						toastr.error(res?.message || @json(__('ui.exports.unexpected_error')));
					}
				});
			});
		});
	</script>
@endsection
