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



	@endphp
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
						{{ __('ui.exports.import_objectids_excel') }}
					</button>

					@if(!empty($importedObjectIds))
						<button type="button" class="btn btn-sm btn-light-danger" id="resetObjectIdsFilterBtn">
							<i class="ki-duotone ki-cross-circle fs-5">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
							{{ __('ui.exports.reset_objectid_import_filter') }}
						</button>
					@endif
				</div>

			</div>

			<div class="card-body">
				@if(session('error'))
					<div class="alert alert-danger">
						{{ session('error') }}
					</div>
				@endif

				@if(!empty($importedObjectIds))
					<div
						class="alert alert-info d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
						<div>
							<strong>{{ __('ui.exports.objectid_import_active') }}</strong>
							<div class="text-muted fs-7 mt-1">
								{{ __('ui.exports.objectid_import_active_count', ['count' => count($importedObjectIds)]) }}
							</div>
						</div>
						<div class="d-flex flex-wrap gap-2">
							@foreach(array_slice($importedObjectIds, 0, 8) as $objectId)
								<span class="badge badge-light-primary">{{ $objectId }}</span>
							@endforeach
							@if(count($importedObjectIds) > 8)
								<span class="badge badge-light">+{{ count($importedObjectIds) - 8 }}</span>
							@endif
						</div>
					</div>
				@endif

				<form id="exportForm" method="POST">
					@csrf

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
								</div>
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
											<div class="mb-5">
												<h5 class="fw-bold text-primary border-bottom pb-2 mb-4">{{ $group }}</h5>

												<div class="row">
													@foreach($columns as $column)
														<div class="col-md-6 mb-3 column-item">
															<label
																class="form-check form-check-custom form-check-solid border rounded p-3 w-100">
																<input class="form-check-input mt-1" type="checkbox"
																	name="building_columns[]" value="{{ $column }}">

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
											<div class="mb-5">
												<h5 class="fw-bold text-success border-bottom pb-2 mb-4">{{ $group }}</h5>

												<div class="row">
													@foreach($columns as $column)
														<div class="col-md-6 mb-3 column-item">
															<label
																class="form-check form-check-custom form-check-solid border rounded p-3 w-100">
																<input class="form-check-input mt-1" type="checkbox"
																	name="housing_columns[]" value="{{ $column }}">

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
						<h2 class="fw-bold">{{ __('ui.exports.import_objectids_excel') }}</h2>
						<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
							<i class="ki-duotone ki-cross fs-1">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
						</div>
					</div>

					<div class="modal-body py-10 px-lg-17">
						<div class="mb-7">
							<label
								class="required fw-semibold fs-6 mb-2 d-block">{{ __('ui.exports.objectid_import_file_label') }}</label>
							<input type="file" name="objectids_file" id="objectids_file"
								class="form-control form-control-solid" accept=".xlsx,.xls,.csv" />
							<div class="form-text">{{ __('ui.exports.objectid_import_file_help') }}</div>
							<div class="invalid-feedback d-block" id="objectids-file-error" style="display: none;"></div>
						</div>
					</div>

					<div class="modal-footer flex-center">
						<button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">
							{{ __('ui.buttons.cancel') }}
						</button>
						<button type="submit" class="btn btn-primary" id="importObjectIdsSubmitBtn">
							<span class="indicator-label">{{ __('ui.exports.import_objectids_excel') }}</span>
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

		function resetFilters() {
			$('.filter-select2').val(null).trigger('change');
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
																</div>
															`);
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
		}

		function updateProgress(progress, processed) {
			progress = parseInt(progress || 0);
			processed = parseInt(processed || 0);

			$('#progressBar')
				.css('width', progress + '%')
				.text(progress + '%');

			$('#processedCount').text(@json(__('ui.exports.processed_records', ['count' => '__COUNT__'])).replace('__COUNT__', processed));
		}

		function startCheckingExport(exportId) {
			stopExportInterval();

			exportInterval = setInterval(function () {
				$.ajax({
					url: "{{ url('') }}/exports/check/" + exportId,
					type: "GET",
					success: function (response) {
						updateProgress(response.progress, response.processed);

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
				url: "{{ url('exports/start') }}",
				type: "POST",
				data: formData,
				success: function (newRes) {
					if (newRes.status) {
						toastr.success(newRes.message || @json(__('ui.exports.export_started')));
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

			$('.filter-select2').select2({
				width: '100%',
				dir: 'rtl',
				closeOnSelect: false,
				placeholder: @json(__('ui.exports.select_values'))
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
				const formData = new FormData(form);

				$('#objectids-file-error').hide().text('');
				setImportObjectIdsLoading(true);

				$.ajax({
					url: @json(route('export.data.objectids.import')),
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function (response) {
						toastr.success(response.message);
						const modalElement = document.getElementById('importObjectIdsModal');
						const modalInstance = bootstrap.Modal.getInstance(modalElement);
						if (modalInstance) {
							modalInstance.hide();
						}
						form.reset();
						window.location.reload();
					},
					error: function (xhr) {
						const message = xhr.responseJSON?.message || @json(__('ui.exports.objectid_import_failed'));
						const fieldMessage = xhr.responseJSON?.errors?.objectids_file?.[0];

						if (fieldMessage) {
							$('#objectids-file-error').text(fieldMessage).show();
						}

						toastr.error(message);
					},
					complete: function () {
						setImportObjectIdsLoading(false);
					}
				});
			});

			$('#resetObjectIdsFilterBtn').on('click', function () {
				$.ajax({
					url: @json(route('export.data.objectids.reset')),
					type: 'POST',
					data: {
						_token: @json(csrf_token())
					},
					success: function (response) {
						toastr.success(response.message);
						window.location.reload();
					},
					error: function (xhr) {
						toastr.error(xhr.responseJSON?.message || @json(__('ui.exports.objectid_import_reset_failed')));
					}
				});
			});

			$('.export-btn').on('click', function (e) {
				e.preventDefault();

				if ($('.export-btn').prop('disabled')) return;

				const exportType = $(this).data('type');
				const formData = $('#exportForm').serializeArray();
				formData.push({ name: 'export_type', value: exportType });

				disableExportButtons();
				isDownloaded = false;
				stopExportInterval();
				showPreparingCard();

				$.ajax({
					url: "{{ url('exports/start') }}",
					type: "POST",
					data: formData,
					success: function (response) {
						if (response.status) {
							toastr.success(response.message || @json(__('ui.exports.export_started')));
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
																					</div>
																				`,
								icon: 'warning',
								showCancelButton: true,
								confirmButtonText: @json(__('ui.exports.cancel_old_and_start_new')),
								cancelButtonText: @json(__('ui.exports.close'))
							}).then((result) => {
								if (result.isConfirmed) {
									$.ajax({
										url: "{{ url('') }}/exports/" + res.running_export.id + "/cancel",
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