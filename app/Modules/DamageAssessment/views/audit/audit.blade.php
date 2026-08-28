@extends('layouts.app')
@php
	$isFieldEngineerAudit = $isFieldEngineerAudit ?? false;
	$bulkEditableBuildingFields = $assessments
		->filter(fn ($assessment) => filled($assessment->name) && \Illuminate\Support\Facades\Schema::hasColumn('buildings', $assessment->name))
		->sortBy(fn ($assessment) => $assessment->hint ?: $assessment->label ?: $assessment->name)
		->values();
	$bulkEditableHousingFields = $assessments
		->filter(fn ($assessment) => filled($assessment->name) && \Illuminate\Support\Facades\Schema::hasColumn('housing_units', $assessment->name))
		->sortBy(fn ($assessment) => $assessment->hint ?: $assessment->label ?: $assessment->name)
		->values();
	$bulkInlineFilterOptions = \App\Models\Filter::query()
		->whereIn('list_name', $bulkEditableBuildingFields->pluck('name')->merge($bulkEditableHousingFields->pluck('name'))->unique()->values())
		->orderBy('list_name')
		->orderBy('label')
		->get(['list_name', 'name', 'label'])
		->groupBy('list_name')
		->map(fn ($options) => $options
			->map(fn ($option) => [
				'value' => $option->name,
				'label' => $option->label ?: $option->name,
			])
			->values())
		->toArray();
@endphp
@section('title', $isFieldEngineerAudit ? 'مباني المهندس الميداني' : __('ui.audit.title'))
@section('pageName', $isFieldEngineerAudit ? 'مباني المهندس الميداني' : __('ui.audit.title'))


@section('content')
	<style>
		table.dataTable thead th.sorting,
		table.dataTable thead th.sorting_asc,
		table.dataTable thead th.sorting_desc {
			padding-right: 15px !important;
		}

		#kt_datatable_audits_wrapper table.dataTable thead th.sorting,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting_asc,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting_desc,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting_disabled {
			background-image: none !important;
			padding-inline-end: 0.35rem !important;
		}

		#kt_datatable_audits_wrapper table.dataTable thead th.sorting::before,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting::after,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting_asc::before,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting_asc::after,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting_desc::before,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting_desc::after,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting_disabled::before,
		#kt_datatable_audits_wrapper table.dataTable thead th.sorting_disabled::after {
			display: none !important;
			content: "" !important;
		}

		#kt_datatable_audits_wrapper table.dataTable thead th .dt-column-order,
		#kt_datatable_audits_wrapper table.dataTable thead th .dt-column-title::after {
			display: none !important;
			content: "" !important;
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
			min-width: 940px;
			table-layout: fixed;
		}

		#kt_datatable_audits th,
		#kt_datatable_audits td {
			padding: 0.45rem 0.35rem !important;
			vertical-align: middle;
		}

		#kt_datatable_audits thead th,
		#kt_datatable_audits tbody td {
			text-align: center;
		}

		#kt_datatable_audits thead th {
			font-size: 0.74rem;
			line-height: 1.35;
			white-space: normal;
			overflow-wrap: anywhere;
		}

		#kt_datatable_audits tbody td {
			font-size: 1rem;
			line-height: 1.4;
		}

		#kt_datatable_audits .audit-cell-text,
		#kt_datatable_audits .audit-cell-name {
			display: block;
			line-height: 1.4;
			
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
			
		}

		#kt_datatable_audits .badge {
			display: inline-flex;
			max-width: 100%;
			min-height: 26px;
			padding: 0.3rem 0.4rem;
			justify-content: center;
			align-items: center;
			font-size: .80rem;
			line-height: 1.25;
			text-align: center;
			
		}

		#kt_datatable_audits .form-check {
			min-height: 1rem;
		}

		#engineer_change_log_table {
			width: 100% !important;
		}

		#engineerChangeLogModal .modal-dialog {
			max-width: min(1800px, 96vw);
		}

		.engineer-change-log-value {
			display: block;
			max-width: 420px;
			white-space: normal;
			overflow-wrap: anywhere;
			line-height: 1.45;
		}

		.engineer-change-log-field-code {
			display: block;
			direction: ltr;
			unicode-bidi: plaintext;
			font-size: .78rem;
			color: var(--bs-gray-600);
			margin-top: .2rem;
		}

		#kt_datatable_audits_wrapper table.dataTable .audit-select-cell {
			min-width: 64px;
			width: 64px;
			padding: 0 !important;
			position: relative;
			text-align: center !important;
		}

		#kt_datatable_audits_wrapper table.dataTable .audit-select-cell .form-check {
			display: block;
			margin: 0 !important;
			padding: 0 !important;
			position: static !important;
			width: 100%;
			min-height: 100%;
		}

		#kt_datatable_audits_wrapper table.dataTable .audit-select-cell .form-check-input {
			float: none !important;
			left: 50%;
			margin: 0 !important;
			position: absolute !important;
			top: 50%;
			transform: translate(-50%, -50%) !important;
		}

		#kt_datatable_audits .audit-actions-cell {
			min-width: 96px;
			white-space: nowrap;
		}

		#kt_datatable_audits .audit-actions-wrapper {
			justify-content: center !important;
		}

		.audit-main-toolbar {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: .6rem;
			justify-content: flex-end;
		}

		.audit-main-toolbar .btn {
			white-space: nowrap;
		}

		.audit-toolbar-menu {
			min-width: 230px;
			padding: .45rem;
		}

		.audit-toolbar-menu .dropdown-item {
			align-items: center;
			border-radius: .475rem;
			display: flex;
			gap: .5rem;
			justify-content: space-between;
			padding: .65rem .8rem;
			text-align: start;
			white-space: normal;
		}

		.audit-toolbar-menu .dropdown-header {
			color: var(--bs-gray-600);
			font-size: .72rem;
			font-weight: 800;
			padding: .35rem .8rem .55rem;
		}

		#bulkInlineEditModal .modal-dialog {
			max-width: min(980px, 96vw);
		}

		.bulk-inline-field-option {
			display: flex;
			flex-direction: column;
			gap: .2rem;
			line-height: 1.35;
			padding-block: .15rem;
			text-align: start;
		}

		.bulk-inline-field-option__label {
			color: #1f2937;
			font-weight: 700;
			white-space: normal;
		}

		.bulk-inline-field-option__meta {
			align-items: center;
			color: var(--bs-gray-600);
			display: flex;
			flex-wrap: wrap;
			font-size: .76rem;
			gap: .35rem;
		}

		.bulk-inline-field-option__code {
			direction: ltr;
			font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
			unicode-bidi: plaintext;
		}

		.bulk-inline-field-option__kind {
			background: var(--bs-light-primary);
			border-radius: 999px;
			color: var(--bs-primary);
			font-size: .68rem;
			font-weight: 800;
			padding: .1rem .45rem;
		}

		#bulkInlineEditModal .select2-results__option {
			padding: .65rem .85rem;
		}

		#bulkInlineEditModal .select2-selection__rendered .bulk-inline-field-option {
			gap: 0;
			padding-block: .05rem;
		}

		#bulkInlineEditModal .select2-selection__rendered .bulk-inline-field-option__meta {
			display: none;
		}

		#bulkInlineEditModal #select2-bulk_inline_field-container {
			line-height: 1.35;
			white-space: normal;
		}

		#bulkInlineEditModal .select2-container--bootstrap5 .select2-selection--single {
			min-height: 46px;
		}

		#kt_datatable_audits .btn {
			padding: 0.35rem 0.45rem;
			font-size: .95rem;
		}

		@media (min-width: 1600px) {
			#kt_datatable_audits {
				min-width: 100%;
			}
		}

		@media (max-width: 1399.98px) {
			#kt_datatable_audits {
				min-width: 900px;
			}

			#kt_datatable_audits th,
			#kt_datatable_audits td {
				padding: 0.4rem 0.28rem !important;
			}

			#kt_datatable_audits thead th {
				font-size: 0.68rem;
			}

			#kt_datatable_audits tbody td,
			#kt_datatable_audits .badge,
			#kt_datatable_audits .btn {
				font-size: 0.72rem;
			}
		}

		@media (max-width: 991.98px) {
			.audit-table-wrapper {
				margin-inline: -0.75rem;
				padding-inline: 0.75rem;
			}
		}

		#kt_datatable_audits td.text-end,
		#kt_datatable_audits th.text-end {
			text-align: right !important;
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
							<label class="form-label fw-semibold">{{ __('ui.audit.survey_status') }}</label>
							<select id="filter_field_status" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="false" data-placeholder="{{ __('ui.audit.select_status') }}">
								<option value="COMPLETED" selected>COMPLETED</option>
								<option value="Not_Completed">Not_Completed</option>
								<option value="all">{{ __('ui.audit.all_statuses') }}</option>
							</select>
						</div>

						@if(! $isFieldEngineerAudit)
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
						@endif

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

						@if(! $isFieldEngineerAudit)
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
						@endif

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.area') }}</label>
							<input type="text" id="filter_area" class="form-control form-control-solid"
								placeholder="{{ __('ui.audit.area_placeholder') }}" />
						</div>
						@if(! $isFieldEngineerAudit)
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
						@endif

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
							<label class="form-label fw-semibold">التحديات القانونية</label>
							<select id="filter_legal_challenge" class="form-select form-select-solid" data-control="select2"
								data-allow-clear="true" data-close-on-select="false" multiple
								data-placeholder="اختر التحدي القانوني">
								@foreach($legalChallenges as $value => $label)
									<option value="{{ $value }}">{{ $label }}</option>
								@endforeach
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
							<input type="date" id="filter_status_from_date"
								placeholder="{{ __('ui.audit.from_status_date') }}" class="form-control form-control-solid">
						</div>

						<div class="col-md-3">
							<label class="form-label fw-semibold">{{ __('ui.audit.to_status_date') }}</label>
							<input type="date" id="filter_status_to_date" placeholder="{{ __('ui.audit.to_status_date') }}"
								class="form-control form-control-solid">
						</div>
						<div class="col-md-3 d-flex align-items-end">
							<button class="btn btn-light-primary w-100" type="button" data-bs-toggle="collapse"
								data-bs-target="#advanced_audit_building_filters" aria-expanded="false"
								aria-controls="advanced_audit_building_filters">
								<i class="ki-duotone ki-filter fs-2"></i>
								{{ __('ui.buildings_page.advanced_filters') }}
							</button>
						</div>
					</div>

					<div id="advanced_audit_building_filters" class="collapse mt-8">
						<div class="separator separator-dashed mb-6"></div>
						<div class="accordion" id="audit_building_filter_sections">
							@foreach (($buildingFilterSections ?? []) as $sectionIndex => $section)
								<div class="accordion-item border border-gray-200 rounded mb-3">
									<h2 class="accordion-header" id="audit_building_filter_heading_{{ $sectionIndex }}">
										<button class="accordion-button fs-6 fw-semibold collapsed" type="button"
											data-bs-toggle="collapse"
											data-bs-target="#audit_building_filter_panel_{{ $sectionIndex }}"
											aria-expanded="false"
											aria-controls="audit_building_filter_panel_{{ $sectionIndex }}">
											{{ $section['title'] }}
										</button>
									</h2>
									<div id="audit_building_filter_panel_{{ $sectionIndex }}" class="accordion-collapse collapse"
										aria-labelledby="audit_building_filter_heading_{{ $sectionIndex }}"
										data-bs-parent="#audit_building_filter_sections">
										<div class="accordion-body">
											<div class="row g-5">
												@foreach ($section['filters'] as $filter)
													<div class="col-md-3">
														<label class="form-label fw-semibold">{{ $filter['label'] }}</label>
														<select data-audit-building-filter="{{ $filter['field'] }}"
															class="form-select form-select-solid audit-building-filter-control"
															data-control="select2"
															data-placeholder="{{ __('ui.buildings_page.select_filter', ['label' => $filter['label']]) }}"
															data-allow-clear="true" data-close-on-select="false" multiple>
															@foreach ($filter['options'] as $option)
																<option value="{{ $option->name }}">{{ $option->label }}</option>
															@endforeach
														</select>
													</div>
												@endforeach
											</div>
										</div>
									</div>
								</div>
							@endforeach
						</div>

						<div class="row g-5 mt-2">
							@foreach ([
								'floor_nos' => __('ui.buildings_page.floor_count'),
								'units_nos' => __('ui.buildings_page.units_count'),
								'damaged_units_nos' => __('ui.buildings_page.damaged_units_count'),
							] as $field => $label)
								<div class="col-md-4">
									<label class="form-label fw-semibold">{{ $label }}</label>
									<div class="d-flex gap-2">
										<input type="number" data-audit-building-filter="{{ $field }}_from"
											class="form-control form-control-solid audit-building-filter-control"
											placeholder="{{ __('ui.buildings_page.from') }}">
										<input type="number" data-audit-building-filter="{{ $field }}_to"
											class="form-control form-control-solid audit-building-filter-control"
											placeholder="{{ __('ui.buildings_page.to') }}">
									</div>
								</div>
							@endforeach
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
					<div class="card-toolbar audit-main-toolbar">
						<button onclick="refreshTable(this)" class="btn btn-light-success btn-sm">
							{{ __('ui.audit.refresh') }} <i class="ki-duotone ki-arrows-circle fs-3"></i>
						</button>

						@unless(auth()->user()->hasRole('Area Manager') || $hideAuditManagementActions)
							<button id="btn_final_approve" class="btn btn-warning btn-sm">
								{{ __('ui.audit.approve_final') }} <i class="ki-duotone ki-check-circle"></i>
							</button>
						@endunless

						@if(! $isFieldEngineerAudit)
							<div class="dropdown">
								<button type="button" class="btn btn-light-success btn-sm dropdown-toggle"
									data-bs-toggle="dropdown" aria-expanded="false">
									التقارير والتصفية
								</button>
								<div class="dropdown-menu dropdown-menu-end audit-toolbar-menu">
									<div class="dropdown-header">التقارير</div>
									<button type="button" class="dropdown-item" data-bs-toggle="modal"
										data-bs-target="#auditExportModal">
										<span>تصدير Excel</span>
										<i class="ki-duotone ki-file-down"></i>
									</button>
									<button type="button" id="export_floor_area_mismatch" class="dropdown-item">
										<span>تصدير مخالفات المساحات Excel</span>
										<i class="ki-duotone ki-file-down"></i>
									</button>
									<div class="dropdown-divider"></div>
									<div class="dropdown-header">فلاتر سريعة</div>
									<button type="button" id="toggle_accepted_with_unevaluated_units" class="dropdown-item"
										data-filter-active="false">
										<span>مقبول وبداخله وحدات غير مقيمة</span>
										<i class="ki-duotone ki-information-5"></i>
									</button>
									<button type="button" id="toggle_floor_area_mismatch" class="dropdown-item"
										data-filter-active="false">
										<span>مخالف لمساحات الطوابق</span>
										<i class="ki-duotone ki-chart-line-down"></i>
									</button>
								</div>
							</div>
						@endif

						@if(($canManageAuditReviewers ?? false) || (! auth()->user()->hasRole('Area Manager') && ! $hideAuditManagementActions))
							<div class="dropdown">
								<button type="button" class="btn btn-light-primary btn-sm dropdown-toggle"
									data-bs-toggle="dropdown" aria-expanded="false">
									التعيينات
								</button>
								<div class="dropdown-menu dropdown-menu-end audit-toolbar-menu">
									<div class="dropdown-header">إدارة الفريق</div>
									@if($canManageAuditReviewers ?? false)
										<button type="button" class="dropdown-item" data-bs-toggle="modal"
											data-bs-target="#auditReviewersModal">
											<span>Audit Reviewers</span>
											<i class="ki-duotone ki-profile-user"></i>
										</button>
									@endif
									@unless(auth()->user()->hasRole('Area Manager') || $hideAuditManagementActions)
										<button id="btn_assign_to_lawyer" class="dropdown-item">
											<span>{{ __('ui.audit.assign_to_lawyer') }}</span>
											<i class="ki-duotone ki-plus"></i>
										</button>
										<button id="btn_assign_to_engineer" class="dropdown-item">
											<span>{{ __('ui.audit.assign_to_engineer') }}</span>
											<i class="ki-duotone ki-plus"></i>
										</button>
									@endunless
								</div>
							</div>
						@endif

						@unless(auth()->user()->hasRole('Area Manager') || $hideAuditManagementActions)
							<button type="button" class="btn btn-light-primary btn-sm" data-bs-toggle="modal"
								data-bs-target="#bulkInlineEditModal">
								تعديل جماعي <i class="ki-duotone ki-notepad-edit"></i>
							</button>

							<div class="dropdown">
								<button type="button" class="btn btn-light-warning btn-sm dropdown-toggle"
									data-bs-toggle="dropdown" aria-expanded="false">
									اعتمادات إضافية
								</button>
								<div class="dropdown-menu dropdown-menu-end audit-toolbar-menu">
									<div class="dropdown-header">اعتماد جماعي</div>
									@hasanyrole('Database Officer|undp-Project Manager')
										<button id="btn_undp_final_approve" class="dropdown-item">
											<span>UNDP Final Approve</span>
											<i class="ki-duotone ki-check-circle"></i>
										</button>
									@endhasanyrole
									<button id="btn_import_final_approve" class="dropdown-item">
										<span>ObjectIDs Final Approve</span>
										<i class="ki-duotone ki-file-up"></i>
									</button>
								</div>
							</div>
						@endunless

						@if(! $isFieldEngineerAudit)
							<div class="dropdown">
								<button type="button" class="btn btn-light-info btn-sm dropdown-toggle"
									data-bs-toggle="dropdown" aria-expanded="false">
									العرض والسجلات
								</button>
								<div class="dropdown-menu dropdown-menu-end audit-toolbar-menu">
									<div class="dropdown-header">أدوات العرض</div>
									@if(! $hideAuditManagementActions)
										<button type="button" id="toggle_select_column" class="dropdown-item"
											data-select-visible="false">
											<span>إظهار التحديد</span>
											<i class="ki-duotone ki-check-square"></i>
										</button>
									@endif
									<button type="button" id="btn_engineer_change_log" class="dropdown-item">
										<span>تغييرات مهندسي التدقيق</span>
										<i class="ki-duotone ki-notepad-edit"></i>
									</button>
								</div>
							</div>
						@endif
					</div>
				</div>

				<div class="card-body pt-0">
					<div class="audit-table-wrapper">
						<table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_datatable_audits">
							<thead>
								<tr class="text-muted fw-bold fs-7 text-uppercase gs-0">
									<th class="audit-select-cell">
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
							 -->
									<th>{{ __('ui.audit.actions') }}</th>
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
	@if($canManageAuditReviewers ?? false)
	<div class="modal fade" id="auditReviewersModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered mw-lg-900px">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="fw-bold">إدارة Audit Reviewers</h2>
					<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
						<i class="ki-duotone ki-cross fs-1"></i>
					</div>
				</div>

				<div class="modal-body">
					<form method="POST" action="{{ route('audit.reviewers.store') }}" class="row g-3 align-items-end mb-8">
						@csrf

						<div class="col-md-8">
							<label for="audit_reviewer_user_id" class="form-label fw-semibold">المستخدم</label>
							<select id="audit_reviewer_user_id" name="user_id" class="form-select form-select-solid"
								data-control="select2" data-dropdown-parent="#auditReviewersModal"
								data-placeholder="اختر مستخدم" data-allow-clear="true" required>
								<option value="">اختر مستخدم</option>
								@foreach ($auditReviewerCandidates as $candidate)
									<option value="{{ $candidate->id }}">
										{{ $candidate->name ?? '-' }}
										@if ($candidate->id_no)
											- {{ $candidate->id_no }}
										@endif
									</option>
								@endforeach
							</select>
						</div>

						<div class="col-md-4">
							<button type="submit" class="btn btn-primary w-100">
								<i class="ki-duotone ki-plus fs-2"></i>
								إضافة
							</button>
						</div>
					</form>

					<div class="table-responsive">
						<table class="table align-middle table-row-dashed fs-6 gy-5 mb-0">
							<thead>
								<tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
									<th>الاسم</th>
									<th>رقم الهوية</th>
									<th>البريد</th>
									<th class="text-end">الإجراءات</th>
								</tr>
							</thead>
							<tbody class="text-gray-600 fw-semibold">
								@forelse ($auditReviewers as $reviewer)
									<tr>
										<td>{{ $reviewer->name ?? '-' }}</td>
										<td>{{ $reviewer->id_no ?? '-' }}</td>
										<td>{{ $reviewer->email ?? '-' }}</td>
										<td class="text-end">
											<form method="POST" action="{{ route('audit.reviewers.destroy', $reviewer) }}">
												@csrf
												@method('DELETE')
												<button type="submit" class="btn btn-sm btn-light-danger">
													إزالة
												</button>
											</form>
										</td>
									</tr>
								@empty
									<tr>
										<td colspan="4" class="text-center text-muted py-10">
											لا يوجد Audit Reviewers حاليا.
										</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('ui.buttons.cancel') }}</button>
				</div>
			</div>
		</div>
	</div>
	@endif
	<div class="modal fade" id="bulkInlineEditModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered mw-lg-800px">
			<div class="modal-content">
				<form id="bulk_inline_edit_form">
					@csrf
					<div class="modal-header">
						<h2 class="fw-bold">تعديل جماعي</h2>
						<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
							<i class="ki-duotone ki-cross fs-1"></i>
						</div>
					</div>

					<div class="modal-body py-8 px-lg-10">
						<div class="row g-5">
							<div class="col-md-6">
								<label class="required form-label fw-semibold">نوع السجلات</label>
								<select name="type" id="bulk_inline_type" class="form-select form-select-solid"
									data-control="select2" data-dropdown-parent="#bulkInlineEditModal">
									<option value="building_table">مباني</option>
									<option value="housing_table">وحدات</option>
								</select>
							</div>

							<div class="col-md-6">
								<label class="required form-label fw-semibold">الحقل</label>
								<select name="field" id="bulk_inline_field" class="form-select form-select-solid"
									data-control="select2" data-dropdown-parent="#bulkInlineEditModal"
									data-placeholder="اختر الحقل">
									<option></option>
									@foreach($bulkEditableBuildingFields as $assessment)
										@php
											$fieldLabel = $assessment->hint ?: $assessment->label ?: $assessment->name;
											$fieldHasOptions = isset($bulkInlineFilterOptions[$assessment->name]) && count($bulkInlineFilterOptions[$assessment->name]) > 0;
										@endphp
										<option value="{{ $assessment->name }}" data-type="building_table"
											data-label="{{ $fieldLabel }}" data-code="{{ $assessment->name }}"
											data-kind="{{ $fieldHasOptions ? 'قائمة منسدلة' : 'نص حر' }}">
											{{ $fieldLabel }}
										</option>
									@endforeach
									@foreach($bulkEditableHousingFields as $assessment)
										@php
											$fieldLabel = $assessment->hint ?: $assessment->label ?: $assessment->name;
											$fieldHasOptions = isset($bulkInlineFilterOptions[$assessment->name]) && count($bulkInlineFilterOptions[$assessment->name]) > 0;
										@endphp
										<option value="{{ $assessment->name }}" data-type="housing_table"
											data-label="{{ $fieldLabel }}" data-code="{{ $assessment->name }}"
											data-kind="{{ $fieldHasOptions ? 'قائمة منسدلة' : 'نص حر' }}">
											{{ $fieldLabel }}
										</option>
									@endforeach
								</select>
							</div>

							<div class="col-12">
								<label class="required form-label fw-semibold">ObjectIDs</label>
								<textarea name="objectids_text" id="bulk_inline_objectids" rows="7"
									class="form-control form-control-solid"
									placeholder="الصق ObjectID لكل سطر، أو افصل بينها بفواصل"></textarea>
								<div class="form-text">يمكن إدخال القيم كسطور منفصلة أو مفصولة بفواصل.</div>
							</div>

							<input type="hidden" name="value" id="bulk_inline_value">

							<div class="col-12" id="bulk_inline_value_text_wrapper">
								<label class="form-label fw-semibold">القيمة الجديدة</label>
								<textarea id="bulk_inline_value_text" rows="3" class="form-control form-control-solid"
									placeholder="اكتب القيمة التي ستظهر كآخر تعديل"></textarea>
							</div>

							<div class="col-12 d-none" id="bulk_inline_value_select_wrapper">
								<label class="required form-label fw-semibold">القيمة الجديدة</label>
								<select id="bulk_inline_value_select" class="form-select form-select-solid"
									data-control="select2" data-dropdown-parent="#bulkInlineEditModal"
									data-placeholder="اختر القيمة من الاستبيان">
									<option></option>
								</select>
								<div class="form-text">هذه الخيارات مأخوذة من نفس قائمة الاستبيان لهذا الحقل.</div>
							</div>
						</div>

						<div id="bulk_inline_result" class="alert alert-light-primary d-none mt-6 mb-0"></div>
					</div>

					<div class="modal-footer">
						<button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('ui.buttons.cancel') }}</button>
						<button type="submit" class="btn btn-primary" id="bulk_inline_submit">
							<span class="indicator-label">تنفيذ التعديل</span>
							<span class="indicator-progress">جاري التنفيذ...
								<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
							</span>
						</button>
					</div>
				</form>
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
					<h2 class="fw-bold" id="notesHistoryModalTitle">{{ __('ui.audit.notes_history') }}</h2>
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
	<div class="modal fade" id="buildingAttachmentsModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered mw-900px">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="fw-bold" id="buildingAttachmentsModalTitle">المرفقات</h2>
					<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
						<i class="ki-duotone ki-cross fs-1"></i>
					</div>
				</div>

				<div class="modal-body">
					<div id="buildingAttachmentsAlert" class="alert d-none"></div>

					<ul class="nav nav-tabs nav-line-tabs mb-7" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" id="building-attachments-tab" data-bs-toggle="tab"
								data-bs-target="#building_attachments_tab_pane" type="button" role="tab">
								مرفقات المبنى
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="housing-unit-attachments-tab" data-bs-toggle="tab"
								data-bs-target="#housing_unit_attachments_tab_pane" type="button" role="tab">
								مرفقات الوحدات السكنية
							</button>
						</li>
					</ul>

					<div class="tab-content">
						<div class="tab-pane fade show active" id="building_attachments_tab_pane" role="tabpanel">
					<form id="buildingAttachmentForm" enctype="multipart/form-data" class="mb-7">
						@csrf
						<input type="hidden" id="building_attachment_globalid">
						<input type="hidden" id="building_attachment_replace_id">

						<div class="row g-3 align-items-end">
							<div class="col-md-8">
								<label class="form-label required">ملف المرفق</label>
								<input type="file" name="attachment" id="building_attachment_file"
									class="form-control form-control-solid" required>
							</div>
							<div class="col-md-4 d-flex gap-2">
								<button type="submit" class="btn btn-primary flex-grow-1" id="buildingAttachmentSubmit">
									<span class="indicator-label" id="buildingAttachmentSubmitLabel">إضافة</span>
									<span class="indicator-progress">يرجى الانتظار...
										<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
									</span>
								</button>
								<button type="button" class="btn btn-light d-none" id="cancelAttachmentReplace">إلغاء</button>
							</div>
						</div>
					</form>

					<div class="table-responsive">
						<table class="table table-row-bordered table-striped gy-4 gs-5">
							<thead>
								<tr class="fw-bold fs-6 text-gray-800">
									<th>معاينة</th>
									<th>الاسم</th>
									<th>النوع</th>
									<th>الحجم</th>
									<th class="text-end">الإجراءات</th>
								</tr>
							</thead>
							<tbody id="buildingAttachmentsTableBody">
								<tr>
									<td colspan="5" class="text-center text-muted">لا توجد مرفقات</td>
								</tr>
							</tbody>
						</table>
					</div>
						</div>

						<div class="tab-pane fade" id="housing_unit_attachments_tab_pane" role="tabpanel">
							<div class="mb-5">
								<label class="form-label fw-semibold">اختر الوحدة السكنية</label>
								<select id="housing_unit_attachment_select" class="form-select form-select-solid"
									data-control="select2" data-dropdown-parent="#buildingAttachmentsModal"
									data-placeholder="اختر الوحدة">
									<option></option>
								</select>
							</div>

							<form id="housingUnitAttachmentForm" enctype="multipart/form-data" class="mb-7 d-none">
								@csrf
								<input type="hidden" id="housing_unit_attachment_replace_id">

								<div class="row g-3 align-items-end">
									<div class="col-md-8">
										<label class="form-label required">ملف المرفق</label>
										<input type="file" name="attachment" id="housing_unit_attachment_file"
											class="form-control form-control-solid" required>
									</div>
									<div class="col-md-4 d-flex gap-2">
										<button type="submit" class="btn btn-primary flex-grow-1" id="housingUnitAttachmentSubmit">
											<span class="indicator-label" id="housingUnitAttachmentSubmitLabel">إضافة</span>
											<span class="indicator-progress">يرجى الانتظار...
												<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
											</span>
										</button>
										<button type="button" class="btn btn-light d-none" id="cancelHousingUnitAttachmentReplace">إلغاء</button>
									</div>
								</div>
							</form>

							<div class="table-responsive">
								<table class="table table-row-bordered table-striped gy-4 gs-5">
									<thead>
										<tr class="fw-bold fs-6 text-gray-800">
											<th>معاينة</th>
											<th>الاسم</th>
											<th>النوع</th>
											<th>الحجم</th>
											<th class="text-end">الإجراءات</th>
										</tr>
									</thead>
									<tbody id="housingUnitAttachmentsTableBody">
										<tr>
											<td colspan="5" class="text-center text-muted">اختر وحدة سكنية لعرض مرفقاتها</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
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

						<div class="mb-8">
							<h4 class="fw-bold mb-4">ملاحظات التدقيق</h4>
							<div class="row g-5">
								<div class="col-md-6">
									<label class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input" type="checkbox" id="audit_include_legal_notes"
											name="include_legal_notes" value="1">
										<span class="form-check-label fw-semibold">تضمين الملاحظات القانونية + اسم المدقق القانوني</span>
									</label>
									<select id="audit_legal_notes_filter" class="form-select form-select-solid">
										<option value="">كل السجلات القانونية</option>
										<option value="with_notes">يوجد ملاحظة قانونية</option>
										<option value="without_notes">لا يوجد ملاحظة قانونية</option>
									</select>
								</div>

								<div class="col-md-6">
									<label class="form-check form-check-custom form-check-solid mb-3">
										<input class="form-check-input" type="checkbox" id="audit_include_engineering_notes"
											name="include_engineering_notes" value="1">
										<span class="form-check-label fw-semibold">تضمين الملاحظات الهندسية + اسم المدقق الهندسي</span>
									</label>
									<select id="audit_engineering_notes_filter" class="form-select form-select-solid">
										<option value="">كل السجلات الهندسية</option>
										<option value="with_notes">يوجد ملاحظة هندسية</option>
										<option value="without_notes">لا يوجد ملاحظة هندسية</option>
									</select>
								</div>
							</div>
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

	@if(! $isFieldEngineerAudit)
	<div class="modal fade" id="engineerChangeLogModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<div>
						<h2 class="fw-bold mb-1">تغييرات مهندسي التدقيق</h2>
						<div class="text-muted fs-7">تغييرات الاستبيان على المباني والوحدات</div>
					</div>
					<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
						<i class="ki-duotone ki-cross fs-1"></i>
					</div>
				</div>

				<div class="modal-body">
					<div class="row g-4 mb-6">
						<div class="col-lg-3 col-md-6">
							<label class="form-label fw-semibold">اسم المهندس</label>
							<select id="engineer_change_log_engineer" class="form-select form-select-solid"
								data-control="select2" data-dropdown-parent="#engineerChangeLogModal"
								data-placeholder="كل المهندسين">
								<option value="">كل المهندسين</option>
							</select>
						</div>
						<div class="col-lg-3 col-md-6">
							<label class="form-label fw-semibold">اسم الحقل</label>
							<select id="engineer_change_log_field" class="form-select form-select-solid"
								data-control="select2" data-dropdown-parent="#engineerChangeLogModal"
								data-placeholder="كل الحقول">
								<option value="">كل الحقول</option>
							</select>
						</div>
						<div class="col-lg-3 col-md-6">
							<label class="form-label fw-semibold">نوع السجل</label>
							<select id="engineer_change_log_type" class="form-select form-select-solid">
								<option value="">مبنى ووحدة</option>
								<option value="building_table">مبنى</option>
								<option value="housing_table">وحدة</option>
							</select>
						</div>
						<div class="col-lg-3 col-md-6">
							<label class="form-label fw-semibold">بحث</label>
							<input type="text" id="engineer_change_log_search" class="form-control form-control-solid"
								placeholder="ابحث في القيم أو رقم السجل">
						</div>
					</div>

					<div class="table-responsive">
						<table class="table align-middle table-row-dashed fs-6 gy-4" id="engineer_change_log_table">
							<thead>
								<tr class="text-muted fw-bold fs-7 text-uppercase gs-0">
									<th>النوع</th>
									<th>السجل</th>
									<th>المهندس</th>
									<th>اسم الحقل</th>
									<th>قبل</th>
									<th>بعد</th>
									<th>التاريخ</th>
									<th>فتح</th>
								</tr>
							</thead>
							<tbody class="text-gray-700 fw-semibold"></tbody>
						</table>
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
				</div>
			</div>
		</div>
	</div>
	@endif

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


			let acceptedWithUnevaluatedUnits = false;
			let floorAreaMismatch = false;
			let housingUnitAttachmentUnits = [];
			const buildingAttachmentRoutes = {
				index: @json(route('audit.building.attachments.index', ['building' => '__BUILDING__'])),
				housingIndex: @json(route('audit.building.housing-unit-attachments.index', ['building' => '__BUILDING__'])),
				store: @json(route('audit.building.attachments.store', ['building' => '__BUILDING__'])),
				replace: @json(route('audit.building.attachments.replace', ['building' => '__BUILDING__', 'attachmentId' => '__ATTACHMENT__'])),
				destroy: @json(route('audit.building.attachments.destroy', ['building' => '__BUILDING__', 'attachmentId' => '__ATTACHMENT__'])),
				housingStore: @json(route('audit.housing-unit.attachments.store', ['housingUnit' => '__HOUSING_UNIT__'])),
				housingReplace: @json(route('audit.housing-unit.attachments.replace', ['housingUnit' => '__HOUSING_UNIT__', 'attachmentId' => '__ATTACHMENT__'])),
				housingDestroy: @json(route('audit.housing-unit.attachments.destroy', ['housingUnit' => '__HOUSING_UNIT__', 'attachmentId' => '__ATTACHMENT__'])),
			};

			function buildingAttachmentUrl(routeName, buildingGlobalId, attachmentId = null) {
				let url = buildingAttachmentRoutes[routeName].replace('__BUILDING__', encodeURIComponent(buildingGlobalId));

				if (attachmentId !== null) {
					url = url.replace('__ATTACHMENT__', encodeURIComponent(attachmentId));
				}

				return url;
			}

			function housingUnitAttachmentUrl(routeName, housingUnitGlobalId, attachmentId = null) {
				let url = buildingAttachmentRoutes[routeName].replace('__HOUSING_UNIT__', encodeURIComponent(housingUnitGlobalId));

				if (attachmentId !== null) {
					url = url.replace('__ATTACHMENT__', encodeURIComponent(attachmentId));
				}

				return url;
			}

			function showBuildingAttachmentsAlert(type, message) {
				$('#buildingAttachmentsAlert')
					.removeClass('d-none alert-success alert-danger alert-warning alert-info')
					.addClass('alert-' + type)
					.text(message);
			}

			function hideBuildingAttachmentsAlert() {
				$('#buildingAttachmentsAlert')
					.addClass('d-none')
					.removeClass('alert-success alert-danger alert-warning alert-info')
					.text('');
			}

			function formatAttachmentSize(size) {
				const bytes = Number(size || 0);

				if (!bytes) {
					return '-';
				}

				if (bytes < 1024) {
					return bytes + ' B';
				}

				if (bytes < 1024 * 1024) {
					return (bytes / 1024).toFixed(1) + ' KB';
				}

				return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
			}

			function isImageAttachment(attachment) {
				const contentType = String(attachment.content_type || '').toLowerCase();
				const name = String(attachment.name || '').toLowerCase();

				return contentType.startsWith('image/')
					|| /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(name);
			}

			function renderAttachmentPreview(attachment) {
				if (!attachment.url) {
					return '<span class="text-muted">-</span>';
				}

				if (!isImageAttachment(attachment)) {
					return `<a href="${attachment.url}" target="_blank" class="btn btn-sm btn-light">فتح</a>`;
				}

				return `
					<a href="${attachment.url}" target="_blank" class="d-inline-block">
						<img src="${attachment.url}" alt="${escapeAuditCell(attachment.name || 'Attachment')}"
							class="rounded border object-fit-cover"
							style="width: 72px; height: 56px;">
					</a>
				`;
			}

			function resetBuildingAttachmentForm() {
				$('#buildingAttachmentForm')[0].reset();
				$('#building_attachment_replace_id').val('');
				$('#buildingAttachmentSubmitLabel').text('إضافة');
				$('#cancelAttachmentReplace').addClass('d-none');
			}

			function selectedHousingUnitAttachmentUnit() {
				const unitKey = $('#housing_unit_attachment_select').val();

				if (!unitKey) {
					return null;
				}

				return housingUnitAttachmentUnits.find(function (item) {
					return String(item.globalid || item.objectid) === String(unitKey);
				}) || null;
			}

			function resetHousingUnitAttachmentForm() {
				const form = $('#housingUnitAttachmentForm')[0];

				if (form) {
					form.reset();
				}

				$('#housing_unit_attachment_replace_id').val('');
				$('#housingUnitAttachmentSubmitLabel').text('إضافة');
				$('#cancelHousingUnitAttachmentReplace').addClass('d-none');
			}

			function renderBuildingAttachments(attachments) {
				if (!attachments || attachments.length === 0) {
					$('#buildingAttachmentsTableBody').html(
						'<tr><td colspan="5" class="text-center text-muted">لا توجد مرفقات</td></tr>'
					);

					return;
				}

				let rows = '';

				attachments.forEach(function (attachment) {
					rows += `
						<tr>
							<td>${renderAttachmentPreview(attachment)}</td>
							<td>${escapeAuditCell(attachment.name || '-')}</td>
							<td>${escapeAuditCell(attachment.content_type || '-')}</td>
							<td>${formatAttachmentSize(attachment.size)}</td>
							<td class="text-end">
								<a href="${attachment.url}" target="_blank" class="btn btn-sm btn-light-primary me-1">فتح</a>
								<button type="button" class="btn btn-sm btn-light-warning me-1 btn-replace-building-attachment"
									data-attachment-id="${attachment.id}">
									تعديل
								</button>
								<button type="button" class="btn btn-sm btn-light-danger btn-delete-building-attachment"
									data-attachment-id="${attachment.id}">
									حذف
								</button>
							</td>
						</tr>
					`;
				});

				$('#buildingAttachmentsTableBody').html(rows);
			}

			function loadBuildingAttachments() {
				const buildingGlobalId = $('#building_attachment_globalid').val();

				if (!buildingGlobalId) {
					return;
				}

				hideBuildingAttachmentsAlert();
				$('#buildingAttachmentsTableBody').html(
					'<tr><td colspan="5" class="text-center text-muted">جاري تحميل المرفقات...</td></tr>'
				);

				$.ajax({
					url: buildingAttachmentUrl('index', buildingGlobalId),
					type: 'GET',
					success: function (response) {
						renderBuildingAttachments(response.attachments || []);
					},
					error: function (xhr) {
						renderBuildingAttachments([]);
						showBuildingAttachmentsAlert('danger', xhr.responseJSON?.message || 'تعذر تحميل المرفقات من ArcGIS.');
					}
				});
			}

			function resetHousingUnitAttachmentSelect(units) {
				housingUnitAttachmentUnits = units || [];
				const select = $('#housing_unit_attachment_select');

				select.empty().append(new Option('', '', true, false));

				housingUnitAttachmentUnits.forEach(function (unit) {
					select.append(new Option(unit.title || '-', unit.globalid || unit.objectid, false, false));
				});

				if (select.data('select2')) {
					select.trigger('change.select2');
				} else {
					select.select2({
						dropdownParent: $('#buildingAttachmentsModal'),
						placeholder: select.data('placeholder') || 'اختر الوحدة',
						allowClear: true,
					});
				}

				select.val(null).trigger('change');
			}

			function renderSelectedHousingUnitAttachments(unitKey) {
				if (!housingUnitAttachmentUnits || housingUnitAttachmentUnits.length === 0) {
					$('#housingUnitAttachmentForm').addClass('d-none');
					resetHousingUnitAttachmentForm();
					$('#housingUnitAttachmentsTableBody').html(
						'<tr><td colspan="5" class="text-center text-muted">لا توجد وحدات سكنية مرتبطة بهذا المبنى</td></tr>'
					);

					return;
				}

				if (!unitKey) {
					$('#housingUnitAttachmentForm').addClass('d-none');
					resetHousingUnitAttachmentForm();
					$('#housingUnitAttachmentsTableBody').html(
						'<tr><td colspan="5" class="text-center text-muted">اختر وحدة سكنية لعرض مرفقاتها</td></tr>'
					);

					return;
				}

				const unit = housingUnitAttachmentUnits.find(function (item) {
					return String(item.globalid || item.objectid) === String(unitKey);
				});

				resetHousingUnitAttachmentForm();

				if (!unit || !unit.globalid) {
					$('#housingUnitAttachmentForm').addClass('d-none');
					$('#housingUnitAttachmentsTableBody').html(
						'<tr><td colspan="5" class="text-center text-muted">لا يمكن إدارة مرفقات هذه الوحدة لعدم توفر GlobalID</td></tr>'
					);

					return;
				}

				$('#housingUnitAttachmentForm').removeClass('d-none');

				if (!unit || !unit.attachments || unit.attachments.length === 0) {
					$('#housingUnitAttachmentsTableBody').html(
						'<tr><td colspan="5" class="text-center text-muted">لا توجد مرفقات لهذه الوحدة</td></tr>'
					);

					return;
				}

				let rows = '';

				unit.attachments.forEach(function (attachment) {
					rows += `
						<tr>
							<td>${renderAttachmentPreview(attachment)}</td>
							<td>${escapeAuditCell(attachment.name || '-')}</td>
							<td>${escapeAuditCell(attachment.content_type || '-')}</td>
							<td>${formatAttachmentSize(attachment.size)}</td>
							<td class="text-end">
								<a href="${attachment.url}" target="_blank" class="btn btn-sm btn-light-primary me-1">فتح</a>
								<button type="button" class="btn btn-sm btn-light-warning me-1 btn-replace-housing-unit-attachment"
									data-attachment-id="${attachment.id}">
									تعديل
								</button>
								<button type="button" class="btn btn-sm btn-light-danger btn-delete-housing-unit-attachment"
									data-attachment-id="${attachment.id}">
									حذف
								</button>
							</td>
						</tr>
					`;
				});

				$('#housingUnitAttachmentsTableBody').html(rows);
			}

			function loadHousingUnitAttachments(selectedUnitKey = null) {
				const buildingGlobalId = $('#building_attachment_globalid').val();

				if (!buildingGlobalId) {
					return;
				}

				$('#housingUnitAttachmentsTableBody').html(
					'<tr><td colspan="6" class="text-center text-muted">جاري تحميل مرفقات الوحدات السكنية...</td></tr>'
				);

				$.ajax({
					url: buildingAttachmentUrl('housingIndex', buildingGlobalId),
					type: 'GET',
					success: function (response) {
						resetHousingUnitAttachmentSelect(response.units || []);
						if (selectedUnitKey) {
							$('#housing_unit_attachment_select').val(String(selectedUnitKey)).trigger('change');
						} else {
							renderSelectedHousingUnitAttachments(null);
						}
					},
					error: function (xhr) {
						resetHousingUnitAttachmentSelect([]);
						renderSelectedHousingUnitAttachments(null);
						showBuildingAttachmentsAlert('danger', xhr.responseJSON?.message || 'تعذر تحميل مرفقات الوحدات السكنية من ArcGIS.');
					}
				});
			}

			$('#housing_unit_attachment_select').on('change', function () {
				renderSelectedHousingUnitAttachments($(this).val());
			});

			$('#housingUnitAttachmentForm').on('submit', function (e) {
				e.preventDefault();

				const unit = selectedHousingUnitAttachmentUnit();
				const attachmentId = $('#housing_unit_attachment_replace_id').val();
				const formData = new FormData(this);
				const button = $('#housingUnitAttachmentSubmit');

				if (!unit || !unit.globalid) {
					showBuildingAttachmentsAlert('warning', 'اختر وحدة سكنية أولاً.');

					return;
				}

				formData.append('_token', @json(csrf_token()));

				button.attr('data-kt-indicator', 'on').prop('disabled', true);
				hideBuildingAttachmentsAlert();

				$.ajax({
					url: attachmentId
						? housingUnitAttachmentUrl('housingReplace', unit.globalid, attachmentId)
						: housingUnitAttachmentUrl('housingStore', unit.globalid),
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function (response) {
						showBuildingAttachmentsAlert('success', response.message || 'تم حفظ المرفق.');
						resetHousingUnitAttachmentForm();
						loadHousingUnitAttachments(unit.globalid);
					},
					error: function (xhr) {
						const errors = xhr.responseJSON?.errors;
						const detail = xhr.responseJSON?.details ? ` ${xhr.responseJSON.details}` : '';
						const message = errors?.attachment?.[0] || ((xhr.responseJSON?.message || 'تعذر حفظ المرفق.') + detail);
						showBuildingAttachmentsAlert('danger', message);
					},
					complete: function () {
						button.removeAttr('data-kt-indicator').prop('disabled', false);
					}
				});
			});

			$(document).on('click', '.btn-replace-housing-unit-attachment', function () {
				$('#housing_unit_attachment_replace_id').val($(this).data('attachment-id'));
				$('#housingUnitAttachmentSubmitLabel').text('استبدال');
				$('#cancelHousingUnitAttachmentReplace').removeClass('d-none');
				$('#housing_unit_attachment_file').trigger('click');
			});

			$('#cancelHousingUnitAttachmentReplace').on('click', function () {
				resetHousingUnitAttachmentForm();
			});

			$(document).on('click', '.btn-delete-housing-unit-attachment', function () {
				const unit = selectedHousingUnitAttachmentUnit();
				const attachmentId = $(this).data('attachment-id');

				if (!unit || !unit.globalid) {
					showBuildingAttachmentsAlert('warning', 'اختر وحدة سكنية أولاً.');

					return;
				}

				Swal.fire({
					text: 'هل تريد حذف هذا المرفق؟',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'نعم',
					cancelButtonText: 'إلغاء',
				}).then(function (result) {
					if (!result.isConfirmed) {
						return;
					}

					hideBuildingAttachmentsAlert();

					$.ajax({
						url: housingUnitAttachmentUrl('housingDestroy', unit.globalid, attachmentId),
						type: 'POST',
						data: {
							_token: @json(csrf_token()),
							_method: 'DELETE',
						},
						success: function (response) {
							showBuildingAttachmentsAlert('success', response.message || 'تم حذف المرفق.');
							resetHousingUnitAttachmentForm();
							loadHousingUnitAttachments(unit.globalid);
						},
						error: function (xhr) {
							const detail = xhr.responseJSON?.details ? ` ${xhr.responseJSON.details}` : '';
							showBuildingAttachmentsAlert('danger', (xhr.responseJSON?.message || 'تعذر حذف المرفق.') + detail);
						}
					});
				});
			});

			$(document).on('click', '.btn-building-attachments', function () {
				const buildingGlobalId = $(this).data('building-globalid');
				const buildingName = $(this).data('building-name') || '-';

				$('#building_attachment_globalid').val(buildingGlobalId);
				$('#buildingAttachmentsModalTitle').text('المرفقات - ' + buildingName);
				bootstrap.Tab.getOrCreateInstance(document.getElementById('building-attachments-tab')).show();
				resetBuildingAttachmentForm();
				hideBuildingAttachmentsAlert();
				bootstrap.Modal.getOrCreateInstance(document.getElementById('buildingAttachmentsModal')).show();
				loadBuildingAttachments();
				loadHousingUnitAttachments();
			});

			$('#buildingAttachmentForm').on('submit', function (e) {
				e.preventDefault();

				const buildingGlobalId = $('#building_attachment_globalid').val();
				const attachmentId = $('#building_attachment_replace_id').val();
				const formData = new FormData(this);
				const button = $('#buildingAttachmentSubmit');

				formData.append('_token', @json(csrf_token()));

				button.attr('data-kt-indicator', 'on').prop('disabled', true);
				hideBuildingAttachmentsAlert();

				$.ajax({
					url: attachmentId
						? buildingAttachmentUrl('replace', buildingGlobalId, attachmentId)
						: buildingAttachmentUrl('store', buildingGlobalId),
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function (response) {
						showBuildingAttachmentsAlert('success', response.message || 'تم حفظ المرفق.');
						resetBuildingAttachmentForm();
						loadBuildingAttachments();
					},
					error: function (xhr) {
						const errors = xhr.responseJSON?.errors;
						const detail = xhr.responseJSON?.details ? ` ${xhr.responseJSON.details}` : '';
						const message = errors?.attachment?.[0] || ((xhr.responseJSON?.message || 'تعذر حفظ المرفق.') + detail);
						showBuildingAttachmentsAlert('danger', message);
					},
					complete: function () {
						button.removeAttr('data-kt-indicator').prop('disabled', false);
					}
				});
			});

			$(document).on('click', '.btn-replace-building-attachment', function () {
				$('#building_attachment_replace_id').val($(this).data('attachment-id'));
				$('#buildingAttachmentSubmitLabel').text('استبدال');
				$('#cancelAttachmentReplace').removeClass('d-none');
				$('#building_attachment_file').trigger('click');
			});

			$('#cancelAttachmentReplace').on('click', function () {
				resetBuildingAttachmentForm();
			});

			$(document).on('click', '.btn-delete-building-attachment', function () {
				const buildingGlobalId = $('#building_attachment_globalid').val();
				const attachmentId = $(this).data('attachment-id');

				Swal.fire({
					text: 'هل تريد حذف هذا المرفق؟',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'نعم',
					cancelButtonText: 'إلغاء',
				}).then(function (result) {
					if (!result.isConfirmed) {
						return;
					}

					hideBuildingAttachmentsAlert();

					$.ajax({
						url: buildingAttachmentUrl('destroy', buildingGlobalId, attachmentId),
						type: 'POST',
						data: {
							_token: @json(csrf_token()),
							_method: 'DELETE',
						},
						success: function (response) {
							showBuildingAttachmentsAlert('success', response.message || 'تم حذف المرفق.');
							loadBuildingAttachments();
						},
						error: function (xhr) {
							const detail = xhr.responseJSON?.details ? ` ${xhr.responseJSON.details}` : '';
							showBuildingAttachmentsAlert('danger', (xhr.responseJSON?.message || 'تعذر حذف المرفق.') + detail);
						}
					});
				});
			});

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
			const parseBulkObjectIds = function (value) {
				return [...new Set(String(value || '')
					.split(/[\s,;،]+/)
					.map(item => item.trim())
					.filter(item => /^\d+$/.test(item)))];
			};
			const bulkInlineFilterOptions = @json($bulkInlineFilterOptions);
			const formatBulkInlineFieldOption = function (option) {
				if (!option.id) {
					return option.text;
				}

				const element = $(option.element);
				const label = element.data('label') || option.text;
				const code = element.data('code') || option.id;
				const kind = element.data('kind') || 'نص حر';

				return $(`
					<div class="bulk-inline-field-option">
						<span class="bulk-inline-field-option__label">${escapeAuditCell(label)}</span>
						<span class="bulk-inline-field-option__meta">
							<span class="bulk-inline-field-option__code">${escapeAuditCell(code)}</span>
							<span class="bulk-inline-field-option__kind">${escapeAuditCell(kind)}</span>
						</span>
					</div>
				`);
			};
			const initBulkInlineSelects = function () {
				if (!$.fn.select2) {
					return;
				}

				$('#bulk_inline_type, #bulk_inline_field, #bulk_inline_value_select').each(function () {
					const select = $(this);

					if (select.hasClass('select2-hidden-accessible')) {
						select.select2('destroy');
					}
				});

				$('#bulk_inline_type').select2({
					dropdownParent: $('#bulkInlineEditModal'),
					minimumResultsForSearch: Infinity
				});

				$('#bulk_inline_field').select2({
					dropdownParent: $('#bulkInlineEditModal'),
					templateResult: formatBulkInlineFieldOption,
					templateSelection: formatBulkInlineFieldOption,
					width: '100%'
				});

				$('#bulk_inline_value_select').select2({
					dropdownParent: $('#bulkInlineEditModal'),
					width: '100%'
				});
			};
			initBulkInlineSelects();
			const activeBulkInlineValue = function () {
				const field = $('#bulk_inline_field').val();

				return (bulkInlineFilterOptions[field] || []).length
					? $('#bulk_inline_value_select').val()
					: $('#bulk_inline_value_text').val();
			};
			const syncBulkInlineValueControl = function () {
				const field = $('#bulk_inline_field').val();
				const options = bulkInlineFilterOptions[field] || [];
				const select = $('#bulk_inline_value_select');

				if (options.length) {
					select.empty().append('<option></option>');

					options.forEach(function (option) {
						select.append(new Option(option.label, option.value, false, false));
					});

					$('#bulk_inline_value_text_wrapper').addClass('d-none');
					$('#bulk_inline_value_select_wrapper').removeClass('d-none');
					select.val(null).trigger('change.select2');

					return;
				}

				$('#bulk_inline_value_select_wrapper').addClass('d-none');
				$('#bulk_inline_value_text_wrapper').removeClass('d-none');
				select.val(null).trigger('change.select2');
			};
			const syncBulkInlineFieldOptions = function () {
				const type = $('#bulk_inline_type').val();
				const selected = $('#bulk_inline_field').val();

				$('#bulk_inline_field option').each(function () {
					const option = $(this);
					const optionType = option.data('type');

					if (!option.val()) {
						return;
					}

					const isVisible = optionType === type;
					option.prop('disabled', !isVisible).toggle(isVisible);
				});

				if (selected && $('#bulk_inline_field option:selected').prop('disabled')) {
					$('#bulk_inline_field').val(null);
				}

				$('#bulk_inline_field').trigger('change.select2');
				syncBulkInlineValueControl();
			};
			const renderBulkInlineResult = function (response) {
				const missing = response.missing_objectids || [];
				const denied = response.denied_objectids || [];
				const failed = response.failed || [];
				let html = `
					<div class="fw-bold mb-2">${escapeAuditCell(response.message || 'تم تنفيذ التعديل الجماعي.')}</div>
					<div>المطلوب: ${escapeAuditCell(response.requested_count)}</div>
					<div>الموجود: ${escapeAuditCell(response.found_count)}</div>
					<div>تم التعديل: ${escapeAuditCell(response.updated_count)}</div>
					<div>بدون تغيير: ${escapeAuditCell(response.unchanged_count)}</div>
				`;

				if (missing.length) {
					html += `<div class="mt-2 text-warning">غير موجود: ${escapeAuditCell(missing.join(', '))}</div>`;
				}

				if (denied.length) {
					html += `<div class="mt-2 text-danger">لا تملك صلاحية تعديلها: ${escapeAuditCell(denied.join(', '))}</div>`;
				}

				if (failed.length) {
					html += `<div class="mt-2 text-danger">فشل: ${escapeAuditCell(failed.map(item => item.objectid).join(', '))}</div>`;
				}

				$('#bulk_inline_result')
					.toggleClass('alert-light-primary', failed.length === 0 && denied.length === 0)
					.toggleClass('alert-light-warning', failed.length > 0 || denied.length > 0)
					.removeClass('d-none')
					.html(html);
			};
			const renderAuditTextCell = function (data) {
				return `<span class="audit-cell-text">${escapeAuditCell(data)}</span>`;
			};
			const renderAuditLtrCell = function (data) {
				return `<span class="audit-cell-text audit-cell-ltr">${escapeAuditCell(data)}</span>`;
			};
			const auditAdvancedBuildingFilterPayload = function () {
				const filters = {};

				$('.audit-building-filter-control').each(function () {
					const field = $(this).data('audit-building-filter');
					const value = $(this).val();

					if (!field) {
						return;
					}

					if (Array.isArray(value)) {
						const values = value.filter(Boolean);

						if (values.length > 0) {
							filters[field] = values;
						}

						return;
					}

					if (value !== null && value !== undefined && value !== '') {
						filters[field] = value;
					}
				});

				return filters;
			};
			const auditFilterPayload = function () {
				return {
					building_name: $('#filter_building_name').val(),
					objectid: $('#filter_objectid').val(),
					field_status: $('#filter_field_status').val(),
					engineer_id: $('#filter_engineer').val(),
					lawyer_id: $('#filter_lawyer').val(),
					eng_status: $('#filter_eng_status').val(),
					legal_status: $('#filter_legal_status').val(),
					final_status: $('#filter_final_status').val(),
					area: $('#filter_area').val(),
					field_engineer: $('#filter_field_engineer').val(),
					damage_status: $('#filter_damage_status').val(),
					legal_challenge: $('#filter_legal_challenge').val(),
					accepted_with_unevaluated_units: acceptedWithUnevaluatedUnits ? 1 : '',
					floor_area_mismatch: floorAreaMismatch ? 1 : '',
					filter_from_date: $('#filter_from_date').val(),
					filter_to_date: $('#filter_to_date').val(),
					status_from_date: $('#filter_status_from_date').val(),
					status_to_date: $('#filter_status_to_date').val(),
					advanced_filters: auditAdvancedBuildingFilterPayload()
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

			@if(! $isFieldEngineerAudit)
			let engineerChangeLogTable = null;
			let engineerChangeLogOptionsLoaded = false;
			let engineerChangeLogSearchTimer = null;
			const engineerChangeLogRoute = @json(route('audit.engineer-change-log'));

			const renderEngineerChangeLogValue = function (value) {
				return `<span class="engineer-change-log-value">${escapeAuditCell(value)}</span>`;
			};

			const loadEngineerChangeLogOptions = function () {
				if (engineerChangeLogOptionsLoaded) {
					return $.Deferred().resolve().promise();
				}

				return $.get(engineerChangeLogRoute, { options: 1 }).done(function (response) {
					const engineerSelect = $('#engineer_change_log_engineer');
					const fieldSelect = $('#engineer_change_log_field');

					(response.engineers || []).forEach(function (engineer) {
						engineerSelect.append(new Option(engineer.name, engineer.id, false, false));
					});

					(response.fields || []).forEach(function (field) {
						const label = field.label && field.label !== field.name
							? `${field.label} (${field.name})`
							: field.name;

						fieldSelect.append(new Option(label, field.name, false, false));
					});

					$('#engineer_change_log_engineer, #engineer_change_log_field').select2({
						dir: 'rtl',
						width: '100%',
						dropdownParent: $('#engineerChangeLogModal'),
						allowClear: true
					});

					$('#engineer_change_log_type').select2({
						dir: 'rtl',
						width: '100%',
						dropdownParent: $('#engineerChangeLogModal'),
						minimumResultsForSearch: Infinity
					});

					engineerChangeLogOptionsLoaded = true;
				});
			};

			const initEngineerChangeLogTable = function () {
				if (engineerChangeLogTable) {
					engineerChangeLogTable.ajax.reload();
					return;
				}

				engineerChangeLogTable = $('#engineer_change_log_table').DataTable({
					processing: true,
					serverSide: true,
					searching: false,
					ordering: false,
					pageLength: 10,
					lengthMenu: [[10, 25, 50], [10, 25, 50]],
					scrollX: true,
					ajax: {
						url: engineerChangeLogRoute,
						data: function (data) {
							data.engineer_id = $('#engineer_change_log_engineer').val();
							data.field_name = $('#engineer_change_log_field').val();
							data.record_type = $('#engineer_change_log_type').val();
							data.search_value = $('#engineer_change_log_search').val();
						}
					},
					columns: [
						{
							data: 'record_type_label',
							render: function (data, type, row) {
								const badgeClass = row.record_type === 'housing_table' ? 'badge-light-info' : 'badge-light-primary';

								return `<span class="badge ${badgeClass}">${escapeAuditCell(data)}</span>`;
							}
						},
						{
							data: null,
							render: function (data, type, row) {
								const buildingName = row.building_name ? escapeAuditCell(row.building_name) : '-';
								const unitNumber = row.housing_unit_number ? `<div class="text-muted fs-8">وحدة: ${escapeAuditCell(row.housing_unit_number)}</div>` : '';

								return `<div>${buildingName}</div><div class="text-muted fs-8 audit-cell-ltr">ObjectID: ${escapeAuditCell(row.objectid)}</div>${unitNumber}`;
							}
						},
						{
							data: 'engineer_name',
							render: renderAuditTextCell
						},
						{
							data: null,
							render: function (data, type, row) {
								return `<span>${escapeAuditCell(row.field_label)}</span><span class="engineer-change-log-field-code">${escapeAuditCell(row.field_name)}</span>`;
							}
						},
						{
							data: 'old_value',
							render: renderEngineerChangeLogValue
						},
						{
							data: 'new_value',
							render: renderEngineerChangeLogValue
						},
						{
							data: 'edited_at',
							render: function (data) {
								return `<span class="audit-cell-date">${escapeAuditCell(data)}</span>`;
							}
						},
						{
							data: 'assessment_url',
							className: 'text-center',
							render: function (data) {
								if (!data) {
									return '-';
								}

								return `<a href="${escapeAuditCell(data)}" target="_blank" class="btn btn-icon btn-light btn-sm" title="فتح الاستبيان"><i class="ki-duotone ki-exit-right fs-3"></i></a>`;
							}
						}
					],
					language: {
						emptyTable: 'لا توجد تغييرات مطابقة',
						zeroRecords: 'لا توجد تغييرات مطابقة',
						processing: 'جاري التحميل...'
					}
				});
			};

			$('#btn_engineer_change_log').on('click', function () {
				$('#engineerChangeLogModal').modal('show');
			});

			$('#engineerChangeLogModal').on('shown.bs.modal', function () {
				loadEngineerChangeLogOptions().done(initEngineerChangeLogTable);
			});

			$('#engineer_change_log_engineer, #engineer_change_log_field, #engineer_change_log_type').on('change', function () {
				if (engineerChangeLogTable) {
					engineerChangeLogTable.ajax.reload();
				}
			});

			$('#engineer_change_log_search').on('input', function () {
				clearTimeout(engineerChangeLogSearchTimer);
				engineerChangeLogSearchTimer = setTimeout(function () {
					if (engineerChangeLogTable) {
						engineerChangeLogTable.ajax.reload();
					}
				}, 350);
			});
			@endif

			$('#audit_export_type').select2({
				dir: 'rtl',
				width: '100%',
				dropdownParent: $('#auditExportModal'),
				minimumResultsForSearch: Infinity
			});

			$('#audit_legal_notes_filter, #audit_engineering_notes_filter').select2({
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
				appendAuditExportParams(params, 'legal_notes_filter', $('#audit_legal_notes_filter').val());
				appendAuditExportParams(params, 'engineering_notes_filter', $('#audit_engineering_notes_filter').val());

				if ($('#audit_include_legal_notes').is(':checked') || $('#audit_legal_notes_filter').val()) {
					appendAuditExportParams(params, 'include_legal_notes', '1');
				}

				if ($('#audit_include_engineering_notes').is(':checked') || $('#audit_engineering_notes_filter').val()) {
					appendAuditExportParams(params, 'include_engineering_notes', '1');
				}

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
					url: "{{ $isFieldEngineerAudit ? route('audit.fieldEngineer') : route('audit.index') }}",
					data: function (d) {
						Object.assign(d, auditFilterPayload());
					}
				},
				lengthMenu: [[10, 20, 25, 50, 100, -1], [10, 20, 25, 50, 100, 'All']],
				pageLength: 20,
				autoWidth: false,
				scrollX: true,
				responsive: false,
				ordering: false,
				columnDefs: [
					{
						targets: '_all',
						orderable: false
					},
					{
						targets: 0,
						visible: false,
						orderable: false,
						searchable: false,
						width: '64px',
						className: 'text-center audit-select-cell'
					},
					{
						targets: 1,
						width: '16%',
						className: 'text-start'
					},
					{
						targets: 2,
						width: '8%',
						className: 'text-center'
					},
					{
						targets: 3,
						width: '12%',
						className: 'audit-cell-ltr'
					},
					{
						targets: [4, 5],
						width: '9%',
						className: 'text-center'
					},
					{
						targets: [6, 7, 8],
						width: '11%',
						className: 'text-center'
					},
					{
						targets: 9,
						width: '10%',
						className: 'text-center audit-actions-cell'
					},
				],
				//order: [[9, 'desc']],
				columns: [
					{
						data: 'objectid',
						name: 'objectid',
						orderable: false,
						searchable: false,
						render: (data) => `
																																																					<div class="form-check form-check-sm form-check-custom form-check-solid">
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
					if (
						parseInt(data.housing_units_count || 0)
						-
						parseInt(data.housing_units_with_status_count || 0)
						> 0
					) {

						$(row).addClass('table-danger');

					}
					$(row).on('dblclick', function (e) {
						if ($(e.target).closest('input, button, a').length) {
							return;
						}
						e.preventDefault();
						var url_eng = "{{ url('damage-assessment/showAssessmentAudit/') }}/" + data.globalid;
						window.open(url_eng, '_blank');
					});
				},

			});

			$(document).on('click', '.btn-complete-building-field-status', function (event) {
				event.preventDefault();
				event.stopPropagation();

				let button = $(this);
				let url = button.data('url');

				if (!url || button.prop('disabled')) {
					return;
				}

				button.prop('disabled', true);

				$.ajax({
					url: url,
					type: 'POST',
					data: {
						_token: "{{ csrf_token() }}"
					},
					success: function (response) {
						toastr.success(response.message || "{{ __('ui.audit.field_status_completed') }}");
						$('#filter_field_status').val('COMPLETED').trigger('change');
						table.ajax.reload(null, false);
					},
					error: function (xhr) {
						toastr.error(xhr.responseJSON?.message || "{{ __('ui.audit.field_status_update_failed') }}");
					},
					complete: function () {
						button.prop('disabled', false);
					}
				});
			});

			const pendingBuildingDeletionTimers = {};

			function showBuildingDeletionUndo(token, buildingName, housingUnitsCount, seconds) {
				const alertId = 'building_delete_undo_' + token;
				const alert = $(`
					<div id="${alertId}" class="alert alert-warning d-flex align-items-center justify-content-between gap-3 mb-4">
						<div>
							<div class="fw-bold">سيتم حذف المبنى خلال <span class="building-delete-countdown">${seconds}</span> ثانية.</div>
							<div class="small text-muted">${buildingName || '-'} - عدد الوحدات: ${housingUnitsCount || 0}</div>
						</div>
						<button type="button" class="btn btn-sm btn-light-primary">تراجع</button>
					</div>
				`);

				$('#building_active_delete_alerts').remove();
				$('.audit-table-wrapper').first().before('<div id="building_active_delete_alerts"></div>');
				$('#building_active_delete_alerts').append(alert);

				let remaining = seconds;
				const countdown = setInterval(function () {
					remaining -= 1;
					alert.find('.building-delete-countdown').text(Math.max(remaining, 0));

					if (remaining <= 0) {
						clearInterval(countdown);
					}
				}, 1000);

				alert.find('button').on('click', function () {
					clearInterval(countdown);
					clearTimeout(pendingBuildingDeletionTimers[token]);
					delete pendingBuildingDeletionTimers[token];

					$.ajax({
						url: "{{ route('audit.building.delete.undo') }}",
						type: 'POST',
						data: {
							_token: "{{ csrf_token() }}",
							token: token
						},
						success: function (response) {
							toastr.success(response.message || 'تم التراجع عن حذف المبنى');
							alert.remove();
							table.ajax.reload(null, false);
						},
						error: function (xhr) {
							toastr.error(xhr.responseJSON?.message || 'تعذر التراجع عن حذف المبنى');
						}
					});
				});
			}

			function commitBuildingDeletion(token) {
				$.ajax({
					url: "{{ route('audit.building.delete.commit') }}",
					type: 'POST',
					data: {
						_token: "{{ csrf_token() }}",
						token: token
					},
					success: function (response) {
						let message = response.message || 'تم حذف المبنى بنجاح';

						if ((response.archived_before_database_deletion || 0) > 0) {
							message += ' - تمت أرشفة ' + response.archived_before_database_deletion + ' سجل.';
						}

						toastr.success(message);
						$('#building_delete_undo_' + token).remove();
						table.ajax.reload(null, false);
					},
					error: function (xhr) {
						if (xhr.status === 410) {
							toastr.info(xhr.responseJSON?.message || 'تم إلغاء عملية حذف المبنى');
						} else {
							toastr.error(xhr.responseJSON?.message || 'تعذر حذف المبنى');
							table.ajax.reload(null, false);
						}

						$('#building_delete_undo_' + token).remove();
					},
					complete: function () {
						delete pendingBuildingDeletionTimers[token];
					}
				});
			}

			$(document).on('click', '.btn-schedule-building-delete', function (event) {
				event.preventDefault();
				event.stopPropagation();

				const button = $(this);
				const url = button.data('url');
				const buildingName = button.data('building-name') || '-';
				const message = 'سيتم حذف المبنى وجميع وحداته من قاعدة البيانات و ArcGIS بعد 50 ثانية: ' + buildingName;

				if (!url || button.prop('disabled')) {
					return;
				}

				Swal.fire({
					title: 'حذف المبنى',
					text: message,
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'نعم، متابعة',
					cancelButtonText: @json(__('ui.buttons.cancel')),
					buttonsStyling: false,
					customClass: {
						confirmButton: 'btn btn-danger',
						cancelButton: 'btn btn-light'
					}
				}).then(function (result) {
					if (!result.isConfirmed) {
						return;
					}

					button.prop('disabled', true);

					$.ajax({
						url: url,
						type: 'POST',
						data: {
							_token: "{{ csrf_token() }}"
						},
						success: function (response) {
							showBuildingDeletionUndo(response.token, buildingName, response.housing_units_count || 0, response.seconds || 50);
							pendingBuildingDeletionTimers[response.token] = setTimeout(function () {
								commitBuildingDeletion(response.token);
							}, (response.seconds || 50) * 1000);
							toastr.warning(response.message || 'تم تجهيز عملية حذف المبنى');
						},
						error: function (xhr) {
							toastr.error(xhr.responseJSON?.message || 'تعذر تجهيز عملية حذف المبنى');
						},
						complete: function () {
							button.prop('disabled', false);
						}
					});
				});
			});

			$(document).on('click', '.btn-restore-audited-to-normal', function (event) {
				event.preventDefault();
				event.stopPropagation();

				let button = $(this);
				let url = button.data('url');
				let buildingName = button.data('building-name') || '-';

				if (!url || button.prop('disabled')) {
					return;
				}

				Swal.fire({
					title: 'تحديث الطبقة العادية',
					text: 'سيتم تحديث بيانات المبنى والوحدات من بيانات التدقيق: ' + buildingName,
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: 'تحديث',
					cancelButtonText: @json(__('ui.buttons.cancel')),
					buttonsStyling: false,
					customClass: {
						confirmButton: 'btn btn-primary',
						cancelButton: 'btn btn-light'
					}
				}).then(function (result) {
					if (!result.isConfirmed) {
						return;
					}

					button.prop('disabled', true);

					$.ajax({
						url: url,
						type: 'POST',
						data: {
							_token: "{{ csrf_token() }}"
						},
						success: function (response) {
							const summary = response.summary || {};
							const html = `
								<div class="text-start" dir="rtl">
									<div>تحديث المبنى في ArcGIS: ${summary.building_arcgis_updated ? 'نعم' : 'لا'}</div>
									<div>تحديث المبنى محلياً: ${summary.building_local_updated ? 'نعم' : 'لا'}</div>
									<div>الوحدات المحدثة في ArcGIS: ${summary.units_arcgis_updated ?? 0}</div>
									<div>الوحدات المحدثة محلياً: ${summary.units_local_updated ?? 0}</div>
									<div>الوحدات غير الموجودة في ArcGIS: ${summary.units_skipped_arcgis ?? 0}</div>
									<div>الوحدات غير الموجودة محلياً: ${summary.units_skipped_local ?? 0}</div>
								</div>
							`;

							Swal.fire({
								title: response.message || 'تمت العملية',
								html: html,
								icon: 'success',
								confirmButtonText: @json(__('ui.buttons.ok')),
								buttonsStyling: false,
								customClass: {
									confirmButton: 'btn btn-primary'
								}
							});

							table.ajax.reload(null, false);
						},
						error: function (xhr) {
							Swal.fire({
								text: xhr.responseJSON?.message || 'تعذر تحديث الطبقة العادية.',
								icon: 'error',
								confirmButtonText: @json(__('ui.buttons.ok')),
								buttonsStyling: false,
								customClass: {
									confirmButton: 'btn btn-primary'
								}
							});
						},
						complete: function () {
							button.prop('disabled', false);
						}
					});
				});
			});

			const renderAcceptedWithUnevaluatedUnitsButton = function () {
				const button = $('#toggle_accepted_with_unevaluated_units');

				if (!button.length) {
					return;
				}

				button.attr('data-filter-active', acceptedWithUnevaluatedUnits ? 'true' : 'false');
				button.toggleClass('btn-light-danger btn-danger', acceptedWithUnevaluatedUnits);
				button.html(
					acceptedWithUnevaluatedUnits
						? 'إظهار الكل <i class="ki-duotone ki-eye"></i>'
						: 'مقبول وبداخله وحدات غير مقيمة <i class="ki-duotone ki-information-5"></i>'
				);
			};

			$('#toggle_accepted_with_unevaluated_units').on('click', function () {
				acceptedWithUnevaluatedUnits = !acceptedWithUnevaluatedUnits;
				renderAcceptedWithUnevaluatedUnitsButton();
				table.ajax.reload(null, true);
			});

			const renderFloorAreaMismatchButton = function () {
				const button = $('#toggle_floor_area_mismatch');

				if (!button.length) {
					return;
				}

				button.attr('data-filter-active', floorAreaMismatch ? 'true' : 'false');
				button.toggleClass('btn-light-warning btn-warning', floorAreaMismatch);
				button.html(
					floorAreaMismatch
						? 'إظهار الكل <i class="ki-duotone ki-eye"></i>'
						: 'مخالف لمساحات الطوابق <i class="ki-duotone ki-chart-line-down"></i>'
				);
			};

			$('#toggle_floor_area_mismatch').on('click', function () {
				floorAreaMismatch = !floorAreaMismatch;
				renderFloorAreaMismatchButton();
				table.ajax.reload(null, true);
			});

			$('#export_floor_area_mismatch').on('click', function () {
				const params = new URLSearchParams();
				const filters = auditFilterPayload();
				filters.floor_area_mismatch = 1;

				Object.keys(filters).forEach(function (key) {
					appendAuditExportParams(params, key, filters[key]);
				});

				window.location.href = "{{ route('audit.floor-area-mismatches.export') }}?" + params.toString();
			});
			$('#toggle_select_column').on('click', function () {
				const button = $(this);
				const selectColumn = table.column(0);
				const shouldShow = !selectColumn.visible();

				selectColumn.visible(shouldShow);
				table.columns.adjust();
				$("[type='checkbox']").prop('checked', false);
				button.attr('data-select-visible', shouldShow ? 'true' : 'false');
				button.html((shouldShow ? 'إخفاء التحديد' : 'إظهار التحديد') + ' <i class="ki-duotone ki-check-square"></i>');
			});

			$('#bulkInlineEditModal').on('shown.bs.modal', function () {
				$('#bulk_inline_result').addClass('d-none').empty();
				syncBulkInlineFieldOptions();
			});

			$('#bulk_inline_type').on('change', syncBulkInlineFieldOptions);
			$('#bulk_inline_field').on('change', syncBulkInlineValueControl);

			$('#bulk_inline_edit_form').on('submit', function (event) {
				event.preventDefault();

				const form = $(this);
				const button = $('#bulk_inline_submit');
				const objectIds = parseBulkObjectIds($('#bulk_inline_objectids').val());
				const fieldLabel = $('#bulk_inline_field option:selected').text().trim();
				const field = $('#bulk_inline_field').val();
				const value = activeBulkInlineValue();

				if (!objectIds.length) {
					Swal.fire({
						text: 'يرجى إدخال ObjectID صحيح واحد على الأقل.',
						icon: 'warning',
						buttonsStyling: false,
						confirmButtonText: @json(__('ui.buttons.ok')),
						customClass: {
							confirmButton: 'btn btn-primary'
						}
					});

					return;
				}

				if (!field) {
					Swal.fire({
						text: 'يرجى اختيار الحقل المراد تعديله.',
						icon: 'warning',
						buttonsStyling: false,
						confirmButtonText: @json(__('ui.buttons.ok')),
						customClass: {
							confirmButton: 'btn btn-primary'
						}
					});

					return;
				}

				if (($('#bulk_inline_value_select_wrapper').is(':visible')) && !value) {
					Swal.fire({
						text: 'يرجى اختيار القيمة من قائمة الاستبيان.',
						icon: 'warning',
						buttonsStyling: false,
						confirmButtonText: @json(__('ui.buttons.ok')),
						customClass: {
							confirmButton: 'btn btn-primary'
						}
					});

					return;
				}

				$('#bulk_inline_value').val(value || '');

				Swal.fire({
					title: 'تأكيد التعديل الجماعي',
					text: 'سيتم إضافة تعديل على ' + objectIds.length + ' سجل للحقل: ' + fieldLabel,
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: 'تنفيذ التعديل',
					cancelButtonText: @json(__('ui.buttons.cancel')),
					buttonsStyling: false,
					customClass: {
						confirmButton: 'btn btn-primary',
						cancelButton: 'btn btn-light'
					}
				}).then(function (result) {
					if (!result.isConfirmed) {
						return;
					}

					button.attr('data-kt-indicator', 'on').prop('disabled', true);
					$('#bulk_inline_result').addClass('d-none').empty();

					$.ajax({
						url: "{{ route('assessment.inline.bulkUpdate') }}",
						type: 'POST',
						data: form.serialize(),
						success: function (response) {
							renderBulkInlineResult(response);
							table.ajax.reload(null, false);
						},
						error: function (xhr) {
							const errors = xhr.responseJSON?.errors;
							const message = errors
								? Object.values(errors).flat().join(' ')
								: (xhr.responseJSON?.message || 'تعذر تنفيذ التعديل الجماعي.');

							$('#bulk_inline_result')
								.removeClass('d-none alert-light-primary')
								.addClass('alert-light-warning')
								.html(escapeAuditCell(message));
						},
						complete: function () {
							button.removeAttr('data-kt-indicator').prop('disabled', false);
						}
					});
				});
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

			$('#filter_engineer, #filter_lawyer, #filter_eng_status, #filter_legal_status, #filter_final_status, #filter_field_status, #filter_field_engineer, #filter_damage_status, #filter_legal_challenge, .audit-building-filter-control')
				.on('change', scheduleFilterReload);

			$('#filter_building_name, #filter_objectid, #filter_area, #filter_from_date, #filter_to_date, #filter_status_from_date, #filter_status_to_date, .audit-building-filter-control')
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
				acceptedWithUnevaluatedUnits = false;
				floorAreaMismatch = false;
				renderAcceptedWithUnevaluatedUnitsButton();
				renderFloorAreaMismatchButton();
				$('select').val(null).trigger('change');
				$('input').val('');
				$('#filter_field_status').val('COMPLETED').trigger('change');
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

				$('#notesHistoryModalTitle').text(@json(__('ui.audit.notes_history')) + ' - ' + buildingName);
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

						const notesHistory = response.status && Array.isArray(response.history)
							? response.history.filter(function (item) {
								return item.notes !== null && item.notes !== undefined && String(item.notes).trim() !== '';
							})
							: [];

						if (notesHistory.length > 0) {
							notesHistory.forEach(function (item) {
								rows += `
																																										<tr>
																																											<td>${escapeAuditCell(item.status_name)}</td>
																																											<td>${escapeAuditCell(item.user_name)}</td>
																																											<td>${escapeAuditCell(item.role_name)}</td>
																																											<td><span class="audit-cell-text">${escapeAuditCell(item.notes)}</span></td>
																																											<td>${escapeAuditCell(item.created_at)}</td>
																																											<td>
																																												${item.can_delete ? `
																																													<button type="button"
																																														class="btn btn-sm btn-light-danger btn-delete-history"
																																														data-id="${escapeAuditCell(item.note_id ?? item.id)}">
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
																																										<td colspan="6" class="text-center text-muted">${@json(__('ui.audit.no_notes_history'))}</td>
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
																																									<td colspan="6" class="text-center text-muted">${@json(__('ui.audit.no_notes_history'))}</td>
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



			$('[data-kt-menu-trigger]').each(function () {

				if ($(this).data('ktMenuInitialized')) {
					return;
				}

				$(this).data('ktMenuInitialized', true);
			});
			KTMenu.createInstances();

		});
		$('#kt_datatable_audits').on('responsive-display.dt draw.dt', function () {

			$('.dtr-title').remove();
			$('[data-kt-menu-trigger]').each(function () {

				if ($(this).data('ktMenuInitialized')) {
					return;
				}

				KTMenu.createInstances();

				$(this).data('ktMenuInitialized', true);
			});

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

		@if($canManageAuditReviewers ?? false)
			function initAuditReviewerSelect() {
				const $select = $('#audit_reviewer_user_id');

				if (!$select.length || !$.fn.select2 || $select.hasClass('select2-hidden-accessible')) {
					return;
				}

				$select.select2({
					dropdownParent: $('#auditReviewersModal'),
					placeholder: $select.data('placeholder') || 'اختر مستخدم',
					allowClear: true,
					width: '100%',
					dir: 'rtl',
				});
			}

			$('#auditReviewersModal').on('shown.bs.modal', initAuditReviewerSelect);
			initAuditReviewerSelect();
		@endif

		@if(($canManageAuditReviewers ?? false) && request()->boolean('audit_reviewers'))
			const auditReviewersModal = new bootstrap.Modal(document.getElementById('auditReviewersModal'));
			auditReviewersModal.show();
		@endif
	</script>
@endsection
