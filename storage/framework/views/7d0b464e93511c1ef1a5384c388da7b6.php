<?php $__env->startSection('content'); ?>
	<style>
		.card-toolbar .dropdown-menu .dropdown-item {
			font-size: 13px;
			padding: 0.65rem 1rem;
			transition: 0.2s ease;
		}

		.card-toolbar .dropdown-menu .dropdown-item:hover {
			background-color: #f8f9fa;
		}
	</style>
	<div class="container py-4">
		<div id="exportResult" class="mt-4"></div>
		<div class="card shadow-sm">
			<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
				<h3 class="mb-0"><?php echo e(__('ui.exports.title')); ?></h3>
				<div class="d-flex align-items-center flex-wrap gap-2">
					<button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal"
						data-bs-target="#importObjectIdsModal">
						<i class="ki-duotone ki-file-up fs-5">
							<span class="path1"></span>
							<span class="path2"></span>
						</i>
						<?php echo e(__('ui.exports.import_objectids_excel')); ?>

					</button>

					<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($importedObjectIds)): ?>
						<button type="button" class="btn btn-sm btn-light-danger" id="resetObjectIdsFilterBtn">
							<i class="ki-duotone ki-cross-circle fs-5">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
							<?php echo e(__('ui.exports.reset_objectid_import_filter')); ?>

						</button>
					<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
				</div>

			</div>

			<div class="card-body">
				<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
					<div class="alert alert-danger">
						<?php echo e(session('error')); ?>

					</div>
				<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

				<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($importedObjectIds)): ?>
					<div class="alert alert-info d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
						<div>
							<strong><?php echo e(__('ui.exports.objectid_import_active')); ?></strong>
							<div class="text-muted fs-7 mt-1">
								<?php echo e(__('ui.exports.objectid_import_active_count', ['count' => count($importedObjectIds)])); ?>

							</div>
						</div>
						<div class="d-flex flex-wrap gap-2">
							<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = array_slice($importedObjectIds, 0, 8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $objectId): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
								<span class="badge badge-light-primary"><?php echo e($objectId); ?></span>
							<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
							<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($importedObjectIds) > 8): ?>
								<span class="badge badge-light">+<?php echo e(count($importedObjectIds) - 8); ?></span>
							<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
						</div>
					</div>
				<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

				<form id="exportForm" method="POST">
					<?php echo csrf_field(); ?>

					
					<div class="card card-bordered mb-5">
						<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
							<h3 class="card-title mb-0"><?php echo e(__('ui.exports.filters')); ?></h3>

							<div class="d-flex gap-2">
								<button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="collapse"
									data-bs-target="#filtersCollapse" aria-expanded="true" aria-controls="filtersCollapse"
									id="toggleFiltersBtn">
									<i class="fas fa-chevron-down me-1"></i>
									<?php echo e(__('ui.exports.show')); ?>

								</button>

								<button type="button" class="btn btn-sm btn-light-danger" onclick="resetFilters()">
									<i class="fas fa-times me-1"></i>
									<?php echo e(__('ui.exports.clear_filters')); ?>

								</button>
								<button type="button"
									class="btn btn-light-primary btn-sm dropdown-toggle d-flex align-items-center gap-1"
									data-bs-toggle="dropdown" aria-expanded="false">

									<i class="ki-duotone ki-exit-down fs-5">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
									<?php echo e(__('ui.exports.export')); ?>

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
											placeholder="<?php echo e(__('ui.exports.search_filter')); ?>"
											onkeyup="filterFilterCards()">

										<button type="button" class="btn btn-light" onclick="clearFilterSearch()">
											<?php echo e(__('ui.exports.clear')); ?>

										</button>
									</div>

									<div class="text-muted fs-7 mt-2">
										<?php echo e(__('ui.exports.visible_filters_count')); ?>

										<span id="filterCardsCounter"><?php echo e(count($filters)); ?></span>
										/ <?php echo e(count($filters)); ?>

									</div>
								</div>

								<div class="row" id="filtersCardsList">
									<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $listName => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
										<div class="col-md-4 mb-4 filter-card-item">
											<label class="form-label fw-bold searchable-filter-name">
												<?php echo e($assessmentLabels[$listName] ?? ucwords(str_replace('_', ' ', $listName))); ?>

											</label>

											<select name="filters[<?php echo e($listName); ?>][]"
												class="form-select form-select-solid filter-select2" multiple
												data-placeholder="<?php echo e(__('ui.exports.select', ['label' => $assessmentLabels[$listName] ?? ucwords(str_replace('_', ' ', $listName))])); ?>">
												<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
													<option value="<?php echo e($item->name); ?>">
														<?php echo e($item->label ?? $item->name); ?>

													</option>
												<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
											</select>
										</div>
									<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label class="form-label fw-bold searchable-filter-name"><?php echo e(__('ui.exports.family_members_from')); ?></label>
										<input type="number" name="family_members_from"
											class="form-control form-control-solid" min="0"
											placeholder="<?php echo e(__('ui.exports.family_members_from')); ?>" value="<?php echo e(old('family_members_from')); ?>">
									</div>

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label class="form-label fw-bold searchable-filter-name"><?php echo e(__('ui.exports.family_members_to')); ?></label>
										<input type="number" name="family_members_to" placeholder="<?php echo e(__('ui.exports.family_members_to')); ?>"
											class="form-control form-control-solid" min="0"
											value="<?php echo e(old('family_members_to')); ?>">
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="row">
						

						<div class="col-lg-6 mb-4">
							<div class="card card-bordered h-100">
								<div class="card-header border-0 pt-4">
									<div class="card-title">
										<h4 class="mb-0"><?php echo e(__('ui.exports.building_table_fields')); ?></h4>
									</div>

									<div class="card-toolbar d-flex gap-2">
										<button type="button" class="btn btn-sm btn-primary"
											onclick="toggleVisibleGroup('buildingColumnsList', 'building_columns[]', true)">
											<?php echo e(__('ui.exports.select_all')); ?>

										</button>

										<button type="button" class="btn btn-sm btn-light-danger"
											onclick="toggleVisibleGroup('buildingColumnsList', 'building_columns[]', false)">
											<?php echo e(__('ui.exports.deselect_all')); ?>

										</button>
									</div>
								</div>

								<div class="card-body pt-2">
									<div class="mb-4">
										<div class="input-group">
											<span class="input-group-text">
												<i class="fas fa-search"></i>
											</span>

											<input type="text" id="buildingSearch" class="form-control form-control-solid"
												placeholder="<?php echo e(__('ui.exports.search_fields')); ?>"
												onkeyup="filterColumns('buildingSearch', 'buildingColumnsList', 'buildingCounter')">

											<button type="button" class="btn btn-light"
												onclick="clearSearch('buildingSearch', 'buildingColumnsList', 'buildingCounter')">
												<?php echo e(__('ui.exports.clear')); ?>

											</button>
										</div>

										<div class="text-muted fs-7 mt-2">
											<?php echo e(__('ui.exports.total_results')); ?>

											<span id="buildingCounter"><?php echo e(count($buildingColumns)); ?></span>
											/ <?php echo e(count($buildingColumns)); ?>

										</div>
									</div>

									<div class="row" id="buildingColumnsList">
										<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $buildingColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
											<div class="col-md-6 mb-3 column-item">
												<label
													class="form-check form-check-custom form-check-solid align-items-start w-100 border rounded p-3">
													<input class="form-check-input mt-1" type="checkbox"
														name="building_columns[]" value="<?php echo e($column); ?>">

													<span class="form-check-label ms-3 w-100">
														<strong class="d-block fs-6 searchable-label">
															<?php echo e($assessmentMeta[$column]['label'] ?? ucwords(str_replace('_', ' ', $column))); ?>

														</strong>

														<small class="text-muted d-block mt-1 searchable-column">
															<?php echo e($assessmentMeta[$column]['hint'] ?? ''); ?>

														</small>

														<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($assessmentMeta[$column]['hint']) && trim($assessmentMeta[$column]['hint']) !== ''): ?>
															<small class="text-info d-block mt-2 searchable-hint">
																<?php echo e($column); ?>

															</small>
														<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
													</span>
												</label>
											</div>
										<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
											<div class="col-12">
												<div class="alert alert-warning mb-0">
													<?php echo e(__('ui.exports.no_building_fields')); ?>

												</div>
											</div>
										<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
									</div>
								</div>
							</div>
						</div>

						
						<div class="col-lg-6 mb-4">
							<div class="card card-bordered h-100">
								<div class="card-header border-0 pt-4">
									<div class="card-title">
										<h4 class="mb-0"><?php echo e(__('ui.exports.housing_table_fields')); ?></h4>
									</div>

									<div class="card-toolbar d-flex gap-2">
										<button type="button" class="btn btn-sm btn-primary"
											onclick="toggleVisibleGroup('housingColumnsList', 'housing_columns[]', true)">
											<?php echo e(__('ui.exports.select_all')); ?>

										</button>

										<button type="button" class="btn btn-sm btn-light-danger"
											onclick="toggleVisibleGroup('housingColumnsList', 'housing_columns[]', false)">
											<?php echo e(__('ui.exports.deselect_all')); ?>

										</button>
									</div>
								</div>

								<div class="card-body pt-2">
									<div class="mb-4">
										<div class="input-group">
											<span class="input-group-text">
												<i class="fas fa-search"></i>
											</span>

											<input type="text" id="housingSearch" class="form-control form-control-solid"
												placeholder="<?php echo e(__('ui.exports.search_fields')); ?>"
												onkeyup="filterColumns('housingSearch', 'housingColumnsList', 'housingCounter')">

											<button type="button" class="btn btn-light"
												onclick="clearSearch('housingSearch', 'housingColumnsList', 'housingCounter')">
												<?php echo e(__('ui.exports.clear')); ?>

											</button>
										</div>

										<div class="text-muted fs-7 mt-2">
											<?php echo e(__('ui.exports.total_results')); ?>

											<span id="housingCounter"><?php echo e(count($housingColumns)); ?></span>
											/ <?php echo e(count($housingColumns)); ?>

										</div>
									</div>

									<div class="row" id="housingColumnsList">
										<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $housingColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
											<div class="col-md-6 mb-3 column-item">
												<label
													class="form-check form-check-custom form-check-solid align-items-start w-100 border rounded p-3">
													<input class="form-check-input mt-1" type="checkbox"
														name="housing_columns[]" value="<?php echo e($column); ?>">

													<span class="form-check-label ms-3 w-100">
														<strong class="d-block fs-6 searchable-label">
															<?php echo e($assessmentMeta[$column]['label'] ?? ucwords(str_replace('_', ' ', $column))); ?>

														</strong>

														<small class="text-muted d-block mt-1 searchable-column">
															<?php echo e($assessmentMeta[$column]['hint'] ?? ''); ?>

														</small>

														<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($assessmentMeta[$column]['hint']) && trim($assessmentMeta[$column]['hint']) !== ''): ?>
															<small class="text-info d-block mt-2 searchable-hint">
																<?php echo e($column); ?>

															</small>
														<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
													</span>
												</label>
											</div>
										<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
											<div class="col-12">
												<div class="alert alert-warning mb-0">
													<?php echo e(__('ui.exports.no_housing_fields')); ?>

												</div>
											</div>
										<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
					<?php echo csrf_field(); ?>
					<div class="modal-header">
						<h2 class="fw-bold"><?php echo e(__('ui.exports.import_objectids_excel')); ?></h2>
						<div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
							<i class="ki-duotone ki-cross fs-1">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
						</div>
					</div>

					<div class="modal-body py-10 px-lg-17">
						<div class="mb-7">
							<label class="required fw-semibold fs-6 mb-2 d-block"><?php echo e(__('ui.exports.objectid_import_file_label')); ?></label>
							<input type="file" name="objectids_file" id="objectids_file"
								class="form-control form-control-solid" accept=".xlsx,.xls,.csv" />
							<div class="form-text"><?php echo e(__('ui.exports.objectid_import_file_help')); ?></div>
							<div class="invalid-feedback d-block" id="objectids-file-error" style="display: none;"></div>
						</div>
					</div>

					<div class="modal-footer flex-center">
						<button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">
							<?php echo e(__('ui.buttons.cancel')); ?>

						</button>
						<button type="submit" class="btn btn-primary" id="importObjectIdsSubmitBtn">
							<span class="indicator-label"><?php echo e(__('ui.exports.import_objectids_excel')); ?></span>
							<span class="indicator-progress"><?php echo e(__('ui.auth.please_wait')); ?>

								<span class="spinner-border spinner-border-sm align-middle ms-2"></span>
							</span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>


<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>


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
							<?php echo e(__('ui.exports.preparing_file')); ?>

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
						<div class="mb-3"><?php echo e(__('ui.exports.file_ready')); ?></div>
						<a href="${fileUrl}" class="btn btn-success" target="_blank">
							<?php echo e(__('ui.exports.download_file')); ?>

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

			$('#processedCount').text(<?php echo json_encode(__('ui.exports.processed_records', ['count' => '__COUNT__']), 512) ?>.replace('__COUNT__', processed));
		}

		function startCheckingExport(exportId) {
			stopExportInterval();

			exportInterval = setInterval(function () {
				$.ajax({
					url: "<?php echo e(url('')); ?>/exports/check/" + exportId,
					type: "GET",
					success: function (response) {
						updateProgress(response.progress, response.processed);

						if (response.status === 'finished' && response.file) {
							showSuccess(response.file);
						} else if (response.status === 'failed') {
							showError(<?php echo json_encode(__('ui.exports.export_failed'), 15, 512) ?>);
						} else if (response.status === 'cancelled') {
							showError(<?php echo json_encode(__('ui.exports.export_cancelled'), 15, 512) ?>);
						}
						if (response.status === 'done' && response.file && !isDownloaded) {
							isDownloaded = true;
							window.open(response.file, '_blank');
							showSuccess(response.file);
						}
					},
					error: function () {
						showError(<?php echo json_encode(__('ui.exports.export_status_failed'), 15, 512) ?>);
					}
				});
			}, 2000);
		}

		function restartExport(formData) {
			$.ajax({
				url: "<?php echo e(url('exports/start')); ?>",
				type: "POST",
				data: formData,
				success: function (newRes) {
					if (newRes.status) {
						toastr.success(newRes.message || <?php echo json_encode(__('ui.exports.export_started'), 15, 512) ?>);
						startCheckingExport(newRes.export_id);
					} else {
						enableExportButtons();
						toastr.error(newRes.message || <?php echo json_encode(__('ui.exports.export_start_failed'), 15, 512) ?>);
					}
				},
				error: function (xhr) {
					enableExportButtons();
					toastr.error(xhr.responseJSON?.message || <?php echo json_encode(__('ui.exports.export_restart_failed'), 15, 512) ?>);
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
				placeholder: <?php echo json_encode(__('ui.exports.select_values'), 15, 512) ?>
			});

			const collapse = document.getElementById('filtersCollapse');
			const btn = document.getElementById('toggleFiltersBtn');

			if (collapse && btn) {
				collapse.addEventListener('shown.bs.collapse', function () {
					btn.innerHTML = '<i class="fas fa-chevron-down me-1"></i> <?php echo e(__('ui.exports.hide')); ?>';
				});

				collapse.addEventListener('hidden.bs.collapse', function () {
					btn.innerHTML = '<i class="fas fa-chevron-left me-1"></i> <?php echo e(__('ui.exports.show')); ?>';
				});
			}

			$('#importObjectIdsForm').on('submit', function (e) {
				e.preventDefault();

				const form = this;
				const formData = new FormData(form);

				$('#objectids-file-error').hide().text('');
				setImportObjectIdsLoading(true);

				$.ajax({
					url: <?php echo json_encode(route('export.data.objectids.import'), 15, 512) ?>,
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
						const message = xhr.responseJSON?.message || <?php echo json_encode(__('ui.exports.objectid_import_failed'), 15, 512) ?>;
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
					url: <?php echo json_encode(route('export.data.objectids.reset'), 15, 512) ?>,
					type: 'POST',
					data: {
						_token: <?php echo json_encode(csrf_token(), 15, 512) ?>
					},
					success: function (response) {
						toastr.success(response.message);
						window.location.reload();
					},
					error: function (xhr) {
						toastr.error(xhr.responseJSON?.message || <?php echo json_encode(__('ui.exports.objectid_import_reset_failed'), 15, 512) ?>);
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
					url: "<?php echo e(url('exports/start')); ?>",
					type: "POST",
					data: formData,
					success: function (response) {
						if (response.status) {
							toastr.success(response.message || <?php echo json_encode(__('ui.exports.export_started'), 15, 512) ?>);
							startCheckingExport(response.export_id);
						} else {
							enableExportButtons();
							toastr.error(response.message || <?php echo json_encode(__('ui.exports.export_start_failed'), 15, 512) ?>);
						}
					},
					error: function (xhr) {
						const res = xhr.responseJSON;

						if (xhr.status === 409 && res?.needs_cancel) {
							stopExportInterval();

							Swal.fire({
								title: <?php echo json_encode(__('ui.exports.running_export_title'), 15, 512) ?>,
								html: `
										<div class="text-center">
											<p>${res.message}</p>
											<p>${<?php echo json_encode(__('ui.exports.running_export_progress', ['progress' => '__PROGRESS__']), 512) ?>.replace('__PROGRESS__', res.running_export.progress ?? 0)}</p>
										</div>
									`,
								icon: 'warning',
								showCancelButton: true,
								confirmButtonText: <?php echo json_encode(__('ui.exports.cancel_old_and_start_new'), 15, 512) ?>,
								cancelButtonText: <?php echo json_encode(__('ui.exports.close'), 15, 512) ?>
							}).then((result) => {
								if (result.isConfirmed) {
									$.ajax({
										url: "<?php echo e(url('')); ?>/exports/" + res.running_export.id + "/cancel",
										type: "POST",
										data: {
											_token: "<?php echo e(csrf_token()); ?>"
										},
										beforeSend: function () {
											Swal.showLoading();
										},
										success: function (cancelRes) {
											toastr.success(cancelRes.message || <?php echo json_encode(__('ui.exports.old_export_cancelled'), 15, 512) ?>);
											showPreparingCard();
											restartExport(formData);
										},
										error: function (cancelXhr) {
											enableExportButtons();
											toastr.error(cancelXhr.responseJSON?.message || <?php echo json_encode(__('ui.exports.old_export_cancel_failed'), 15, 512) ?>);
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
						toastr.error(res?.message || <?php echo json_encode(__('ui.exports.unexpected_error'), 15, 512) ?>);
					}
				});
			});
		});
	</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/exports/index.blade.php ENDPATH**/ ?>